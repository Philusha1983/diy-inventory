<?php
/**
 * db.php — PDO Database Connection
 * Include this file in any script that needs database access.
 * $pdo will be available after including this file.
 */

$host    = 'localhost';
$db      = 'diy_lab_db';
$user    = 'root';
$pass    = '';          // Default MAMP/XAMPP password — change if needed
$charset = 'utf8mb4';

$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In production, do NOT expose the full error message
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed. Check db.php credentials.']));
}
