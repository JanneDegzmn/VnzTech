<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Manage Trade Items (Admin)
|--------------------------------------------------------------------------
|
| Handles create / update / delete / toggle / requirement operations for
| the trade_items and trade_requirements tables.
| POST only. Session-gated + CSRF-protected + permission-gated.
|
*/

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function jsonError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

function getSanitizedText(?string $value, int $maxLength = 255): string
{
    $text = trim((string) ($value ?? ''));

    return mb_substr($text, 0, $maxLength);
}

function normalizeBoolean($value): int
{
    return isset($value) && $value !== '' && (string) $value !== '0' ? 1 : 0;
}

function validateItemPayload(array $post): array
{
    $errors = [];

    $name = getSanitizedText($post['name'] ?? '', 120);
    $condition = getSanitizedText($post['condition'] ?? '', 120);
    $description = trim((string) ($post['description'] ?? ''));
    $sortOrder = isset($post['sort_order']) ? (int) $post['sort_order'] : 0;

    if ($name === '') {
        $errors[] = 'Trade item name is required.';
    }

    if ($condition === '') {
        $errors[] = 'Condition is required.';
    }

    if (mb_strlen($description) > 2000) {
        $errors[] = 'Description is too long.';
    }

    if ($sortOrder < 0 || $sortOrder > 999999) {
        $errors[] = 'Sort order must be a valid number.';
    }

    return [
        'errors' => $errors,
        'name' => $name,
        'condition' => $condition,
        'description' => $description,
        'sort_order' => $sortOrder,
        'is_featured' => normalizeBoolean($post['is_featured'] ?? 0),
        'is_active' => normalizeBoolean($post['is_active'] ?? 1),
    ];
}

function validateRequirementPayload(array $post): array
{
    $errors = [];

    $allowedRequirementTypes = ['phone', 'scrap_board'];

    $requirementType = trim((string) ($post['requirement_type'] ?? ''));
    $quantity = isset($post['quantity']) ? (int) $post['quantity'] : 0;
    $storage = getSanitizedText($post['storage'] ?? '', 100);
    $description = trim((string) ($post['description'] ?? ''));
    $sortOrder = isset($post['sort_order']) ? (int) $post['sort_order'] : 0;

    if (!in_array($requirementType, $allowedRequirementTypes, true)) {
        $errors[] = 'Requirement type is invalid.';
    }

    if ($quantity < 1 || $quantity > 99999) {
        $errors[] = 'Quantity must be greater than zero.';
    }

    if ($storage === '') {
        $errors[] = 'Storage or classification is required.';
    }

    if (mb_strlen($description) > 500) {
        $errors[] = 'Requirement description is too long.';
    }

    if ($sortOrder < 0 || $sortOrder > 999999) {
        $errors[] = 'Requirement sort order must be a valid number.';
    }

    return [
        'errors' => $errors,
        'requirement_type' => $requirementType,
        'quantity' => $quantity,
        'storage' => $storage,
        'description' => $description,
        'sort_order' => $sortOrder,
    ];
}

function normalizeRequirementsPayload($raw): array
{
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return [];
    }

    $requirements = [];

    foreach ($decoded as $index => $requirement) {
        if (!is_array($requirement)) {
            continue;
        }

        $requirements[] = [
            'id' => isset($requirement['id']) ? (int) $requirement['id'] : null,
            'requirement_type' => trim((string) ($requirement['requirement_type'] ?? '')),
            'quantity' => isset($requirement['quantity']) ? (int) $requirement['quantity'] : 0,
            'storage' => getSanitizedText($requirement['storage'] ?? '', 100),
            'description' => trim((string) ($requirement['description'] ?? '')),
            'sort_order' => isset($requirement['sort_order']) ? (int) $requirement['sort_order'] : ($index + 1),
        ];
    }

    return $requirements;
}

function handleTradeImageUpload($file, ?string $existingImagePath = null): ?string
{
    if (!isset($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingImagePath;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The trade item image upload failed.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid trade item image upload.');
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Trade item image must not exceed 5MB.');
    }

    $mimeType = mime_content_type($file['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $uploadDirectory = __DIR__ . '/../uploads/trade_items';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Unable to create the trade image folder.');
        }
    }

    $extension = $allowedMimeTypes[$mimeType];
    $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $uploadDirectory . '/' . $randomName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save the trade item image.');
    }

    if ($existingImagePath && $existingImagePath !== '') {
        $existingAbsolutePath = __DIR__ . '/../' . $existingImagePath;
        if (is_file($existingAbsolutePath)) {
            @unlink($existingAbsolutePath);
        }
    }

    return 'uploads/trade_items/' . $randomName;
}

requireAdminLogin();

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    jsonError('Admin session is invalid. Please log in again.', 401);
}

