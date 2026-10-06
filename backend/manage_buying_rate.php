<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Manage Buying Rate (Admin)
|--------------------------------------------------------------------------
|
| Handles create / update / toggle-active for the buying_rates table.
| POST only. Session-gated + CSRF-protected + permission-gated.
|
*/

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_activity.php';

requireAdminLogin();

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

if (!hasPermission('manage_rates')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to manage buying rates.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Method Check
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Check
|--------------------------------------------------------------------------
*/

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session token. Please refresh the page and try again.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedRateTypes = ['phone', 'bagsakan', 'non_code_board'];
$allowedUnits = ['piece', 'kg', 'gram', 'set', 'lot'];

$action = trim($_POST['action'] ?? '');


/*
|--------------------------------------------------------------------------
| Shared Validation Helper
|--------------------------------------------------------------------------
*/

function validateRatePayload(array $post, array $allowedRateTypes): array
{
    $errors = [];

    $rateType = trim($post['rate_type'] ?? '');
    $category = trim($post['category'] ?? '');
    $description = trim($post['description'] ?? '');
    $price = $post['price'] ?? null;
    $unit = trim($post['unit'] ?? 'piece');

    if (!in_array($rateType, $allowedRateTypes, true)) {
        $errors[] = 'Invalid rate type.';
    }

    if ($category === '' || mb_strlen($category) > 100) {
        $errors[] = 'Category is required (max 100 characters).';
    }

    if (mb_strlen($description) > 255) {
        $errors[] = 'Description is too long (max 255 characters).';
    }

    if (
        !is_numeric($price)
        || (float) $price < 0
        || (float) $price > 99999999.99
    ) {
        $errors[] = 'Price must be a valid positive number.';
    }

    if ($unit === '' || mb_strlen($unit) > 30) {
        $unit = 'piece';
    }

    return [
        'errors' => $errors,
        'rate_type' => $rateType,
        'category' => $category,
        'description' => $description !== '' ? $description : null,
        'price' => is_numeric($price) ? round((float) $price, 2) : 0,
        'unit' => $unit
    ];
}


/*
|--------------------------------------------------------------------------
| ACTION: Create
|--------------------------------------------------------------------------
*/

if ($action === 'create') {

    $data = validateRatePayload($_POST, $allowedRateTypes);

    if (!empty($data['errors'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $data['errors'])
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO buying_rates (
            rate_type,
            category,
            description,
            price,
            unit,
            is_active,
            created_by_admin_id,
            updated_by_admin_id
        ) VALUES (
            ?, ?, ?, ?, ?, 1, ?, ?
        )
    ");

    $stmt->execute([
        $data['rate_type'],
        $data['category'],
        $data['description'],
        $data['price'],
        $data['unit'],
        $currentAdmin['id'],
        $currentAdmin['id']
    ]);

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.created', 'Buying rate #' . (int) $pdo->lastInsertId() . ' created.');
    echo json_encode([
        'success' => true,
        'message' => 'Buying rate added successfully.',
        'id' => (int) $pdo->lastInsertId()
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: Update
|--------------------------------------------------------------------------
*/

if ($action === 'update') {

    $rateId = (int) ($_POST['id'] ?? 0);

    if ($rateId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid rate ID.'
        ]);
        exit;
    }

    $data = validateRatePayload($_POST, $allowedRateTypes);

    if (!empty($data['errors'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $data['errors'])
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE buying_rates
        SET
            rate_type = ?,
            category = ?,
            description = ?,
            price = ?,
            unit = ?,
            updated_by_admin_id = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['rate_type'],
        $data['category'],
        $data['description'],
        $data['price'],
        $data['unit'],
        $currentAdmin['id'],
        $rateId
    ]);

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.updated', 'Buying rate #' . $rateId . ' updated.');
    echo json_encode([
        'success' => true,
        'message' => 'Buying rate updated successfully.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: Toggle Active
|--------------------------------------------------------------------------
*/

if ($action === 'toggle') {

    $rateId = (int) ($_POST['id'] ?? 0);

    if ($rateId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid rate ID.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT is_active
        FROM buying_rates
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$rateId]);
    $rate = $stmt->fetch();

    if (!$rate) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Buying rate not found.'
        ]);
        exit;
    }

    $newStatus = $rate['is_active'] ? 0 : 1;

    $update = $pdo->prepare("
        UPDATE buying_rates
        SET
            is_active = ?,
            updated_by_admin_id = ?
        WHERE id = ?
    ");

    $update->execute([
        $newStatus,
        $currentAdmin['id'],
        $rateId
    ]);

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.active_toggled', 'Buying rate #' . $rateId . ' active status changed.');
    echo json_encode([
        'success' => true,
        'message' => $newStatus
            ? 'Rate activated.'
            : 'Rate deactivated.',
        'is_active' => (bool) $newStatus
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: Archive (soft delete)
|--------------------------------------------------------------------------
*/

if ($action === 'archive') {

    $rateId = (int) ($_POST['id'] ?? 0);

    if ($rateId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid rate ID.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE buying_rates
        SET
            archived_at = NOW(),
            archived_by_admin_id = ?
        WHERE id = ?
          AND archived_at IS NULL
    ");

    $stmt->execute([
        $currentAdmin['id'],
        $rateId
    ]);

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.archived', 'Buying rate #' . $rateId . ' archived.');

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rate not found or already archived.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rate moved to archive.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: Restore (unarchive)
|--------------------------------------------------------------------------
*/

if ($action === 'restore') {

    $rateId = (int) ($_POST['id'] ?? 0);

    if ($rateId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid rate ID.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE buying_rates
        SET
            archived_at = NULL,
            archived_by_admin_id = NULL
        WHERE id = ?
          AND archived_at IS NOT NULL
    ");

    $stmt->execute([$rateId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rate not found in archive.'
        ]);
        exit;
    }

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.restored', 'Buying rate #' . $rateId . ' restored.');
    echo json_encode([
        'success' => true,
        'message' => 'Rate restored.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: Delete Permanently
|--------------------------------------------------------------------------
|
| Safety: only rows already archived can be hard-deleted, so a rate
| must always pass through the archive step first.
|
*/

if ($action === 'delete_permanent') {

    $rateId = (int) ($_POST['id'] ?? 0);

    if ($rateId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid rate ID.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM buying_rates
        WHERE id = ?
          AND archived_at IS NOT NULL
    ");

    $stmt->execute([$rateId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rate not found in archive. It must be archived before it can be permanently deleted.'
        ]);
        exit;
    }

    logAdminActivity((int) $currentAdmin['id'], 'buying_rate.deleted', 'Buying rate #' . $rateId . ' permanently deleted.');
    echo json_encode([
        'success' => true,
        'message' => 'Rate permanently deleted.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Unknown Action
|--------------------------------------------------------------------------
*/

http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Unknown action.'
]);