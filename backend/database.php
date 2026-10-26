<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Database Connection
|--------------------------------------------------------------------------
| Database: vnz_tech
| Server: localhost
| XAMPP / MySQL
|--------------------------------------------------------------------------
*/

if (!defined('VNZ_TECH_DB_CONNECTED')) {
    define('VNZ_TECH_DB_CONNECTED', true);
}

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

$dbConfig = [
    'host' => 'localhost',
    'name' => 'vnz_tech',
    'user' => 'root',
    'pass' => ''
];

$localConfigPath = __DIR__ . '/database.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (!is_array($localConfig)) {
        die("Database configuration is invalid. Please contact the administrator.");
    }

    $dbConfig = array_merge($dbConfig, $localConfig);
}

foreach (['host', 'name', 'user', 'pass'] as $key) {
    if (!isset($dbConfig[$key]) || !is_string($dbConfig[$key])) {
        die("Database configuration is invalid. Please contact the administrator.");
    }
}

$db_host = $dbConfig['host'];
$db_name = $dbConfig['name'];
$db_user = $dbConfig['user'];
$db_pass = $dbConfig['pass'];

/*
|--------------------------------------------------------------------------
| PDO Connection
|--------------------------------------------------------------------------
*/

try {

    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

} catch (PDOException $e) {

    // Don't expose database credentials/details to users.
    die("Database connection failed. Please contact the administrator.");

}

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/remember_login.php';
vnzRestoreRememberedLogin($pdo);