"""Actual submit/API HTTP integration in a disposable app and SQLite database.

ONLY presence inference is mocked in the disposable helper copy. Preparation,
readiness, actor/nonce/consent checks, uploads, transaction, private encryption,
rollback and redirects execute the real source. Blank real-model refusal has
separate PHP/Node tests; these success fixtures do not prove face accuracy.
"""
from contextlib import closing
from http.cookiejar import CookieJar
import hashlib
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

    with tempfile.TemporaryDirectory(prefix='vnz-face-submit-http-') as temp:
        root = Path(temp)
        web = root / 'web'
        app = web / 'VNZ-TECH'
        backend = app / 'backend'
        private = root / 'private'
        backend.mkdir(parents=True)
        (private / 'evidence').mkdir(parents=True)
        (root / 'sessions').mkdir()
        for name in ['identity_api.php', 'identity_verification.php', 'identity_automation.php',
                     'submission_security_face.php', 'identity_face_matcher.php',
                     'submit_submission.php', 'session.php', 'csrf.php', 'offer_response_authorization.php']:
            shutil.copyfile(REPO / 'backend' / name, backend / name)
        # Deliberate and isolated positive-inference fixture; never alters repo source.
        helper = (backend / 'submission_security_face.php').read_text(encoding='utf-8')
        start = helper.index('function vnzSubmissionFacePresence(')
        end = helper.index('\nfunction vnzSubmissionFacePrepare(', start)
        helper = helper[:start] + '''function vnzSubmissionFacePresence(string $jpeg, array $config): array
{
    file_put_contents(__DIR__.'/fixture-presence-runs.log',hash('sha256',$jpeg).PHP_EOL,FILE_APPEND);
    $state=trim((string)file_get_contents(__DIR__.'/fixture-presence-state.txt'));
    return match($state) {
        'pass'=>['outcome'=>'pass','code'=>'face_present'],
        'unavailable'=>['outcome'=>'unavailable','code'=>'worker_busy'],
        default=>['outcome'=>'retry','code'=>'no_face']
    };
}
''' + helper[end:]
        (backend / 'submission_security_face.php').write_text(helper, encoding='utf-8')
        state_file = backend / 'fixture-presence-state.txt'
        state_file.write_text('pass')
        (backend / 'email_verification.php').write_text('''<?php
function getVerificationMailConfig():array {return ['admin_notification_email'=>'admin@example.invalid'];}
function sendVnzEmail(...$args):bool {
    file_put_contents(__DIR__.'/fixture-mail.log',json_encode(['to'=>$args[0],'subject'=>$args[1]]).PHP_EOL,FILE_APPEND);
    if(is_file(__DIR__.'/fixture-mail-fail')){throw new RuntimeException('Synthetic post-commit mail failure');}
    return true;
}
''')
        (app / 'tools').mkdir()
        shutil.copyfile(REPO / 'tools' / 'identity_match_worker.cjs', app / 'tools' / 'identity_match_worker.cjs')
        shutil.copytree(REPO / 'assets' / 'vendor' / 'face-api', app / 'assets' / 'vendor' / 'face-api')
        (private / 'key.bin').write_bytes(os.urandom(32))
        (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time())}))
        (private / 'config.php').write_text('<?php return ["enabled"=>true,"mode"=>"face_submission_v6","storage_path"=>'
            + json.dumps(private.as_posix()) + ',"key_path"=>'
            + json.dumps((private / 'key.bin').as_posix())
            + ',"face_submission"=>["node_binary"=>"C:/Program Files/nodejs/node.exe"]];')
        database = root / 'fixture.sqlite'
        with closing(sqlite3.connect(database)) as db, db:
            db.executescript('''
CREATE TABLE users (id INTEGER PRIMARY KEY,full_name TEXT,mobile TEXT,address TEXT,email TEXT,email_verified_at TEXT,is_active INTEGER);
INSERT INTO users VALUES (1,'Synthetic Account','09000000001','Synthetic account address','seller@example.invalid','2026-01-01 00:00:00',1),(2,'Inactive Account','09000000002','Inactive address','inactive@example.invalid',NULL,0);
CREATE TABLE roles (id INTEGER PRIMARY KEY,is_active INTEGER);
CREATE TABLE admins (id INTEGER PRIMARY KEY,role_id INTEGER,is_active INTEGER);
CREATE TABLE permissions (id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE role_permissions (role_id INTEGER,permission_id INTEGER);
INSERT INTO roles VALUES(1,1),(2,1); INSERT INTO admins VALUES(1,1,1),(2,2,1);
INSERT INTO permissions VALUES(1,'view_submissions'),(2,'identity_review'); INSERT INTO role_permissions VALUES(1,1),(1,2);
CREATE TABLE identity_attempts (id INTEGER PRIMARY KEY);
CREATE TABLE identity_bindings (id INTEGER PRIMARY KEY);
CREATE TABLE identity_audit (id INTEGER PRIMARY KEY);
CREATE TABLE submissions (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NULL,reference_number TEXT UNIQUE,seller_name TEXT,seller_mobile TEXT,seller_email TEXT,seller_address TEXT,selling_method TEXT,bulk_format INTEGER NOT NULL DEFAULT 0,status TEXT,seller_notes TEXT,created_at TEXT,updated_at TEXT,seen_by_admin INTEGER);
CREATE TABLE submission_items (id INTEGER PRIMARY KEY AUTOINCREMENT,submission_id INTEGER,item_type TEXT,brand TEXT,model TEXT,storage TEXT,issue TEXT,issue_details TEXT,damage_level INTEGER,board_code TEXT,quantity INTEGER,quantity_is_estimated INTEGER NOT NULL DEFAULT 0,seller_asking_price REAL,additional_information TEXT,created_at TEXT,updated_at TEXT,FOREIGN KEY(submission_id) REFERENCES submissions(id));
CREATE TABLE submission_photos (id INTEGER PRIMARY KEY AUTOINCREMENT,submission_item_id INTEGER,file_name TEXT,file_path TEXT,original_name TEXT,mime_type TEXT,file_size INTEGER,photo_type TEXT,sort_order INTEGER,created_at TEXT,FOREIGN KEY(submission_item_id) REFERENCES submission_items(id));
CREATE TABLE submission_security_faces (submission_id INTEGER PRIMARY KEY,file_name TEXT UNIQUE NOT NULL,photo_hash TEXT NOT NULL,nonce_hash TEXT UNIQUE NOT NULL,context TEXT,user_id INTEGER NULL,policy_version TEXT,detector_version TEXT,consent_at TEXT,created_at TEXT,terminal_at TEXT NULL,purge_after TEXT NULL,purged_at TEXT NULL,FOREIGN KEY(submission_id) REFERENCES submissions(id));
''')
        (backend / 'database.php').write_text('''<?php
$pdo=new PDO('sqlite:'.getenv('VNZ_FACE_TEST_DB'));
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->sqliteCreateFunction('NOW',fn()=>gmdate('Y-m-d H:i:s'),0);
''')
        (app / 'fixture.php').write_text('''<?php
require __DIR__.'/backend/session.php';require __DIR__.'/backend/csrf.php';
if(isset($_GET['reset'])){$_SESSION=[];}
if(isset($_GET['user'])){$_SESSION['user_logged_in']=true;$_SESSION['user_id']=(int)$_GET['user'];}
if(isset($_GET['admin'])){$_SESSION['admin_id']=(int)$_GET['admin'];}
if(isset($_GET['expire'])&&isset($_SESSION['submission_face_nonce'][$_GET['expire']])){$_SESSION['submission_face_nonce'][$_GET['expire']]['issued_at']=time()-601;}
header('Content-Type:application/json');echo json_encode(['csrf_token'=>generateCsrfToken(),'nonce_contexts'=>array_keys($_SESSION['submission_face_nonce']??[])]);
''')
        subprocess.run([PHP, '-r', '$i=imagecreatetruecolor(160,180);imagefill($i,0,0,imagecolorallocate($i,80,110,130));imagejpeg($i,$argv[1]);', str(root / 'synthetic.jpg')], check=True, capture_output=True)
        image = (root / 'synthetic.jpg').read_bytes()
        subprocess.run([PHP, '-r', 'require $argv[1];file_put_contents($argv[3],vnzSubmissionFaceImage(["error"=>0,"tmp_name"=>$argv[2]]));', str(backend / 'submission_security_face.php'), str(root / 'synthetic.jpg'), str(root / 'canonical.jpg')], check=True, capture_output=True)
        canonical_hash = hashlib.sha256((root / 'canonical.jpg').read_bytes()).hexdigest()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        env = dict(os.environ, VNZ_IDENTITY_CONFIG_PATH=str(private / 'config.php'), VNZ_FACE_TEST_DB=str(database))
        logs = (root / 'php.log').open('w+', encoding='utf-8')
        server = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d', 'session.save_path="' + (root / 'sessions').as_posix() + '"', '-d', 'upload_tmp_dir="' + root.as_posix() + '"', '-S', f'127.0.0.1:{port}', '-t', str(web)], env=env, stdout=logs, stderr=logs)
        base = f'http://127.0.0.1:{port}/VNZ-TECH'

        def client():
            return build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())

        guest, account, outsider, inactive, admin = (client() for _ in range(5))

        def request(who, path, fields=None, files=None):
            headers, data = {}, None
            if files:
                boundary = 'VNZFaceSubmissionSyntheticBoundary'
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
                response = who.open(Request(base + path, data=data, headers=headers), timeout=10)
            except HTTPError as error:
                response = error
            return response.code, response.headers, response.read()

        def jsonrequest(who, path, fields=None):
            code, headers, body = request(who, path, fields)
            return code, headers, json.loads(body)

        def mint(who, csrf, context):
            return jsonrequest(who, '/backend/identity_api.php', {'action': 'face_token', 'context': context, 'csrf_token': csrf})

        def fields(csrf, nonce, source='guest'):
            return {'csrf_token': csrf, 'face_nonce': nonce, 'face_consent': '1', 'submission_source': source,
                    'seller_name': 'Synthetic Guest', 'seller_mobile': '09000000000', 'seller_email': 'guest@example.invalid',
                    'seller_address': 'Synthetic guest address', 'selling_method': 'per_piece',
                    'items[0][item_type]': 'Whole Phone', 'items[0][brand]': 'Synthetic', 'items[0][model]': 'Test device',
                    'items[0][issue]': 'Broken screen', 'items[0][damage_level]': '3', 'items[0][quantity]': '1',
                    'items[0][seller_asking_price]': '700'}

        def rows(sql, args=()):
            with closing(sqlite3.connect(database)) as db:
                return db.execute(sql, args).fetchall()

        files = {'submission_face': image, 'photos[0][]': image}
        try:
            for _ in range(50):
                try:
                    _, _, initial = jsonrequest(guest, '/fixture.php?reset=1')
                    break
                except (URLError, ConnectionError):
                    time.sleep(.05)
            else:
                raise RuntimeError('PHP fixture did not start')
            csrf = initial['csrf_token']
            code, _, body = mint(guest, 'wrong', 'guest')
            check(code == 419 and not body['success'], 'nonce API rejects invalid CSRF')
            code, _, body = jsonrequest(guest, '/backend/identity_api.php?action=face_token&context=guest')
            check(code == 419 and not body['success'], 'nonce API only accepts POST with CSRF')
            code, headers, body = mint(guest, csrf, 'guest')
            nonce = body['face_nonce']
            check(code == 200 and body['success'] and body['expires_in'] == 600 and len(nonce) == 48 and 'no-store' in headers.get('Cache-Control', ''), 'guest nonce API is private and short-lived without old ID approval')
            code, _, second = mint(guest, csrf, 'guest')
            check(code == 200 and second['face_nonce'] != nonce, 'camera opening refreshes the old guest draft nonce')
            stale_nonce, nonce = nonce, second['face_nonce']
            _, _, other_auth = jsonrequest(outsider, '/fixture.php?reset=1')
            code, _, body = mint(outsider, other_auth['csrf_token'], 'account')
            check(code == 403 and not body['success'], 'account nonce cannot be minted by a guest')
            _, _, inactive_auth = jsonrequest(inactive, '/fixture.php?reset=1&user=2')
            code, _, body = mint(inactive, inactive_auth['csrf_token'], 'account')
            check(code == 403 and not body['success'], 'inactive seller cannot mint an account nonce')
            for change, label, expected in [
                ({'csrf_token': 'wrong'}, 'invalid submission CSRF', 'session expired'),
                ({'face_consent': '0'}, 'missing selfie consent', 'agree'),
                ({'face_nonce': 'b' * 48}, 'forged nonce', 'expired'),
                ({'face_nonce': stale_nonce}, 'stale refreshed nonce', 'expired')
            ]:
                payload = fields(csrf, nonce); payload.update(change)
                code, headers, _ = request(guest, '/backend/submit_submission.php', payload, files)
                check(code == 302 and expected in unquote(headers.get('Location', '')), label + ' rejected by actual creation handler')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, nonce), {'photos[0][]': image})
            check(code == 302 and 'selfie' in unquote(headers.get('Location', '')).lower(), 'missing selfie upload cannot reach submission transaction')
            foreign_fields = fields(other_auth['csrf_token'], nonce)
            code, headers, _ = request(outsider, '/backend/submit_submission.php', foreign_fields, files)
            check(code == 302 and 'expired' in unquote(headers.get('Location', '')), 'another HTTP session cannot use the guest capture nonce')
            foreign_fields['submission_source'] = 'account'
            code, headers, _ = request(outsider, '/backend/submit_submission.php', foreign_fields, files)
            check(code == 302 and 'active seller account' in unquote(headers.get('Location', '')), 'explicit account submission cannot silently become a guest submission')
            check(not (app / 'uploads').exists() and not list((private / 'evidence').glob('*.enc')) and rows('SELECT COUNT(*) FROM submissions') == [(0,)], 'all proof/consent failures happen before permanent upload mkdir or INSERT')
            check(not (backend / 'fixture-presence-runs.log').exists(), 'invalid actor/nonce/consent/CSRF never runs expensive inference')
            state_file.write_text('no_face')
            payload = fields(csrf, nonce); payload.update({'verified': '1', 'identity_status': 'approved', 'score': '0'})
            code, headers, _ = request(guest, '/backend/submit_submission.php', payload, files)
            check(code == 302 and 'No clear face' in unquote(headers.get('Location', '')), 'forged browser flags cannot override simulated server no-face result')
            state_file.write_text('unavailable')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, nonce), files)
            check(code == 302 and 'error=' in headers.get('Location', '') and not (app / 'uploads').exists(), 'simulated inference outage blocks before public uploads with no local/manual bypass')
            state_file.write_text('pass')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, nonce), files)
            check(code == 302 and 'submitted=1' in headers.get('Location', ''), 'guest success uses actual transaction with explicitly MOCKED positive presence only')
            saved = rows('SELECT s.id,s.user_id,s.status,f.file_name,f.photo_hash,f.context,f.policy_version,f.nonce_hash FROM submissions s JOIN submission_security_faces f ON f.submission_id=s.id')
            check(len(saved) == 1 and saved[0][1:3] == (None, 'new_submission') and saved[0][4:] == (canonical_hash, 'guest', 'face-submission-v6', hashlib.sha256(nonce.encode()).hexdigest()), 'guest selfie and checked canonical hash are linked to the exact committed submission')
            ciphertext = (private / 'evidence' / saved[0][3]).read_bytes()
            check(ciphertext[:4] == b'VNZ1' and ciphertext != image and len(list((private / 'evidence').glob('*.enc'))) == 1, 'successful real route writes encrypted private evidence instead of a public selfie')
            check(rows('SELECT COUNT(*) FROM submission_items') == [(1,)] and rows('SELECT COUNT(*) FROM submission_photos') == [(1,)] and len(list((app / 'uploads' / 'submissions').iterdir())) == 1, 'only ordinary item photo is saved in the public item-upload directory')
            mail = [json.loads(line) for line in (backend / 'fixture-mail.log').read_text().splitlines()]
            check(len(mail) == 2 and {entry['to'] for entry in mail} == {'guest@example.invalid', 'admin@example.invalid'}, 'post-commit receipt paths execute against a non-network mail fixture')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, nonce), files)
            check(code == 302 and 'expired' in unquote(headers.get('Location', '')) and rows('SELECT COUNT(*) FROM submissions') == [(1,)], 'successful HTTP commit consumes the capture nonce and rejects double submit')
            _, _, next_token = mint(guest, csrf, 'guest')
            next_nonce = next_token['face_nonce']
            check(next_nonce != nonce, 'next guest submission needs a new nonce and selfie')
            payload = fields(csrf, next_nonce)
            payload['items[1][item_type]'] = 'Invalid type'
            code, headers, _ = request(guest, '/backend/submit_submission.php', payload, files)
            check(code == 302 and 'Invalid item type' in unquote(headers.get('Location', '')), 'later invalid item forces rollback after private photo and first public item upload')
            check(rows('SELECT COUNT(*) FROM submissions') == [(1,)] and rows('SELECT COUNT(*) FROM submission_security_faces') == [(1,)] and len(list((private / 'evidence').glob('*.enc'))) == 1 and len(list((app / 'uploads' / 'submissions').iterdir())) == 1, 'actual rollback removes only new private/public files and preserves committed evidence')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, next_nonce), files)
            check(code == 302 and 'submitted=1' in headers.get('Location', '') and rows('SELECT COUNT(*) FROM submissions') == [(2,)], 'rollback leaves the same guest nonce usable for a corrected retry')
            _, _, account_auth = jsonrequest(account, '/fixture.php?reset=1&user=1')
            account_csrf = account_auth['csrf_token']
            _, _, account_token = mint(account, account_csrf, 'account')
            account_nonce = account_token['face_nonce']
            (backend / 'fixture-mail-fail').touch()
            payload = fields(account_csrf, account_nonce, 'account')
            payload['seller_name'] = 'Client-forged name'
            payload['seller_email'] = 'forged@example.invalid'
            code, headers, _ = request(account, '/backend/submit_submission.php', payload, files)
            check(code == 302 and 'page=submission&ref=' in headers.get('Location', ''), 'account success keeps committed evidence despite simulated post-commit mail failure')
            account_rows = rows("SELECT s.user_id,s.seller_name,s.seller_email,f.context FROM submissions s JOIN submission_security_faces f ON f.submission_id=s.id WHERE s.user_id=1")
            check(account_rows == [(1, 'Synthetic Account', 'seller@example.invalid', 'account')], 'account submission uses active server profile and fresh submission-linked selfie')
            code, _, _ = request(account, '/backend/submit_submission.php', payload, files)
            check(code == 302 and rows('SELECT COUNT(*) FROM submissions') == [(3,)], 'account nonce is also one-use rather than durable identity approval')
            _, _, public_token = mint(account, account_csrf, 'guest')
            code, headers, _ = request(account, '/backend/submit_submission.php', fields(account_csrf, public_token['face_nonce']), files)
            check(code == 302 and 'submitted=1' in headers.get('Location', '') and rows('SELECT COUNT(*) FROM submissions WHERE user_id IS NULL') == [(3,)], 'logged-in seller using public guest form remains guest-owned with a separate selfie')
            _, _, expiring = mint(guest, csrf, 'guest')
            jsonrequest(guest, '/fixture.php?expire=guest')
            code, headers, _ = request(guest, '/backend/submit_submission.php', fields(csrf, expiring['face_nonce']), files)
            check(code == 302 and 'expired' in unquote(headers.get('Location', '')) and rows('SELECT COUNT(*) FROM submissions') == [(4,)], 'ten-minute expired nonce is rejected by real submit handler')
            _, _, admin_auth = jsonrequest(admin, '/fixture.php?reset=1&admin=1')
            code, _, body = jsonrequest(admin, '/backend/identity_api.php', {'action': 'review', 'decision': 'approve', 'attempt_id': '1', 'review_confirm': '1', 'csrf_token': admin_auth['csrf_token']})
            check(code == 410 and not body['success'], 'even historical identity reviewer cannot approve via retired manual API')
            code, _, body = jsonrequest(guest, '/backend/identity_api.php?action=status&context=guest')
            check(code == 410 and not body['success'], 'old ID-enrollment status API is retired in replacement mode')
            (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time()) - 172801}))
            code, _, body = mint(guest, csrf, 'guest')
            check(code == 503 and not body['success'], 'stale maintenance prevents new capture token without disabling required policy')
            check(rows('SELECT COUNT(*) FROM submission_security_faces') == [(4,)] and rows('SELECT COUNT(*) FROM identity_attempts') == [(0,)], 'all successful submissions have selfie evidence and no new ID-review records')
            (private / 'cleanup-heartbeat.json').write_text(json.dumps({'success_at': int(time.time())}))
            bulk_spoof = fields(csrf, '', 'guest')
            bulk_spoof.update({'selling_method': 'wholesale', 'bulk_format': '1'})
            code, headers, _ = request(guest, '/backend/submit_submission.php', bulk_spoof, files)
            check(code == 302 and 'seller account portal' in unquote(headers.get('Location', '')) and rows('SELECT COUNT(*) FROM submissions') == [(4,)], 'bulk format cannot be submitted through the public guest form')

            _, _, bulk_token = mint(account, account_csrf, 'account')
            bulk_payload = fields(account_csrf, bulk_token['face_nonce'], 'account')
            bulk_payload.update({
                'selling_method': 'wholesale',
                'bulk_format': '1',
                'items[0][item_type]': 'Whole Phone',
                'items[0][brand]': 'Forged bulk brand',
                'items[0][model]': 'Forged bulk model',
                'items[0][issue]': 'Forged bulk issue',
                'items[0][damage_level]': '3',
                'items[0][quantity]': '12',
                'items[0][quantity_is_estimated]': '1',
                'items[0][seller_asking_price]': '8500',
                'items[0][additional_information]': 'Mixed phone lot',
                'items[1][item_type]': 'Scrap Board',
                'items[1][quantity]': '7',
                'items[1][quantity_is_estimated]': '0',
                'items[1][seller_asking_price]': '1800',
                'items[1][additional_information]': 'Scrap board lot'
            })
            code, headers, _ = request(account, '/backend/submit_submission.php', bulk_payload, {
                'submission_face': image,
                'photos[0][]': image,
                'photos[1][]': image
            })
            check(code == 302 and 'page=submission&ref=' in headers.get('Location', ''), 'account bulk submission accepts mixed phone and scrap lots without per-device specifications')
            bulk_submission = rows("SELECT bulk_format,selling_method FROM submissions WHERE user_id=1 ORDER BY id DESC LIMIT 1")
            bulk_items = rows("SELECT item_type,brand,model,issue,damage_level,board_code,quantity,quantity_is_estimated,seller_asking_price,additional_information FROM submission_items WHERE submission_id=(SELECT MAX(id) FROM submissions WHERE user_id=1) ORDER BY id ASC")
            check(bulk_submission == [(1, 'wholesale')] and len(bulk_items) == 2, 'new bulk format is stored separately from legacy wholesale submissions')
            check(bulk_items[0] == ('whole_phone', '', '', '', 0, '', 12, 1, 8500.0, 'Mixed phone lot') and bulk_items[1] == ('scrap_board', '', '', '', 0, '', 7, 0, 1800.0, 'Scrap board lot'), 'bulk saves category, exact/estimated quantity, total lot price and item notes while discarding device-spec fields')
            bulk_totals = rows("SELECT SUM(quantity),SUM(seller_asking_price) FROM submission_items WHERE submission_id=(SELECT MAX(id) FROM submissions WHERE user_id=1)")
            check(bulk_totals == [(19, 10300.0)], 'bulk total asking price is stored once per lot rather than multiplied by quantity')
            print(f'Completed {checks} isolated submit/API HTTP checks; positive presence MOCKED only in disposable helper, no real face acceptance, SMTP or Face++ calls.')
        finally:
            server.terminate()
            server.wait(timeout=10)
            logs.close()


if __name__ == '__main__':
    main()
