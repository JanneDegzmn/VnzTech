<?php

function logAdminActivity(?int $adminId, string $action, ?string $details = null): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $adminId,
        $action,
        $details !== null ? mb_substr($details, 0, 500) : null,
        isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
    ]);
}
