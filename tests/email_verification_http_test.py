"""Exercise the real verification PHP routes over isolated local HTTP.

Only temporary copies are served. A SQLite adapter replaces the database
bootstrap; every account, email address, token, session, and submission is fake.
No SMTP or application database connection is used.
"""

import hashlib
from contextlib import closing
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
from urllib.request import build_opener, HTTPRedirectHandler, HTTPCookieProcessor


REPO = Path(__file__).resolve().parent.parent
PHP = os.environ.get("PHP_BINARY") or shutil.which("php") or "C:/xampp/php/php.exe"
TOKEN = "ab" * 32  # Deliberately fake; used only in the disposable fixture.

SCHEMA = """
CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL,
    email_verified_at TEXT, is_active INTEGER NOT NULL);
CREATE TABLE email_verification_tokens (user_id INTEGER PRIMARY KEY,
    token_hash TEXT, expires_at TEXT, last_requested_at TEXT);
CREATE TABLE submissions (id INTEGER PRIMARY KEY, user_id INTEGER,
    seller_email TEXT, status TEXT NOT NULL, assigned_admin_id INTEGER);
CREATE TABLE guest_chat_identities (id INTEGER PRIMARY KEY,
    submission_id INTEGER NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT);
CREATE TABLE transactions (id INTEGER PRIMARY KEY, submission_id INTEGER NOT NULL,
    offer_id INTEGER, processed_by_admin_id INTEGER);
CREATE TABLE offers (id INTEGER PRIMARY KEY, submission_id INTEGER NOT NULL,
    sender_type TEXT NOT NULL DEFAULT 'admin', created_by_admin_id INTEGER);
CREATE TABLE admins (id INTEGER PRIMARY KEY);
CREATE TABLE guest_submission_claims (submission_id INTEGER PRIMARY KEY,
    seller_id INTEGER NOT NULL, guest_identity_id INTEGER NOT NULL, proof_method TEXT NOT NULL);
CREATE TABLE conversations (id INTEGER PRIMARY KEY, type TEXT NOT NULL,
    transaction_id INTEGER, submission_id INTEGER);
CREATE TABLE conversation_participants (id INTEGER PRIMARY KEY,
    conversation_id INTEGER NOT NULL, actor_type TEXT NOT NULL,
    actor_id INTEGER NOT NULL, last_read_message_id INTEGER,
    UNIQUE (conversation_id, actor_type, actor_id));
CREATE TABLE messages (id INTEGER PRIMARY KEY, conversation_id INTEGER NOT NULL,
    sender_type TEXT NOT NULL, sender_id INTEGER NOT NULL);
CREATE TABLE message_deliveries (message_id INTEGER NOT NULL,
    recipient_type TEXT NOT NULL, recipient_id INTEGER NOT NULL,
    delivered_at TEXT NOT NULL, PRIMARY KEY (message_id, recipient_type, recipient_id));
CREATE TABLE message_reactions (message_id INTEGER NOT NULL, actor_type TEXT NOT NULL,
    actor_id INTEGER NOT NULL, reaction TEXT NOT NULL,
    UNIQUE (message_id, actor_type, actor_id, reaction));
"""

DATABASE_BOOTSTRAP = r"""<?php
// Fixture boundary: production routes/helpers below remain unmodified.
class VerificationFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(preg_replace('/\bFOR\s+UPDATE\b/i', '', $query), $options);
    }
}
$pdo = new VerificationFixturePDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('NOW', static fn() => gmdate('Y-m-d H:i:s'), 0);
"""

