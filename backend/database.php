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

$db_host = 'localhost';
$db_name = 'vnz_tech';
$db_user = 'root';
$db_pass = '';

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