<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

function jsonTradeResponse(bool $success, string $message, int $httpCode = 200, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

function getCurrentUserTrade(): ?array
{
    global $pdo;

    if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, mobile, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        return null;
    }

    return $user;
}

function generateTradeRequestReference(PDO $pdo): string
{
    $datePrefix = 'TR-' . date('Ymd') . '-';

    for ($attempt = 0; $attempt < 100; $attempt++) {
        $reference = $datePrefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM trade_requests WHERE request_reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        if (!$stmt->fetch()) {
            return $reference;
        }
    }

    throw new RuntimeException('Unable to generate a unique trade request reference.');
}

function validateTradeRequirements(PDO $pdo, int $tradeItemId): void
{
    $stmt = $pdo->prepare("
        SELECT requirement_type, quantity, storage
        FROM trade_requirements
        WHERE trade_item_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$tradeItemId]);
    $requirements = $stmt->fetchAll();

    if (!$requirements) {
        throw new RuntimeException('This trade item has no requirements configured.');
    }

    foreach ($requirements as $requirement) {
        if (!in_array((string) $requirement['requirement_type'], ['phone', 'scrap_board'], true)) {
            throw new RuntimeException('This trade item has an invalid requirement type.');
        }
        if ((int) $requirement['quantity'] < 1 || trim((string) $requirement['storage']) === '') {
            throw new RuntimeException('This trade item has an invalid requirement.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonTradeResponse(false, 'Method not allowed.', 405);
}

$action = strtolower(trim((string) ($_POST['action'] ?? $_GET['action'] ?? '')));

if ($action === 'admin_update_status') {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/admin_activity.php';

    if (!isAdminLoggedIn()) {
        jsonTradeResponse(false, 'Admin session expired.', 401);
    }
    if (!hasPermission('manage_trades')) {
        jsonTradeResponse(false, 'You do not have permission to manage trade requests.', 403);
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        jsonTradeResponse(false, 'Your session has expired. Please refresh the page and try again.', 419);
    }

    $tradeRequestId = (int) ($_POST['request_id'] ?? 0);
    $statusAction = strtolower(trim((string) ($_POST['status_action'] ?? '')));
    $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
    $adminUser = getCurrentAdmin();

    if ($tradeRequestId <= 0 || !$adminUser || !in_array($statusAction, ['approve', 'reject', 'cancel', 'complete'], true)) {
        jsonTradeResponse(false, 'Invalid trade request action.', 422);
    }
    if (mb_strlen($adminNotes) > 5000) {
        jsonTradeResponse(false, 'Admin notes must not exceed 5000 characters.', 422);
    }

    try {
        $pdo->beginTransaction();
        $requestStmt = $pdo->prepare('SELECT id, status FROM trade_requests WHERE id = ? FOR UPDATE');
        $requestStmt->execute([$tradeRequestId]);
        $request = $requestStmt->fetch();

        if (!$request) {
            throw new RuntimeException('Trade request not found.');
        }

        $requestStatus = (string) $request['status'];
        $now = date('Y-m-d H:i:s');

        if ($statusAction === 'approve') {
            if ($requestStatus !== 'PENDING') {
                throw new RuntimeException('Only pending requests can be approved.');
            }
            $update = $pdo->prepare("
                UPDATE trade_requests
                SET status = 'APPROVED', reviewed_by_admin_id = ?, reviewed_at = ?,
                    approved_at = ?, updated_at = ?, admin_notes = ?
                WHERE id = ?
            ");
            $update->execute([(int) $adminUser['id'], $now, $now, $now, $adminNotes, $tradeRequestId]);
        } elseif ($statusAction === 'reject') {
            if ($requestStatus !== 'PENDING') {
                throw new RuntimeException('Only pending requests can be rejected.');
            }
            $update = $pdo->prepare("
                UPDATE trade_requests
                SET status = 'REJECTED', reviewed_by_admin_id = ?, reviewed_at = ?,
                    rejected_at = ?, updated_at = ?, admin_notes = ?
                WHERE id = ?
            ");
            $update->execute([(int) $adminUser['id'], $now, $now, $now, $adminNotes, $tradeRequestId]);
        } elseif ($statusAction === 'cancel') {
            if ($requestStatus !== 'PENDING') {
                throw new RuntimeException('Only pending requests can be cancelled.');
            }
            $update = $pdo->prepare("
                UPDATE trade_requests
                SET status = 'CANCELLED', reviewed_by_admin_id = ?, reviewed_at = ?,
                    cancelled_at = ?, updated_at = ?, admin_notes = ?
                WHERE id = ?
            ");
            $update->execute([(int) $adminUser['id'], $now, $now, $now, $adminNotes, $tradeRequestId]);
        } else {
            if ($requestStatus !== 'APPROVED') {
                throw new RuntimeException('Only approved requests can be completed.');
            }
            $update = $pdo->prepare("
                UPDATE trade_requests
                SET status = 'COMPLETED', completed_at = ?, updated_at = ?, admin_notes = ?
                WHERE id = ?
            ");
            $update->execute([$now, $now, $adminNotes, $tradeRequestId]);
        }

        logAdminActivity(
            (int) $adminUser['id'],
            'trade_request.' . $statusAction,
            'Trade request #' . $tradeRequestId . ' status changed from ' . $requestStatus . '.'
        );
        $pdo->commit();
        jsonTradeResponse(true, 'Trade request status updated successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonTradeResponse(false, $e->getMessage() ?: 'Unable to update the trade request status.', 409);
    }
}

if ($action !== 'create_request') {
    jsonTradeResponse(false, 'Unsupported trade request action.', 400);
}

$currentUser = getCurrentUserTrade();
if (!$currentUser) {
    jsonTradeResponse(false, 'You must be logged in to make a trade request.', 401);
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonTradeResponse(false, 'Your session has expired. Please refresh the page and try again.', 419);
}

$tradeItemId = (int) ($_POST['trade_item_id'] ?? 0);
if ($tradeItemId <= 0) {
    jsonTradeResponse(false, 'Invalid trade item ID.', 422);
}

$tradeItemStmt = $pdo->prepare('SELECT id, is_active FROM trade_items WHERE id = ? LIMIT 1');
$tradeItemStmt->execute([$tradeItemId]);
$tradeItem = $tradeItemStmt->fetch();
if (!$tradeItem) {
    jsonTradeResponse(false, 'Trade item not found.', 404);
}
if ((int) $tradeItem['is_active'] !== 1) {
    jsonTradeResponse(false, 'This trade item is not currently available.', 409);
}

try {
    validateTradeRequirements($pdo, $tradeItemId);
} catch (RuntimeException $e) {
    jsonTradeResponse(false, $e->getMessage(), 422);
}

try {
    $pdo->beginTransaction();
    $reference = generateTradeRequestReference($pdo);
    $insertRequest = $pdo->prepare("
        INSERT INTO trade_requests (
            user_id, trade_item_id, request_reference, status, admin_notes,
            reviewed_by_admin_id, requested_at, reviewed_at, approved_at,
            completed_at, rejected_at, cancelled_at, created_at, updated_at
        ) VALUES (
            ?, ?, ?, 'PENDING', NULL, NULL, NOW(), NULL, NULL,
            NULL, NULL, NULL, NOW(), NOW()
        )
    ");
    $insertRequest->execute([(int) $currentUser['id'], $tradeItemId, $reference]);
    $tradeRequestId = (int) $pdo->lastInsertId();
    $pdo->commit();

    jsonTradeResponse(true, 'Trade request submitted successfully.', 201, [
        'trade_request_id' => $tradeRequestId,
        'reference' => $reference,
        'status' => 'PENDING',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonTradeResponse(false, 'Unable to create your trade request. Please try again.', 500);
}