FIXTURE_ROUTE = r"""<?php
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/csrf.php';
require_once __DIR__ . '/backend/email_verification_feedback.php';
$action = $_GET['action'] ?? 'inspect';
$sellerId = (int) ($_GET['seller'] ?? 0);
if ($action === 'reset') {
    $_SESSION = [];
    if ($sellerId > 0) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $sellerId;
    }
    if (($_GET['onboarding'] ?? '') === '1') {
        $_SESSION['guest_submission_onboarding'] = [
            'seller_id' => 1, 'email' => 'seller@example.test',
            'submission_id' => 101, 'auto_show' => false, 'last_sent_at' => 0
        ];
    }
} elseif ($action === 'signin') {
    session_regenerate_id(true);
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $sellerId;
}
header('Content-Type: application/json');
echo json_encode([
    'csrf_token' => generateCsrfToken(),
    'seller_id' => $_SESSION['user_id'] ?? null,
    'feedback' => $_SESSION['email_verification_feedback'] ?? null,
    'onboarding' => $_SESSION['guest_submission_onboarding'] ?? null
], JSON_THROW_ON_ERROR);
"""


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def seed_database(db_path):
    with closing(sqlite3.connect(db_path)) as db, db:
        tables = db.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()
        for (table,) in tables:
            db.execute('DROP TABLE "' + table.replace('"', '""') + '"')
        db.executescript(SCHEMA)
        db.executemany("INSERT INTO users VALUES (?, ?, NULL, 1)", [
            (1, "seller@example.test"), (2, "other@example.test")])
        db.execute("INSERT INTO email_verification_tokens VALUES (?, ?, datetime('now', '+30 minutes'), datetime('now'))",
                   (1, hashlib.sha256(TOKEN.encode()).hexdigest()))
        db.executescript("""
            INSERT INTO submissions VALUES (101, NULL, 'Seller@Example.Test', 'accepted', 9);
            INSERT INTO guest_chat_identities VALUES (111, 101, datetime('now', '+1 day'), NULL);
            INSERT INTO transactions VALUES (121, 101, NULL, 9);
            INSERT INTO admins VALUES (9);
            INSERT INTO conversations VALUES (131, 'private', NULL, 101);
            INSERT INTO conversation_participants VALUES (141, 131, 'guest', 111, 57);
            INSERT INTO conversation_participants VALUES (142, 131, 'admin', 9, 81);
            INSERT INTO messages VALUES (151, 131, 'guest', 111);
            INSERT INTO message_deliveries VALUES (151, 'guest', 111, datetime('now'));
            INSERT INTO message_reactions VALUES (151, 'guest', 111, 'like');
        """)


def scalar(db_path, sql):
    with closing(sqlite3.connect(db_path)) as db:
        return db.execute(sql).fetchone()[0]


