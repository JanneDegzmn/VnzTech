<?php
/** Synthetic-only integration checks; never touches the application's database. */
require_once __DIR__ . '/../backend/identity_verification.php';

$root = sys_get_temp_dir() . '/vnz-identity-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
mkdir($root . '/evidence', 0700);
file_put_contents($root . '/key.bin', random_bytes(32));
file_put_contents($root . '/config.php', '<?php return ' . var_export(['enabled' => true, 'storage_path' => $root, 'key_path' => $root . '/key.bin'], true) . ';');
putenv('VNZ_IDENTITY_CONFIG_PATH=' . $root . '/config.php');
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,email TEXT,email_verified_at TEXT,is_active INTEGER)');
$pdo->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY,role_id INTEGER,is_active INTEGER)');
$pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY,is_active INTEGER)');
$pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY,name TEXT)');
$pdo->exec('CREATE TABLE role_permissions (role_id INTEGER,permission_id INTEGER)');
$pdo->exec("INSERT INTO users VALUES (1,'seller@example.invalid','2026-01-01',1),(2,'guest@example.invalid',NULL,1),(3,'other@example.invalid','2026-01-01',1),(4,'inactive@example.invalid','2026-01-01',0)");
$pdo->exec("INSERT INTO admins VALUES (1,1,1),(2,2,1),(3,1,0); INSERT INTO permissions VALUES (1,'identity_review'); INSERT INTO role_permissions VALUES (1,1)");
$pdo->exec('INSERT INTO roles VALUES (1,1),(2,1)');
$pdo->exec('CREATE TABLE identity_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,context TEXT,user_id INTEGER,draft_id TEXT,capability_hash TEXT,email TEXT,status TEXT,id_file TEXT,selfie_file TEXT,evidence_hash TEXT,rate_limit_hash TEXT,consent_at TEXT,created_at TEXT,updated_at TEXT,expires_at TEXT,grant_expires_at TEXT,submission_id INTEGER UNIQUE,reserved_user_id INTEGER,reservation_expires_at TEXT,reviewed_by INTEGER,reviewed_at TEXT,review_reason TEXT,purge_after TEXT,purged_at TEXT)');
$pdo->exec('CREATE TABLE identity_bindings (user_id INTEGER PRIMARY KEY,attempt_id INTEGER UNIQUE,created_at TEXT)');
$pdo->exec('CREATE TABLE identity_audit (id INTEGER PRIMARY KEY AUTOINCREMENT,attempt_id INTEGER,event TEXT,admin_id INTEGER,user_id INTEGER,created_at TEXT)');
$_SESSION = [];
$checks = 0;
function identityAssert(bool $ok, string $name): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAILED: ' . $name); }
    $checks++;
    echo 'PASS ' . $name . PHP_EOL;
}
function identityReject(callable $fn, string $name): void {
    try { $fn(); } catch (Throwable $e) { identityAssert(true, $name); return; }
    identityAssert(false, $name);
}
function identityImage(string $root, string $name, int $seed): array {
    $image = imagecreatetruecolor(160, 180);
    imagefill($image, 0, 0, imagecolorallocate($image, $seed, 80, 100));
    imagejpeg($image, $root . '/' . $name);
    imagedestroy($image);
    return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $root . '/' . $name];
}
function identityApprove(PDO $pdo, int $id): void {
    $_SESSION['admin_id'] = 1;
    $row = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?', [$id]);
    vnzIdentityReview($pdo, $id, $row['evidence_hash'], 'approve', '', true);
    unset($_SESSION['admin_id']);
}
try {
    identityReject(fn() => vnzIdentityReady($pdo), 'activation requires a successful cleanup heartbeat');
    vnzIdentityCleanup($pdo);
    vnzIdentityReady($pdo);
    identityAssert(true, 'private configuration and schema become ready after cleanup');
    identityAssert(vnzIdentityPublicPath(dirname(__DIR__, 2) . '/other-site/private'), 'sibling site under documentroot is not private storage');
    file_put_contents($root . '/cleanup-heartbeat.json', json_encode(['success_at' => time() - 172801]));
    identityReject(fn() => vnzIdentityReady($pdo), 'stale cleanup fails closed');
    vnzIdentityCleanup($pdo);
    $idImage = identityImage($root, 'id.jpg', 50);
    $selfie = identityImage($root, 'selfie.jpg', 70);
    file_put_contents($root . '/invalid.jpg', '<?php echo "bad";');
    identityReject(fn() => vnzIdentityValidateImage(['tmp_name' => $root . '/invalid.jpg','error' => 0]), 'fake image rejected by content');
    identityReject(fn() => vnzIdentitySubmit($pdo, 'guest', 'guest@example.invalid', $idImage, $selfie, false), 'consent is required');
    $guest = vnzIdentityStatus($pdo, 'guest');
    identityAssert(strlen($guest['draft_id']) === 48 && !$guest['approved'], 'new guest has random draft and no authority');
    $pending = vnzIdentitySubmit($pdo, 'guest', 'guest@example.invalid', $idImage, $selfie, true);
    $guestId = $pending['attempt_id'];
    $guestSession = $_SESSION;
    identityAssert($pending['status'] === 'pending_review' && !$pending['approved'], 'upload enters pending review without authorization');
    $row = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?', [$guestId]);
    $blob = file_get_contents(vnzIdentityEvidencePath($row['id_file']));
    identityAssert(substr($blob, 0, 4) === 'VNZ1' && $blob !== vnzIdentityDecrypt($blob, vnzIdentityKey()), 'evidence is encrypted outside the webroot');
    identityReject(fn() => vnzIdentityDecrypt(substr_replace($blob, chr(ord($blob[32]) ^ 1), 32, 1), vnzIdentityKey()), 'tampered ciphertext fails authentication');
    identityAssert(strlen(vnzIdentityEvidence($pdo, $guestId, 'id')) > 100, 'bound guest may read its own private evidence');
    $_SESSION = [];
    identityReject(fn() => vnzIdentityEvidence($pdo, $guestId, 'id'), 'unrelated session cannot read evidence');
    $_SESSION = ['admin_id' => 2];
    identityReject(fn() => vnzIdentityReview($pdo, $guestId, $row['evidence_hash'], 'approve', '', true), 'ordinary staff cannot approve identity');
    $_SESSION = ['admin_id' => 3];
    identityAssert(!vnzIdentityReviewer($pdo), 'inactive admin has no reviewer authority');
    $_SESSION = ['admin_id' => 1];
    $pdo->exec('UPDATE roles SET is_active=0 WHERE id=1');
    identityAssert(!vnzIdentityReviewer($pdo), 'inactive reviewer role has no identity access');
    $pdo->exec('UPDATE roles SET is_active=1 WHERE id=1');
    identityReject(fn() => vnzIdentityReview($pdo, $guestId, str_repeat('a', 64), 'approve', '', true), 'stale evidence version cannot be approved');
    identityReject(fn() => vnzIdentityReview($pdo, $guestId, $row['evidence_hash'], 'approve', '', false), 'explicit human confirmation is required');
    $_SESSION = $guestSession;
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, null, 'guest@example.invalid', $pending['draft_id']), 'pending guest cannot submit');
    identityApprove($pdo, $guestId);
    identityAssert(vnzIdentityStatus($pdo, 'guest')['approved'], 'authorized review grants exact guest proof');
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, null, 'other@example.invalid', $pending['draft_id']), 'guest cannot change the verified submission email');
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, null, 'guest@example.invalid', 'another-draft'), 'guest cannot use proof for another draft');
    $pdo->beginTransaction();
    vnzIdentityConsumeSubmission($pdo, null, 'guest@example.invalid', $pending['draft_id'], 100);
    $pdo->rollBack();
    identityAssert(vnzIdentityRequireSubmission($pdo, null, 'guest@example.invalid', $pending['draft_id'])['submission_id'] === null, 'submission rollback restores one-use guest grant');
    $pdo->beginTransaction();
    vnzIdentityConsumeSubmission($pdo, null, 'guest@example.invalid', $pending['draft_id'], 100);
    $pdo->commit();
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, null, 'guest@example.invalid', $pending['draft_id']), 'guest grant replay is rejected');
    $fresh = vnzIdentityStatus($pdo, 'guest');
    identityAssert($fresh['draft_id'] !== $pending['draft_id'] && !$fresh['approved'], 'next guest submission requires new verification');
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 2, 'other@example.invalid');
    $pdo->commit();
    identityAssert(vnzIdentityFetch($pdo, 'SELECT reserved_user_id FROM identity_attempts WHERE id=?', [$guestId])['reserved_user_id'] === null, 'email mismatch does not reserve identity');
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 2, 'guest@example.invalid');
    vnzIdentityFinalizeAccount($pdo, 2);
    $pdo->commit();
    identityAssert(vnzIdentityFetch($pdo, 'SELECT * FROM identity_bindings WHERE user_id=2') === null, 'unverified email cannot finalize reserved guest identity');
    $pdo->exec("UPDATE users SET email_verified_at='2026-01-01' WHERE id=2");
    $pdo->beginTransaction();
    vnzIdentityFinalizeAccount($pdo, 2);
    $pdo->commit();
    identityAssert((int) vnzIdentityFetch($pdo, 'SELECT * FROM identity_bindings WHERE user_id=2')['attempt_id'] === $guestId, 'verified exact reserved account inherits approved identity');
    identityReject(fn() => vnzIdentityEvidence($pdo, $guestId, 'id'), 'guest capability is consumed after account binding');
    $_SESSION = ['user_logged_in' => true, 'user_id' => 2];
    identityAssert(vnzIdentityStatus($pdo, 'account')['approved'], 'bound account keeps durable approval');
    $pdo->exec('UPDATE users SET is_active=0 WHERE id=2');
    identityReject(fn() => vnzIdentityEvidence($pdo, $guestId, 'id'), 'inactive account owner cannot read private evidence');
    $pdo->exec('UPDATE users SET is_active=1 WHERE id=2');
    vnzIdentityRequireSubmission($pdo, 2, 'guest@example.invalid', '');
    vnzIdentityRequireSubmission($pdo, 2, 'guest@example.invalid', '');
    identityAssert(true, 'approved account can submit repeatedly without recapture');
    $pdo->exec("UPDATE identity_attempts SET purge_after='2000-01-01' WHERE id=" . $guestId);
    $cleanup = vnzIdentityCleanup($pdo);
    identityAssert($cleanup['purged'] === 1 && !is_file(vnzIdentityEvidencePath($row['id_file'])), 'decided evidence retention cleanup removes raw files');
    identityAssert(vnzIdentityStatus($pdo, 'account')['approved'], 'raw-file retention does not revoke durable account decision');
    $_SESSION['admin_id'] = 1;
    vnzIdentityReview($pdo, $guestId, $row['evidence_hash'], 'revoke', 'Synthetic review correction', true);
    unset($_SESSION['admin_id']);
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, 2, 'guest@example.invalid', ''), 'revocation immediately blocks future account submissions');
    $_SESSION = ['user_logged_in' => true, 'user_id' => 1];
    $accountPending = vnzIdentitySubmit($pdo, 'account', 'ignored@example.invalid', $idImage, $selfie, true);
    identityAssert($accountPending['email'] === 'seller@example.invalid', 'account email is resolved from trusted user row');
    $accountId = $accountPending['attempt_id'];
    $pdo->exec("UPDATE identity_attempts SET expires_at='2000-01-01' WHERE id=" . $accountId);
    $_SESSION['admin_id'] = 1;
    $accountRow = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?', [$accountId]);
    identityReject(fn() => vnzIdentityReview($pdo, $accountId, $accountRow['evidence_hash'], 'approve', '', true), 'expired pending cannot be approved');
    $cleanup = vnzIdentityCleanup($pdo);
    identityAssert($cleanup['expired'] === 1 && $cleanup['purged'] === 1, 'expired pending evidence is both invalidated and purged');
    unset($_SESSION['admin_id']);
    $_SESSION = [];
    $p2 = vnzIdentitySubmit($pdo, 'guest', 'other@example.invalid', $idImage, $selfie, true);
    identityApprove($pdo, $p2['attempt_id']);
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 3, 'other@example.invalid');
    $pdo->rollBack();
    identityAssert(vnzIdentityFetch($pdo, 'SELECT reserved_user_id FROM identity_attempts WHERE id=?', [$p2['attempt_id']])['reserved_user_id'] === null, 'signup rollback restores reservation capability');
    $pdo->exec("UPDATE identity_attempts SET grant_expires_at='2000-01-01' WHERE id=" . $p2['attempt_id']);
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, null, 'other@example.invalid', $p2['draft_id']), 'guest grant expires independently from raw evidence');
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 3, 'other@example.invalid');
    $pdo->commit();
    identityAssert(vnzIdentityFetch($pdo, 'SELECT reserved_user_id FROM identity_attempts WHERE id=?', [$p2['attempt_id']])['reserved_user_id'] === null, 'expired guest grant cannot be promoted through signup');
    // A separate guest-to-account binding remains pending until actual review.
    $_SESSION = [];
    $p3 = vnzIdentitySubmit($pdo, 'guest', 'other@example.invalid', $idImage, $selfie, true);
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 3, 'other@example.invalid');
    vnzIdentityFinalizeAccount($pdo, 3);
    $pdo->commit();
    $_SESSION = ['user_logged_in'=>true,'user_id'=>3];
    identityAssert(vnzIdentityStatus($pdo, 'account')['status'] === 'pending_review', 'pending guest carryover remains pending after email verification');
    identityReject(fn() => vnzIdentityRequireSubmission($pdo, 3, 'other@example.invalid', ''), 'email verification alone cannot approve carried identity');
    identityApprove($pdo, $p3['attempt_id']);
    identityAssert(vnzIdentityStatus($pdo, 'account')['approved'], 'later exact admin review approves the carried pending account');
    // Reserving a valid grant does not freeze its expiry before email confirmation.
    $pdo->exec("INSERT INTO users VALUES (5,'late@example.invalid','2026-01-01',1)");
    $_SESSION = [];
    $late = vnzIdentitySubmit($pdo, 'guest', 'late@example.invalid', $idImage, $selfie, true);
    identityApprove($pdo, $late['attempt_id']);
    $pdo->beginTransaction();
    vnzIdentityReserveGuest($pdo, 5, 'late@example.invalid');
    $pdo->commit();
    $pdo->exec("UPDATE identity_attempts SET grant_expires_at='2000-01-01' WHERE id=" . $late['attempt_id']);
    $pdo->beginTransaction();
    vnzIdentityFinalizeAccount($pdo, 5);
    $pdo->commit();
    identityAssert(vnzIdentityFetch($pdo, 'SELECT * FROM identity_bindings WHERE user_id=5') === null, 'grant expiry after reservation prevents final account promotion');
    file_put_contents($root . '/evidence/' . str_repeat('a',48) . '.enc', 'orphan');
    touch($root . '/evidence/' . str_repeat('a',48) . '.enc', time() - 86401);
    identityAssert(vnzIdentityCleanup($pdo)['orphaned'] === 1, 'abandoned orphan evidence is removed after 24 hours');
    identityReject(fn() => vnzIdentityEvidencePath('../key.bin'), 'private path traversal is rejected');
    file_put_contents($root . '/config.php', "<?php return ['enabled'=>false];");
    identityAssert(vnzIdentityStatus($pdo, 'guest')['status'] === 'disabled', 'preactivation disabled configuration preserves explicit disabled state');
    identityAssert(vnzIdentityRequireSubmission($pdo, null, '', '') === null, 'disabled rollout gate does not alter existing submission policy');
    echo 'Completed ' . $checks . ' synthetic identity checks.' . PHP_EOL;
} finally {
    putenv('VNZ_IDENTITY_CONFIG_PATH');
    // Exact test-owned files only; no recursive filesystem deletion.
    foreach (glob($root . '/evidence/*') ?: [] as $path) { unlink($path); }
    rmdir($root . '/evidence');
    foreach (glob($root . '/*') ?: [] as $path) { unlink($path); }
    rmdir($root);
}
