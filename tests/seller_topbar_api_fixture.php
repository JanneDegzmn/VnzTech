<?php
// Isolated endpoint fixture: never connects to the application database/session.
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE users (id INTEGER,full_name TEXT,profile_picture TEXT,language TEXT,is_active INTEGER); CREATE TABLE submissions(id INTEGER,user_id INTEGER,reference_number TEXT,status TEXT,result_seen_by_seller INTEGER,updated_at TEXT); CREATE TABLE offers(id INTEGER,submission_id INTEGER,sender_type TEXT)');
$pdo->exec("INSERT INTO users VALUES(7,'Fixture','', 'en',1),(8,'Inactive','','en',0); INSERT INTO submissions VALUES(1,7,'OWN','accepted',0,'2026-11-20'),(2,99,'OTHER','accepted',0,'2026-11-20')");
$case=$argv[1]??'ok';
$_SERVER['REQUEST_METHOD']=$case==='post'?'POST':'GET';
$_SESSION=$case==='anonymous'?[]:['user_logged_in'=>true,'user_id'=>$case==='inactive'?8:7];
$_GET=['user_id'=>99];
register_shutdown_function(function(){echo "\nHTTP:".(http_response_code()?:200);});
$source=file_get_contents(__DIR__.'/../backend/seller_topbar_api.php');
$source=preg_replace("~require_once __DIR__ \\. '/(?:database|session)\\.php';~",'', $source);
$source=str_replace("require_once __DIR__ . '/seller_topbar.php';", "require_once '".str_replace('\\','/',__DIR__)."/../backend/seller_topbar.php';", $source);
eval('?>'.$source);
