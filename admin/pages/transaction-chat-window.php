<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';

requireAdminLogin();

if (!hasPermission('view_submissions')) {
    http_response_code(403);
    exit('Unauthorized.');
}

require __DIR__ . '/../../includes/transaction-chat-window.php';
