<?php
function generateCsrfToken(){return 'fixture';}
function chatScaledroneConfig(){return [];}
function chatGetCurrentActor(){return ['actor_type'=>'admin','actor_id'=>7];}
function hasPermission($permission){return ($GLOBALS['fixturePermission'] ?? 'all') !== 'none';}
$GLOBALS['fixturePermission']=$argv[1] ?? 'all';
$currentAdmin=['first_name'=>'Fixture','last_name'=>'Admin','username'=>'fixture'];
$floatingMessagesPlacement='admin-topbar';
$shared=file_get_contents(__DIR__.'/../includes/floating-messages.php');
$shared=preg_replace("~require_once __DIR__ \. '/../backend/chat_scaledrone.php';~",'', $shared);
ob_start();eval('?>'.$shared);$chat=ob_get_clean();
$tools=file_get_contents(__DIR__.'/../includes/admin-topbar-tools.php');
$tools=str_replace("<?php if (hasPermission('view_submissions')): $" . "floatingMessagesPlacement = 'admin-topbar'; require __DIR__ . '/floating-messages.php'; unset($" . "floatingMessagesPlacement); endif; ?>",hasPermission('view_submissions')?$chat:'',$tools);
ob_start();eval('?>'.$tools);$rendered=ob_get_clean();
$shell=file_get_contents(__DIR__.'/../admin/dashboard.php');
preg_match('~<header class="topbar seller-topbar admin-topbar".*?</header>~s',$shell,$match);
$source=str_replace("<?php require __DIR__ . '/../includes/admin-topbar-tools.php'; ?>",$rendered,$match[0]);
eval('?>'.$source);
