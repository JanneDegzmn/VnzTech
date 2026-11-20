<?php

require_once __DIR__ . '/site_status.php';
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? 'status');
if (in_array($action, ['evidence', 'review'], true)) {
    vnzEnforceSiteMaintenance('auto', true);
} elseif (in_array($action, ['face_token', 'status', 'submit'], true)) {
    vnzEnforceSiteMaintenance('auto', false, true, 'submissions');
} else {
    vnzEnforceSiteMaintenance('auto', false);
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/identity_verification.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
try {
    if (vnzSubmissionFaceMode()) {
        header('Content-Type: application/json; charset=utf-8');
        if ($action !== 'face_token') {
            http_response_code(410);
            throw new DomainException('ID verification has been replaced by the selfie step at submission.');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            throw new DomainException('Your session expired. Refresh the page and try again.');
        }
        $context = (string) ($_POST['context'] ?? '');
        if (!in_array($context, ['guest', 'account'], true)) { throw new DomainException('Invalid submission context.'); }
        if ($context === 'account') { vnzIdentityUser($pdo); }
        vnzSubmissionFaceReady($pdo);
        echo json_encode(['success' => true, 'mode' => 'face_submission_v6', 'face_nonce' => vnzSubmissionFaceToken($context, true), 'expires_in' => 600, 'csrf_token' => generateCsrfToken()]);
        exit;
    }
    if ($action === 'evidence') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new DomainException('Invalid request method.');
        }
        $bytes = vnzIdentityEvidence($pdo, (int) ($_GET['attempt_id'] ?? 0), (string) ($_GET['kind'] ?? ''));
        header('Content-Type: image/jpeg');
        header('Content-Disposition: inline; filename="private-identity-image.jpg"');
        echo $bytes;
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = vnzIdentityStatus($pdo, (string) ($_GET['context'] ?? 'guest'));
    } else {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            throw new DomainException('Your session expired. Refresh the page and try again.');
        }
        if ($action === 'submit') {
            foreach (['id_image', 'selfie_image'] as $field) {
                if (!isset($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
                    throw new DomainException('Both identity images must be uploaded.');
                }
            }
            $result = vnzIdentitySubmit($pdo, (string) ($_POST['context'] ?? ''), (string) ($_POST['email'] ?? ''), $_FILES['id_image'], $_FILES['selfie_image'], ($_POST['consent'] ?? '') === '1', (string) ($_POST['consent_version'] ?? ''));
        } elseif ($action === 'review') {
            vnzIdentityReview($pdo, (int) ($_POST['attempt_id'] ?? 0), (string) ($_POST['evidence_hash'] ?? ''), (string) ($_POST['decision'] ?? ''), (string) ($_POST['reason'] ?? ''), ($_POST['review_confirm'] ?? '') === '1');
            $result = ['success' => true, 'message' => 'Identity review saved.'];
        } else {
            throw new DomainException('Unknown identity action.');
        }
    }
    $result['csrf_token'] = generateCsrfToken();
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if (http_response_code() < 400) {
        http_response_code($error instanceof DomainException ? 403 : 503);
    }
    header('Content-Type: application/json; charset=utf-8');
    try { $mode = vnzIdentityMode(); } catch (Throwable $ignored) { $mode = 'unavailable'; }
    echo json_encode(['success' => false, 'enabled' => true, 'available' => false, 'approved' => false, 'mode' => $mode, 'policy_version' => $mode === 'auto_dual_v4' ? vnzIdentityPolicy() : null, 'outcome_code' => 'service_unavailable', 'status' => 'unavailable', 'message' => $error instanceof DomainException ? $error->getMessage() : 'Face checking is temporarily unavailable. Please try again later.', 'csrf_token' => generateCsrfToken()]);
}
