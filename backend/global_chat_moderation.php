<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_activity.php';
require_once __DIR__ . '/global_chat_moderation_service.php';

function globalChatModerationResponse(
    bool $success,
    string $message,
    int $status = 200,
    array $data = []
): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => (object) $data],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    requireAdminLogin();
    if (!hasPermission('manage_settings')) {
        globalChatModerationResponse(false, 'You do not have permission to manage Global Chat moderation.', 403);
    }

    global $pdo;
    if (!globalChatModerationSchemaAvailable($pdo)) {
        globalChatModerationResponse(
            false,
            'Global Chat moderation is not configured yet. Apply the Global Chat moderation migration first.',
            503
        );
    }

    $adminId = (int) $_SESSION['admin_id'];
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') {
        $rulesStmt = $pdo->query("
            SELECT
                id,
                rule_text,
                match_type,
                category,
                severity,
                action,
                enabled,
                created_at,
                updated_at
            FROM global_chat_moderation_rules
            ORDER BY category, rule_text
        ");
        $casesStmt = $pdo->query("
            SELECT
                moderation_case.id,
                moderation_case.message_id,
                moderation_case.matched_rule,
                moderation_case.category,
                moderation_case.severity,
                moderation_case.action,
                moderation_case.status,
                moderation_case.message_snapshot,
                moderation_case.sender_type,
                moderation_case.sender_id,
                moderation_case.decision_reason,
                moderation_case.created_at,
                moderation_case.reviewed_at,
                m.content AS current_content,
                COALESCE(
                    NULLIF(TRIM(u.full_name), ''),
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    IF(moderation_case.sender_type = 'guest', 'Guest Seller', 'Admin')
                ) AS sender_name,
                TRIM(CONCAT(reviewer.first_name, ' ', reviewer.last_name)) AS reviewer_name
            FROM global_chat_moderation_cases moderation_case
            LEFT JOIN users u
                ON moderation_case.sender_type = 'seller'
               AND u.id = moderation_case.sender_id
            LEFT JOIN admins a
                ON moderation_case.sender_type = 'admin'
               AND a.id = moderation_case.sender_id
            LEFT JOIN admins reviewer
                ON reviewer.id = moderation_case.reviewer_admin_id
            LEFT JOIN messages m
                ON m.id = moderation_case.message_id
            ORDER BY
                CASE moderation_case.status WHEN 'pending' THEN 0 ELSE 1 END,
                moderation_case.created_at DESC,
                moderation_case.id DESC
        ");
        globalChatModerationResponse(true, 'Global Chat moderation loaded.', 200, [
            'rules' => $rulesStmt->fetchAll(PDO::FETCH_ASSOC),
            'cases' => $casesStmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        globalChatModerationResponse(false, 'Method not allowed.', 405);
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        globalChatModerationResponse(false, 'Your session has expired. Please refresh and try again.', 403);
    }

    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        globalChatModerationResponse(false, 'Invalid moderation action.', 422);
    }

    if ($action === 'save_rule') {
        $ruleTextInput = $_POST['rule_text'] ?? null;
        $matchType = $_POST['match_type'] ?? '';
        $categoryInput = $_POST['category'] ?? null;
        $severity = $_POST['severity'] ?? '';
        $ruleAction = $_POST['rule_action'] ?? '';
        $enabledInput = $_POST['enabled'] ?? null;
        $ruleIdValue = $_POST['rule_id'] ?? '';
        if (
            !is_string($ruleTextInput)
            || !is_string($matchType)
            || !is_string($categoryInput)
            || !is_string($severity)
            || !is_string($ruleAction)
            || !is_string($enabledInput)
            || (!is_string($ruleIdValue) && !is_int($ruleIdValue))
        ) {
            globalChatModerationResponse(false, 'Invalid moderation rule fields.', 422);
        }
        $ruleText = trim($ruleTextInput);
        $category = trim($categoryInput);
        $enabled = filter_var($enabledInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $ruleId = $ruleIdValue === ''
            ? null
            : filter_var($ruleIdValue, FILTER_VALIDATE_INT);

        if (
            $ruleText === ''
            || mb_strlen($ruleText, 'UTF-8') > 191
            || globalChatModerationNormalize($ruleText) === ''
            || !in_array($matchType, ['word', 'phrase'], true)
            || $category === ''
            || mb_strlen($category, 'UTF-8') > 80
            || !in_array($severity, ['low', 'medium', 'high', 'critical'], true)
            || !in_array($ruleAction, ['warn', 'block', 'review'], true)
            || $enabled === null
            || ($ruleId !== null && ($ruleId === false || $ruleId <= 0))
        ) {
            globalChatModerationResponse(false, 'Check the rule text, category, match type, severity, and action.', 422);
        }
        if (
            $matchType === 'word'
            && str_contains(globalChatModerationNormalize($ruleText), ' ')
        ) {
            globalChatModerationResponse(false, 'A whole-word rule must contain only one word.', 422);
        }

        if ($ruleId !== null) {
            $existsStmt = $pdo->prepare('SELECT id FROM global_chat_moderation_rules WHERE id = ? LIMIT 1');
            $existsStmt->execute([$ruleId]);
            if (!$existsStmt->fetchColumn()) {
                globalChatModerationResponse(false, 'Moderation rule not found.', 404);
            }
        }

        $pdo->beginTransaction();
        try {
            if ($ruleId === null) {
                $stmt = $pdo->prepare("
                    INSERT INTO global_chat_moderation_rules (
                        rule_text,
                        match_type,
                        category,
                        severity,
                        action,
                        enabled,
                        created_by_admin_id,
                        updated_by_admin_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $ruleText,
                    $matchType,
                    $category,
                    $severity,
                    $ruleAction,
                    (int) $enabled,
                    $adminId,
                    $adminId
                ]);
                $ruleId = (int) $pdo->lastInsertId();
                logAdminActivity($adminId, 'global_chat_moderation.rule_created', 'Global Chat rule #' . $ruleId . ' created.');
            } else {
                $stmt = $pdo->prepare("
                    UPDATE global_chat_moderation_rules
                    SET
                        rule_text = ?,
                        match_type = ?,
                        category = ?,
                        severity = ?,
                        action = ?,
                        enabled = ?,
                        updated_by_admin_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $ruleText,
                    $matchType,
                    $category,
                    $severity,
                    $ruleAction,
                    (int) $enabled,
                    $adminId,
                    $ruleId
                ]);
                logAdminActivity($adminId, 'global_chat_moderation.rule_updated', 'Global Chat rule #' . $ruleId . ' updated.');
            }
            $pdo->commit();
        } catch (PDOException $e) {
            error_log('Global Chat moderation database request failed: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                globalChatModerationResponse(
                    false,
                    'A rule with this word or phrase already exists, or a related moderation record prevented the change.',
                    409
                );
            }
            globalChatModerationResponse(false, 'Unable to save the moderation change.', 500);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        globalChatModerationResponse(true, 'Global Chat rule saved.', 200, ['rule_id' => $ruleId]);
    }

    if ($action === 'delete_rule') {
        $ruleIdInput = $_POST['rule_id'] ?? null;
        if (!is_string($ruleIdInput) && !is_int($ruleIdInput)) {
            globalChatModerationResponse(false, 'Invalid moderation rule.', 422);
        }
        $ruleId = filter_var($ruleIdInput, FILTER_VALIDATE_INT);
        if ($ruleId === false || $ruleId <= 0) {
            globalChatModerationResponse(false, 'Invalid moderation rule.', 422);
        }
        $existsStmt = $pdo->prepare('SELECT id FROM global_chat_moderation_rules WHERE id = ? LIMIT 1');
        $existsStmt->execute([$ruleId]);
        if (!$existsStmt->fetchColumn()) {
            globalChatModerationResponse(false, 'Moderation rule not found.', 404);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM global_chat_moderation_rules WHERE id = ?');
            $stmt->execute([$ruleId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to delete this moderation rule.');
            }
            logAdminActivity($adminId, 'global_chat_moderation.rule_deleted', 'Global Chat rule #' . $ruleId . ' deleted.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        globalChatModerationResponse(true, 'Global Chat rule deleted.');
    }

    if ($action === 'review_case') {
        $caseIdInput = $_POST['case_id'] ?? null;
        $decision = $_POST['decision'] ?? '';
        $reasonInput = $_POST['reason'] ?? null;
        if (
            (!is_string($caseIdInput) && !is_int($caseIdInput))
            || !is_string($decision)
            || !is_string($reasonInput)
        ) {
            globalChatModerationResponse(false, 'Invalid moderation decision fields.', 422);
        }
        $caseId = filter_var($caseIdInput, FILTER_VALIDATE_INT);
        $reason = trim($reasonInput);
        if (
            $caseId === false
            || $caseId <= 0
            || !in_array($decision, ['reviewed', 'dismissed', 'restricted'], true)
            || $reason === ''
            || mb_strlen($reason, 'UTF-8') > 500
        ) {
            globalChatModerationResponse(false, 'Select a decision and enter a reason of 1–500 characters.', 422);
        }

        $pdo->beginTransaction();
        try {
            $caseStmt = $pdo->prepare("
                SELECT
                    moderation_case.id,
                    moderation_case.message_id,
                    moderation_case.status,
                    moderation_case.message_snapshot,
                    m.content AS current_content,
                    c.type AS conversation_type
                FROM global_chat_moderation_cases moderation_case
                INNER JOIN messages m
                    ON m.id = moderation_case.message_id
                INNER JOIN conversations c
                    ON c.id = m.conversation_id
                WHERE moderation_case.id = ?
                FOR UPDATE
            ");
            $caseStmt->execute([$caseId]);
            $moderationCase = $caseStmt->fetch(PDO::FETCH_ASSOC);
            if (!$moderationCase) {
                throw new RuntimeException('Moderation case not found.');
            }
            if ($moderationCase['status'] !== 'pending') {
                throw new RuntimeException('This moderation case has already been reviewed.');
            }

            if ($decision === 'restricted') {
                if (
                    $moderationCase['conversation_type'] !== 'global'
                    || $moderationCase['current_content']
                        !== $moderationCase['message_snapshot']
                ) {
                    throw new RuntimeException(
                        'This message has changed since it was flagged. Review its current content before restricting it.'
                    );
                }
                $restrictStmt = $pdo->prepare("
                    UPDATE messages m
                    INNER JOIN conversations c
                        ON c.id = m.conversation_id
                       AND c.type = 'global'
                    SET m.deleted_at = COALESCE(m.deleted_at, NOW())
                    WHERE m.id = ?
                ");
                $restrictStmt->execute([(int) $moderationCase['message_id']]);
            }

            $updateStmt = $pdo->prepare("
                UPDATE global_chat_moderation_cases
                SET
                    status = ?,
                    reviewer_admin_id = ?,
                    decision_reason = ?,
                    reviewed_at = NOW()
                WHERE id = ?
                  AND status = 'pending'
            ");
            $updateStmt->execute([$decision, $adminId, $reason, $caseId]);
            if ($updateStmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to save this moderation decision.');
            }
            logAdminActivity(
                $adminId,
                'global_chat_moderation.case_' . $decision,
                'Global Chat case #' . $caseId . ': ' . $reason
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $status = $e->getMessage() === 'Moderation case not found.'
                ? 404
                : (in_array($e->getMessage(), [
                    'This moderation case has already been reviewed.',
                    'This message has changed since it was flagged. Review its current content before restricting it.'
                ], true)
                    ? 409
                    : 500);
            globalChatModerationResponse(
                false,
                $status === 500 ? 'Unable to save this moderation decision.' : $e->getMessage(),
                $status
            );
        }
        globalChatModerationResponse(true, 'Moderation decision recorded.');
    }

    globalChatModerationResponse(false, 'Unknown moderation action.', 422);
} catch (Throwable $e) {
    error_log('Global Chat moderation endpoint failed: ' . $e->getMessage());
    globalChatModerationResponse(false, 'Unable to process the moderation request.', 500);
}
