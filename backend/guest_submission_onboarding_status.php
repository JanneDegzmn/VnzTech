<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'submissions');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/guest_submission_onboarding.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function vnzGuestOnboardingResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

if (empty($_SESSION['user_logged_in']) || (int) ($_SESSION['user_id'] ?? 0) <= 0) {
    vnzGuestOnboardingResponse(['success' => false, 'message' => 'Your session has expired. Please sign in again.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    vnzGuestOnboardingResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if ($method === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    vnzGuestOnboardingResponse(['success' => false, 'message' => 'Please refresh the page and try again.'], 419);
}

try {
    $userStmt = $pdo->prepare('SELECT id, email, email_verified_at, is_active FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([(int) $_SESSION['user_id']]);
    $seller = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$seller || (int) $seller['is_active'] !== 1) {
        vnzGuestOnboardingResponse(['success' => false, 'message' => 'This seller account is unavailable.'], 403);
    }

    $state = vnzGuestSubmissionOnboardingStatus($pdo, $_SESSION, $seller);
    if ($method === 'POST' && $state !== null) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'dismiss') {
            if ($state['guest_flow']) {
                $_SESSION['guest_submission_onboarding']['auto_show'] = false;
                if ($state['phase'] !== 'verify' && ($_POST['presented_phase'] ?? '') === $state['phase']) {
                    $_SESSION['guest_submission_onboarding']['verified_notice_seen'] = true;
                }
            }
            vnzDismissEmailVerificationFeedback($_SESSION, (int) $seller['id'], (string) ($_POST['feedback_id'] ?? ''));
            $state['auto_show'] = false;
        } elseif ($action === 'acknowledge' && $state['phase'] !== 'verify') {
            if ($state['guest_flow']) unset($_SESSION['guest_submission_onboarding']);
            vnzDismissEmailVerificationFeedback($_SESSION, (int) $seller['id'], (string) ($_POST['feedback_id'] ?? ''));
            $state = null;
        } else {
            vnzGuestOnboardingResponse(['success' => false, 'message' => 'Invalid onboarding action.'], 422);
        }
    }

    // No guest reference, contact details, item data, or private submission IDs.
    vnzGuestOnboardingResponse([
        'success' => true,
        'available' => $state !== null,
        'phase' => $state['phase'] ?? null,
        'auto_show' => $state['auto_show'] ?? false,
        'last_sent_at' => $state['last_sent_at'] ?? 0,
        'guest_flow' => $state['guest_flow'] ?? false,
        'verification_result' => $state['verification_result'] ?? null,
        'feedback_id' => $state['feedback_id'] ?? ''
    ]);
} catch (Throwable $error) {
    error_log('Guest submission onboarding status could not be loaded.');
    vnzGuestOnboardingResponse(['success' => false, 'message' => 'Unable to check your submission right now. Please try again.'], 503);
}
