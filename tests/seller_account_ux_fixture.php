<?php
// Synthetic fixture: actual seller page/endpoint source, no database or mail connection.
require_once __DIR__ . '/../backend/site_status.php';
$mode = $argv[1] ?? 'profile';
$_SESSION = ['user_logged_in' => true, 'user_id' => 7, 'user_language' => 'fil'];
$_GET = [];
function generateCsrfToken(): string { return 'fixture-token'; }
function verifyCsrfToken($token): bool { return $token === 'fixture-token'; }
class SellerAccountFixtureStatement {
    public function __construct(private $db, private string $sql) {}
    public function execute(array $params = []): bool {
        $this->db->executions[] = [$this->sql, $params];
        if (str_contains($this->sql, 'UPDATE users')) {
            $this->db->writes++;
            if ($params[3] !== null) $this->db->language = $params[3];
        }
        return true;
    }
    public function fetch() {
        return ['id'=>7,'full_name'=>'Fixture Seller','mobile'=>'09000000000','address'=>'Fixture address','email'=>'fixture@example.test','profile_picture'=>null,'language'=>'fil','email_verified_at'=>null,'created_at'=>'2026-01-01','totp_enabled_at'=>($GLOBALS['fixtureEnabled'] ? '2026-01-01' : null),'totp_login_required'=>1,'submission_updates'=>1,'offer_updates'=>0,'transaction_updates'=>1];
    }
}
class SellerAccountFixtureDatabase {
    public array $executions=[];
    public string $language='fil';
    public int $writes=0;
    private bool $transaction=false;
    public function prepare(string $sql) { return new SellerAccountFixtureStatement($this, $sql); }
    public function beginTransaction() { $this->transaction=true; }
    public function commit() { $this->transaction=false; }
    public function rollBack() { $this->transaction=false; }
    public function inTransaction() { return $this->transaction; }
}
$pdo = new SellerAccountFixtureDatabase();
$GLOBALS['fixtureEnabled'] = ($argv[2] ?? '') === 'enabled';
if (str_starts_with($mode, 'update-')) {
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['full_name'=>'Fixture Seller','mobile'=>'09000000000','address'=>'Fixture address','csrf_token'=>'fixture-token'];
    $_FILES=[];
    if ($mode === 'update-language') $_POST['language']='en';
    if ($mode === 'update-invalid') $_POST['language']='unknown';
    if ($mode === 'update-csrf') $_POST['csrf_token']='bad';
    if ($mode === 'update-auth') $_SESSION=[];
    if ($mode === 'update-method') $_SERVER['REQUEST_METHOD']='GET';
    ob_start();
    register_shutdown_function(function() use ($pdo) {
        $response=ob_get_clean();
        echo json_encode(['response'=>json_decode($response,true),'status'=>http_response_code() ?: 200,'language'=>$pdo->language,'session_language'=>$_SESSION['user_language'] ?? null,'writes'=>$pdo->writes]);
    });
    $source=file_get_contents(__DIR__.'/../backend/update_profile.php');
} else {
    if (!in_array($mode, ['profile','settings','security'], true)) exit(2);
    $source=file_get_contents(__DIR__.'/../account/pages/'.$mode.'.php');
}
$source=preg_replace('~require_once __DIR__ \. .+?;~', '', $source);
eval('?>'.$source);
