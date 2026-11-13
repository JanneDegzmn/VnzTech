<?php

function resolveVerificationBaseUrl(string $configuredBaseUrl): string
{
    $environmentBaseUrl = getenv('VNZ_APP_BASE_URL');
    if ($environmentBaseUrl === false || trim($environmentBaseUrl) === '') {
        $environmentBaseUrl = $_SERVER['VNZ_APP_BASE_URL']
            ?? $_ENV['VNZ_APP_BASE_URL']
            ?? '';
    }

    $configuredParts = parse_url($configuredBaseUrl);
    $configuredHost = strtolower((string) ($configuredParts['host'] ?? ''));
    $localHosts = ['localhost', '127.0.0.1', '::1'];
    $requestHostHeader = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $requestParts = $requestHostHeader !== ''
        ? parse_url('//' . $requestHostHeader)
        : false;
    $requestHost = strtolower((string) ($requestParts['host'] ?? ''));
    $applicationPath = rtrim((string) ($configuredParts['path'] ?? ''), '/');

    if (trim((string) $environmentBaseUrl) !== '') {
        $baseUrl = trim((string) $environmentBaseUrl);
        $parts = parse_url($baseUrl);
        if (
            !filter_var($baseUrl, FILTER_VALIDATE_URL) ||
            !is_array($parts) ||
            !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) ||
            empty($parts['host']) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['query']) ||
            isset($parts['fragment'])
        ) {
            throw new RuntimeException('VNZ_APP_BASE_URL must be a valid HTTP or HTTPS application URL.');
        }

        $environmentHost = strtolower((string) ($parts['host'] ?? ''));
        if (
            $requestHost === 'vnztech.alwaysdata.net' &&
            in_array($environmentHost, $localHosts, true)
        ) {
            return 'https://vnztech.alwaysdata.net' . $applicationPath;
        }

        return rtrim($baseUrl, '/');
    }

    if ($requestHost === 'vnztech.alwaysdata.net') {
        return 'https://vnztech.alwaysdata.net' . $applicationPath;
    }

    if (!in_array($configuredHost, $localHosts, true)) {
        return rtrim($configuredBaseUrl, '/');
    }

    if ($requestHostHeader === '') {
        return rtrim($configuredBaseUrl, '/');
    }

    if (in_array($requestHost, $localHosts, true)) {
        $scheme = (
            !empty($_SERVER['HTTPS']) &&
            strtolower((string) $_SERVER['HTTPS']) !== 'off'
        ) ? 'https' : 'http';
        $port = isset($requestParts['port']) ? ':' . (int) $requestParts['port'] : '';

        return $scheme . '://' . $requestHost . $port . $applicationPath;
    }

    throw new RuntimeException(
        'Set VNZ_APP_BASE_URL to the public application URL before sending verification email.'
    );
}

function getVerificationMailConfig(): array
{
    $autoloadPath =
        dirname(__DIR__) . '/vendor/autoload.php';

    $configPath =
        __DIR__ . '/mail_config.local.php';

    if (!is_file($autoloadPath)) {
        throw new RuntimeException(
            'Email service dependency is not installed.'
        );
    }

    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Email service is not configured. Copy mail_config.example.php to mail_config.local.php and add Gmail App Password settings.'
        );
    }

    require_once $autoloadPath;

    $config = require $configPath;

    if (!is_array($config)) {
        throw new RuntimeException(
            'Email service configuration is invalid.'
        );
    }

    foreach ([
        'base_url',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'from_email',
        'from_name'
    ] as $key) {
        if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
            throw new RuntimeException(
                'Email service configuration is missing ' . $key . '.'
            );
        }
    }

    if (
        !filter_var($config['smtp_username'], FILTER_VALIDATE_EMAIL) ||
        !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) ||
        !filter_var($config['base_url'], FILTER_VALIDATE_URL)
    ) {
        throw new RuntimeException(
            'Email service configuration contains an invalid email or base URL.'
        );
    }

    $config['base_url'] = resolveVerificationBaseUrl(
        (string) $config['base_url']
    );

    return $config;
}

function sendVnzEmail(
    string $email,
    string $subject,
    string $htmlBody,
    string $textBody,
    ?array $config = null
): void {
    $config =
        $config ?? getVerificationMailConfig();

    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) $config['smtp_host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) $config['smtp_username'];
        $mailer->Password = preg_replace(
            '/\s+/',
            '',
            (string) $config['smtp_password']
        );
        $mailer->SMTPSecure =
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int) $config['smtp_port'];
        $mailer->CharSet =
            PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mailer->setFrom(
            (string) $config['from_email'],
            (string) $config['from_name']
        );
        $mailer->addAddress($email);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $textBody;

        if (!$mailer->send()) {
            throw new RuntimeException('SMTP did not accept the message.');
        }
    } catch (Throwable) {
        throw new RuntimeException(
            'Unable to send email using the configured SMTP service.'
        );
    }
}

function sendEmailVerification(PDO $pdo, int $userId, string $email): void
{
    $config = getVerificationMailConfig();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $pdo->beginTransaction();

    try {
        $lockStmt = $pdo->prepare("
            SELECT
                last_requested_at >=
                    DATE_SUB(NOW(), INTERVAL 1 MINUTE) AS is_rate_limited
            FROM email_verification_tokens
            WHERE user_id = ?
            FOR UPDATE
        ");
        $lockStmt->execute([$userId]);
        $existing = $lockStmt->fetch();

        if (
            $existing &&
            (int) $existing['is_rate_limited'] === 1
        ) {
            throw new RuntimeException(
                'Please wait one minute before requesting another verification email.'
            );
        }

        $storeStmt = $pdo->prepare("
            INSERT INTO email_verification_tokens (
                user_id,
                token_hash,
                expires_at,
                last_requested_at
            )
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                expires_at = VALUES(expires_at),
                last_requested_at = VALUES(last_requested_at)
        ");
        $storeStmt->execute([$userId, $tokenHash]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $verificationUrl =
        rtrim((string) $config['base_url'], '/') .
        '/backend/verify_email.php?token=' .
        rawurlencode($token);

    $verificationHtml =
        '<p>Thanks for creating a VNZ TECH seller account.</p>' .
        '<p><a href="' . htmlspecialchars(
            $verificationUrl,
            ENT_QUOTES,
            'UTF-8'
        ) . '">Verify your email address</a></p>' .
        '<p>Open the link, then click Verify my email to finish verification. Use the latest email if you requested a new link.</p>' .
        '<p>This one-time link expires in 30 minutes. If you did not ' .
        'create this account, you can ignore this email.</p>';

    $verificationText =
        "Verify your VNZ TECH email address using this one-time link:\n\n" .
        $verificationUrl .
        "\n\nOpen the link, then click Verify my email. Use the latest email if you requested a new link. This link expires in 30 minutes.";

    try {
        sendVnzEmail(
            $email,
            'Verify your VNZ TECH email address',
            $verificationHtml,
            $verificationText,
            $config
        );
    } catch (Throwable $e) {
        $invalidateStmt = $pdo->prepare("
            UPDATE email_verification_tokens
            SET
                token_hash = NULL,
                expires_at = NULL
            WHERE user_id = ?
              AND token_hash = ?
        ");
        $invalidateStmt->execute([$userId, $tokenHash]);

        throw new RuntimeException(
            'Unable to send verification email. Please check the mail configuration and try again.',
            0,
            $e
        );
    }
}
