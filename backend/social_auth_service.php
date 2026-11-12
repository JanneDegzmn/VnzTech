<?php
declare(strict_types=1);

require_once __DIR__ . '/password_policy.php';

function vnzSocialAuthConfigErrors(array $config): array
{
    $errors = [];
    foreach ([
        'google_client_id',
        'google_client_secret',
        'facebook_client_id',
        'facebook_client_secret',
    ] as $key) {
        if (
            !isset($config[$key])
            || !is_string($config[$key])
            || trim($config[$key]) === ''
            || str_starts_with($config[$key], 'REPLACE_WITH_')
        ) {
            $errors[] = $key . '_missing';
        }
    }

    $redirectUri = (string) ($config['redirect_uri'] ?? '');
    $parts = parse_url($redirectUri);
    if (
        !filter_var($redirectUri, FILTER_VALIDATE_URL)
        || !is_array($parts)
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || ($parts['path'] ?? '') !== '/VNZ-TECH/backend/social_auth.php'
        || !in_array(
            strtolower((string) ($parts['host'] ?? '')),
            ['localhost', '127.0.0.1'],
            true
        )
    ) {
        $errors[] = 'redirect_uri_invalid';
    }

    if (
        !isset($config['facebook_graph_api_version'])
        || !is_string($config['facebook_graph_api_version'])
        || preg_match('/\Av[0-9]+\.[0-9]+\z/', $config['facebook_graph_api_version']) !== 1
    ) {
        $errors[] = 'facebook_graph_api_version_invalid';
    }

    return array_values(array_unique($errors));
}

function vnzSocialAuthProvider(string $provider): ?array
{
    return match ($provider) {
        'google' => [
            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'profile_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'facebook' => [
            'authorization_endpoint' => 'https://www.facebook.com/',
            'scope' => 'email,public_profile',
        ],
        default => null,
    };
}

function vnzSocialAuthBuildAuthorizationUrl(
    string $provider,
    array $config,
    string $state
): ?string {
    $providerConfig = vnzSocialAuthProvider($provider);
    if ($providerConfig === null || $state === '') {
        return null;
    }

    $clientIdKey = $provider . '_client_id';
    if (empty($config[$clientIdKey]) || empty($config['redirect_uri'])) {
        return null;
    }

    $endpoint = $providerConfig['authorization_endpoint'];
    if ($provider === 'facebook') {
        $endpoint .= rawurlencode((string) $config['facebook_graph_api_version']) . '/dialog/oauth';
    }

    return $endpoint . '?' . http_build_query([
        'client_id' => $config[$clientIdKey],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => $providerConfig['scope'],
        'state' => $state,
        'code_challenge' => $provider === 'google'
            ? rtrim(strtr(base64_encode(hash('sha256', (string) ($_SESSION['social_oauth_verifier'] ?? ''), true)), '+/', '-_'), '=')
            : null,
        'code_challenge_method' => $provider === 'google' ? 'S256' : null,
    ], '', '&', PHP_QUERY_RFC3986);
}

