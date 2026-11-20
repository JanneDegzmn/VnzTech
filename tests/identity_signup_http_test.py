"""Exercise ID carryover integration with real signup/verification routes.

Disposable SQLite, sessions and synthetic image uploads only. Identity and mail
adapters deliberately expose transaction ordering/failure boundaries; the actual
identity helper's capability/expiry policy is checked by its separate tests.
No application DB, SMTP, OAuth provider or personal ID is accessed.
"""

import base64
from contextlib import closing
import hashlib
from http.cookiejar import CookieJar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit, parse_qs
from urllib.request import build_opener, HTTPRedirectHandler, HTTPCookieProcessor, Request


REPO = Path(__file__).resolve().parent.parent
PHP = os.environ.get("PHP_BINARY") or shutil.which("php") or "C:/xampp/php/php.exe"
TOKEN = "cd" * 32
PNG = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMj8AAAAASUVORK5CYII=")

DATABASE = r"""<?php
class IdentitySignupFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(preg_replace('/\bFOR\s+UPDATE\b/i', '', $query), $options);
    }
}
$pdo = new IdentitySignupFixturePDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('NOW', static fn() => gmdate('Y-m-d H:i:s'), 0);
"""

IDENTITY = r"""<?php
// Test double, not a substitute for real identity helper authorization tests.
function vnzIdentityReserveGuest(PDO $pdo, int $userId, string $email): void {
    if (!$pdo->inTransaction()) throw new RuntimeException('Reserve must be atomic with account creation.');
    if (empty($_SESSION['fixture_guest_proof'])) return;
    $stmt = $pdo->prepare('INSERT INTO fixture_identity (user_id, email, state) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $email, 'reserved']);
    if (!empty($_SESSION['fixture_reserve_failure'])) throw new RuntimeException('Synthetic reserve failure.');
}
function vnzIdentityFinalizeAccount(PDO $pdo, int $userId): void {
    if (!$pdo->inTransaction()) throw new RuntimeException('Finalize must share the verification transaction.');
    $stmt = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('Email must be verified before finalizing.');
    $stmt = $pdo->prepare('UPDATE fixture_identity SET state = ? WHERE user_id = ?');
    $stmt->execute(['finalized', $userId]);
    if (!empty($_SESSION['fixture_finalize_failure'])) throw new RuntimeException('Synthetic finalize failure.');
}
"""

MAIL = r"""<?php
function sendEmailVerification(PDO $pdo, int $userId, string $email): void {
    if ($pdo->inTransaction()) throw new RuntimeException('Mail must follow committed account ownership.');
    if (!empty($_SESSION['fixture_mail_failure'])) throw new RuntimeException('Synthetic mail failure.');
    $pdo->prepare('INSERT INTO email_verification_tokens VALUES (?, ?, datetime("now", "+30 minutes"))')
        ->execute([$userId, hash('sha256', str_repeat('cd', 32))]);
}
"""

SOCIAL = r"""<?php
require_once __DIR__ . '/password_policy.php';
function vnzSocialAuthCompleteLogin(PDO $pdo, array $seller): never {
    if ($pdo->inTransaction()) throw new RuntimeException('Login must follow signup commit.');
    if (!empty($_SESSION['fixture_login_failure'])) throw new RuntimeException('Synthetic login failure.');
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $seller['id'];
    header('Location: /VNZ-TECH/account/dashboard.php');
    exit;
}
"""

FIXTURE = r"""<?php
require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/csrf.php';
if (($_GET['action'] ?? '') === 'reset') {
    $_SESSION = [];
    foreach (['guest_proof', 'reserve_failure', 'finalize_failure', 'mail_failure', 'login_failure'] as $flag) {
        $_SESSION['fixture_' . $flag] = ($_GET[$flag] ?? '') === '1';
    }
    $provider = $_GET['provider'] ?? '';
    if (in_array($provider, ['google', 'facebook'], true)) {
        $_SESSION['social_signup_profile'] = [
            'provider' => $provider, 'email' => 'seller@example.test',
            'email_verified' => true, 'subject' => 'synthetic-subject'
        ];
        $_SESSION['social_signup_started_at'] = time();
    }
}
header('Content-Type: application/json');
echo json_encode(['csrf' => generateCsrfToken(), 'seller' => $_SESSION['user_id'] ?? null,
    'logged_in' => !empty($_SESSION['user_logged_in'])], JSON_THROW_ON_ERROR);
"""

