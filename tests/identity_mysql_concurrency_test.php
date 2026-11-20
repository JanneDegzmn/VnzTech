<?php
/** Actual InnoDB races in a uniquely named disposable DB; no app table reads. */
declare(strict_types=1);

require_once __DIR__ . '/../backend/identity_verification.php';

function identityMysqlDatabase(string $name): string
{
    if (!preg_match('/\Avnz_identity_test_[a-f0-9]{16}\z/', $name)) {
        throw new RuntimeException('Invalid disposable database name.');
    }
    return '`' . $name . '`';
}

function identityMysqlConnect(string $database): PDO
{
    // Execute the existing connection bootstrap without reading or printing its
    // configuration. Every subsequent query uses only the test-owned schema.
    require __DIR__ . '/../backend/database.php';
    if (!isset($pdo) || !$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('A local MySQL PDO connection is required.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('USE ' . identityMysqlDatabase($database));
    return $pdo;
}

function identityMysqlAssert(bool $ok, string $name): void
{
    if (!$ok) {
        throw new RuntimeException($name);
    }
    echo 'PASS ' . $name . PHP_EOL;
}

function identityMysqlWait(callable $ready, string $description): void
{
    $deadline = microtime(true) + 15;
    while (!$ready()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for ' . $description . '.');
        }
        usleep(20000);
    }
}

function identityMysqlWorker(string $database, string $root, string $mode, string $name): never
{
    ini_set('session.save_path', $root . '/sessions');
    session_id(bin2hex(random_bytes(16)));
    $pdo = identityMysqlConnect($database);
    $seed = json_decode((string) file_get_contents($root . '/seed.json'), true, 512, JSON_THROW_ON_ERROR);
    $_SESSION = $seed['session'];
    file_put_contents($root . '/' . $name . '.ready', 'ready');
    identityMysqlWait(static fn() => is_file($root . '/' . $name . '.go'), 'worker start');
    try {
        if ($mode === 'revoke') {
            $_SESSION['admin_id'] = 1;
            vnzIdentityReview($pdo, $seed['attempt_id'], $seed['evidence_hash'], 'revoke', 'Synthetic concurrent review correction', true);
        } else {
            $pdo->beginTransaction();
            $submissionId = $name === 'race_a' ? 1001 : ($name === 'race_b' ? 1002 : 4001);
            $pdo->prepare('INSERT INTO submissions (id) VALUES (?)')->execute([$submissionId]);
            vnzIdentityConsumeSubmission($pdo, null, 'concurrency@example.invalid', $seed['draft_id'], $submissionId);
            usleep(250000); // Keep the winning lock long enough for the peer to contend.
            $pdo->commit();
        }
        $result = ['outcome' => 'committed'];
    } catch (DomainException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result = ['outcome' => 'rejected'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Never emit a driver error/DSN or connection configuration.
        $result = ['outcome' => 'unexpected_failure'];
    }
    file_put_contents($root . '/' . $name . '.result', json_encode($result, JSON_THROW_ON_ERROR));
    exit($result['outcome'] === 'unexpected_failure' ? 1 : 0);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        identityMysqlWorker((string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''), (string) ($argv[4] ?? ''), (string) ($argv[5] ?? ''));
    } catch (Throwable $error) {
        fwrite(STDERR, "Disposable MySQL worker setup failed.\n");
        exit(1);
    }
}

$database = 'vnz_identity_test_' . bin2hex(random_bytes(8));
$root = sys_get_temp_dir() . '/vnz-identity-mysql-' . bin2hex(random_bytes(8));
$createdDatabase = false;
$workers = [];
$pdo = null;
$exitCode = 0;
try {
    mkdir($root, 0700);
    mkdir($root . '/evidence', 0700);
    mkdir($root . '/sessions', 0700);
    ini_set('session.save_path', $root . '/sessions');
    session_id(bin2hex(random_bytes(16)));
    file_put_contents($root . '/key.bin', random_bytes(32));
    file_put_contents($root . '/config.php', '<?php return ' . var_export([
        'enabled' => true, 'storage_path' => $root, 'key_path' => $root . '/key.bin',
    ], true) . ';');
    putenv('VNZ_IDENTITY_CONFIG_PATH=' . $root . '/config.php');

    require __DIR__ . '/../backend/database.php';
    if (!isset($pdo) || !$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('A local MySQL PDO connection is required.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE DATABASE ' . identityMysqlDatabase($database) . ' CHARACTER SET utf8mb4');
    $createdDatabase = true;
    $pdo->exec('USE ' . identityMysqlDatabase($database));
    $pdo->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY,email VARCHAR(190),email_verified_at DATETIME,is_active TINYINT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE roles (id INT UNSIGNED PRIMARY KEY,name VARCHAR(80),is_active TINYINT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE admins (id INT UNSIGNED PRIMARY KEY,role_id INT UNSIGNED,is_active TINYINT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE permissions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100),description VARCHAR(300)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE role_permissions (role_id INT UNSIGNED,permission_id INT UNSIGNED) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE submissions (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO roles VALUES (1,'Super Admin',1); INSERT INTO admins VALUES (1,1,1)");
    $pdo->exec((string) file_get_contents(__DIR__ . '/../database/migrations/20261119_create_identity_verification.sql'));
    vnzIdentityCleanup($pdo);

    // Solid-color JPEG fixtures contain no person, face or personal document.
    $image = imagecreatetruecolor(160, 180);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 70, 100));
    imagejpeg($image, $root . '/synthetic.jpg');
    imagedestroy($image);
    $upload = ['tmp_name' => $root . '/synthetic.jpg', 'error' => UPLOAD_ERR_OK];
    $_SESSION = [];
    $pending = vnzIdentitySubmit($pdo, 'guest', 'concurrency@example.invalid', $upload, $upload, true);
    $guestSession = $_SESSION;
    $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?', [$pending['attempt_id']]);
    $_SESSION['admin_id'] = 1;
    vnzIdentityReview($pdo, $pending['attempt_id'], $attempt['evidence_hash'], 'approve', '', true);
    $_SESSION = $guestSession;
    file_put_contents($root . '/seed.json', json_encode([
        'session' => $guestSession, 'attempt_id' => $pending['attempt_id'],
        'draft_id' => $pending['draft_id'], 'evidence_hash' => $attempt['evidence_hash'],
    ], JSON_THROW_ON_ERROR));

    $start = static function (string $name, string $mode = 'consume') use ($database, $root, &$workers): void {
        $streams = [0 => ['pipe', 'r'], 1 => ['file', $root . '/' . $name . '.stdout', 'w'], 2 => ['file', $root . '/' . $name . '.stderr', 'w']];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $database, $root, $mode, $name], $streams, $pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start isolated concurrency worker.');
        }
        fclose($pipes[0]);
        $workers[$name] = $process;
    };
    $result = static function (string $name) use ($root): string {
        identityMysqlWait(static fn() => is_file($root . '/' . $name . '.result'), 'worker result');
        return json_decode((string) file_get_contents($root . '/' . $name . '.result'), true, 512, JSON_THROW_ON_ERROR)['outcome'];
    };

    $start('race_a');
    $start('race_b');
    identityMysqlWait(static fn() => is_file($root . '/race_a.ready') && is_file($root . '/race_b.ready'), 'both grant contenders');
    file_put_contents($root . '/race_a.go', 'go');
    file_put_contents($root . '/race_b.go', 'go');
    $results = [$result('race_a'), $result('race_b')];
    sort($results);
    identityMysqlAssert($results === ['committed', 'rejected'], 'concurrent guest submissions permit exactly one commit');
    identityMysqlAssert((int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn() === 1, 'losing transaction leaves no submission row');
    identityMysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM identity_audit WHERE event='submission_created'")->fetchColumn() === 1, 'one committed guest consumption has one audit event');

    $pdo->exec('DELETE FROM submissions');
    $pdo->prepare('UPDATE identity_attempts SET submission_id=NULL WHERE id=?')->execute([$pending['attempt_id']]);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO submissions VALUES (2001)');
    vnzIdentityConsumeSubmission($pdo, null, 'concurrency@example.invalid', $pending['draft_id'], 2001);
    $pdo->rollBack();
    identityMysqlAssert(vnzIdentityRequireSubmission($pdo, null, 'concurrency@example.invalid', $pending['draft_id'])['submission_id'] === null, 'InnoDB rollback restores the unused grant');
    identityMysqlAssert((int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn() === 0, 'rollback removes its submission row');

    // Hold the row lock as a submission, then let an actual reviewer revoke.
    $pdo->beginTransaction();
    vnzIdentityRequireSubmission($pdo, null, 'concurrency@example.invalid', $pending['draft_id']);
    $start('revoke', 'revoke');
    identityMysqlWait(static fn() => is_file($root . '/revoke.ready'), 'revocation contender');
    file_put_contents($root . '/revoke.go', 'go');
    usleep(300000);
    identityMysqlAssert(!is_file($root . '/revoke.result'), 'revocation waits for the existing submission row lock');
    $pdo->exec('INSERT INTO submissions VALUES (3001)');
    vnzIdentityConsumeSubmission($pdo, null, 'concurrency@example.invalid', $pending['draft_id'], 3001);
    $pdo->commit();
    identityMysqlAssert($result('revoke') === 'committed', 'review revocation commits after earlier submission releases its lock');
    identityMysqlAssert(vnzIdentityFetch($pdo, 'SELECT status FROM identity_attempts WHERE id=?', [$pending['attempt_id']])['status'] === 'revoked', 'serialized review leaves the identity revoked');
    $start('after_revoke');
    identityMysqlWait(static fn() => is_file($root . '/after_revoke.ready'), 'post-revocation submission');
    file_put_contents($root . '/after_revoke.go', 'go');
    identityMysqlAssert($result('after_revoke') === 'rejected', 'future submission cannot use the revoked grant');
    identityMysqlAssert((int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn() === 1, 'revoked retry leaves the previously committed submission unchanged');
    echo "Completed 10 actual MySQL/InnoDB concurrency checks; disposable synthetic DB only.\n";
} catch (Throwable $error) {
    // Do not leak driver errors, connection config, DSNs, or user records.
    fwrite(STDERR, "Identity MySQL concurrency checks failed (connection/privilege/test assertion).\n");
    if ($error instanceof RuntimeException && !$error instanceof PDOException) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
    }
    $exitCode = 1;
} finally {
    foreach ($workers as $worker) {
        if (is_resource($worker)) {
            if (proc_get_status($worker)['running']) {
                proc_terminate($worker);
            }
            proc_close($worker);
        }
    }
    if ($pdo instanceof PDO && $createdDatabase) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('DROP DATABASE ' . identityMysqlDatabase($database));
    }
    putenv('VNZ_IDENTITY_CONFIG_PATH');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    // Delete only exact files in this uniquely named test directory, no recursion.
    if (is_dir($root . '/evidence')) {
        foreach (glob($root . '/evidence/*.enc') ?: [] as $path) {
            unlink($path);
        }
        rmdir($root . '/evidence');
    }
    if (is_dir($root . '/sessions')) {
        foreach (glob($root . '/sessions/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($root . '/sessions');
    }
    if (is_dir($root)) {
        foreach (glob($root . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($root);
    }
}
exit($exitCode);
