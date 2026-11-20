<?php
function generateCsrfToken(){return 'fixture';}
function chatScaledroneConfig(){return [];}
function chatGetCurrentActor(){return ['actor_type'=>'seller','actor_id'=>7];}
$sellerAccount=['full_name'=>'Example Seller With A Longer Display Name','language'=>'en','profile_picture'=>''];
require __DIR__.'/../backend/seller_topbar.php';
$floatingMessagesPlacement='seller-topbar';
$shared=file_get_contents(__DIR__.'/../includes/floating-messages.php');
$shared=preg_replace("~require_once __DIR__ \\. '/../backend/chat_scaledrone.php';~",'', $shared);
ob_start();eval('?>'.$shared);$chat=ob_get_clean();
$source=file_get_contents(__DIR__.'/../includes/seller-topbar.php');
$source=preg_replace("~require_once __DIR__ \\. '/../backend/seller_topbar.php';~",'', $source);
$source=str_replace("<?php \$floatingMessagesPlacement = 'seller-topbar'; require __DIR__ . '/floating-messages.php'; unset(\$floatingMessagesPlacement); ?>",$chat,$source);
eval('?>'.$source);
