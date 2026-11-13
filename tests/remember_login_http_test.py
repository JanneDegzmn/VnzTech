"""Real seller login/remember-cookie HTTP regressions in a disposable fixture.

Routes and remember/session helpers are copied without changes. SQLite replaces
the application database; chat presence is stubbed at its integration boundary.
All credentials, tokens, users, and sessions are fake. No live DB or SMTP is used.
"""

from contextlib import closing
import hashlib
from http.cookiejar import Cookie, CookieJar
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
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPRedirectHandler, HTTPCookieProcessor, Request


REPO = Path(__file__).resolve().parent.parent
PHP = os.environ.get("PHP_BINARY") or shutil.which("php") or "C:/xampp/php/php.exe"
PASSWORD = "Fixture-only-remember-2026!"
EMAIL = "seller@example.test"
COOKIE_NAME = "vnz_remember"

USERS_SCHEMA = """
CREATE TABLE users (
    id INTEGER PRIMARY KEY, full_name TEXT NOT NULL, email TEXT NOT NULL,
    mobile TEXT NOT NULL, password_hash TEXT NOT NULL, is_active INTEGER NOT NULL,
    totp_secret TEXT, totp_enabled_at TEXT, totp_last_used_counter INTEGER,
    totp_reminder_dismissed INTEGER NOT NULL, totp_login_required INTEGER NOT NULL
);
"""

REMEMBER_SCHEMA = """
CREATE TABLE remember_login_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT, selector TEXT NOT NULL UNIQUE,
    validator_hash TEXT NOT NULL, actor_type TEXT NOT NULL,
    actor_id INTEGER NOT NULL, expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, last_used_at TEXT
);
"""

DATABASE_BOOTSTRAP = r"""<?php
// Fixture boundary: no production database bootstrap is loaded.
$pdo = new PDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('FROM_UNIXTIME', static fn($timestamp) => gmdate('Y-m-d H:i:s', (int) $timestamp), 1);
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/remember_login.php';
$fixtureRestored = vnzRestoreRememberedLogin($pdo);
"""

PRESENCE_BOUNDARY = r"""<?php
// Persistence authentication is under test; presence transport/storage is not.
function chatRegisterPresenceSession(string $type, int $id): void {
    $_SESSION['fixture_presence_registered'] = true;
}
function chatEndPresenceSession(string $type, int $id): bool { return true; }
function chatLogAuthenticationTiming(string $operation, float $startedAt): void {}
"""

FIXTURE_ROUTE = r"""<?php
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/csrf.php';
$invalidActorRejected = false;
if (($_GET['action'] ?? '') === 'invalid_actor') {
    try {
        vnzIssueRememberLoginToken($pdo, 'invalid', 1);
    } catch (InvalidArgumentException $error) {
        $invalidActorRejected = true;
    }
}
header('Content-Type: application/json');
echo json_encode([
    'csrf_token' => generateCsrfToken(),
    'signed_in' => !empty($_SESSION['user_logged_in']),
    'seller_id' => $_SESSION['user_id'] ?? null,
    'pending_two_factor' => $_SESSION['seller_2fa_login_user_id'] ?? null,
    'remember_after_two_factor' => $_SESSION['remember_seller_after_2fa'] ?? false,
    'presence_registered' => $_SESSION['fixture_presence_registered'] ?? false,
    'restored' => $fixtureRestored,
    'invalid_actor_rejected' => $invalidActorRejected
], JSON_THROW_ON_ERROR);
"""


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def seed(db_path, password_hash, remember=True, two_factor=False, active=True):
    with closing(sqlite3.connect(db_path)) as db, db:
        for (table,) in db.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall():
            if not table.startswith("sqlite_"):
                db.execute('DROP TABLE "' + table.replace('"', '""') + '"')
        db.executescript(USERS_SCHEMA)
        if remember:
            db.executescript(REMEMBER_SCHEMA)
        db.execute("INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, ?)", (
            1, "Fixture Seller", EMAIL, "09000000001", password_hash, int(active),
            "JBSWY3DPEHPK3PXP" if two_factor else None,
            "2026-01-01 00:00:00" if two_factor else None, int(two_factor)))


def rows(db_path, sql, parameters=()):
    with closing(sqlite3.connect(db_path)) as db:
        return db.execute(sql, parameters).fetchall()


def mutate(db_path, sql, parameters=()):
    with closing(sqlite3.connect(db_path)) as db, db:
        db.execute(sql, parameters)


