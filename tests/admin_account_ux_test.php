<?php
$checks=0;
function check(bool $ok,string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function fixture(string $kind,string $scenario): string { return shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/admin_account_ux_fixture.php').' '.escapeshellarg($kind).' '.escapeshellarg($scenario)); }
foreach (['settings'=>['Appearance','admin-profile-card','admin-password-card'],'profile'=>['Admin profile','admin-appearance-card','admin-password-card'],'security'=>['Change password','admin-appearance-card','admin-profile-card']] as $page=>$markers) {
    $html=fixture('page',$page);
    check(str_contains($html,$markers[0]),$page.' actual page content');
    foreach (array_slice($markers,1) as $absent) check(!str_contains($html,$absent),$page.' excludes '.$absent);
    check(!str_contains($html,'Access denied'),$page.' is self service for limited active admin');
    check(!str_contains($html,'admin_activity_logs'),$page.' does not query global activity');
}
foreach (['moderation','activity'] as $page) check(str_contains(fixture('page',$page),'Access denied'),$page.' retains privileged guard');
foreach (['guest'=>401,'inactive'=>401,'csrf'=>419,'get'=>405,'unknown'=>400,'password'=>403,'self'=>200] as $scenario=>$expected) {
    $output=fixture('endpoint',$scenario);[$body,$result]=explode("\nRESULT:",$output,2);$response=json_decode($body,true,512,JSON_THROW_ON_ERROR);$meta=json_decode($result,true,512,JSON_THROW_ON_ERROR);
    check($meta['status']===$expected,$scenario.' status');
    check($meta['accounts'][1]['first_name']==='Other',$scenario.' arbitrary target ignored');
    check($meta['accounts'][0]['first_name']===($scenario==='self'?'Changed':'Fixture'),$scenario.' permitted session-owned write only');
}
$security=file_get_contents(__DIR__.'/../admin/pages/security.php');
check(str_contains($security,"window.loadAdminPage('security')"),'Authenticator reload returns to Security');
check(str_contains($security,"const forms = document.querySelectorAll('[data-admin-settings-form]')"),'Existing account handler moved with forms');
$moderation=file_get_contents(__DIR__.'/../admin/pages/moderation.php');check(str_contains($moderation,'const rulesPerPage = 3'),'Moderation retains three-rule pagination');
$activity=file_get_contents(__DIR__.'/../admin/pages/activity.php');check(str_contains($activity,'vnzPaginationState(12)'),'Activity retains twelve-row pagination');
echo $checks." admin account UX checks passed.\n";
