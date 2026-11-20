<?php
// Render production widget markup with synthetic values; never load DB/session/providers.
function generateCsrfToken(){return 'fixture';}
function chatScaledroneConfig(){return [];}
function chatGetCurrentActor(){return ['actor_type'=>'admin','actor_id'=>7,'display_name'=>'Fixture Seller','profile_picture'=>''];}
function chatAttachmentMaxBytes(){return 1024;}
function chatDocumentMaxBytes(){return 1024;}
function chatVoiceMaxBytes(){return 1024;}
function chatVideoMaxBytes(){return 1024;}
function chatVoiceMaxDurationSeconds(){return 60;}
$transactionChatId=max(1,(int)($argv[1]??9));
$transactionChatConversationDbId=max(0,(int)($argv[2]??0));
$transactionChatReference='TEST-CHAT-'.$transactionChatId;
$transactionChatStatus='pending';
$transactionChatPaymentStatus='pending';
$transactionChatSubmissionReference='TEST-SUB-9';
$transactionChatSubmissionStatus='accepted';
$transactionChatOfferAmount=100;
$transactionChatFinalAmount=100;
$source=file_get_contents(__DIR__.'/../includes/transaction-chat.php');
$source=preg_replace("~require_once __DIR__ \\. '/../backend/(?:csrf|chat_scaledrone|chat_attachments)\\.php';~",'', $source);
eval('?>'.$source);
