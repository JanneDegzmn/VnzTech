<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/email_verification.php';
require_once __DIR__ . '/chat_presence_store.php';
require_once __DIR__ . '/social_auth_service.php';

function vnzSocialSignupError(string $message): never
{
    header('Location: /VNZ-TECH/account/social_signup.php?error=' . urlencode($message));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /VNZ-TECH/account/register.php');
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    vnzSocialSignupError('Your session expired. Refresh the page and try again.');
}

$profile = $_SESSION['social_signup_profile'] ?? null;
if (
    !is_array($profile)
    || !isset($_SESSION['social_signup_started_at'])
    || time() - (int) $_SESSION['social_signup_started_at'] > 600
) {
    unset($_SESSION['social_signup_profile'], $_SESSION['social_signup_started_at']);
    vnzSocialSignupError('Your social sign-up expired. Please start again.');
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$mobile = trim((string) ($_POST['mobile'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? $profile['email'] ?? '')));
$passwordValue = $_POST['password'] ?? '';
$confirmPasswordValue = $_POST['confirm_password'] ?? '';
$password = is_string($passwordValue) ? $passwordValue : '';
$confirmPassword = is_string($confirmPasswordValue) ? $confirmPasswordValue : '';
$passwordProvided = $password !== '' || $confirmPassword !== '';
$normalizedMobile = preg_replace('/\D+/', '', $mobile);
if ($fullName === '' || strlen($fullName) > 150) {
    vnzSocialSignupError('Enter your full name.');
}
if ($normalizedMobile === null || strlen($normalizedMobile) < 8 || strlen($normalizedMobile) > 15) {
    vnzSocialSignupError('Enter a valid mobile number.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    vnzSocialSignupError('Enter a valid email address.');
}
if ($passwordProvided) {
    if ($password === '') {
        vnzSocialSignupError('Enter a password if you want to use email sign-in.');
    }
    if ($confirmPassword === '') {
        vnzSocialSignupError('Confirm your password to continue.');
    }
    if (vnzPasswordStrength($password) === 'weak') {
        vnzSocialSignupError('Choose a Medium or Strong password using at least 8 characters and 3 character types.');
    }
    if ($password !== $confirmPassword) {
        vnzSocialSignupError('Passwords do not match.');
    }
}
if (
    ($profile['provider'] ?? '') === 'google'
    && (
        ($profile['email_verified'] ?? false) !== true
        || strcasecmp($email, (string) ($profile['email'] ?? '')) !== 0
    )
) {
    vnzSocialSignupError('Use the verified email address returned by Google.');
}
if (($_POST['terms'] ?? '') !== '1') {
    vnzSocialSignupError('You must agree to the Terms & Conditions.');
}

try {
    $passwordToHash = $passwordProvided ? $password : bin2hex(random_bytes(32));
    $passwordHash = password_hash($passwordToHash, PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Unable to secure the new account password.');
    }

    $pdo->beginTransaction();
    $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
    $emailStmt->execute([$email]);
    if ($emailStmt->fetch()) {
        $pdo->rollBack();
        vnzSocialSignupError(
            'An account already uses this email. Sign in with that account; social accounts are not linked automatically.'
        );
    }

    $emailVerifiedAt = (
        ($profile['provider'] ?? '') === 'google'
        && ($profile['email_verified'] ?? false) === true
        && strcasecmp($email, (string) ($profile['email'] ?? '')) === 0
    ) ? date('Y-m-d H:i:s') : null;
    $insertUser = $pdo->prepare("
        INSERT INTO users (
            full_name,
            mobile,
            email,
            password_hash,
            profile_picture,
            language,
            email_verified_at,
            is_active
        )
        VALUES (?, ?, ?, ?, NULL, 'en', ?, 1)
    ");
    $insertUser->execute([
        $fullName,
        $mobile,
        $email,
        $passwordHash,
        $emailVerifiedAt,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $insertIdentity = $pdo->prepare("
        INSERT INTO user_oauth_accounts (user_id, provider, provider_subject_hash)
        VALUES (?, ?, ?)
    ");
    $insertIdentity->execute([
        $userId,
        $profile['provider'],
        hash('sha256', (string) $profile['subject']),
    ]);
    $pdo->commit();
    unset($_SESSION['social_signup_profile'], $_SESSION['social_signup_started_at']);

    if ($emailVerifiedAt === null) {
        try {
            sendEmailVerification($pdo, $userId, $email);
        } catch (Throwable $mailError) {
            error_log('Social signup verification email could not be sent for user ' . $userId . ': ' . $mailError->getMessage());
        }
    }

    unset($_SESSION['social_auth_started_at']);
    try {
        vnzSocialAuthCompleteLogin($pdo, [
            'id' => $userId,
            'full_name' => $fullName,
            'email' => $email,
            'is_active' => 1,
            'email_verified_at' => $emailVerifiedAt,
            'totp_secret' => null,
            'totp_enabled_at' => null,
            'totp_reminder_dismissed' => 0,
            'totp_login_required' => 0,
        ]);
    } catch (Throwable $loginError) {
        error_log('Social account was created but login setup failed for user ' . $userId . ': ' . $loginError->getMessage());
        header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Your account was created, but sign-in could not be completed. Please try the provider again.'));
        exit;
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Social account creation failed: ' . $error->getMessage());
    vnzSocialSignupError('Your account could not be created right now. Please try again.');
}
