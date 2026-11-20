<?php


require_once __DIR__ . '/backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false);
header('Location: sell.php', true, 302);
exit;
