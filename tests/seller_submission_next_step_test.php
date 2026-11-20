<?php
require __DIR__.'/../backend/seller_submission_next_step.php';
$checks=0;
foreach (['new_submission'=>'review queue','under_review'=>'reviewing','accepted'=>'Transactions','declined'=>'feedback','cancelled'=>'cancelled','completed'=>'complete','unknown'=>'latest status'] as $status=>$text) {
    if(!str_contains(vnzSellerSubmissionNextStep(['status'=>$status]),$text))throw new RuntimeException($status);
    $checks++;
}
foreach(['sent','offer_sent','pending','active'] as $offer) {
    if(!str_contains(vnzSellerSubmissionNextStep(['status'=>'offer_sent','latest_offer_status'=>$offer,'latest_offer_sender'=>'admin']),'send your response'))throw new RuntimeException('Admin offer');
    if(!str_contains(vnzSellerSubmissionNextStep(['status'=>'offer_sent','latest_offer_status'=>$offer,'latest_offer_sender'=>'seller']),'with the team'))throw new RuntimeException('Counter-offer');
    $checks+=2;
}
if(str_contains(vnzSellerSubmissionNextStep(['status'=>'offer_sent','latest_offer_status'=>'expired','latest_offer_sender'=>'admin']),'send your response'))throw new RuntimeException('Expired offer');
$checks++;
echo "PASS $checks seller next-step state assertions (no DB/provider)\n";