if (!hasPermission('manage_trades')) {
    jsonError('You do not have permission to manage trades.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 405);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    jsonError('Invalid session token. Please refresh the page and try again.', 419);
}

$action = trim((string) ($_POST['action'] ?? ''));

try {
    if ($action === 'create_item') {
        $itemData = validateItemPayload($_POST);

        if (!empty($itemData['errors'])) {
            jsonError(implode(' ', $itemData['errors']), 422);
        }

        $requirements = normalizeRequirementsPayload($_POST['requirements'] ?? '[]');

        if ($requirements === []) {
            jsonError('At least one requirement is required.', 422);
        }

        foreach ($requirements as $index => $requirement) {
            $requirementData = validateRequirementPayload($requirement);
            if (!empty($requirementData['errors'])) {
                jsonError('Requirement ' . ($index + 1) . ': ' . implode(' ', $requirementData['errors']), 422);
            }
        }

        $imagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = handleTradeImageUpload($_FILES['image']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO trade_items (
                name,
                `condition`,
                description,
                image_path,
                is_featured,
                is_active,
                sort_order,
                created_by_admin_id,
                updated_by_admin_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $itemData['name'],
            $itemData['condition'],
            $itemData['description'],
            $imagePath,
            $itemData['is_featured'],
            $itemData['is_active'],
            $itemData['sort_order'],
            $currentAdmin['id'],
            $currentAdmin['id']
        ]);

        $tradeItemId = (int) $pdo->lastInsertId();

        $requirementInsert = $pdo->prepare("
            INSERT INTO trade_requirements (
                trade_item_id,
                requirement_type,
                quantity,
                storage,
                description,
                sort_order
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($requirements as $index => $requirement) {
            $normalized = validateRequirementPayload($requirement);
            $requirementInsert->execute([
                $tradeItemId,
                $normalized['requirement_type'],
                $normalized['quantity'],
                $normalized['storage'],
                $normalized['description'],
                isset($requirement['sort_order']) ? (int) $requirement['sort_order'] : ($index + 1),
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Trade item created successfully.',
            'id' => $tradeItemId
        ]);
        exit;
    }

    if ($action === 'update_item') {
        $tradeItemId = (int) ($_POST['id'] ?? 0);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        $existingStmt = $pdo->prepare("SELECT id, image_path FROM trade_items WHERE id = ? LIMIT 1");
        $existingStmt->execute([$tradeItemId]);
        $existingItem = $existingStmt->fetch();

        if (!$existingItem) {
            jsonError('Trade item not found.', 404);
        }

        $itemData = validateItemPayload($_POST);

        if (!empty($itemData['errors'])) {
            jsonError(implode(' ', $itemData['errors']), 422);
        }

        $requirements = normalizeRequirementsPayload($_POST['requirements'] ?? '[]');

        if ($requirements === []) {
            jsonError('At least one requirement is required.', 422);
        }

        foreach ($requirements as $index => $requirement) {
            $requirementData = validateRequirementPayload($requirement);
            if (!empty($requirementData['errors'])) {
                jsonError('Requirement ' . ($index + 1) . ': ' . implode(' ', $requirementData['errors']), 422);
            }
        }

        $imagePath = $existingItem['image_path'];

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = handleTradeImageUpload($_FILES['image'], $existingItem['image_path']);
        }

        $updateStmt = $pdo->prepare("
            UPDATE trade_items
            SET
                name = ?,
                `condition` = ?,
                description = ?,
                image_path = ?,
                is_featured = ?,
                is_active = ?,
                sort_order = ?,
                updated_by_admin_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateStmt->execute([
            $itemData['name'],
            $itemData['condition'],
            $itemData['description'],
            $imagePath,
            $itemData['is_featured'],
            $itemData['is_active'],
            $itemData['sort_order'],
            $currentAdmin['id'],
            $tradeItemId
        ]);

        $deleteExistingRequirements = $pdo->prepare("DELETE FROM trade_requirements WHERE trade_item_id = ?");
        $deleteExistingRequirements->execute([$tradeItemId]);

        $insertNewRequirement = $pdo->prepare("
            INSERT INTO trade_requirements (
                trade_item_id,
                requirement_type,
                quantity,
                storage,
                description,
                sort_order
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($requirements as $index => $requirement) {
            $normalized = validateRequirementPayload($requirement);
            $insertNewRequirement->execute([
                $tradeItemId,
                $normalized['requirement_type'],
                $normalized['quantity'],
                $normalized['storage'],
                $normalized['description'],
                isset($requirement['sort_order']) ? (int) $requirement['sort_order'] : ($index + 1),
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Trade item updated successfully.'
        ]);
        exit;
    }

    if ($action === 'delete_item') {
        $tradeItemId = (int) ($_POST['id'] ?? 0);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        $stmt = $pdo->prepare("SELECT image_path FROM trade_items WHERE id = ? LIMIT 1");
        $stmt->execute([$tradeItemId]);
        $tradeItem = $stmt->fetch();

        if (!$tradeItem) {
            jsonError('Trade item not found.', 404);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM trade_items WHERE id = ?");
        $deleteStmt->execute([$tradeItemId]);

        if ($tradeItem['image_path'] && $tradeItem['image_path'] !== '') {
            $absolutePath = __DIR__ . '/../' . $tradeItem['image_path'];
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Trade item deleted successfully.'
        ]);
        exit;
    }

    if ($action === 'toggle_active') {
        $tradeItemId = (int) ($_POST['id'] ?? 0);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        $stmt = $pdo->prepare("
            UPDATE trade_items
            SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                updated_by_admin_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([$currentAdmin['id'], $tradeItemId]);

        echo json_encode([
            'success' => true,
            'message' => 'Trade item status updated.'
        ]);
        exit;
    }

    if ($action === 'toggle_featured') {
        $tradeItemId = (int) ($_POST['id'] ?? 0);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        $stmt = $pdo->prepare("
            UPDATE trade_items
            SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END,
                updated_by_admin_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([$currentAdmin['id'], $tradeItemId]);

        echo json_encode([
            'success' => true,
            'message' => 'Trade item featured status updated.'
        ]);
        exit;
    }

    if ($action === 'update_sort') {
        $tradeItemId = (int) ($_POST['id'] ?? 0);
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        if ($sortOrder < 0 || $sortOrder > 999999) {
            jsonError('Sort order must be a valid number.', 422);
        }

        $stmt = $pdo->prepare("
            UPDATE trade_items
            SET sort_order = ?,
                updated_by_admin_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([$sortOrder, $currentAdmin['id'], $tradeItemId]);

        echo json_encode([
            'success' => true,
            'message' => 'Trade item sort order updated.'
        ]);
        exit;
    }

    if ($action === 'create_requirement') {
        $tradeItemId = (int) ($_POST['trade_item_id'] ?? 0);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        $check = $pdo->prepare("SELECT id FROM trade_items WHERE id = ? LIMIT 1");
        $check->execute([$tradeItemId]);

        if (!$check->fetch()) {
            jsonError('Trade item not found.', 404);
        }

        $data = validateRequirementPayload($_POST);

        if (!empty($data['errors'])) {
            jsonError(implode(' ', $data['errors']), 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO trade_requirements (
                trade_item_id,
                requirement_type,
                quantity,
                storage,
                description,
                sort_order
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tradeItemId,
            $data['requirement_type'],
            $data['quantity'],
            $data['storage'],
            $data['description'],
            $data['sort_order']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Requirement added successfully.',
            'id' => (int) $pdo->lastInsertId()
        ]);
        exit;
    }

    if ($action === 'update_requirement') {
        $requirementId = (int) ($_POST['id'] ?? 0);

        if ($requirementId <= 0) {
            jsonError('Invalid requirement ID.', 422);
        }

        $check = $pdo->prepare("SELECT trade_item_id FROM trade_requirements WHERE id = ? LIMIT 1");
        $check->execute([$requirementId]);
        $requirement = $check->fetch();

        if (!$requirement) {
            jsonError('Requirement not found.', 404);
        }

        $data = validateRequirementPayload($_POST);

        if (!empty($data['errors'])) {
            jsonError(implode(' ', $data['errors']), 422);
        }

        $stmt = $pdo->prepare("
            UPDATE trade_requirements
            SET
                requirement_type = ?,
                quantity = ?,
                storage = ?,
                description = ?,
                sort_order = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['requirement_type'],
            $data['quantity'],
            $data['storage'],
            $data['description'],
            $data['sort_order'],
            $requirementId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Requirement updated successfully.'
        ]);
        exit;
    }

    if ($action === 'delete_requirement') {
        $requirementId = (int) ($_POST['id'] ?? 0);

        if ($requirementId <= 0) {
            jsonError('Invalid requirement ID.', 422);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM trade_requirements WHERE id = ?");
        $deleteStmt->execute([$requirementId]);

        echo json_encode([
            'success' => true,
            'message' => 'Requirement removed successfully.'
        ]);
        exit;
    }

    if ($action === 'reorder_requirements') {
        $tradeItemId = (int) ($_POST['trade_item_id'] ?? 0);
        $decoded = json_decode((string) ($_POST['order'] ?? '[]'), true);

        if ($tradeItemId <= 0) {
            jsonError('Invalid trade item ID.', 422);
        }

        if (!is_array($decoded)) {
            jsonError('Requirement order data is invalid.', 422);
        }

        $ids = [];
        foreach ($decoded as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            jsonError('No valid requirement order was provided.', 422);
        }

        $check = $pdo->prepare("SELECT id FROM trade_requirements WHERE trade_item_id = ?");
        $check->execute([$tradeItemId]);
        $existingIds = $check->fetchAll(PDO::FETCH_COLUMN, 0);

        $allowed = array_flip($existingIds);

        foreach ($ids as $position => $id) {
            if (!isset($allowed[$id])) {
                jsonError('One of the requirement IDs is invalid.', 422);
            }

            $updateStmt = $pdo->prepare("UPDATE trade_requirements SET sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND trade_item_id = ?");
            $updateStmt->execute([$position + 1, $id, $tradeItemId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Requirement order updated successfully.'
        ]);
        exit;
    }

    jsonError('Unknown trade action.', 400);
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}