def main():
    check(Path(PHP).is_file() or shutil.which(PHP), "PHP CLI is required.")
    source_files = [
        "backend/verify_email.php", "backend/session.php", "backend/csrf.php",
        "backend/guest_submission_link.php", "backend/guest_submission_onboarding.php",
        "backend/guest_submission_onboarding_status.php", "backend/email_verification_feedback.php",
        "backend/identity_verification.php",
        "backend/identity_automation.php",
        "backend/submission_security_face.php",
        "account/login.php",
    ]
    for relative in source_files:
        check((REPO / relative).is_file(), f"Required implementation is missing: {relative}")

    with tempfile.TemporaryDirectory(prefix="vnz-email-verification-http-") as temp:
        temporary = Path(temp)
        document_root = temporary / "public"
        app = document_root / "VNZ-TECH"
        sessions = temporary / "sessions"
        sessions.mkdir()
        for relative in source_files:
            target = app / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(REPO / relative, target)
        (app / "backend/database.php").write_text(DATABASE_BOOTSTRAP, encoding="utf-8")
        (app / "__fixture.php").write_text(FIXTURE_ROUTE, encoding="utf-8")
        for relative in ["includes/loader-head.php", "includes/loader-body.php", "includes/social_auth_buttons.php",
                         "assets/css/social_auth.css", "assets/css/public_theme.css", "assets/js/public-theme.js"]:
            target = app / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text("", encoding="utf-8")
        db_path = document_root / "fixture.sqlite"
        seed_database(db_path)
        # This regression checks existing email/submission claims, independently
        # of the new optional identity module. Never load the host's real config.
        identity_config = temporary / "identity-disabled.php"
        identity_config.write_text("<?php return ['enabled' => false];", encoding="utf-8")
        server_environment = dict(os.environ)
        server_environment["VNZ_IDENTITY_CONFIG_PATH"] = str(identity_config)
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        base = f"http://127.0.0.1:{port}/VNZ-TECH"
        log_path = temporary / "php-http.log"
        passed = []
        with log_path.open("w+", encoding="utf-8") as server_log:
            server = subprocess.Popen([
                PHP, "-d", 'session.save_path="' + sessions.as_posix() + '"',
                "-S", f"127.0.0.1:{port}", "-t", str(document_root)
            ], cwd=temporary, env=server_environment, stdout=server_log, stderr=subprocess.STDOUT)
            try:
                opener = build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())

                def request(path, fields=None):
                    data = None if fields is None else urlencode(fields).encode()
                    try:
                        response = opener.open(base + path, data=data, timeout=5)
                    except HTTPError as error:
                        response = error
                    return response.code, response.headers, response.read().decode("utf-8")

                def state(action="inspect", seller=0, onboarding=False):
                    code, _, body = request("/__fixture.php?" + urlencode({
                        "action": action, "seller": seller, "onboarding": int(onboarding)}))
                    check(code == 200, f"Fixture response failed: {code} {body[:180]}")
                    return json.loads(body)

                def reset(seller=1, onboarding=True):
                    seed_database(db_path)
                    return state("reset", seller, onboarding)

                def confirm():
                    code, _, body = request("/backend/verify_email.php?token=" + TOKEN)
                    check(code == 200, f"Confirmation route returned {code}: {body[:180]}")
                    token_match = re.search(r'name="csrf_token" value="([^"]+)"', body)
                    check(token_match is not None, "Confirmation must include its real session CSRF token.")
                    check('method="POST"' in body and "VERIFY MY EMAIL" in body,
                          "Valid emailed link must present an explicit verification form.")
                    return token_match.group(1)

                def post_verify(csrf):
                    return request("/backend/verify_email.php", {"token": TOKEN, "csrf_token": csrf})

                def redirect_matches(headers, path, **parameters):
                    target = urlsplit(headers.get("Location", ""))
                    return target.path == "/VNZ-TECH" + path and all(
                        parse_qs(target.query).get(key) == [value] for key, value in parameters.items())

                for _ in range(80):
                    try:
                        state()
                        break
                    except (URLError, ConnectionError):
                        if server.poll() is not None:
                            raise RuntimeError("Isolated PHP HTTP server exited before startup.")
                        time.sleep(0.05)
                else:
                    raise RuntimeError("Isolated PHP HTTP server did not start.")

                reset()
                csrf = confirm()
                check(scalar(db_path, "SELECT email_verified_at FROM users WHERE id=1") is None,
                      "Opening a link must not verify the account.")
                check(scalar(db_path, "SELECT COUNT(*) FROM email_verification_tokens") == 1,
                      "Opening a link must not consume its token.")
                passed.append("valid GET requires confirmation without mutation")

                code, _, _ = post_verify("wrong-fixture-csrf")
                check(code == 419, "Verification POST must reject invalid CSRF.")
                check(scalar(db_path, "SELECT email_verified_at FROM users WHERE id=1") is None,
                      "Rejected CSRF must leave verification unchanged.")
                check(scalar(db_path, "SELECT user_id FROM submissions WHERE id=101") is None,
                      "Rejected CSRF must leave guest ownership unchanged.")
                passed.append("invalid CSRF cannot verify or link")

                code, headers, body = post_verify(csrf)
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php", page="dashboard", verification="success"),
                      f"Guest verification must return to dashboard: {code} {body[:180]}")
                check(scalar(db_path, "SELECT email_verified_at FROM users WHERE id=1") is not None,
                      "Successful POST must verify the seller.")
                check(scalar(db_path, "SELECT user_id FROM submissions WHERE id=101") == 1,
                      "Successful verification must assign the exact eligible guest submission.")
                check(scalar(db_path, "SELECT COUNT(*) FROM email_verification_tokens") == 0,
                      "Successful verification must consume the token.")
                check(scalar(db_path, "SELECT seller_id FROM guest_submission_claims WHERE submission_id=101") == 1,
                      "Actual linking must retain the verified-email ownership audit.")
                check(scalar(db_path, "SELECT sender_type FROM messages WHERE id=151") == "seller",
                      "Actual linking must preserve and transfer existing chat history.")
                current = state()
                feedback = current["feedback"]
                check(feedback and feedback["seller_id"] == 1 and feedback["result"] == "success" and feedback["id"],
                      "Successful commit must queue trusted, seller-bound feedback.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                status = json.loads(body)
                check(code == 200 and status["phase"] == "linked", "Real status route must report actual linked ownership.")
                check(status["auto_show"] is True and status["verification_result"] == "success",
                      "Successful verification must reopen previously dismissed matching onboarding.")
                passed.append("POST verifies, links, consumes token, and reopens success feedback")

                code, _, _ = request("/backend/guest_submission_onboarding_status.php", {
                    "action": "dismiss", "csrf_token": csrf, "presented_phase": "verify", "feedback_id": ""})
                check(code == 200 and state()["feedback"]["id"] == feedback["id"],
                      "A delayed pre-verification dismissal must not consume new success feedback.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                check(json.loads(body)["auto_show"] is True,
                      "A delayed dismissal must not suppress the new verified notice.")
                code, _, _ = request("/backend/guest_submission_onboarding_status.php", {
                    "action": "dismiss", "csrf_token": csrf, "presented_phase": "linked", "feedback_id": feedback["id"]})
                check(code == 200 and state()["feedback"] is None,
                      "Displaying the matching feedback must consume its event once.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                check(json.loads(body)["auto_show"] is False,
                      "A verified notice already shown must not reopen on every dashboard refresh.")
                passed.append("success notice survives delayed dismissal and is consumed once")

                code, headers, _ = request("/backend/verify_email.php?token=" + TOKEN)
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php", page="dashboard", verification="invalid"),
                      "Already-used link must return a visible invalid result to the signed-in dashboard.")
                check(state()["feedback"]["result"] == "invalid", "Invalid link must queue truthful failure feedback.")
                check(scalar(db_path, "SELECT COUNT(*) FROM guest_submission_claims") == 1,
                      "Repeating a used link must not duplicate its ownership claim.")
                passed.append("used link preserves invalid feedback instead of a silent dashboard redirect")

                reset()
                code, headers, _ = request("/backend/verify_email.php?token=not-a-valid-token")
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php", verification="invalid"),
                      "Malformed link must preserve invalid feedback for an authenticated seller.")
                check(state()["feedback"]["result"] == "invalid", "Malformed token must queue an invalid result.")
                code, headers, _ = request("/account/login.php?verification=invalid")
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php")
                      and state()["feedback"]["result"] == "invalid",
                      "Legacy login invalid result must not disappear in the logged-in redirect.")
                passed.append("malformed and legacy invalid redirects remain visible")

                reset()
                with closing(sqlite3.connect(db_path)) as db, db:
                    db.execute("UPDATE email_verification_tokens SET expires_at=datetime('now', '-1 minute')")
                code, headers, _ = request("/backend/verify_email.php?token=" + TOKEN)
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php", verification="invalid")
                      and state()["feedback"]["result"] == "invalid",
                      "An expired token must report invalid rather than silently opening the dashboard.")
                check(scalar(db_path, "SELECT email_verified_at FROM users WHERE id=1") is None,
                      "Expired tokens cannot verify the account.")
                passed.append("expired token reports failure without verification")

                reset(seller=1, onboarding=False)
                code, headers, _ = request("/account/login.php?verification=success")
                check(code == 302 and redirect_matches(headers, "/account/dashboard.php"),
                      "An already signed-in seller should continue to the dashboard.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                check(code == 200 and json.loads(body)["available"] is False and state()["feedback"] is None,
                      "An untrusted success query must never manufacture verified feedback.")
                check(scalar(db_path, "SELECT email_verified_at FROM users WHERE id=1") is None,
                      "An untrusted success query must never verify an account.")
                passed.append("forged success query cannot manufacture verification feedback")

                reset(seller=2, onboarding=False)
                csrf = confirm()
                code, headers, _ = post_verify(csrf)
                check(code == 302 and redirect_matches(headers, "/account/login.php", verification="success"),
                      "Verifying another seller must not show success for the signed-in account.")
                current = state()
                check(current["seller_id"] == 2 and current["feedback"]["seller_id"] == 1,
                      "Verification feedback must remain bound to the token owner.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                status = json.loads(body)
                check(code == 200 and status["available"] is False,
                      "Another signed-in seller must never receive the token owner's onboarding success.")
                passed.append("another account cannot inherit verification success")

                reset(seller=0, onboarding=False)
                csrf = confirm()
                code, headers, _ = post_verify(csrf)
                check(code == 302 and redirect_matches(headers, "/account/login.php", verification="success"),
                      "Logged-out verification must lead to login.")
                feedback_before = state()["feedback"]
                check(feedback_before["seller_id"] == 1 and feedback_before["result"] == "success",
                      "Logged-out verification must queue seller-bound success.")
                code, _, body = request("/account/login.php?verification=success")
                check(code == 200, f"Logged-out real login route must render: {code} {body[:180]}")
                check(state()["feedback"] == feedback_before,
                      "Rendering login must retain success for the matching seller dashboard.")
                state("signin", seller=1)
                check(state()["feedback"] == feedback_before, "Normal session regeneration must preserve verification feedback.")
                code, headers, _ = request("/account/login.php")
                check(code == 302 and urlsplit(headers.get("Location", "")).path == "/VNZ-TECH/account/dashboard.php",
                      "Matching signed-in seller must continue to dashboard.")
                code, _, body = request("/backend/guest_submission_onboarding_status.php")
                status = json.loads(body)
                check(code == 200 and status["available"] is True and status["phase"] in {"verified", "linked"},
                      "The real status route must expose matching seller verification success after login.")
                passed.append("logged-out verification success survives login and session regeneration")

                server_log.flush()
                log = log_path.read_text(encoding="utf-8")
                check(not any(message in log for message in ["PHP Fatal error", "PHP Warning", "PHP Parse error"]),
                      "HTTP fixture must run without PHP errors or warnings.")
            except Exception:
                server_log.flush()
                print(log_path.read_text(encoding="utf-8"))
                raise
            finally:
                server.terminate()
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=5)
        for name in passed:
            print("PASS " + name)
        print(json.dumps({"passed": len(passed), "isolation": "temporary PHP copies, SQLite, fake sessions; no SMTP or live accounts"}))


if __name__ == "__main__":
    main()