class Client:
    def __init__(self, base):
        self.base = base
        self.cookies = CookieJar()
        self.opener = build_opener(HTTPCookieProcessor(self.cookies), NoRedirect())

    def request(self, path, fields=None):
        request = Request(self.base + path,
                          data=None if fields is None else urlencode(fields).encode(),
                          headers={"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"})
        try:
            response = self.opener.open(request, timeout=5)
        except HTTPError as error:
            response = error
        with response:
            return response.code, response.headers, response.read().decode("utf-8")

    def state(self):
        code, _, body = self.request("/__fixture.php")
        check(code == 200, f"Fixture state returned {code}: {body[:160]}")
        return json.loads(body)

    def login(self, remember=True, password=PASSWORD, csrf=None):
        if csrf is None:
            csrf = self.state()["csrf_token"]
        fields = {"login": EMAIL, "password": password, "csrf_token": csrf}
        if remember:
            fields["remember"] = "1"
        code, headers, body = self.request("/backend/login.php", fields)
        return code, headers, json.loads(body)

    def cookie(self, name=COOKIE_NAME):
        return next((cookie for cookie in self.cookies if cookie.name == name), None)

    def forget_session(self):
        for cookie in list(self.cookies):
            if cookie.name == "PHPSESSID":
                self.cookies.clear(cookie.domain, cookie.path, cookie.name)

    def set_remember(self, value):
        self.cookies.set_cookie(Cookie(
            version=0, name=COOKIE_NAME, value=value, port=None, port_specified=False,
            domain="127.0.0.1", domain_specified=False, domain_initial_dot=False,
            path="/VNZ-TECH", path_specified=True, secure=False,
            expires=int(time.time()) + 3600, discard=False, comment=None,
            comment_url=None, rest={}, rfc2109=False))


