<?php
/** Real additive schema/retention trigger on a disposable synthetic InnoDB database. */
declare(strict_types=1);
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../backend/database.php';
$database = 'vnz_face_test_' . bin2hex(random_bytes(8));
if (!preg_match('/^vnz_face_test_[a-f0-9]{16}$/D', $database)) { exit(1); }
$quoted = '`' . $database . '`';
$checks = 0;
function faceMysqlCheck(bool $value, string $label): void
{
    global $checks;
    if (!$value) { throw new RuntimeException($label); }
    $checks++;
    echo 'PASS ' . $label . PHP_EOL;
}
try {
    $pdo->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4');
    $pdo->exec('USE ' . $quoted);
    $pdo->exec('CREATE TABLE submissions (id BIGINT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED NULL,status VARCHAR(40),updated_at DATETIME) ENGINE=InnoDB');
    $sql = (string) file_get_contents(__DIR__ . '/../database/migrations/20261119_create_submission_security_faces_v6.sql');
    $sql = str_replace('submission_id INT UNSIGNED', 'submission_id BIGINT UNSIGNED', $sql);
    $pdo->exec($sql);
    $pdo->exec((string) file_get_contents(__DIR__ . '/../database/migrations/20261119_submission_face_terminal_trigger.sql'));
    $pdo->exec("INSERT INTO submissions VALUES (1,NULL,'new_submission',UTC_TIMESTAMP()),(2,NULL,'new_submission',UTC_TIMESTAMP()),(3,NULL,'new_submission',UTC_TIMESTAMP())");
    $insert = $pdo->prepare('INSERT INTO submission_security_faces (submission_id,file_name,photo_hash,nonce_hash,context,policy_version,detector_version,consent_at,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
    $insert->execute([1,str_repeat('a',48).'.enc',str_repeat('b',64),str_repeat('c',64),'guest','face-submission-v6','face-api.js-0.22.2',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    $insert->execute([2,str_repeat('d',48).'.enc',str_repeat('e',64),str_repeat('f',64),'guest','face-submission-v6','face-api.js-0.22.2',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    faceMysqlCheck($pdo->query('SELECT terminal_at FROM submission_security_faces WHERE submission_id=1')->fetchColumn() === null,'new submission has no fabricated terminal time');
    $pdo->beginTransaction();
    $pdo->exec("UPDATE submissions SET status='accepted',updated_at=UTC_TIMESTAMP() WHERE id=1");
    faceMysqlCheck((int)$pdo->query('SELECT TIMESTAMPDIFF(SECOND,terminal_at,purge_after) FROM submission_security_faces WHERE submission_id=1')->fetchColumn() === 30*86400,'actual accepted transition latches exactly thirty days');
    $pdo->rollBack();
    faceMysqlCheck($pdo->query('SELECT terminal_at FROM submission_security_faces WHERE submission_id=1')->fetchColumn() === null,'status rollback also rolls back retention latch');
    $pdo->exec("UPDATE submissions SET status='accepted',updated_at=UTC_TIMESTAMP() WHERE id=1");
    $first = $pdo->query('SELECT terminal_at,purge_after FROM submission_security_faces WHERE submission_id=1')->fetch(PDO::FETCH_ASSOC);
    faceMysqlCheck(!empty($first['terminal_at']) && !empty($first['purge_after']),'committed accepted transition records UTC retention boundary');
    $pdo->exec("UPDATE submissions SET updated_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 DAY) WHERE id=1");
    $next = $pdo->query('SELECT terminal_at,purge_after FROM submission_security_faces WHERE submission_id=1')->fetch(PDO::FETCH_ASSOC);
    faceMysqlCheck($next === $first,'notification or seen updates cannot extend photo retention');
    $pdo->exec("UPDATE submissions SET status='declined',updated_at=UTC_TIMESTAMP() WHERE id=1");
    faceMysqlCheck($pdo->query('SELECT terminal_at,purge_after FROM submission_security_faces WHERE submission_id=1')->fetch(PDO::FETCH_ASSOC) === $first,'another terminal transition preserves earliest retention boundary');
    $pdo->exec("UPDATE submissions SET status='declined',updated_at=UTC_TIMESTAMP() WHERE id=2");
    faceMysqlCheck((int)$pdo->query('SELECT TIMESTAMPDIFF(SECOND,terminal_at,purge_after) FROM submission_security_faces WHERE submission_id=2')->fetchColumn() === 30*86400,'declined transition also has exactly thirty-day retention');
    $rejected = false;
    try { $insert->execute([3,str_repeat('1',48).'.enc',str_repeat('2',64),str_repeat('c',64),'guest','face-submission-v6','face-api.js-0.22.2',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]); }
    catch (PDOException $error) { $rejected = true; }
    faceMysqlCheck($rejected && (int)$pdo->query('SELECT COUNT(*) FROM submission_security_faces')->fetchColumn() === 2,'real InnoDB unique nonce prevents another submission reusing a capture');
    $rejected = false;
    try { $insert->execute([999,str_repeat('3',48).'.enc',str_repeat('4',64),str_repeat('5',64),'guest','face-submission-v6','face-api.js-0.22.2',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]); }
    catch (PDOException $error) { $rejected = true; }
    faceMysqlCheck($rejected,'real foreign key rejects a photo without its exact submission');
    $statement = $pdo->query("SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='vnz_submission_face_terminal_v6'")->fetchColumn();
    $source = (string)file_get_contents(__DIR__.'/../database/migrations/20261119_submission_face_terminal_trigger.sql');
    $body = preg_split('/FOR EACH ROW\s*/i',$source,2)[1];
    $normalize = static fn(string $text):string => strtolower(trim((string)preg_replace('/\s+/',' ',str_replace('`','',rtrim(trim($text),';')))));
    faceMysqlCheck(hash_equals($normalize($body),$normalize($statement)),'installed trigger matches activation health contract');
    echo 'Completed '.$checks.' actual InnoDB schema/trigger checks; disposable records only.'.PHP_EOL;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $pdo->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
