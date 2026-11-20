<?php
require __DIR__.'/../backend/admin_submission_queue.php';
function submissionStatusLabel($value){return ucwords(str_replace('_',' ',$value));}
$queueBaseline=50;$queueOpeningTotal=50;$queueState=vnzAdminQueueState(['queue'=>'new']);$queueGroups=vnzAdminQueueGroups();$pagination=['page'=>1];$search='';$statusFilter='';$itemTypeFilter='';$allowedStatuses=['new_submission','under_review','offer_sent','accepted'];$allowedItemTypes=['Whole Phone','Scrap Board'];
$source=file_get_contents(__DIR__.'/../admin/pages/submissions.php');
$start=strpos($source,'<div data-admin-submission-queue');
$end=strpos($source,'SUBMISSIONS TABLE',$start);
$end=strrpos(substr($source,0,$end),'<!--');
eval('?>'.substr($source,$start,$end-$start).'</div>');
