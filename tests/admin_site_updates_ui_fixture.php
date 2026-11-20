<?php
// Actual Site Updates page, isolated active-admin permission scenarios.
require_once __DIR__.'/../backend/site_status.php';
$scenario=$argv[1]??'both';
function getCurrentAdmin(): ?array { global $scenario; return $scenario==='inactive'?null:['id'=>7,'is_active'=>1]; }
function hasPermission(string $permission): bool { global $scenario; return $scenario==='both'||($scenario==='announce'&&$permission==='manage_announcements')||($scenario==='maintain'&&$permission==='manage_maintenance'); }
function generateCsrfToken(): string { return 'fixture'; }
$path=__DIR__.'/../admin/pages/announcements.php';$source=file_get_contents($path);
$source=preg_replace('/^require_once[^\n]*\n/m','',$source);
$source=str_replace('__DIR__',var_export(dirname($path),true),$source);
eval('?>'.$source);
