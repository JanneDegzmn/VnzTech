<?php
require_once __DIR__ . "/../backend/site_status.php";
$scenario = $argv[1] ?? 'all';
$_SERVER['REQUEST_METHOD'] = $scenario === 'post' ? 'POST' : 'GET';
function getCurrentAdmin(){global $scenario; return in_array($scenario,['guest','inactive'],true)?null:['id'=>7];}
function hasPermission($permission){global $scenario;return $scenario==='all'||($scenario==='view'&&$permission==='view_submissions')||($scenario==='trade'&&$permission==='manage_trades');}
$pdo=new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE submissions (seen_by_admin INTEGER); INSERT INTO submissions VALUES (0),(0),(1); CREATE TABLE offers (sender_type TEXT,seen_by_admin INTEGER); INSERT INTO offers VALUES ("seller",0),("admin",0); CREATE TABLE transactions (seen_by_admin INTEGER);INSERT INTO transactions VALUES (0);CREATE TABLE trade_requests (status TEXT);INSERT INTO trade_requests VALUES ("PENDING"),("ACCEPTED")');
register_shutdown_function(function(){echo "\nSTATUS:".(http_response_code()?:200);});
$source=file_get_contents(__DIR__.'/../backend/admin_topbar_updates.php');
$source=preg_replace("~require_once __DIR__ \\. '/(?:database|session|auth|site_status)\\.php';~",'',$source);
eval('?>'.$source);