function vnzSocialAuthHttpRequest(
    string $url,
    ?array $formFields = null,
    ?string $bearerToken = null
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('OAuth sign-in is unavailable because cURL is not enabled.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the OAuth request.');
    }

    $headers = ['Accept: application/json'];
    if ($bearerToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($formFields !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($formFields, '', '&', PHP_QUERY_RFC3986);
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $errorNumber = curl_errno($curl);
    curl_close($curl);

    if ($response === false || $errorNumber !== 0 || $status < 200 || $status >= 300) {
        throw new RuntimeException('The identity provider request failed.');
    }

    try {
        $decoded = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('The identity provider returned an invalid response.');
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('The identity provider returned an invalid response.');
    }

    return $decoded;
}

function vnzSocialAuthFetchProfile(
    string $provider,
    string $code,
    array $config
): array {
    $providerConfig = vnzSocialAuthProvider($provider);
    if ($providerConfig === null || $code === '') {
        throw new RuntimeException('The OAuth callback is invalid.');
    }

    $tokenFields = [
        'client_id' => $config[$provider . '_client_id'],
        'client_secret' => $config[$provider . '_client_secret'],
        'code' => $code,
        'redirect_uri' => $config['redirect_uri'],
    ];
    if ($provider === 'google') {
        $tokenFields['grant_type'] = 'authorization_code';
        $verifier = (string) ($_SESSION['social_oauth_verifier'] ?? '');
        if ($verifier === '') {
            throw new RuntimeException('The OAuth transaction is incomplete.');
        }
        $tokenFields['code_verifier'] = $verifier;
        $token = vnzSocialAuthHttpRequest($providerConfig['token_endpoint'], $tokenFields);
    } else {
        $tokenFields['code'] = $code;
        $tokenEndpoint = 'https://graph.facebook.com/'
            . rawurlencode((string) $config['facebook_graph_api_version'])
            . '/oauth/access_token?'
            . http_build_query($tokenFields, '', '&', PHP_QUERY_RFC3986);
        $token = vnzSocialAuthHttpRequest($tokenEndpoint);
    }
    unset($_SESSION['social_oauth_verifier']);
    $accessToken = $token['access_token'] ?? null;
    if (!is_string($accessToken) || $accessToken === '') {
        throw new RuntimeException('The identity provider did not return an access token.');
    }

    if ($provider === 'google') {
        $profile = vnzSocialAuthHttpRequest($providerConfig['profile_endpoint'], null, $accessToken);
        $emailVerified = in_array($profile['email_verified'] ?? false, [true, 1, '1', 'true'], true);
        if (!$emailVerified) {
            throw new RuntimeException('Google did not confirm the email address.');
        }
    } else {
        $profileUrl = 'https://graph.facebook.com/'
            . rawurlencode((string) $config['facebook_graph_api_version'])
            . '/me?'
            . http_build_query(['fields' => 'id,name,email'], '', '&', PHP_QUERY_RFC3986);
        $profile = vnzSocialAuthHttpRequest($profileUrl, null, $accessToken);
        $emailVerified = false;
    }

    $subject = (string) ($profile['sub'] ?? $profile['id'] ?? '');
    $name = trim((string) ($profile['name'] ?? ''));
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    if (
        $subject === ''
        || strlen($subject) > 255
        || $name === ''
        || strlen($name) > 150
        || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190))
    ) {
        throw new RuntimeException('The identity provider profile is incomplete.');
    }

    return [
        'provider' => $provider,
        'subject' => $subject,
        'full_name' => $name,
        'email' => $email,
        'email_verified' => $emailVerified,
    ];
}

function vnzSocialAuthCompleteLogin(PDO $pdo, array $user): never
{
    if ((int) $user['is_active'] !== 1) {
        header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Your account is currently inactive.'));
        exit;
    }

    if (
        !empty($user['totp_enabled_at'])
        && !empty($user['totp_secret'])
        && (int) $user['totp_login_required'] === 1
    ) {
        $_SESSION['seller_2fa_login_user_id'] = (int) $user['id'];
        $_SESSION['seller_2fa_login_started_at'] = time();
        $_SESSION['remember_seller_after_2fa'] = false;
        header('Location: /VNZ-TECH/account/login.php?two_factor=1');
        exit;
    }

    session_regenerate_id(true);
    clearAdminSessionState();
    clearSellerSessionState();
    vnzRevokeRememberLoginCookie($pdo);
    chatRegisterPresenceSession('seller', (int) $user['id']);
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    chatLogAuthenticationTiming(
        'seller_social_login',
        (float) ($_SESSION['social_auth_started_at'] ?? microtime(true))
    );

    if (
        (int) $user['totp_reminder_dismissed'] !== 1
        && (empty($user['totp_enabled_at']) || (int) $user['totp_login_required'] !== 1)
    ) {
        $_SESSION['seller_2fa_reminder'] = true;
    }
    unset($_SESSION['social_auth_started_at'], $_SESSION['social_oauth_verifier']);

    header('Location: /VNZ-TECH/account/dashboard.php');
    exit;
}