SCHEMA = """
CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT, mobile TEXT,
 email TEXT NOT NULL UNIQUE, password_hash TEXT, profile_picture TEXT, language TEXT,
 email_verified_at TEXT, is_active INTEGER);
CREATE TABLE fixture_identity (user_id INTEGER PRIMARY KEY, email TEXT, state TEXT);
CREATE TABLE email_verification_tokens (user_id INTEGER PRIMARY KEY, token_hash TEXT, expires_at TEXT);
CREATE TABLE user_oauth_accounts (user_id INTEGER, provider TEXT, provider_subject_hash TEXT,
 UNIQUE(provider, provider_subject_hash));
CREATE TABLE fixture_claim_events (user_id INTEGER);
"""


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def main():
    sources = [
        "account/register.php", "backend/register.php", "backend/social_signup_complete.php",
        "backend/verify_email.php", "backend/session.php", "backend/csrf.php",
        "backend/password_policy.php", "backend/guest_submission_onboarding.php",
        "backend/email_verification_feedback.php",
    ]
    with tempfile.TemporaryDirectory(prefix="vnz-identity-signup-http-") as temporary_name:
        temporary = Path(temporary_name)
        public = temporary / "public"
        app = public / "VNZ-TECH"
        sessions = temporary / "sessions"
        sessions.mkdir()
        for relative in sources:
            destination = app / relative
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(REPO / relative, destination)
        adapters = {
            "backend/database.php": DATABASE,
            "backend/identity_verification.php": IDENTITY,
            "backend/email_verification.php": MAIL,
            "backend/social_auth_service.php": SOCIAL,
            "backend/guest_submission_link.php": "<?php function linkVerifiedGuestSubmissions(PDO $pdo, int $id): void { $pdo->prepare('INSERT INTO fixture_claim_events VALUES (?)')->execute([$id]); }",
            "backend/chat_presence_store.php": "<?php",
            "__fixture.php": FIXTURE,
        }
        empty_files = [
            "includes/loader-head.php", "includes/loader-body.php", "includes/social_auth_buttons.php",
            "includes/terms_modal.php", "assets/css/seller_register.css", "assets/css/public_theme.css",
            "assets/css/social_auth.css", "assets/css/terms_modal.css", "assets/js/public-theme.js",
            "assets/js/password-strength.js", "assets/js/terms-modal.js",
        ]
        for relative, content in {**dict.fromkeys(empty_files, ""), **adapters}.items():
            destination = app / relative
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_text(content, encoding="utf-8")
        database = public / "fixture.sqlite"
        with closing(sqlite3.connect(database)) as db, db:
            db.executescript(SCHEMA)

        def sql(statement, parameters=()):
            with closing(sqlite3.connect(database)) as db, db:
                return db.execute(statement, parameters).fetchall()

        def scalar(statement):
            return sql(statement)[0][0]

        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        base = f"http://127.0.0.1:{port}/VNZ-TECH"
        with (temporary / "http.log").open("w+", encoding="utf-8") as log:
            server = subprocess.Popen([
                PHP, "-d", 'session.save_path="' + sessions.as_posix() + '"',
                "-S", f"127.0.0.1:{port}", "-t", str(public),
            ], cwd=temporary, stdout=log, stderr=subprocess.STDOUT)
            try:
                opener = build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())

                def request(path, fields=None, picture=False):
                    headers = {}
                    if picture:
                        boundary = "vnz-synthetic-signup-boundary"
                        pieces = []
                        for key, value in fields.items():
                            pieces.append(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n".encode())
                        pieces.extend([
                            f"--{boundary}\r\nContent-Disposition: form-data; name=\"profile_picture\"; filename=\"fixture.png\"\r\nContent-Type: image/png\r\n\r\n".encode(),
                            PNG, f"\r\n--{boundary}--\r\n".encode(),
                        ])
                        data = b"".join(pieces)
                        headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
                    else:
                        data = None if fields is None else urlencode(fields).encode()
                    try:
                        response = opener.open(Request(base + path, data=data, headers=headers), timeout=10)
                    except HTTPError as error:
                        response = error
                    return response.code, response.headers, response.read().decode("utf-8")

                def state(action="inspect", **flags):
                    code, _, body = request("/__fixture.php?" + urlencode({"action": action, **flags}))
                    check(code == 200, f"Fixture route failed: {code} {body[:160]}")
                    return json.loads(body)

                def reset(**flags):
                    for table in ["users", "fixture_identity", "email_verification_tokens", "user_oauth_accounts", "fixture_claim_events"]:
                        sql("DELETE FROM " + table)
                    return state("reset", **flags)

                def fields(csrf):
                    return {"csrf_token": csrf, "full_name": "Synthetic Seller", "mobile": "09123456789",
                            "email": "seller@example.test", "password": "Synthetic1!", "confirm_password": "Synthetic1!",
                            "terms": "1", "language": "en"}

                def target(headers, path, **query):
                    location = urlsplit(headers.get("Location", ""))
                    return location.path == "/VNZ-TECH" + path and all(
                        parse_qs(location.query).get(key) == [value] for key, value in query.items())

                for _ in range(80):
                    try:
                        state()
                        break
                    except (URLError, ConnectionError):
                        check(server.poll() is None, "Fixture PHP server exited.")
                        time.sleep(0.05)
                else:
                    raise RuntimeError("Fixture PHP server did not start.")
                passed = []

                reset(guest_proof=1)
                code, _, body = request("/account/register.php")
                token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
                check(code == 200 and token and token.group(1) == state()["csrf"], "Signup form must render its real session CSRF token.")
                check('aria-describedby="profilePictureHelp"' in body, "Previously approved upload accessibility must remain.")
                response = request("/backend/register.php", fields("wrong-csrf"))
                check(response[0] == 302 and scalar("SELECT COUNT(*) FROM users") == 0, "Invalid CSRF cannot create or reserve an account.")
                passed.append("password signup CSRF and previous upload markup preserved")

                csrf = reset()["csrf"]
                code, headers, _ = request("/backend/register.php", fields(csrf))
                check(code == 303 and target(headers, "/account/login.php", success="1"), "Normal signup must remain available without a guest identity attempt.")
                check(scalar("SELECT COUNT(*) FROM users") == 1 and scalar("SELECT COUNT(*) FROM fixture_identity") == 0,
                      "No guest capability must not invent a guest identity reservation from email alone.")
                passed.append("normal signup without guest proof remains available without invented identity")

                csrf = reset(guest_proof=1)["csrf"]
                code, headers, _ = request("/backend/register.php", fields(csrf))
                check(code == 303 and target(headers, "/account/login.php", success="1", verification="sent"), "Password account must still return to login.")
                check(scalar("SELECT email_verified_at FROM users") is None, "Signup must not imply verified email.")
                check(scalar("SELECT state FROM fixture_identity") == "reserved", "Guest identity remains reserved until verified email.")
                check(not state()["logged_in"], "Password signup must not establish a login session.")
                passed.append("password user/reservation commit precedes email and stays signed out")

                code, _, body = request("/backend/verify_email.php?token=" + TOKEN)
                verify_csrf = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
                check(code == 200 and verify_csrf, "Verification confirmation must still render.")
                code, _, _ = request("/backend/verify_email.php", {"token": TOKEN, "csrf_token": verify_csrf.group(1)})
                check(code == 302 and scalar("SELECT state FROM fixture_identity") == "finalized", "Email verification must finalize reserved identity.")
                check(scalar("SELECT COUNT(*) FROM fixture_claim_events") == 1, "Existing submission-claim flow must remain independent and called.")
                check(scalar("SELECT COUNT(*) FROM email_verification_tokens") == 0, "Successful verification consumes email token.")
                passed.append("email confirmation commits identity alongside independent guest claims")

                csrf = reset(guest_proof=1, reserve_failure=1)["csrf"]
                before = set((app / "uploads/profile_pictures").glob("*"))
                code, headers, _ = request("/backend/register.php", fields(csrf), picture=True)
                check(code == 302 and target(headers, "/account/register.php"), "Reservation failure must return signup error.")
                check(scalar("SELECT COUNT(*) FROM users") == 0 and scalar("SELECT COUNT(*) FROM fixture_identity") == 0,
                      "Reservation failure must roll back both user and partial identity binding.")
                check(set((app / "uploads/profile_pictures").glob("*")) == before, "Rolled-back signup must remove its new profile upload.")
                passed.append("reservation failure rolls back user/binding and cleans only new profile")

                csrf = reset(guest_proof=1, mail_failure=1)["csrf"]
                code, headers, _ = request("/backend/register.php", fields(csrf), picture=True)
                check(code == 303 and target(headers, "/account/login.php", verification="pending"), "Mail failure must retain successful signup recovery path.")
                profile = scalar("SELECT profile_picture FROM users")
                check((app / profile).is_file() and scalar("SELECT COUNT(*) FROM fixture_identity") == 1,
                      "Post-commit mail failure must retain owned profile and guest reservation.")
                passed.append("mail failure retains committed account/profile/reservation")

                # Duplicate-account failure may remove only the new upload, never an existing account's image.
                code, _, _ = request("/backend/register.php", fields(csrf), picture=True)
                check(code == 302 and scalar("SELECT COUNT(*) FROM users") == 1 and (app / profile).is_file(),
                      "Duplicate signup must leave existing account/profile unchanged.")
                passed.append("duplicate signup preserves existing account profile")

                for provider, expected in [("google", "finalized"), ("facebook", "reserved")]:
                    csrf = reset(guest_proof=1, provider=provider)["csrf"]
                    code, headers, _ = request("/backend/social_signup_complete.php", fields(csrf))
                    check(code == 302 and target(headers, "/account/dashboard.php"), "Existing social automatic login must remain.")
                    check(scalar("SELECT state FROM fixture_identity") == expected, "Only provider-verified matching email can finalize during signup.")
                    check(state()["logged_in"] and scalar("SELECT COUNT(*) FROM user_oauth_accounts") == 1,
                          "Social identity and login must remain committed.")
                    passed.append(provider + " signup retains provider email trust and auth behavior")

                csrf = reset(guest_proof=1, provider="google")["csrf"]
                sql("INSERT INTO user_oauth_accounts VALUES (999, 'google', ?)", (hashlib.sha256(b"synthetic-subject").hexdigest(),))
                code, _, _ = request("/backend/social_signup_complete.php", fields(csrf))
                check(code == 302 and scalar("SELECT COUNT(*) FROM users") == 0 and scalar("SELECT COUNT(*) FROM fixture_identity") == 0,
                      "OAuth conflict must roll back new user and reserved/finalized identity.")
                passed.append("social identity conflict rolls back complete new-account transaction")

                csrf = reset(guest_proof=1)["csrf"]
                request("/backend/register.php", fields(csrf))
                state("reset", finalize_failure=1)
                csrf = state()["csrf"]
                code, _, _ = request("/backend/verify_email.php", {"token": TOKEN, "csrf_token": csrf})
                check(code == 500 and scalar("SELECT email_verified_at FROM users") is None, "Finalize failure must roll back email verification.")
                check(scalar("SELECT state FROM fixture_identity") == "reserved" and scalar("SELECT COUNT(*) FROM fixture_claim_events") == 0,
                      "Finalize failure must roll back identity and guest claim changes.")
                check(scalar("SELECT COUNT(*) FROM email_verification_tokens") == 1, "Retry must retain verification token after rollback.")
                passed.append("email finalize failure rolls back verified email/claims and keeps token usable")

                log.flush()
                log.seek(0)
                output = log.read()
                check(not any(message in output for message in ["PHP Fatal error", "PHP Warning", "PHP Parse error"]),
                      "Signup HTTP checks must run without PHP failures or warnings.")
                print(f"Identity signup HTTP checks passed: {len(passed)}")
                for result in passed:
                    print("- " + result)
            finally:
                server.terminate()
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=5)


if __name__ == "__main__":
    main()