def main():
    check(Path(PHP).is_file() or shutil.which(PHP), "PHP CLI is required.")
    password_hash = subprocess.run(
        [PHP, "-r", "echo password_hash('" + PASSWORD + "', PASSWORD_DEFAULT);"],
        capture_output=True, text=True, check=True).stdout
    source_files = ["backend/login.php", "backend/remember_login.php", "backend/session.php", "backend/csrf.php"]
    with tempfile.TemporaryDirectory(prefix="vnz-remember-login-http-") as temp:
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
        (app / "backend/chat_presence_store.php").write_text(PRESENCE_BOUNDARY, encoding="utf-8")
        (app / "__fixture.php").write_text(FIXTURE_ROUTE, encoding="utf-8")
        db_path = document_root / "fixture.sqlite"
        seed(db_path, password_hash)
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
            ], cwd=temporary, stdout=server_log, stderr=subprocess.STDOUT)
            try:
                for _ in range(80):
                    try:
                        Client(base).state()
                        break
                    except (URLError, ConnectionError):
                        check(server.poll() is None, "Isolated PHP HTTP server exited during startup.")
                        time.sleep(0.05)
                else:
                    raise RuntimeError("Isolated PHP HTTP server did not start.")

                def reset(**options):
                    seed(db_path, password_hash, **options)
                    return Client(base)

                client = reset()
                code, headers, data = client.login()
                check(code == 200 and data.get("success") is True, "Remember-me login must accept valid credentials.")
                cookie = client.cookie()
                check(cookie is not None and cookie.expires and cookie.expires > time.time() + 86400,
                      "Remember-me login must issue a persistent cookie.")
                cookie_headers = headers.get_all("Set-Cookie", [])
                persistent_header = next((value for value in cookie_headers if value.startswith(COOKIE_NAME + "=")), "")
                check("HttpOnly" in persistent_header and "SameSite=Lax" in persistent_header
                      and "path=/VNZ-TECH" in persistent_header,
                      "The persistent cookie must retain HttpOnly, SameSite, and application path restrictions.")
                match = re.fullmatch(r"([a-f0-9]{32})\.([a-f0-9]{64})", cookie.value)
                check(match is not None, "Remember cookie must have an independent selector and validator.")
                token = rows(db_path, "SELECT validator_hash, actor_type, actor_id FROM remember_login_tokens WHERE selector=?", (match[1],))
                check(len(token) == 1 and token[0] == (hashlib.sha256(match[2].encode()).hexdigest(), "seller", 1),
                      "Remember token must store only the hashed validator and the authenticated seller identity.")
                old_session = client.cookie("PHPSESSID").value
                client.forget_session()
                state = client.state()
                check(state["signed_in"] and state["seller_id"] == 1 and state["restored"],
                      "The persistent cookie must restore the seller in a fresh session.")
                check(client.cookie("PHPSESSID").value != old_session,
                      "Restoring a remembered seller must create a fresh session identifier.")
                check(rows(db_path, "SELECT last_used_at FROM remember_login_tokens")[0][0] is not None,
                      "Restoring a token must record its use.")
                passed.append("checked login issues a protected hashed token and restores a fresh session")

                client = reset()
                code, _, data = client.login(remember=False)
                check(code == 200 and data.get("success") is True and client.state()["signed_in"],
                      "Unchecked Remember me must permit valid password login.")
                check(client.cookie() is None and rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "Unchecked login must not issue a persistent cookie or token.")
                client.forget_session()
                check(not client.state()["signed_in"], "Unchecked login must not restore after its session is gone.")
                passed.append("unchecked login succeeds without persistence")

                client = reset(remember=False)
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True and client.state()["signed_in"],
                      "Missing remember-token schema must not turn correct credentials into a login failure.")
                check(client.cookie() is None, "Failed optional persistence must not leave a usable remember cookie.")
                client.forget_session()
                check(not client.state()["signed_in"], "Missing persistence storage must never restore an account.")
                passed.append("missing remember schema preserves checked password login")

                client = reset(remember=False)
                client.set_remember("ab" * 16 + "." + "cd" * 32)
                code, _, data = client.login(remember=False)
                check(code == 200 and data.get("success") is True and client.state()["signed_in"],
                      "An obsolete cookie and missing remember table must not break unchecked login.")
                check(client.cookie() is None, "Unchecked login must clear an obsolete cookie even when persistence is unavailable.")
                passed.append("missing schema and obsolete cookie preserve unchecked password login")

                client = reset()
                client.set_remember("malformed-fixture-cookie")
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "Malformed cookies must remain signed out and be cleared.")
                passed.append("malformed remember cookie cannot authenticate")

                client = reset()
                client.set_remember("ab" * 16 + "." + "cd" * 32)
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "A syntactically valid cookie without a token record cannot authenticate.")
                passed.append("unknown remember token cannot authenticate")

                client = reset()
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True, "Fixture token login failed.")
                mutate(db_path, "UPDATE remember_login_tokens SET expires_at=datetime('now', '-1 minute')")
                client.forget_session()
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "Expired remember tokens must never restore a seller.")
                check(rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "Expired presented tokens should be revoked.")
                passed.append("expired remember token is rejected and revoked")

                client = reset()
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True, "Fixture token login failed.")
                original = client.cookie().value
                replacement = "0" if original[-1] != "0" else "1"
                client.set_remember(original[:-1] + replacement)
                client.forget_session()
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "A tampered validator must never restore a seller.")
                check(rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "A presented token with a mismatched validator must be revoked.")
                passed.append("tampered remember validator is rejected and revoked")

                client = reset()
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True, "Fixture token login failed.")
                mutate(db_path, "UPDATE users SET is_active=0 WHERE id=1")
                client.forget_session()
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "A remembered token must not restore an inactive account.")
                passed.append("inactive seller cannot restore a remembered login")

                client = reset()
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True, "Fixture token login failed.")
                mutate(db_path, "UPDATE remember_login_tokens SET actor_type='invalid'")
                client.forget_session()
                check(not client.state()["signed_in"] and client.cookie() is None,
                      "A token with an invalid actor type cannot restore authentication.")
                check(rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "A remembered token with an invalid actor type must be revoked.")
                passed.append("invalid remembered actor type cannot authenticate")

                client = reset()
                code, _, body = client.request("/__fixture.php?action=invalid_actor")
                check(code == 200 and json.loads(body)["invalid_actor_rejected"] is True,
                      "Remember-token issuer must reject invalid actor types.")
                check(client.cookie() is None and rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "Invalid actor input must not issue tokens or cookies.")
                passed.append("invalid actor input is rejected before token issuance")

                client = reset()
                code, _, data = client.login()
                check(code == 200 and data.get("success") is True, "Fixture token login failed.")
                mutate(db_path, """CREATE TRIGGER fixture_reject_token_use
                    BEFORE UPDATE OF last_used_at ON remember_login_tokens
                    BEGIN SELECT RAISE(ABORT, 'fixture-only token-use storage failure'); END""")
                client.forget_session()
                state = client.state()
                check(not state["signed_in"] and state["seller_id"] is None and not state["restored"],
                      "A token-use update failure must not leave a partially restored authenticated session.")
                check(client.cookie() is None, "Failed token restoration must clear its remember cookie.")
                passed.append("token-use storage failure leaves restoration signed out")

                client = reset(two_factor=True)
                code, _, data = client.login()
                state = client.state()
                check(code == 200 and data.get("requires_two_factor") is True and data.get("success") is False,
                      "A 2FA-enabled seller must receive the existing challenge instead of a completed login.")
                check(not state["signed_in"] and state["pending_two_factor"] == 1 and state["remember_after_two_factor"] is True,
                      "Remember preference must wait in the pending 2FA session.")
                check(client.cookie() is None and rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "No remember token or cookie may be issued before successful 2FA confirmation.")
                passed.append("2FA challenge defers persistent authentication until confirmation")

                client = reset()
                code, _, data = client.login(password="wrong-fixture-password")
                check(code == 401 and data.get("success") is False and not client.state()["signed_in"],
                      "Incorrect credentials must remain rejected.")
                check(client.cookie() is None and rows(db_path, "SELECT COUNT(*) FROM remember_login_tokens")[0][0] == 0,
                      "Incorrect credentials cannot obtain persistent authentication.")
                passed.append("incorrect credentials cannot issue remember tokens")

                server_log.flush()
                log = log_path.read_text(encoding="utf-8")
                check(not any(message in log for message in ["PHP Fatal error", "PHP Warning", "PHP Parse error"]),
                      "Isolated PHP HTTP requests must complete without warnings or fatal errors.")
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
        print(json.dumps({"passed": len(passed), "isolation": "temporary PHP copies, SQLite and fake sessions; presence stubbed; no live DB or mail"}))


if __name__ == "__main__":
    main()
