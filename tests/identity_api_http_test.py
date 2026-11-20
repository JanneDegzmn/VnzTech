"""Actual identity API/creation-handler HTTP boundaries, fake SQLite and synthetic images only."""
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
from urllib.parse import urlencode, unquote
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request

REPO = Path(__file__).resolve().parent.parent
PHP = os.environ.get('PHP_BINARY') or shutil.which('php') or 'C:/xampp/php/php.exe'

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def main():
    checks = 0
    def check(ok, message):
        nonlocal checks
        if not ok:
            raise AssertionError(message)
        checks += 1
        print('PASS', message)
    with tempfile.TemporaryDirectory(prefix='vnz-id-http-') as temp:
        root = Path(temp)
        web = root / 'web'
        app = web / 'VNZ-TECH'
        backend = app / 'backend'
        private = root / 'private'
        backend.mkdir(parents=True)
        (private / 'evidence').mkdir(parents=True)
        (root / 'sessions').mkdir()
        for name in ['identity_api.php', 'identity_verification.php', 'identity_automation.php', 'submission_security_face.php', 'submit_submission.php', 'session.php', 'csrf.php', 'email_verification.php', 'offer_response_authorization.php']:
            shutil.copyfile(REPO / 'backend' / name, backend / name)
        (private / 'key.bin').write_bytes(os.urandom(32))
        (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time())}))
        (private / 'config.php').write_text('<?php return ["enabled"=>true,"storage_path"=>' + json.dumps(private.as_posix()) + ',"key_path"=>' + json.dumps((private / 'key.bin').as_posix()) + '];')
        database = root / 'fixture.sqlite'
        with closing(sqlite3.connect(database)) as db, db:
            schema_source = (REPO / 'tests' / 'identity_verification_test.php').read_text(encoding='utf-8')
            for sql in re.findall(r"\$pdo->exec\('(CREATE TABLE.*?)'\);", schema_source):
                db.execute(sql)
            db.executescript("INSERT INTO users VALUES(1,'seller@example.invalid','2026-01-01',1); INSERT INTO admins VALUES(1,1,1),(2,2,1); INSERT INTO roles VALUES(1,1),(2,1); INSERT INTO permissions VALUES(1,'identity_review'); INSERT INTO role_permissions VALUES(1,1);")
        (backend / 'database.php').write_text('''<?php
$pdo=new PDO('sqlite:'.getenv('VNZ_IDENTITY_TEST_DB'));
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
''')
        (app / 'fixture.php').write_text('''<?php
require __DIR__.'/backend/session.php'; require __DIR__.'/backend/csrf.php';
if(isset($_GET['reset'])){$_SESSION=[];}
if(isset($_GET['admin'])){$_SESSION['admin_id']=(int)$_GET['admin'];}
header('Content-Type:application/json');echo json_encode(['csrf_token'=>generateCsrfToken()]);
''')
        subprocess.run([PHP, '-r', '$i=imagecreatetruecolor(160,180);imagefill($i,0,0,imagecolorallocate($i,80,110,130));imagejpeg($i,$argv[1]);', str(root / 'fake.jpg')], check=True, capture_output=True)
        image = (root / 'fake.jpg').read_bytes()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        env = dict(os.environ, VNZ_IDENTITY_CONFIG_PATH=str(private / 'config.php'), VNZ_IDENTITY_TEST_DB=str(database))
        logs = (root / 'php.log').open('w+', encoding='utf-8')
        server = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d', 'session.save_path="' + (root / 'sessions').as_posix() + '"', '-d', 'upload_tmp_dir="' + root.as_posix() + '"', '-S', f'127.0.0.1:{port}', '-t', str(web)], env=env, stdout=logs, stderr=logs)
        base = f'http://127.0.0.1:{port}/VNZ-TECH'
        def client():
            return build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())
        guest, outsider, admin = client(), client(), client()
        def request(client, path, fields=None, files=None):
            headers = {}
            data = None
            if files:
                boundary = 'VNZIdentitySyntheticBoundary'
                parts = []
                for key, value in (fields or {}).items():
                    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
                for key, value in files.items():
                    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="synthetic.jpg"\r\nContent-Type: image/jpeg\r\n\r\n'.encode() + value + b'\r\n')
                data = b''.join(parts) + f'--{boundary}--\r\n'.encode()
                headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
            elif fields is not None:
                data = urlencode(fields).encode()
                headers['Content-Type'] = 'application/x-www-form-urlencoded'
            try:
                response = client.open(Request(base + path, data=data, headers=headers), timeout=10)
            except HTTPError as e:
                response = e
            return response.code, response.headers, response.read()
        def jsonrequest(client, path, fields=None, files=None):
            code, headers, body = request(client, path, fields, files)
            return code, headers, json.loads(body)
        try:
            for _ in range(40):
                try:
                    status, _, body = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
                    break
                except (URLError, ConnectionError):
                    time.sleep(.05)
            else:
                raise RuntimeError('PHP fixture did not start')
            check(status == 200 and body['enabled'] and not body['approved'], 'guest API starts without submission authority')
            token = body['csrf_token']
            draft = body['draft_id']
            status, _, data = jsonrequest(guest, '/backend/identity_api.php', {'action':'submit','context':'guest','email':'guest@example.invalid','consent':'1','csrf_token':'wrong'}, {'id_image':image,'selfie_image':image})
            check(status == 419 and not data['success'], 'actual multipart endpoint rejects missing CSRF proof')
            check(data['csrf_token'] == token, 'session CSRF remains stable after rejected upload')
            with closing(sqlite3.connect(database)) as db:
                check(db.execute('SELECT COUNT(*) FROM identity_attempts').fetchone()[0] == 0, 'failed CSRF does not create evidence rows')
            status, _, data = jsonrequest(guest, '/backend/identity_api.php', {'action':'submit','context':'guest','email':'guest@example.invalid','consent':'1','csrf_token':token,'verified':'true','score':'1'}, {'id_image':image,'selfie_image':image})
            check(status == 200 and data['status'] == 'pending_review' and not data['approved'], 'forged browser score cannot grant approval')
            attempt = data['attempt_id']
            code, headers, imagebody = request(guest, f'/backend/identity_api.php?action=evidence&attempt_id={attempt}&kind=id')
            check(code == 200 and headers.get('Content-Type') == 'image/jpeg' and 'no-store' in headers.get('Cache-Control','') and imagebody[:2] == b'\xff\xd8', 'owner private evidence response is an uncached JPEG')
            code, _, data = jsonrequest(outsider, f'/backend/identity_api.php?action=evidence&attempt_id={attempt}&kind=id')
            check(code == 403 and not data['success'], 'unrelated HTTP session cannot read private ID')
            fields = {'submission_source':'guest','seller_name':'Synthetic Seller','seller_mobile':'09000000000','seller_address':'Synthetic address','seller_email':'guest@example.invalid','selling_method':'per_piece','items[0][brand]':'Synthetic','csrf_token':token,'identity_draft':draft,'verified':'1','identity_status':'approved'}
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields)
            check(code == 302 and 'admin approval' in unquote(headers.get('Location','')), 'actual submission handler blocks pending client-forged approval')
            check(not (app / 'uploads').exists(), 'submission gate rejects before permanent photo directory creation')
            fields['submission_source'] = 'account'
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields)
            check(code == 302 and 'active seller account' in unquote(headers.get('Location','')), 'explicit account intent cannot fall back to guest')
            with closing(sqlite3.connect(database)) as db:
                evidence_hash = db.execute('SELECT evidence_hash FROM identity_attempts WHERE id=?',(attempt,)).fetchone()[0]
            _, _, auth = jsonrequest(admin, '/fixture.php?reset=1&admin=2')
            decision = {'action':'review','attempt_id':attempt,'evidence_hash':evidence_hash,'decision':'approve','review_confirm':'1','csrf_token':auth['csrf_token']}
            code, _, data = jsonrequest(admin, '/backend/identity_api.php', decision)
            check(code == 403 and not data['success'], 'staff session cannot review through direct API request')
            _, _, auth = jsonrequest(admin, '/fixture.php?reset=1&admin=1')
            decision['csrf_token'] = auth['csrf_token']
            decision['evidence_hash'] = 'a' * 64
            code, _, data = jsonrequest(admin, '/backend/identity_api.php', decision)
            check(code == 403 and not data['success'], 'authorized reviewer cannot approve stale evidence version')
            decision['evidence_hash'] = evidence_hash
            code, _, data = jsonrequest(admin, '/backend/identity_api.php', decision)
            check(code == 200 and data['success'], 'dedicated reviewer can approve exact confirmed evidence')
            _, _, data = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
            check(data['approved'], 'guest sees server-approved status after reviewer decision')
            code, _, data = jsonrequest(admin, '/backend/identity_api.php', decision)
            check(code == 403 and not data['success'], 'review decision replay is rejected')
            code, _, _ = request(outsider, '/../../private/key.bin')
            check(code == 404, 'private key is outside HTTP documentroot')
            (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time()) - 172801}))
            code, _, data = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
            check(code == 503 and data['enabled'] and not data['approved'] and not data['available'], 'enabled maintenance failure blocks status instead of disabling policy')
            # Stage dual mode in the isolated app only. Blank synthetic photos cannot
            # reach Face++: the real local model must reject them before cloud dispatch.
            (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time())}))
            for name in ['identity_face_matcher.php', 'identity_faceplusplus.php']:
                shutil.copyfile(REPO / 'backend' / name, backend / name)
            (app / 'tools').mkdir()
            shutil.copyfile(REPO / 'tools' / 'identity_match_worker.cjs', app / 'tools' / 'identity_match_worker.cjs')
            shutil.copytree(REPO / 'assets' / 'vendor' / 'face-api', app / 'assets' / 'vendor' / 'face-api')
            migration = (REPO / 'database' / 'migrations' / '20261119_create_identity_auto_decisions.sql').read_text()
            migration = re.sub(r'\s+CONSTRAINT identity_auto_attempt_fk[^\n]+', '', migration)
            migration = migration.replace(",\n)", "\n)")
            migration = re.sub(r'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', '', migration)
            with closing(sqlite3.connect(database)) as db, db:
                db.executescript(migration)
            (private / 'config.php').write_text('<?php return ["enabled"=>true,"mode"=>"auto_dual_v4","storage_path"=>' + json.dumps(private.as_posix()) + ',"key_path"=>' + json.dumps((private / 'key.bin').as_posix()) + ',"dual"=>["node_binary"=>"C:/Program Files/nodejs/node.exe","region"=>"us","api_key"=>"' + 'a'*32 + '","api_secret"=>"' + 'b'*32 + '","contract_confirmed"=>true,"matching_validated"=>true,"validation_policy"=>"dual-v4-portrait-v1"]];')
            code, _, data = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
            check(code == 200 and data['mode'] == 'auto_dual_v4' and data['status'] == 'needs_verification' and not data['approved'], 'live API excludes historical manual approval after dual-policy switch')
            code, _, data = jsonrequest(admin, '/backend/identity_api.php', decision)
            check(code == 403 and not data['approved'], 'automatic mode retires direct manual-approval API authority')
            code, _, data = jsonrequest(admin, f'/backend/identity_api.php?action=evidence&attempt_id={attempt}&kind=id')
            check(code == 403 and not data['success'], 'automatic mode removes admin-reviewer private evidence access')
            payload = {'action':'submit','context':'guest','email':'guest@example.invalid','consent':'1','csrf_token':token,'verified':'true','score':'1'}
            code, _, data = jsonrequest(guest, '/backend/identity_api.php', payload, {'id_image':image,'selfie_image':image})
            check(code == 403 and not data['approved'], 'dual enrollment requires explicit new provider-consent version')
            payload['consent_version'] = 'auto_dual_v4'
            code, _, data = jsonrequest(guest, '/backend/identity_api.php', payload, {'id_image':image,'selfie_image':image})
            check(code == 200 and data['status'] == 'retry' and not data['approved'], 'real blank-image server inference retries despite forged browser match flags')
            with closing(sqlite3.connect(database)) as db:
                meta = db.execute('SELECT local_pass,provider_pass,outcome_code FROM identity_auto_decisions').fetchall()
            check(meta == [(0,0,'capture_quality')], 'local quality failure creates no cloud pass or submission grant')
            fields['submission_source'] = 'guest'
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields)
            check(code == 302 and 'automatic' in unquote(headers.get('Location','')), 'actual submission endpoint blocks failed dual check before item uploads')
            check(not (app / 'uploads').exists(), 'dual gate also rejects before public item-photo mkdir')
            (private / 'config.php').write_text('<?php return ["enabled"=>true,"mode"=>"auto_dual_v4","storage_path"=>' + json.dumps(private.as_posix()) + ',"key_path"=>' + json.dumps((private / 'key.bin').as_posix()) + '];')
            code, _, data = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
            check(code == 503 and data['enabled'] and not data['available'] and not data['approved'] and data['mode'] == 'auto_dual_v4', 'missing provider setup stays mandatory/unavailable with no manual or local-only fallback')
            print(f'Completed {checks} isolated identity HTTP checks.')
        finally:
            server.terminate()
            server.wait(timeout=10)
            logs.close()

if __name__ == '__main__':
    main()
