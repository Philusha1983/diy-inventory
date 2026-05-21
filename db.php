<?php
/**
 * db.php — PDO Database Connection
 * Include this file in any script that needs database access.
 * $pdo will be available after including this file.
 */

$config_file = __DIR__ . '/config.php';

if (!file_exists($config_file)) {
    if (php_sapi_name() !== 'cli') {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/install/') === false) {
            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
                || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '_api.php') !== false)
                || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '_worker.php') !== false);

            if ($is_ajax) {
                http_response_code(503);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Application not installed. Please run the installation wizard at /install/.']);
                exit;
            } else {
                // Calculate relative path to root for installation redirect
                $temp_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
                $root_dir = str_replace('\\', '/', __DIR__);
                $rel_path = '';
                if (strpos($temp_dir, $root_dir) === 0) {
                    $sub = substr($temp_dir, strlen($root_dir));
                    $depth = substr_count(trim($sub, '/'), '/');
                    if (trim($sub, '/') !== '') {
                        $depth += 1;
                    }
                    $rel_path = str_repeat('../', $depth);
                }
                header('Location: ' . $rel_path . 'install/index.php');
                exit;
            }
        }
    }
} else {
    require_once $config_file;
}

// Backwards compatibility variables
$host    = defined('DB_HOST') ? DB_HOST : 'localhost';
$db      = defined('DB_NAME') ? DB_NAME : 'diy_lab_db';
$user    = defined('DB_USER') ? DB_USER : 'root';
$pass    = defined('DB_PASS') ? DB_PASS : '';
$charset = 'utf8mb4';

// Only establish connection if configuration exists, or if running under CLI
if (file_exists($config_file) || php_sapi_name() === 'cli') {
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
        die(json_encode(['error' => 'Database connection failed. Check config.php credentials.']));
    }
}

