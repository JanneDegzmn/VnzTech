<?php

/*
|--------------------------------------------------------------------------
| CSRF Protection Helper
|--------------------------------------------------------------------------
|
| Shared by both the admin panel (session-authenticated) pages and
| endpoints. Include this AFTER session.php in any page/endpoint
| that renders a form or verifies a submitted token.
|
| Usage (page that renders a form):
|
|     require_once __DIR__ . '/../../backend/csrf.php';
|     $csrfToken = generateCsrfToken();
|     ...
|     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
|
| Usage (endpoint that receives the form):
|
|     require_once __DIR__ . '/csrf.php';
|
|     if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
|         http_response_code(419);
|         exit('Your session has expired. Please refresh and try again.');
|     }
|
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/**
 * Returns the current CSRF token for this session, generating
 * one on first use. The same token is reused for the lifetime
 * of the session so multiple forms on the same page (or across
 * page loads) all validate against it.
 */
function generateCsrfToken(): string
{
    if (
        empty($_SESSION['csrf_token'])
        ||
        !is_string($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

    }

    return $_SESSION['csrf_token'];
}


/**
 * Verifies a submitted token against the session's token using
 * a timing-safe comparison. Returns false (never throws) so
 * callers can respond with a clean 419 instead of a fatal error.
 */
function verifyCsrfToken($submittedToken): bool
{
    if (
        empty($_SESSION['csrf_token'])
        ||
        !is_string($_SESSION['csrf_token'])
    ) {
        return false;
    }

    if (
        !is_string($submittedToken)
        ||
        $submittedToken === ''
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $submittedToken
    );
}