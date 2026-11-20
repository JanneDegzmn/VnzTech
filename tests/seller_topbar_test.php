<?php
require __DIR__ . '/../backend/seller_topbar.php';
function check($condition, $message) { if (!$condition) throw new Exception($message); }
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE submissions (id INTEGER, user_id INTEGER, reference_number TEXT, status TEXT, result_seen_by_seller INTEGER, updated_at TEXT); CREATE TABLE offers (id INTEGER, submission_id INTEGER, sender_type TEXT)');
$db->exec("INSERT INTO submissions VALUES (1,7,'TEST1','offer_sent',0,'2026-11-20'),(2,7,'TEST2','accepted',0,'2026-11-20'),(3,7,'TEST3','declined',1,'2026-11-20'),(4,8,'OTHER','accepted',0,'2026-11-20'),(5,7,'TEST5','offer_sent',0,'2026-11-20'); INSERT INTO offers VALUES(1,1,'admin'),(2,5,'admin'),(3,5,'seller')");
$result=vnzSellerTopbarUpdates($db,7);
check($result['total']===2, 'Only actionable own updates');
check(array_column($result['items'],'submission_id')===[2,1], 'Ownership, latest sender, seen flag and stable sorting');
check((int)$db->query('SELECT result_seen_by_seller FROM submissions WHERE id=2')->fetchColumn()===0, 'No acknowledgement on read');
for($i=10;$i<35;$i++) $db->exec("INSERT INTO submissions VALUES ($i,7,'TEST','accepted',0,'2026-11-20')");
$result=vnzSellerTopbarUpdates($db,7);
check($result['total']===27 && count($result['items'])===20, 'Bounded results with full count');
check(vnzSellerTopbarProfile(['full_name'=>'Example Seller','profile_picture'=>'uploads/profiles/test.jpg'])['avatar_url']==='/VNZ-TECH/uploads/profiles/test.jpg','Avatar normalization');
foreach(['javascript:alert(1)','uploads/../secret','file:///secret'] as $url) check(vnzSellerTopbarProfile(['profile_picture'=>$url])['avatar_url']==='', 'Reject unsafe avatar');
echo "8 seller topbar data checks passed\n";
