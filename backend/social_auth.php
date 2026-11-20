<?php
declare(strict_types=1);
require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false);


require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/chat_presence_store.php';
require_once __DIR__ . '/social_auth_service.php';

function vnzSocialAuthRedirect(string $message, string $page = 'login'): never
{
    $target = $page === 'register'
        ? '/VNZ-TECH/account/social_signup.php'
        : '/VNZ-TECH/account/login.php';
    header('Location: ' . $target . '?error=' . urlencode($message));
    exit;
}

function vnzSocialAuthConfig(): array
{
    $path = __DIR__ . '/social_auth_config.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Social login is not configured.');
    }

    $config = require $path;
    if (!is_array($config) || vnzSocialAuthConfigErrors($config) !== []) {
        throw new RuntimeException('Social login configuration is invalid.');
    }

    return $config;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$action = (string) ($_GET['action'] ?? 'callback');
if ($action === 'start') {
    if (!empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {
        header('Location: /VNZ-TECH/account/dashboard.php');
        exit;
    }

    $provider = (string) ($_GET['provider'] ?? '');
    if (vnzSocialAuthProvider($provider) === null) {
        vnzSocialAuthRedirect('That sign-in provider is not supported.');
    }
    if (!is_file(__DIR__ . '/social_auth_config.local.php')) {
        vnzSocialAuthRedirect('Google and Facebook sign-in are not configured yet.');
    }

    try {
        $config = vnzSocialAuthConfig();
        $state = bin2hex(random_bytes(32));
        if ($provider === 'google') {
            $_SESSION['social_oauth_verifier'] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } else {
            unset($_SESSION['social_oauth_verifier']);
        }
        $_SESSION['social_oauth_transaction'] = [
            'provider' => $provider,
            'state' => $state,
            'created_at' => time(),
        ];
        $_SESSION['social_auth_started_at'] = microtime(true);
        $authorizationUrl = vnzSocialAuthBuildAuthorizationUrl($provider, $config, $state);
        if ($authorizationUrl === null) {
            throw new RuntimeException('Social login could not be started.');
        }
        header('Location: ' . $authorizationUrl);
        exit;
    } catch (Throwable $error) {
        error_log('Social login start failed: ' . $error->getMessage());
        vnzSocialAuthRedirect('Social sign-in is temporarily unavailable.');
    }
}

if ($action !== 'callback') {
    http_response_code(404);
    exit;
}

if (!empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {
    unset($_SESSION['social_oauth_transaction'], $_SESSION['social_oauth_verifier']);
    header('Location: /VNZ-TECH/account/dashboard.php');
    exit;
}

$transaction = $_SESSION['social_oauth_transaction'] ?? null;
unset($_SESSION['social_oauth_transaction']);
if (
    !is_array($transaction)
    || !is_string($transaction['provider'] ?? null)
    || !is_string($transaction['state'] ?? null)
    || !is_int($transaction['created_at'] ?? null)
    || time() - $transaction['created_at'] > 600
    || !isset($_GET['state'])
    || !is_string($_GET['state'])
    || !hash_equals($transaction['state'], $_GET['state'])
) {
    unset($_SESSION['social_oauth_verifier']);
    vnzSocialAuthRedirect('Your social sign-in request expired. Please try again.');
}

if (isset($_GET['error'])) {
    unset($_SESSION['social_oauth_verifier']);
    vnzSocialAuthRedirect('Social sign-in was cancelled or declined.');
}

$provider = $transaction['provider'];
$code = $_GET['code'] ?? null;
if (!is_string($code) || $code === '' || strlen($code) > 4096) {
    unset($_SESSION['social_oauth_verifier']);
    vnzSocialAuthRedirect('The identity provider returned an invalid sign-in response.');
}

try {
    $config = vnzSocialAuthConfig();
    $profile = vnzSocialAuthFetchProfile($provider, $code, $config);

    $identityStmt = $pdo->prepare("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.is_active,
            u.email_verified_at,
            u.totp_secret,
            u.totp_enabled_at,
            u.totp_reminder_dismissed,
            u.totp_login_required
        FROM user_oauth_accounts oa
        INNER JOIN users u ON u.id = oa.user_id
        WHERE oa.provider = ? AND oa.provider_subject_hash = ?
        LIMIT 1
    ");
    $identityStmt->execute([$provider, hash('sha256', $profile['subject'])]);
    $user = $identityStmt->fetch();
    if ($user) {
        vnzSocialAuthCompleteLogin($pdo, $user);
    }

    if ($profile['email'] !== '') {
        $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $emailStmt->execute([$profile['email']]);
        if ($emailStmt->fetch()) {
            vnzSocialAuthRedirect(
                'An account already uses this email. Sign in with that account; social accounts are not linked automatically.'
            );
        }
    }

    $_SESSION['social_signup_profile'] = $profile;
    $_SESSION['social_signup_started_at'] = time();
    header('Location: /VNZ-TECH/account/social_signup.php');
    exit;
} catch (Throwable $error) {
    unset($_SESSION['social_oauth_verifier'], $_SESSION['social_auth_started_at']);
    error_log('Social login callback failed: ' . $error->getMessage());
    vnzSocialAuthRedirect('Social sign-in could not be completed. Please try again.');
}
