<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'messaging');
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';

if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Authentication required.');
}

require __DIR__ . '/../../includes/transaction-chat-window.php';
