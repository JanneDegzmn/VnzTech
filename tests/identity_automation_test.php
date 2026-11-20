<?php
/** Automatic policy integration: synthetic JPEGs/private SQLite, no network or live records. */
require_once __DIR__ . '/../backend/identity_verification.php';
$root = sys_get_temp_dir() . '/vnz-auto-test-' . bin2hex(random_bytes(6));
mkdir($root,0700); mkdir($root.'/evidence',0700);
file_put_contents($root.'/key.bin',random_bytes(32));
$config = ['enabled'=>true,'mode'=>'auto_dual_v4','storage_path'=>$root,'key_path'=>$root.'/key.bin','dual'=>['node_binary'=>'C:/Program Files/nodejs/node.exe','region'=>'us','api_key'=>str_repeat('a',32),'api_secret'=>str_repeat('b',32),'contract_confirmed'=>true,'matching_validated'=>true,'validation_policy'=>'dual-v4-portrait-v1']];
file_put_contents($root.'/config.php','<?php return '.var_export($config,true).';');
file_put_contents($root.'/cleanup-heartbeat.json',json_encode(['success_at'=>time()]));
putenv('VNZ_IDENTITY_CONFIG_PATH='.$root.'/config.php');
$pdo = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
// Reuse the isolated manual fixture's schema, never its test execution/configuration.
$source = file_get_contents(__DIR__.'/identity_verification_test.php');
preg_match_all("/\\\$pdo->exec\\('(CREATE TABLE.*?)'\\);/s",$source,$schemas);
foreach($schemas[1] as $sql){$pdo->exec($sql);}
$pdo->exec('CREATE TABLE identity_auto_decisions (attempt_id INTEGER PRIMARY KEY,policy_version TEXT,evidence_hash TEXT,consent_version TEXT,crop_hash TEXT,crop_policy TEXT,local_engine TEXT,provider_engine TEXT,local_pass INTEGER DEFAULT 0,provider_pass INTEGER DEFAULT 0,local_distance REAL,local_threshold REAL,provider_confidence REAL,provider_threshold REAL,processing_nonce_hash TEXT,lease_expires_at TEXT,outcome_code TEXT,completed_at TEXT)');
$pdo->exec("INSERT INTO users VALUES(1,'one@example.invalid','2026-01-01',1),(2,'two@example.invalid','2026-01-01',1),(3,'three@example.invalid',NULL,1),(4,'inactive@example.invalid','2026-01-01',0); INSERT INTO admins VALUES(1,1,1); INSERT INTO roles VALUES(1,1); INSERT INTO permissions VALUES(1,'identity_review'); INSERT INTO role_permissions VALUES(1,1)");
$image=imagecreatetruecolor(160,160); imagefill($image,0,0,imagecolorallocate($image,80,100,120)); ob_start();imagejpeg($image,null,90);$jpeg=ob_get_clean();imagedestroy($image);
$local=['outcome'=>'pass','code'=>'matched','distance'=>0.3,'threshold'=>0.45,'crop_hash'=>hash('sha256',$jpeg."\0".$jpeg),'id_crop'=>$jpeg,'selfie_crop'=>$jpeg,'engine'=>'face-api.js-0.22.2','crop_policy'=>'portrait-v1'];
$cloud=['outcome'=>'pass','code'=>'provider_matched','confidence'=>95.0,'threshold'=>80.0,'threshold_key'=>'1e-5','engine'=>'faceplusplus-v3'];
$_SESSION=[];
$checks=0;
function autoCheck(bool $ok,string $message):void{global$checks;if(!$ok)throw new RuntimeException('FAILED '.$message);$checks++;echo'PASS '.$message.PHP_EOL;}
function autoReject(callable$fn,string$message):void{try{$fn();}catch(Throwable$e){autoCheck(true,$message);return;}autoCheck(false,$message);}
function autoSeed(PDO$pdo,string$root,string$jpeg,?int$user=null,?string$draft=null):array{
    $draft??=bin2hex(random_bytes(24));$cap=bin2hex(random_bytes(32));$lease=bin2hex(random_bytes(32));$hash=hash('sha256',$jpeg."\0".$jpeg);
    $names=[bin2hex(random_bytes(24)).'.enc',bin2hex(random_bytes(24)).'.enc'];foreach($names as$name){file_put_contents($root.'/evidence/'.$name,vnzIdentityEncrypt($jpeg,vnzIdentityKey()));}
    $email=$user?(string)vnzIdentityFetch($pdo,'SELECT email FROM users WHERE id=?',[$user])['email']:'three@example.invalid';
    $pdo->prepare('INSERT INTO identity_attempts(context,user_id,draft_id,capability_hash,email,status,id_file,selfie_file,evidence_hash,consent_at,created_at,updated_at,expires_at)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$user?'account':'guest',$user,$draft,hash('sha256',$cap),$email,'processing',$names[0],$names[1],$hash,vnzIdentityNow(),vnzIdentityNow(),vnzIdentityNow(),vnzIdentityAfter(86400)]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO identity_auto_decisions(attempt_id,policy_version,evidence_hash,consent_version,processing_nonce_hash,lease_expires_at,outcome_code)VALUES(?,?,?,?,?,?,?)')->execute([$id,vnzIdentityPolicy(),$hash,'auto_dual_v4',hash('sha256',$lease),vnzIdentityAfter(90),'processing']);
    if($user){$pdo->prepare('DELETE FROM identity_bindings WHERE user_id=?')->execute([$user]);$pdo->prepare('INSERT INTO identity_bindings VALUES(?,?,?)')->execute([$user,$id,vnzIdentityNow()]);}
    return ['id'=>$id,'lease'=>$lease,'hash'=>$hash,'draft_id'=>$draft,'capability'=>$cap,'names'=>$names];
}
function autoFinish(PDO$pdo,array$a,array$local,?array$cloud):void{vnzIdentityAutoFinalize($pdo,$a['id'],$a['lease'],$a['hash'],$local,$cloud);}
function autoAttempt(PDO$pdo,array$a):array{return vnzIdentityFetch($pdo,'SELECT * FROM identity_attempts WHERE id=?',[$a['id']]);}
try{
    vnzIdentityReady($pdo);autoCheck(true,'automatic fixture readiness uses isolated config and schema');
    foreach(['pass','retry','unavailable']as$l){foreach(['pass','retry','unavailable']as$c){$lr=array_merge($local,['outcome'=>$l]);$cr=array_merge($cloud,['outcome'=>$c]);$r=vnzIdentityDualResult($lr,$cr);autoCheck(($r['status']==='approved')===($l==='pass'&&$c==='pass'),'AND policy '.$l.' / '.$c);}}
    foreach([
        ['local',array_merge($local,['distance'=>0.450001])],['local',array_merge($local,['distance'=>NAN])],['local',array_merge($local,['engine'=>'browser'])],['local',array_merge($local,['crop_hash'=>str_repeat('0',64)])],['cloud',array_merge($cloud,['threshold_key'=>'1e-3'])],['cloud',array_merge($cloud,['confidence'=>80])],['cloud',array_merge($cloud,['threshold'=>0])],['cloud',array_merge($cloud,['confidence'=>INF])]
    ]as$i=>[$side,$changed]){autoCheck(vnzIdentityDualResult($side==='local'?$changed:$local,$side==='cloud'?$changed:$cloud)['status']!=='approved','malformed provenance cannot approve #'.$i);}
    $a=autoSeed($pdo,$root,$jpeg,1);autoFinish($pdo,$a,$local,$cloud);autoCheck(vnzIdentityPolicyStatus($pdo,autoAttempt($pdo,$a))==='approved','exact successful engines finalize current policy');
    autoReject(fn()=>autoFinish($pdo,$a,$local,$cloud),'completed lease cannot replay');
    $d=vnzIdentityAutoDecision($pdo,$a['id']);autoCheck((int)$d['local_pass']===1&&(int)$d['provider_pass']===1&&$d['completed_at']!==null&&$d['lease_expires_at']===null,'finalization persists minimal dual provenance and consumes lease');
    $pdo->prepare('UPDATE identity_auto_decisions SET provider_pass=0 WHERE attempt_id=?')->execute([$a['id']]);autoCheck(vnzIdentityPolicyStatus($pdo,autoAttempt($pdo,$a))==='needs_verification','database approved status without both provenances is insufficient');
    $pdo->prepare('UPDATE identity_auto_decisions SET provider_pass=1 WHERE attempt_id=?')->execute([$a['id']]);
    foreach(['policy_version'=>'old','consent_version'=>'manual_v2','crop_hash'=>'invalid','crop_policy'=>'client_crop','local_engine'=>'browser','provider_engine'=>'browser','local_distance'=>0.450001,'provider_confidence'=>80,'completed_at'=>vnzIdentityAfter(3600),'outcome_code'=>'unconfirmed']as$field=>$value){
        $old=$d[$field];$pdo->prepare('UPDATE identity_auto_decisions SET '.$field.'=? WHERE attempt_id=?')->execute([$value,$a['id']]);
        autoCheck(vnzIdentityPolicyStatus($pdo,autoAttempt($pdo,$a))==='needs_verification','forged stored provenance does not authorize: '.$field);
        $pdo->prepare('UPDATE identity_auto_decisions SET '.$field.'=? WHERE attempt_id=?')->execute([$old,$a['id']]);
    }
    $_SESSION=['user_logged_in'=>true,'user_id'=>1];autoCheck(vnzIdentityStatus($pdo,'account')['approved'],'current dual account approval is visible');
    $pdo->beginTransaction();vnzIdentityConsumeSubmission($pdo,1,'one@example.invalid','',100);$pdo->rollBack();autoCheck(vnzIdentityStatus($pdo,'account')['approved'],'account proof is durable and submission rollback safe');
    $manual=autoSeed($pdo,$root,$jpeg,2);$pdo->prepare("UPDATE identity_attempts SET status='approved',expires_at=NULL WHERE id=?")->execute([$manual['id']]);$pdo->prepare('DELETE FROM identity_auto_decisions WHERE attempt_id=?')->execute([$manual['id']]);autoReject(fn()=>vnzIdentityRequireSubmission($pdo,2,'two@example.invalid',''),'old manual approval cannot satisfy current dual gate');
    $file=['error'=>0,'tmp_name'=>$root.'/synthetic.jpg'];file_put_contents($file['tmp_name'],$jpeg);$_SESSION=[];autoReject(fn()=>vnzIdentitySubmit($pdo,'guest','three@example.invalid',$file,$file,true,'manual_v2'),'fresh explicit dual consent is required before capture processing');
    foreach(['lease','evidence','policy','consent','expiry','tamper','replaced','inactive','latest']as$kind){
        $actor=in_array($kind,['replaced','inactive'],true)?($kind==='inactive'?4:1):null;$x=autoSeed($pdo,$root,$jpeg,$actor);
        if($kind==='lease')$x['lease']='wrong';
        if($kind==='evidence')$x['hash']=str_repeat('0',64);
        if($kind==='policy')$pdo->prepare("UPDATE identity_auto_decisions SET policy_version='old' WHERE attempt_id=?")->execute([$x['id']]);
        if($kind==='consent')$pdo->prepare("UPDATE identity_auto_decisions SET consent_version='manual_v2' WHERE attempt_id=?")->execute([$x['id']]);
        if($kind==='expiry')$pdo->prepare("UPDATE identity_auto_decisions SET lease_expires_at='2000-01-01' WHERE attempt_id=?")->execute([$x['id']]);
        if($kind==='tamper')file_put_contents($root.'/evidence/'.$x['names'][0],'tampered ciphertext');
        if($kind==='replaced')autoSeed($pdo,$root,$jpeg,1);
        if($kind==='latest')autoSeed($pdo,$root,$jpeg,null,$x['draft_id']);
        autoReject(fn()=>autoFinish($pdo,$x,$local,$cloud),'stale completion denied: '.$kind);
        autoCheck(autoAttempt($pdo,$x)['status']==='processing','failed completion rolls back state: '.$kind);
    }
    $_SESSION=[];$guest=autoSeed($pdo,$root,$jpeg);$_SESSION['identity_guest_current']=['draft_id'=>$guest['draft_id'],'capability'=>$guest['capability']];autoFinish($pdo,$guest,$local,$cloud);
    $pdo->beginTransaction();vnzIdentityConsumeSubmission($pdo,null,'three@example.invalid',$guest['draft_id'],200);$pdo->rollBack();autoCheck(vnzIdentityRequireSubmission($pdo,null,'three@example.invalid',$guest['draft_id'])['submission_id']===null,'dual guest rollback preserves unused one-use proof');
    $pdo->beginTransaction();vnzIdentityConsumeSubmission($pdo,null,'three@example.invalid',$guest['draft_id'],200);$pdo->commit();autoReject(fn()=>vnzIdentityRequireSubmission($pdo,null,'three@example.invalid',$guest['draft_id']),'dual guest proof cannot replay after successful submission');
    $pdo->beginTransaction();vnzIdentityReserveGuest($pdo,3,'three@example.invalid');vnzIdentityFinalizeAccount($pdo,3);$pdo->commit();autoCheck(vnzIdentityFetch($pdo,'SELECT * FROM identity_bindings WHERE user_id=3')===null,'verified email required for automatic guest carryover');
    $pdo->exec("UPDATE users SET email_verified_at='2026-01-01' WHERE id=3");$pdo->beginTransaction();vnzIdentityFinalizeAccount($pdo,3);$pdo->commit();$_SESSION=['user_logged_in'=>true,'user_id'=>3];autoCheck(vnzIdentityStatus($pdo,'account')['approved'],'approved dual guest identity safely carries to exact verified account');
    $_SESSION=[];$pending=autoSeed($pdo,$root,$jpeg);$_SESSION['identity_guest_current']=['draft_id'=>$pending['draft_id'],'capability'=>$pending['capability']];$pdo->prepare('DELETE FROM identity_bindings WHERE user_id=3')->execute([]);$pdo->beginTransaction();vnzIdentityReserveGuest($pdo,3,'three@example.invalid');vnzIdentityFinalizeAccount($pdo,3);$pdo->commit();$_SESSION=['user_logged_in'=>true,'user_id'=>3];autoCheck(vnzIdentityStatus($pdo,'account')['status']==='processing','processing guest binding cannot grant premature approval');autoFinish($pdo,$pending,$local,$cloud);autoCheck(vnzIdentityStatus($pdo,'account')['approved'],'exact in-flight completion grants carried account after both checks');
    $_SESSION=['admin_id'=>1];autoCheck(!vnzIdentityReviewer($pdo)&&vnzIdentityQueue($pdo)===[],'automatic mode removes administrator review authority');autoReject(fn()=>vnzIdentityReview($pdo,$pending['id'],$pending['hash'],'approve','',true),'manual direct approval is rejected under automatic policy');autoReject(fn()=>vnzIdentityEvidence($pdo,$pending['id'],'id'),'administrator cannot read automatic owner evidence');
    $_SESSION=[];$conflict=autoSeed($pdo,$root,$jpeg);autoFinish($pdo,$conflict,$local,$cloud);$_SESSION['identity_guest_current']=['draft_id'=>$conflict['draft_id'],'capability'=>$conflict['capability']];
    $pdo->beginTransaction();vnzIdentityReserveGuest($pdo,3,'three@example.invalid');vnzIdentityFinalizeAccount($pdo,3);$pdo->commit();
    autoCheck((int)vnzIdentityFetch($pdo,'SELECT attempt_id FROM identity_bindings WHERE user_id=3')['attempt_id']===$pending['id'],'guest carryover cannot overwrite an existing account identity binding');
    $_SESSION=[];$lost=autoSeed($pdo,$root,$jpeg);autoFinish($pdo,$lost,$local,$cloud);$pdo->beginTransaction();vnzIdentityReserveGuest($pdo,3,'three@example.invalid');$pdo->commit();
    autoCheck(autoAttempt($pdo,$lost)['reserved_user_id']===null,'matching email without exact session capability cannot reserve identity');
    $expired=autoSeed($pdo,$root,$jpeg);$pdo->prepare("UPDATE identity_attempts SET expires_at='2000-01-01' WHERE id=?")->execute([$expired['id']]);$summary=vnzIdentityCleanup($pdo);autoCheck(autoAttempt($pdo,$expired)['status']==='expired'&&!is_file($root.'/evidence/'.$expired['names'][0]),'abandoned processing expires and purges after 24-hour lifecycle');
    $pdo->prepare("UPDATE identity_attempts SET purge_after='2000-01-01' WHERE id=?")->execute([$pending['id']]);vnzIdentityCleanup($pdo);$_SESSION=['user_logged_in'=>true,'user_id'=>3];autoCheck(vnzIdentityStatus($pdo,'account')['approved']&&!is_file($root.'/evidence/'.$pending['names'][0]),'decided photo deletion preserves durable automatic account decision');
    echo'Completed '.$checks.' synthetic automatic-state checks; no provider requests or personal evidence.'.PHP_EOL;
}finally{
    putenv('VNZ_IDENTITY_CONFIG_PATH');foreach(glob($root.'/evidence/*')?:[]as$path){unlink($path);}rmdir($root.'/evidence');foreach(glob($root.'/*')?:[]as$path){unlink($path);}rmdir($root);
}
