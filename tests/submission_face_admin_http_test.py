"""Private submission-photo HTTP authorization, using isolated synthetic evidence only."""
from contextlib import closing
from http.cookiejar import CookieJar
import json
import os
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.request import build_opener, HTTPCookieProcessor, Request

REPO = Path(__file__).resolve().parent.parent
PHP = os.environ.get('PHP_BINARY') or shutil.which('php') or 'C:/xampp/php/php.exe'


def main():
    checks = 0

    def check(ok, message):
        nonlocal checks
        if not ok:
            raise AssertionError(message)
        checks += 1
        print('PASS', message)

    with tempfile.TemporaryDirectory(prefix='vnz-face-admin-') as temp:
        root = Path(temp)
        web = root / 'web'
        app = web / 'VNZ-TECH'
        backend = app / 'backend'
        private = root / 'private'
        backend.mkdir(parents=True)
        (private / 'evidence').mkdir(parents=True)
        (root / 'sessions').mkdir()
        for name in ['submission_face_photo.php', 'submission_security_face.php',
                     'identity_verification.php', 'identity_automation.php', 'identity_face_matcher.php', 'auth.php',
                     'session.php', 'csrf.php']:
            shutil.copyfile(REPO / 'backend' / name, backend / name)
        (app / 'admin' / 'pages').mkdir(parents=True)
        shutil.copyfile(REPO / 'admin' / 'pages' / 'identity_verifications.php',
                        app / 'admin' / 'pages' / 'identity_verifications.php')
        (private / 'key.bin').write_bytes(os.urandom(32))
        (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time())}))
        (private / 'config.php').write_text('<?php return ["enabled"=>true,"mode"=>"face_submission_v6","storage_path"=>'
            + json.dumps(private.as_posix()) + ',"key_path"=>'
            + json.dumps((private / 'key.bin').as_posix()) + '];')
        database = root / 'fixture.sqlite'
        with closing(sqlite3.connect(database)) as db, db:
            db.executescript('''
CREATE TABLE admins (id INTEGER PRIMARY KEY, role_id INTEGER, is_active INTEGER);
CREATE TABLE roles (id INTEGER PRIMARY KEY, is_active INTEGER);
CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER);
CREATE TABLE submissions (id INTEGER PRIMARY KEY, status TEXT, updated_at TEXT);
CREATE TABLE submission_security_faces (
submission_id INTEGER PRIMARY KEY, file_name TEXT, photo_hash TEXT, context TEXT,
user_id INTEGER, policy_version TEXT, detector_version TEXT, consent_at TEXT,
created_at TEXT, terminal_at TEXT, purge_after TEXT, purged_at TEXT);
INSERT INTO admins VALUES (1,1,1),(2,2,1),(3,1,0),(4,3,1);
INSERT INTO roles VALUES(1,1),(2,1),(3,0);
INSERT INTO permissions VALUES(1,'view_submissions');
INSERT INTO role_permissions VALUES(1,1),(3,1);
''')
            for ident in range(1, 8):
                db.execute('INSERT INTO submissions VALUES(?,?,?)',
                    (ident, 'declined' if ident == 6 else 'new_submission',
                     '2000-01-01 00:00:00' if ident == 6 else '2099-01-01 00:00:00'))
        (backend / 'database.php').write_text('''<?php
$pdo=new PDO('sqlite:'.getenv('VNZ_FACE_ADMIN_TEST_DB'));
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
''')
        (app / 'fixture.php').write_text('''<?php
require __DIR__.'/backend/session.php';
$_SESSION=[];
if(isset($_GET['admin'])){$_SESSION['admin_id']=(int)$_GET['admin'];}
if(isset($_GET['seller'])){$_SESSION['user_id']=1;$_SESSION['user_logged_in']=true;$_SESSION['admin_role']='Super Admin';}
echo 'fixture session';
''')
        # Actual AES-GCM helper, generated canvas JPEG: no personal face or private app storage.
        seed = root / 'seed.php'
        seed.write_text('''<?php
require $argv[1].'/backend/identity_verification.php';
$i=imagecreatetruecolor(180,160);imagefill($i,0,0,imagecolorallocate($i,80,110,130));
ob_start();imagejpeg($i,null,85);$jpg=ob_get_clean();imagedestroy($i);
file_put_contents($argv[2],$jpg);
file_put_contents($argv[3],vnzIdentityEncrypt($jpg,vnzIdentityKey()));
''')
        env = dict(os.environ, VNZ_IDENTITY_CONFIG_PATH=str(private / 'config.php'),
                   VNZ_FACE_ADMIN_TEST_DB=str(database))
        name = 'a' * 48 + '.enc'
        generated = root / 'synthetic.jpg'
        subprocess.run([PHP, str(seed), str(app), str(generated), str(private / 'evidence' / name)],
                       env=env, check=True, capture_output=True)
        image = generated.read_bytes()
        import hashlib
        image_hash = hashlib.sha256(image).hexdigest()
        with closing(sqlite3.connect(database)) as db, db:
            for ident in range(1, 7):
                db.execute('INSERT INTO submission_security_faces VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    (ident, '../key.bin' if ident == 4 else name,
                     '0' * 64 if ident == 5 else image_hash, 'guest', None,
                     'face-submission-v6', 'face-api.js-0.22.2', '2026-01-01 00:00:00',
                     '1999-01-01 00:00:00' if ident == 6 else '2026-01-01 00:00:00', None,
                     '2000-01-01 00:00:00' if ident == 2 else None,
                     '2026-01-01 00:00:00' if ident == 3 else None))
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        logs = (root / 'php.log').open('w+', encoding='utf-8')
        server = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d',
            'session.save_path="' + (root / 'sessions').as_posix() + '"', '-S',
            f'127.0.0.1:{port}', '-t', str(web)], env=env, stdout=logs, stderr=logs)
        base = f'http://127.0.0.1:{port}/VNZ-TECH'

        def client():
            return build_opener(HTTPCookieProcessor(CookieJar()))

        def request(actor, path, data=None):
            try:
                response = actor.open(Request(base + path, data=data), timeout=10)
            except HTTPError as error:
                response = error
            return response.code, response.headers, response.read()

        photo = '/backend/submission_face_photo.php?submission_id='
        anonymous, admin, staff, inactive, role_inactive, stale, seller = [client() for _ in range(7)]
        try:
            for _ in range(40):
                try:
                    code, headers, body = request(anonymous, photo + '1')
                    break
                except (URLError, ConnectionError):
                    time.sleep(.05)
            else:
                raise RuntimeError('PHP fixture did not start')
            check(code == 403 and body == b'Private photo is unavailable.', 'anonymous request gets generic denial')
            check('no-store' in headers.get('Cache-Control', ''), 'denied private responses prohibit caching')
            for actor, ident in [(admin,1),(staff,2),(inactive,3),(role_inactive,4),(stale,99)]:
                request(actor, f'/fixture.php?admin={ident}')
            request(seller, '/fixture.php?seller=1')
            for actor, message in [(staff,'staff without permission'),(inactive,'inactive admin'),
                                   (role_inactive,'inactive role'),(stale,'stale admin session'),
                                   (seller,'seller session with forged role label')]:
                code, _, body = request(actor, photo + '1')
                check(code == 403 and body == b'Private photo is unavailable.', message + ' cannot read photo')
            code, headers, body = request(admin, photo + '1')
            check(code == 200 and body == image and headers.get('Content-Type') == 'image/jpeg', 'authorized active admin receives exact decrypted synthetic JPEG')
            check('no-store' in headers.get('Cache-Control','') and headers.get('X-Content-Type-Options') == 'nosniff'
                  and headers.get('Referrer-Policy') == 'no-referrer', 'successful photo response is private and hardened')
            check(name not in str(headers) and 'key.bin' not in str(headers), 'response does not expose private filenames or key paths')
            check(not (private / 'evidence' / name).read_bytes().startswith(b'\xff\xd8'), 'stored evidence is encrypted rather than public JPEG')
            code, _, body = request(admin, '/admin/pages/identity_verifications.php')
            check(code == 410 and b'ID verification is closed' in body and b'data-identity-evidence=' not in body,
                  'retired ID page returns 410 without querying the absent legacy identity tables')
            for ident, reason in [(2,'expired'),(3,'purged'),(4,'path traversal'),(5,'hash mismatch'),(6,'past terminal retention'),(7,'missing')]:
                code, _, body = request(admin, photo + str(ident))
                check(code == 404 and body == b'Private photo is unavailable.', reason + ' evidence is unavailable')
            for value in ['../key.bin', '0', '-1', '999999999999999999999999999999']:
                code, _, body = request(admin, photo + value)
                check(code == 404 and body == b'Private photo is unavailable.', 'invalid submission identifier is rejected: ' + value)
            code, _, body = request(admin, photo + '1', b'x=1')
            check(code == 405 and body == b'Private photo is unavailable.', 'photo endpoint is GET only')
            with closing(sqlite3.connect(database)) as db, db:
                db.execute('DELETE FROM role_permissions WHERE role_id=1')
            code, _, _ = request(admin, photo + '1')
            check(code == 403, 'permission revocation takes effect on the next image request')
        finally:
            server.terminate()
            server.wait(timeout=5)
            logs.close()
        print(f'{checks} private submission-photo HTTP checks passed')


if __name__ == '__main__':
    main()
