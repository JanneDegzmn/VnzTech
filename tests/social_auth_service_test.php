<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/social_auth_service.php';

function assertSocialAuth(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = [
    'google_client_id' => 'google-client-id',
    'google_client_secret' => 'google-client-secret',
    'facebook_client_id' => 'facebook-app-id',
    'facebook_client_secret' => 'facebook-app-secret',
    'facebook_graph_api_version' => 'v27.0',
    'redirect_uri' => 'http://localhost/VNZ-TECH/backend/social_auth.php',
];

assertSocialAuth(vnzSocialAuthConfigErrors($config) === [], 'Valid local OAuth configuration must pass.');
assertSocialAuth(
    in_array('google_client_secret_missing', vnzSocialAuthConfigErrors([
        ...$config,
        'google_client_secret' => 'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_SECRET',
    ]), true),
    'OAuth placeholders must fail validation.'
);
assertSocialAuth(
    in_array('redirect_uri_invalid', vnzSocialAuthConfigErrors([
        ...$config,
        'redirect_uri' => 'https://example.com/VNZ-TECH/backend/social_auth.php',
    ]), true),
    'OAuth callback must use the expected local application route.'
);
assertSocialAuth(vnzSocialAuthProvider('unknown') === null, 'Unknown providers must be rejected.');
assertSocialAuth(vnzPasswordStrength('') === 'weak', 'Empty passwords must be weak.');
assertSocialAuth(vnzPasswordStrength('short') === 'weak', 'Short passwords must be weak.');
assertSocialAuth(vnzPasswordStrength('lowercase1!') === 'medium', 'Eight characters and three types must be medium.');
assertSocialAuth(vnzPasswordStrength('Aa1!aaaaaaaaaaaa') === 'strong', 'Sixteen characters and all types must be strong.');

$_SESSION = ['social_oauth_verifier' => str_repeat('v', 43)];
$googleUrl = vnzSocialAuthBuildAuthorizationUrl('google', $config, str_repeat('s', 64));
assertSocialAuth(is_string($googleUrl), 'Google authorization URL must be generated.');
assertSocialAuth(str_contains($googleUrl, 'code_challenge_method=S256'), 'Google authorization must use PKCE.');
assertSocialAuth(str_contains($googleUrl, 'state=' . str_repeat('s', 64)), 'OAuth URL must include its state.');
assertSocialAuth(
    str_starts_with(
        vnzSocialAuthBuildAuthorizationUrl('facebook', $config, 'state') ?? '',
        'https://www.facebook.com/v27.0/dialog/oauth?'
    ),
    'Facebook authorization endpoint must not encode its path separator.'
);
assertSocialAuth(
    !str_contains(vnzSocialAuthBuildAuthorizationUrl('facebook', $config, 'state') ?? '', 'code_challenge'),
    'Facebook authorization must not receive the Google PKCE challenge.'
);

echo "Social auth service tests passed.\n";
