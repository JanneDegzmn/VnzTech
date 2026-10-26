<?php

const VNZ_REMEMBER_COOKIE = 'vnz_remember';
const VNZ_REMEMBER_LIFETIME = 2592000;

function vnzRememberCookieOptions(int $expires): array
{
    $https = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    return [
        'expires' => $expires,
        'path' => '/VNZ-TECH',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function vnzRememberCookieSelector(): ?string
{
    $cookie = $_COOKIE[VNZ_REMEMBER_COOKIE] ?? '';
    if (
        !is_string($cookie)
        || preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/i', $cookie, $matches) !== 1
    ) {
        return null;
    }

    return strtolower($matches[1]);
}

function vnzClearRememberCookie(): void
{
    setcookie(
        VNZ_REMEMBER_COOKIE,
        '',
        vnzRememberCookieOptions(time() - 3600)
    );
    unset($_COOKIE[VNZ_REMEMBER_COOKIE]);
}

function vnzRevokeRememberLoginCookie(PDO $pdo): void
{
    $selector = vnzRememberCookieSelector();
    if ($selector !== null) {
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM remember_login_tokens WHERE selector = ?'
            );
            $stmt->execute([$selector]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '42S02') {
                throw $error;
            }
            error_log('Remember-token migration is required before token revocation.');
        }
    }
    vnzClearRememberCookie();
}

function vnzRevokeRememberLoginTokens(
    PDO $pdo,
    string $actorType,
    int $actorId
): void {
    if (!in_array($actorType, ['admin', 'seller'], true) || $actorId <= 0) {
        throw new InvalidArgumentException('Invalid remember-token identity.');
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM remember_login_tokens WHERE actor_type = ? AND actor_id = ?'
        );
        $stmt->execute([$actorType, $actorId]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') {
            throw $error;
        }
        error_log('Remember-token migration is required before token revocation.');
    }
}

function vnzIssueRememberLoginToken(
    PDO $pdo,
    string $actorType,
    int $actorId
): void {
    if (!in_array($actorType, ['admin', 'seller'], true) || $actorId <= 0) {
        throw new InvalidArgumentException('Invalid remember-token identity.');
    }

    vnzRevokeRememberLoginCookie($pdo);
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = time() + VNZ_REMEMBER_LIFETIME;
    $stmt = $pdo->prepare("
        INSERT INTO remember_login_tokens (
            selector,
            validator_hash,
            actor_type,
            actor_id,
            expires_at
        )
        VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
    ");
    $stmt->execute([
        $selector,
        hash('sha256', $validator),
        $actorType,
        $actorId,
        $expiresAt
    ]);
    $cookieValue = $selector . '.' . $validator;
    setcookie(
        VNZ_REMEMBER_COOKIE,
        $cookieValue,
        vnzRememberCookieOptions($expiresAt)
    );
    $_COOKIE[VNZ_REMEMBER_COOKIE] = $cookieValue;
}

function vnzRestoreRememberedLogin(PDO $pdo): bool
{
    if (
        !empty($_SESSION['user_logged_in'])
        || !empty($_SESSION['admin_id'])
        || !isset($_COOKIE[VNZ_REMEMBER_COOKIE])
    ) {
        return false;
    }

    $cookie = $_COOKIE[VNZ_REMEMBER_COOKIE];
    if (
        !is_string($cookie)
        || preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/i', $cookie, $matches) !== 1
    ) {
        vnzClearRememberCookie();
        return false;
    }

    $selector = strtolower($matches[1]);
    $validator = strtolower($matches[2]);
    try {
        $stmt = $pdo->prepare("
            SELECT validator_hash, actor_type, actor_id
            FROM remember_login_tokens
            WHERE selector = ?
              AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$token
            || !hash_equals(
                (string) $token['validator_hash'],
                hash('sha256', $validator)
            )
        ) {
            vnzRevokeRememberLoginCookie($pdo);
            return false;
        }

        $actorType = (string) $token['actor_type'];
        $actorId = (int) $token['actor_id'];
        if ($actorType === 'seller') {
            $identityStmt = $pdo->prepare("
                SELECT id, full_name, email
                FROM users
                WHERE id = ? AND is_active = 1
                LIMIT 1
            ");
            $identityStmt->execute([$actorId]);
            $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$identity) {
                vnzRevokeRememberLoginCookie($pdo);
                return false;
            }
        } elseif ($actorType === 'admin') {
            $identityStmt = $pdo->prepare("
                SELECT a.id, a.role_id, a.username, a.first_name, a.last_name,
                       a.email, r.name AS role_name
                FROM admins a
                INNER JOIN roles r ON r.id = a.role_id
                WHERE a.id = ? AND a.is_active = 1
                LIMIT 1
            ");
            $identityStmt->execute([$actorId]);
            $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$identity) {
                vnzRevokeRememberLoginCookie($pdo);
                return false;
            }
        } else {
            vnzRevokeRememberLoginCookie($pdo);
            return false;
        }

        session_regenerate_id(true);
        if ($actorType === 'seller') {
            clearAdminSessionState();
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = (int) $identity['id'];
            $_SESSION['user_email'] = $identity['email'];
            $_SESSION['user_name'] = $identity['full_name'];
        } else {
            clearSellerSessionState();
            $_SESSION['admin_id'] = (int) $identity['id'];
            $_SESSION['admin_role_id'] = (int) $identity['role_id'];
            $_SESSION['admin_role'] = $identity['role_name'];
            $_SESSION['admin_username'] = $identity['username'];
            $_SESSION['admin_first_name'] = $identity['first_name'];
            $_SESSION['admin_last_name'] = $identity['last_name'];
            $_SESSION['admin_email'] = $identity['email'];
        }
        $usedStmt = $pdo->prepare("
            UPDATE remember_login_tokens
            SET last_used_at = CURRENT_TIMESTAMP
            WHERE selector = ?
        ");
        $usedStmt->execute([$selector]);
        return true;
    } catch (PDOException $error) {
        error_log('Remembered login could not be restored; verify its database migration.');
        return false;
    }
}
