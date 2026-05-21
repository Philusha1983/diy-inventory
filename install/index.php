<?php
/**
 * DIY Lab Inventory System - Web-based Installation Wizard
 * Matches DIY Lab design aesthetics.
 */

// 1. Security Check: If config.php exists, check if the installer should be blocked.
$config_file = __DIR__ . '/../config.php';
if (file_exists($config_file)) {
    try {
        // Load the config file silently
        $old_err = error_reporting(0);
        include $config_file;
        error_reporting($old_err);
        
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ];
            $pdo_check = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Check if admin_username exists in settings table
            $stmt = $pdo_check->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_username'");
            $stmt->execute();
            $admin_exists = $stmt->fetchColumn();
            
            if ($admin_exists !== false && !empty($admin_exists)) {
                // Application is already fully installed and configured, block access.
                header('Location: ../index.php');
                exit;
            }
        }
    } catch (Throwable $e) {
        // Connection failed or settings table not populated.
        // This means the setup is incomplete or needs reconfiguration, so we allow access.
    }
}

// 2. Isolated Error Logging and Debugging Utility
function write_install_log($step, $error_msg, $extra_data = []) {
    $log_file = __DIR__ . '/install_debug.log';
    
    // Mask sensitive details
    $masked_data = $extra_data;
    if (isset($masked_data['db_pass'])) {
        $masked_data['db_pass'] = '********';
    }
    if (isset($masked_data['admin_password'])) {
        $masked_data['admin_password'] = '********';
    }
    if (isset($masked_data['admin_confirm_password'])) {
        $masked_data['admin_confirm_password'] = '********';
    }

    $log_entry = "=========================================\n";
    $log_entry .= "[" . date('Y-m-d H:i:s') . "] STEP: " . $step . "\n";
    $log_entry .= "ERROR: " . $error_msg . "\n";
    if (!empty($masked_data)) {
        $log_entry .= "CONTEXT DATA:\n" . print_r($masked_data, true) . "\n";
    }
    $log_entry .= "SERVER CONTEXT:\n";
    $log_entry .= "  PHP Version: " . PHP_VERSION . "\n";
    $log_entry .= "  Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
    $log_entry .= "  OS: " . PHP_OS . "\n";
    $log_entry .= "  Root writable: " . (is_writable(__DIR__ . '/../') ? 'YES' : 'NO') . "\n";
    $log_entry .= "  Uploads writable: " . (is_writable(__DIR__ . '/../uploads') ? 'YES' : 'NO') . "\n";
    $log_entry .= "=========================================\n\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    return $log_entry;
}

// 3. AJAX Requests Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    switch ($action) {
        case 'test_connection':
            $db_host = trim($_POST['db_host'] ?? '');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';

            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                echo json_encode(['success' => false, 'error' => 'Database Host, Name, and Username are required.']);
                exit;
            }

            try {
                // Test connection without selecting database first (in case it needs to be created)
                $dsn = "mysql:host=$db_host;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ];
                $conn = new PDO($dsn, $db_user, $db_pass, $options);
                
                // Connection worked! Now let's see if the database exists or can be selected.
                $stmt = $conn->query("SHOW DATABASES LIKE " . $conn->quote($db_name));
                $db_exists = $stmt->fetchColumn() !== false;
                
                echo json_encode([
                    'success' => true, 
                    'db_exists' => $db_exists,
                    'message' => $db_exists ? 'Connection successful! Database exists.' : 'Connection successful! Database does not exist and will be created.'
                ]);
            } catch (PDOException $e) {
                $log = write_install_log('test_connection', $e->getMessage(), $_POST);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database connection failed: ' . $e->getMessage(),
                    'log' => $log
                ]);
            }
            exit;

        case 'install_db':
            $db_host = trim($_POST['db_host'] ?? '');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';
            $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');

            if (empty($db_host) || empty($db_name) || empty($db_user) || empty($site_url)) {
                echo json_encode(['success' => false, 'error' => 'All configuration fields are required.']);
                exit;
            }

            try {
                // 1. Establish PDO Connection
                $dsn = "mysql:host=$db_host;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                $conn = new PDO($dsn, $db_user, $db_pass, $options);

                // 2. Create database if it doesn't exist
                $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $conn->exec("USE `$db_name`");

                // 3. Execute base database schema
                $schema_file = __DIR__ . '/../schema.sql';
                if (!file_exists($schema_file)) {
                    throw new Exception('schema.sql file is missing from the application root.');
                }
                $sql = file_get_contents($schema_file);
                
                // Execute schema queries
                $conn->exec($sql);

                // 4. Generate Production config.php File
                $config_template = <<<EOT
<?php
/**
 * DIY Lab Inventory System - Production Configuration
 * Generated by the Setup Wizard.
 */

define('DB_HOST', %s);
define('DB_NAME', %s);
define('DB_USER', %s);
define('DB_PASS', %s);
define('SITE_URL', %s);
EOT;

                $config_content = sprintf(
                    $config_template,
                    var_export($db_host, true),
                    var_export($db_name, true),
                    var_export($db_user, true),
                    var_export($db_pass, true),
                    var_export($site_url, true)
                );

                $root_writable = is_writable(__DIR__ . '/../');
                $written = false;

                if ($root_writable) {
                    $written = @file_put_contents($config_file, $config_content) !== false;
                }

                if ($written) {
                    echo json_encode([
                        'success' => true,
                        'manual_fallback' => false
                    ]);
                } else {
                    // Return the configuration for manual creation if folder not writable
                    echo json_encode([
                        'success' => true,
                        'manual_fallback' => true,
                        'config_content' => $config_content
                    ]);
                }
            } catch (Exception $e) {
                $log = write_install_log('install_db', $e->getMessage(), $_POST);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database installation failed: ' . $e->getMessage(),
                    'log' => $log
                ]);
            }
            exit;

        case 'create_admin':
            $admin_user = trim($_POST['admin_username'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_confirm = $_POST['admin_confirm_password'] ?? '';

            if (empty($admin_user) || empty($admin_email) || empty($admin_password)) {
                echo json_encode(['success' => false, 'error' => 'All administrator fields are required.']);
                exit;
            }

            if (strlen($admin_password) < 6) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
                exit;
            }

            if ($admin_password !== $admin_confirm) {
                echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
                exit;
            }

            // Verify config.php is now present
            if (!file_exists($config_file)) {
                echo json_encode(['success' => false, 'error' => 'config.php was not found. Please complete the previous step.']);
                exit;
            }

            try {
                // Connect using generated config.php credentials
                require_once $config_file;
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ];
                $pdo_inst = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Hash the password
                $pw_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);

                // Insert/Update settings table
                $stmt = $pdo_inst->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                
                $stmt->execute(['lab_password', $pw_hash, $pw_hash]);
                $stmt->execute(['admin_email', $admin_email, $admin_email]);
                $stmt->execute(['admin_username', $admin_user, $admin_user]);

                // Try to automatically rename/disable the install/ folder right now
                $install_dir = __DIR__;
                $parent_dir = dirname($install_dir);
                $random_suffix = substr(md5(uniqid(rand(), true)), 0, 8);
                $new_name = $parent_dir . '/install_disabled_' . $random_suffix;
                $renamed = false;
                $cleanup_message = '';

                if (@rename($install_dir, $new_name)) {
                    $renamed = true;
                    $cleanup_message = 'Successfully disabled the installer directory for security.';
                } else {
                    $cleanup_message = 'Could not automatically rename the "install/" folder. Please manually delete or rename it on your server.';
                }

                echo json_encode([
                    'success' => true,
                    'cleanup' => [
                        'renamed' => $renamed,
                        'message' => $cleanup_message
                    ]
                ]);
            } catch (PDOException $e) {
                $log = write_install_log('create_admin', $e->getMessage(), $_POST);
                echo json_encode([
                    'success' => false,
                    'error' => 'Admin account creation failed: ' . $e->getMessage(),
                    'log' => $log
                ]);
            }
            exit;

        case 'cleanup':
            // Try to automatically rename/disable the install/ folder
            $install_dir = __DIR__;
            $parent_dir = dirname($install_dir);
            $random_suffix = substr(md5(uniqid(rand(), true)), 0, 8);
            $new_name = $parent_dir . '/install_disabled_' . $random_suffix;

            if (@rename($install_dir, $new_name)) {
                echo json_encode([
                    'success' => true,
                    'renamed' => true,
                    'message' => 'Successfully disabled the installer directory for security.'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'renamed' => false,
                    'message' => 'Could not automatically rename the "install/" folder. Please manually delete or rename it on your server.'
                ]);
            }
            exit;
    }
}

// 4. GET View Logic: System environment checks
$php_version_ok = version_compare(PHP_VERSION, '8.0.0', '>=');

$required_extensions = [
    'pdo' => 'PDO Extension',
    'pdo_mysql' => 'PDO MySQL Driver',
    'mysqli' => 'MySQLi Extension (Optional)',
    'mbstring' => 'Multibyte String Support',
    'curl' => 'cURL Extension',
    'gd' => 'GD Graphics Library'
];

$extension_checks = [];
foreach ($required_extensions as $ext => $label) {
    $extension_checks[$ext] = [
        'label' => $label,
        'pass' => extension_loaded($ext)
    ];
}

// Write permissions
$root_dir = __DIR__ . '/../';
$uploads_dir = $root_dir . 'uploads';
$logo_dir = $uploads_dir . '/logo';

// Pre-create uploads if permissions allow
if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}
if (!is_dir($logo_dir)) {
    @mkdir($logo_dir, 0755, true);
}

$write_checks = [
    'root' => [
        'label' => 'Application Root Directory (for config.php)',
        'path' => $root_dir,
        'pass' => is_writable($root_dir)
    ],
    'uploads' => [
        'label' => 'Uploads Directory',
        'path' => $uploads_dir,
        'pass' => is_writable($uploads_dir)
    ],
    'logo' => [
        'label' => 'Logo Uploads Directory',
        'path' => $logo_dir,
        'pass' => is_writable($logo_dir)
    ]
];

// Determine if we can proceed based on critical requirements
$critical_pass = $php_version_ok 
    && $extension_checks['pdo']['pass'] 
    && $extension_checks['pdo_mysql']['pass'] 
    && $extension_checks['mbstring']['pass'] 
    && $extension_checks['gd']['pass'] 
    && $extension_checks['curl']['pass'];

// Detect Site URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$base_uri = preg_replace('/\/install\/(index\.php)?$/i', '', $uri);
$detected_site_url = $protocol . '://' . $host . $base_uri;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DIY Lab - Installation Wizard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --bg-color: #0b0b14;
      --card-bg: rgba(255, 255, 255, 0.03);
      --card-border: rgba(255, 255, 255, 0.07);
      --text-main: #f1f5f9;
      --text-muted: #94a3b8;
      --primary: #7c3aed;
      --primary-hover: #6d28d9;
      --accent: #06b6d4;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: var(--bg-color);
      color: var(--text-main);
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      overflow-x: hidden;
      position: relative;
    }

    /* Ambient background grid and glow orbs */
    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: 
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
      background-size: 40px 40px;
      background-position: center;
      z-index: 1;
      pointer-events: none;
    }

    .orbs {
      position: absolute;
      inset: 0;
      overflow: hidden;
      z-index: 2;
      pointer-events: none;
    }

    .orb-purple {
      position: absolute;
      top: -10%;
      left: -10%;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(124, 58, 237, 0.15) 0%, rgba(124, 58, 237, 0) 70%);
      filter: blur(40px);
    }

    .orb-cyan {
      position: absolute;
      bottom: -10%;
      right: -10%;
      width: 450px;
      height: 450px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(6, 182, 212, 0.1) 0%, rgba(6, 182, 212, 0) 75%);
      filter: blur(50px);
    }

    .container {
      width: 100%;
      max-width: 600px;
      z-index: 10;
      position: relative;
    }

    /* Logo Header */
    .header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo-container {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      box-shadow: 0 0 30px rgba(124, 58, 237, 0.3);
      animation: float 4s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }

    .logo-container svg {
      width: 32px;
      height: 32px;
      color: white;
    }

    .header h1 {
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.5px;
      margin-bottom: 0.25rem;
      background: linear-gradient(135deg, #fff, var(--text-muted));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .header p {
      font-size: 0.875rem;
      color: var(--text-muted);
    }

    /* Stepper Progress Bar */
    .stepper {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2rem;
      position: relative;
      padding: 0 0.5rem;
    }

    .stepper::before {
      content: '';
      position: absolute;
      top: 14px;
      left: 1rem;
      right: 1rem;
      height: 2px;
      background-color: rgba(255, 255, 255, 0.05);
      z-index: 1;
    }

    .step-indicator-bar {
      position: absolute;
      top: 14px;
      left: 1rem;
      height: 2px;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      z-index: 2;
      width: 0%;
      transition: width 0.4s ease;
    }

    .step {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background-color: #121221;
      border: 2px solid rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.825rem;
      font-weight: 600;
      color: var(--text-muted);
      z-index: 3;
      transition: all 0.3s ease;
      cursor: default;
    }

    .step.active {
      border-color: var(--primary);
      color: white;
      background-color: var(--primary);
      box-shadow: 0 0 15px rgba(124, 58, 237, 0.4);
    }

    .step.completed {
      border-color: var(--accent);
      color: white;
      background-color: var(--accent);
      box-shadow: 0 0 15px rgba(6, 182, 212, 0.3);
    }

    /* Main Glassmorphism Card */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 2.25rem;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
      transition: all 0.3s ease;
    }

    .wizard-step {
      display: none;
    }

    .wizard-step.active {
      display: block;
      animation: fadeIn 0.4s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .card h2 {
      font-size: 1.35rem;
      font-weight: 600;
      margin-bottom: 0.75rem;
      color: white;
    }

    .card p.description {
      font-size: 0.9rem;
      color: var(--text-muted);
      line-height: 1.5;
      margin-bottom: 1.75rem;
    }

    /* Form Fields */
    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-group label {
      display: block;
      font-size: 0.825rem;
      font-weight: 500;
      color: #cbd5e1;
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      background-color: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 0.75rem 1rem;
      color: white;
      font-family: inherit;
      font-size: 0.9rem;
      transition: all 0.2s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      background-color: rgba(255, 255, 255, 0.06);
      box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }

    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.2);
    }

    /* List items for checks */
    .check-list {
      list-style: none;
      margin-bottom: 1.75rem;
    }

    .check-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem 1rem;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 12px;
      margin-bottom: 0.5rem;
      font-size: 0.875rem;
    }

    .check-label {
      font-weight: 500;
    }

    .check-detail {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-top: 0.15rem;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.6rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-pass {
      background-color: rgba(16, 185, 129, 0.15);
      color: #34d399;
      border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .badge-fail {
      background-color: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .badge-warn {
      background-color: rgba(245, 158, 11, 0.15);
      color: #fbbf24;
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    /* Button Actions */
    .card-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .btn {
      flex: 1;
      padding: 0.825rem 1.25rem;
      border-radius: 12px;
      font-family: inherit;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border: none;
      text-decoration: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
      box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
    }

    .btn-primary:active:not(:disabled) {
      transform: translateY(0);
    }

    .btn-secondary {
      background-color: rgba(255, 255, 255, 0.05);
      color: var(--text-main);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover:not(:disabled) {
      background-color: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.12);
    }

    .btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
      box-shadow: none;
    }

    /* Alerts and Diagnostics */
    .alert {
      padding: 0.875rem 1rem;
      border-radius: 12px;
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
      line-height: 1.4;
      border: 1px solid transparent;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .alert-danger {
      background-color: rgba(239, 68, 68, 0.12);
      color: #f87171;
      border-color: rgba(239, 68, 68, 0.2);
    }

    .alert-success {
      background-color: rgba(16, 185, 129, 0.12);
      color: #34d399;
      border-color: rgba(16, 185, 129, 0.2);
    }

    .alert-info {
      background-color: rgba(6, 182, 212, 0.12);
      color: #67e8f9;
      border-color: rgba(6, 182, 212, 0.2);
    }

    .log-container {
      display: none;
      margin-top: 1rem;
    }

    .log-textarea {
      width: 100%;
      height: 150px;
      background-color: #05050a;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      padding: 0.75rem;
      color: #cbd5e1;
      font-family: monospace;
      font-size: 0.75rem;
      resize: vertical;
      white-space: pre-wrap;
      overflow-y: auto;
    }

    .btn-log-toggle {
      background: none;
      border: none;
      color: var(--text-muted);
      font-size: 0.75rem;
      text-decoration: underline;
      cursor: pointer;
      align-self: flex-start;
      margin-top: 0.25rem;
    }

    .btn-log-toggle:hover {
      color: var(--text-main);
    }

    /* Loading Spinner */
    .spinner {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 0.8s linear infinite;
      display: none;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Manual fallback code block */
    .code-block {
      background-color: #05050a;
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 12px;
      padding: 1rem;
      font-family: monospace;
      font-size: 0.8rem;
      color: #e2e8f0;
      white-space: pre-wrap;
      overflow-x: auto;
      max-height: 250px;
      margin: 1rem 0;
    }

    .status-text {
      font-size: 0.875rem;
      margin-top: 0.5rem;
      color: var(--text-muted);
      text-align: center;
    }

    .footer {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.75rem;
      color: rgba(255, 255, 255, 0.15);
      z-index: 10;
    }
  </style>
</head>
<body>

  <div class="orbs">
    <div class="orb-purple"></div>
    <div class="orb-cyan"></div>
  </div>

  <div class="container">
    
    <!-- Logo & Title -->
    <div class="header">
      <div class="logo-container">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.332 2.798H4.13c-1.362 0-2.332-1.798-1.332-2.798L4 14.5"/>
        </svg>
      </div>
      <h1>DIY Lab</h1>
      <p>Self-Hosted Setup Wizard</p>
    </div>

    <!-- Stepper Navigation -->
    <div class="stepper">
      <div class="step-indicator-bar" id="stepper-bar"></div>
      <div class="step active" data-step="1">1</div>
      <div class="step" data-step="2">2</div>
      <div class="step" data-step="3">3</div>
      <div class="step" data-step="4">4</div>
      <div class="step" data-step="5">5</div>
    </div>

    <!-- Glass Card Container -->
    <div class="card">

      <!-- STEP 1: WELCOME & ENVIRONMENT CHECK -->
      <div class="wizard-step active" id="step-1">
        <h2>Environment Verification</h2>
        <p class="description">Before setting up DIY Lab, we need to verify that your hosting server meets the minimum software requirements and file system permissions.</p>
        
        <ul class="check-list">
          <!-- PHP Check -->
          <li class="check-item">
            <div>
              <div class="check-label">PHP Version 8.0+ Required</div>
              <div class="check-detail">Running PHP <?= PHP_VERSION ?></div>
            </div>
            <span class="badge <?= $php_version_ok ? 'badge-pass' : 'badge-fail' ?>">
              <?= $php_version_ok ? 'Pass' : 'Fail' ?>
            </span>
          </li>

          <!-- Extension Checks -->
          <?php foreach ($extension_checks as $ext => $chk): ?>
          <li class="check-item">
            <div>
              <div class="check-label"><?= htmlspecialchars($chk['label']) ?></div>
              <div class="check-detail">Module: <?= htmlspecialchars($ext) ?></div>
            </div>
            <span class="badge <?= $chk['pass'] ? 'badge-pass' : ($ext === 'mysqli' ? 'badge-warn' : 'badge-fail') ?>">
              <?= $chk['pass'] ? 'Pass' : ($ext === 'mysqli' ? 'Optional' : 'Fail') ?>
            </span>
          </li>
          <?php endforeach; ?>

          <!-- Permission Checks -->
          <?php foreach ($write_checks as $key => $chk): ?>
          <li class="check-item">
            <div>
              <div class="check-label"><?= htmlspecialchars($chk['label']) ?></div>
              <div class="check-detail">Path: <?= htmlspecialchars(basename($chk['path'])) ?>/</div>
            </div>
            <span class="badge <?= $chk['pass'] ? 'badge-pass' : ($key === 'root' ? 'badge-warn' : 'badge-fail') ?>">
              <?php if ($chk['pass']): ?>
                Writable
              <?php else: ?>
                <?= $key === 'root' ? 'Manual Config Fallback' : 'Read-Only' ?>
              <?php endif; ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if (!$critical_pass): ?>
          <div class="alert alert-danger">
            <strong>Check Failed:</strong> Your server does not meet the minimum requirements. Please address the errors highlighted in red above and refresh this page.
          </div>
        <?php endif; ?>

        <div class="card-actions">
          <button class="btn btn-primary" id="btn-goto-2" <?= $critical_pass ? '' : 'disabled' ?>>
            Configure Database &rarr;
          </button>
        </div>
      </div>

      <!-- STEP 2: DATABASE & SERVER CONFIG FORM -->
      <div class="wizard-step" id="step-2">
        <h2>Database &amp; Site Configuration</h2>
        <p class="description">Enter the credentials for your MySQL database. If the database does not exist, the installer will attempt to create it for you automatically.</p>

        <div id="step-2-error" class="alert alert-danger" style="display: none;">
          <div class="error-msg"></div>
          <button class="btn-log-toggle" onclick="toggleLogs('step-2-log')">Show Diagnostics</button>
          <div id="step-2-log" class="log-container">
            <textarea class="log-textarea" readonly id="step-2-log-text"></textarea>
          </div>
        </div>

        <form id="db-form" onsubmit="event.preventDefault();">
          <div class="form-group">
            <label for="db_host">Database Host</label>
            <input type="text" id="db_host" name="db_host" class="form-control" placeholder="localhost" value="localhost" required>
          </div>
          <div class="form-group">
            <label for="db_name">Database Name</label>
            <input type="text" id="db_name" name="db_name" class="form-control" placeholder="diy_lab_db" value="diy_lab_db" required>
          </div>
          <div class="form-group">
            <label for="db_user">Database User</label>
            <input type="text" id="db_user" name="db_user" class="form-control" placeholder="root" value="root" required>
          </div>
          <div class="form-group">
            <label for="db_pass">Database Password</label>
            <input type="password" id="db_pass" name="db_pass" class="form-control" placeholder="Database password (usually empty or root)">
          </div>
          <div class="form-group">
            <label for="site_url">Site Base URL</label>
            <input type="url" id="site_url" name="site_url" class="form-control" value="<?= htmlspecialchars($detected_site_url) ?>" placeholder="http://example.com/diy-inventory" required>
          </div>

          <div class="card-actions">
            <button class="btn btn-secondary" onclick="gotoStep(1)">&larr; Back</button>
            <button class="btn btn-primary" id="btn-test-conn" onclick="testConnection()">
              <span class="spinner" id="test-spinner"></span>
              <span id="test-btn-text">Test Connection</span>
            </button>
          </div>
        </form>
      </div>

      <!-- STEP 3: DATABASE INITIALIZATION & CONFIG GENERATION -->
      <div class="wizard-step" id="step-3">
        <h2>Initializing Database</h2>
        <p class="description" id="step-3-desc">Installing base schema and generating production configuration file...</p>

        <div id="step-3-status" class="status-text">
          Initializing setup execution...
        </div>

        <div id="step-3-error" class="alert alert-danger" style="display: none;">
          <div class="error-msg"></div>
          <button class="btn-log-toggle" onclick="toggleLogs('step-3-log')">Show Diagnostics</button>
          <div id="step-3-log" class="log-container">
            <textarea class="log-textarea" readonly id="step-3-log-text"></textarea>
          </div>
        </div>

        <!-- Manual config fallback instructions -->
        <div id="manual-config-wrap" style="display: none;">
          <div class="alert alert-info">
            <strong>Manual Setup Required:</strong> The installer completed database operations successfully, but was unable to write `config.php` automatically due to server write permissions.
          </div>
          <p class="description">Please create a file named <strong>config.php</strong> in the root folder of your installation and paste the following content inside it:</p>
          <div class="code-block" id="manual-config-code"></div>
          <p class="description">Once you have uploaded the file, click the button below to verify configuration and proceed.</p>
        </div>

        <div class="card-actions">
          <button class="btn btn-secondary" id="btn-step-3-back" onclick="gotoStep(2)">&larr; Back</button>
          <button class="btn btn-primary" id="btn-step-3-next" disabled>
            <span class="spinner" id="install-spinner"></span>
            <span id="install-btn-text">Verifying...</span>
          </button>
        </div>
      </div>

      <!-- STEP 4: ADMIN ACCOUNT CREATION -->
      <div class="wizard-step" id="step-4">
        <h2>Create Administrator</h2>
        <p class="description">Configure the primary access credentials to manage your DIY Lab. These will replace the default installation password.</p>

        <div id="step-4-error" class="alert alert-danger" style="display: none;">
          <div class="error-msg"></div>
          <button class="btn-log-toggle" onclick="toggleLogs('step-4-log')">Show Diagnostics</button>
          <div id="step-4-log" class="log-container">
            <textarea class="log-textarea" readonly id="step-4-log-text"></textarea>
          </div>
        </div>

        <form id="admin-form" onsubmit="event.preventDefault();">
          <div class="form-group">
            <label for="admin_username">Admin Username</label>
            <input type="text" id="admin_username" name="admin_username" class="form-control" placeholder="admin" value="admin" required autocomplete="username">
          </div>
          <div class="form-group">
            <label for="admin_email">Admin Email Address</label>
            <input type="email" id="admin_email" name="admin_email" class="form-control" placeholder="admin@domain.local" required autocomplete="email">
          </div>
          <div class="form-group">
            <label for="admin_password">Admin Password (min. 6 characters)</label>
            <input type="password" id="admin_password" name="admin_password" class="form-control" placeholder="••••••••" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="admin_confirm_password">Confirm Admin Password</label>
            <input type="password" id="admin_confirm_password" name="admin_confirm_password" class="form-control" placeholder="••••••••" required autocomplete="new-password">
          </div>

          <div class="card-actions">
            <!-- No back button to prevent config generation reflows, but they can exit if needed -->
            <button class="btn btn-primary" id="btn-create-admin" onclick="createAdmin()">
              <span class="spinner" id="admin-spinner"></span>
              <span id="admin-btn-text">Finalize Setup</span>
            </button>
          </div>
        </form>
      </div>

      <!-- STEP 5: SUCCESS & CLEANUP -->
      <div class="wizard-step" id="step-5">
        <h2>Installation Complete!</h2>
        <p class="description">DIY Lab has been successfully installed. The configuration file was successfully written, database schema imported, and admin account registered.</p>

        <div class="alert alert-success">
          <strong>Setup Complete:</strong> The database was initialized, and your administrator user credentials are active.
        </div>

        <div id="cleanup-message" class="alert alert-info">
          Attempting directory cleanup...
        </div>

        <div class="card-actions">
          <a href="../index.php" class="btn btn-primary" style="width: 100%;">
            Go to Login Page &rarr;
          </a>
        </div>
      </div>

    </div>

    <!-- Footer -->
    <div class="footer">
      DIY Lab · Modern Self-Hosted Setup
    </div>

  </div>

  <script>
    // Wizard state variables
    let currentStep = 1;
    let cleanupResult = null;
    const totalSteps = 5;

    // Stepper elements
    const steps = document.querySelectorAll('.step');
    const stepperBar = document.getElementById('stepper-bar');

    // Page-load actions
    document.getElementById('btn-goto-2').addEventListener('click', () => {
      gotoStep(2);
    });

    // Navigation function
    function gotoStep(stepNum) {
      if (stepNum < 1 || stepNum > totalSteps) return;
      
      // Update DOM wizard view classes
      document.querySelectorAll('.wizard-step').forEach(step => {
        step.classList.remove('active');
      });
      document.getElementById(`step-${stepNum}`).classList.add('active');

      // Update stepper indicators
      steps.forEach(indicator => {
        const indStepNum = parseInt(indicator.getAttribute('data-step'));
        indicator.classList.remove('active', 'completed');
        if (indStepNum === stepNum) {
          indicator.classList.add('active');
        } else if (indStepNum < stepNum) {
          indicator.classList.add('completed');
        }
      });

      // Update stepper bar width
      const pct = ((stepNum - 1) / (totalSteps - 1)) * 100;
      stepperBar.style.width = `${pct}%`;

      currentStep = stepNum;

      // Handle step-specific triggers
      if (currentStep === 3) {
        runDatabaseInstallation();
      } else if (currentStep === 5) {
        runCleanup();
      }
    }

    // Toggle logs visibility
    function toggleLogs(logContainerId) {
      const container = document.getElementById(logContainerId);
      if (container.style.display === 'none' || !container.style.display) {
        container.style.display = 'block';
      } else {
        container.style.display = 'none';
      }
    }

    // AJAX helper
    function ajaxPost(data, callback) {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          try {
            const response = JSON.parse(xhr.responseText);
            callback(null, response);
          } catch(e) {
            callback({
              error: 'Invalid response from server. Check server PHP logs.',
              raw: xhr.responseText
            }, null);
          }
        }
      };

      const params = Object.keys(data)
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
        .join('&');
      
      xhr.send(params);
    }

    // AJAX: Test Database Connection (Step 2)
    function testConnection() {
      const form = document.getElementById('db-form');
      const dbHost = document.getElementById('db_host').value;
      const dbName = document.getElementById('db_name').value;
      const dbUser = document.getElementById('db_user').value;
      const dbPass = document.getElementById('db_pass').value;

      const btn = document.getElementById('btn-test-conn');
      const spinner = document.getElementById('test-spinner');
      const btnText = document.getElementById('test-btn-text');
      const errorDiv = document.getElementById('step-2-error');

      // Reset error state
      errorDiv.style.display = 'none';
      btn.disabled = true;
      spinner.style.display = 'inline-block';
      btnText.textContent = 'Testing...';

      ajaxPost({
        action: 'test_connection',
        db_host: dbHost,
        db_name: dbName,
        db_user: dbUser,
        db_pass: dbPass
      }, function(err, res) {
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Test Connection';

        if (err) {
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Network Error:</strong> ${err.error}`;
          document.getElementById('step-2-log-text').value = err.raw;
          return;
        }

        if (res.success) {
          // Success! Auto transition to database setup step
          gotoStep(3);
        } else {
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Connection Failed:</strong> ${res.error}`;
          document.getElementById('step-2-log-text').value = res.log || res.error;
        }
      });
    }

    // AJAX: Run Schema & Config Generation (Step 3)
    function runDatabaseInstallation() {
      const dbHost = document.getElementById('db_host').value;
      const dbName = document.getElementById('db_name').value;
      const dbUser = document.getElementById('db_user').value;
      const dbPass = document.getElementById('db_pass').value;
      const siteUrl = document.getElementById('site_url').value;

      const statusText = document.getElementById('step-3-status');
      const errorDiv = document.getElementById('step-3-error');
      
      const spinner = document.getElementById('install-spinner');
      const btnText = document.getElementById('install-btn-text');
      const nextBtn = document.getElementById('btn-step-3-next');
      const backBtn = document.getElementById('btn-step-3-back');

      statusText.innerHTML = "Creating database, executing schema.sql, and building config file...";
      errorDiv.style.display = 'none';
      
      nextBtn.disabled = true;
      backBtn.disabled = true;
      spinner.style.display = 'inline-block';
      btnText.textContent = 'Installing...';

      ajaxPost({
        action: 'install_db',
        db_host: dbHost,
        db_name: dbName,
        db_user: dbUser,
        db_pass: dbPass,
        site_url: siteUrl
      }, function(err, res) {
        spinner.style.display = 'none';

        if (err) {
          statusText.innerHTML = "Installation halted due to a critical error.";
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Install Error:</strong> ${err.error}`;
          document.getElementById('step-3-log-text').value = err.raw;
          backBtn.disabled = false;
          return;
        }

        if (res.success) {
          statusText.innerHTML = "Database setup completed successfully!";
          
          if (res.manual_fallback) {
            // Root wasn't writable, show manual instruction
            document.getElementById('manual-config-wrap').style.display = 'block';
            document.getElementById('manual-config-code').textContent = res.config_content;
            
            // Allow them to click "Next" once they verify they've uploaded it
            btnText.textContent = 'I Have Uploaded config.php';
            nextBtn.disabled = false;
            
            nextBtn.onclick = function() {
              // Wait, let's verify if config.php exists now by requesting step-4 or reloading config check
              location.reload(); // Re-check config existence on page load, which will auto-redirect if present
            };
          } else {
            // Config was written automatically, proceed straight to admin creation
            btnText.textContent = 'Create Admin Account &rarr;';
            nextBtn.disabled = false;
            nextBtn.onclick = function() {
              gotoStep(4);
            };
          }
        } else {
          statusText.innerHTML = "Setup execution failed.";
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Schema Execution Failed:</strong> ${res.error}`;
          document.getElementById('step-3-log-text').value = res.log || res.error;
          backBtn.disabled = false;
        }
      });
    }

    // AJAX: Create Administrator Account (Step 4)
    function createAdmin() {
      const username = document.getElementById('admin_username').value;
      const email = document.getElementById('admin_email').value;
      const password = document.getElementById('admin_password').value;
      const confirmPassword = document.getElementById('admin_confirm_password').value;

      const btn = document.getElementById('btn-create-admin');
      const spinner = document.getElementById('admin-spinner');
      const btnText = document.getElementById('admin-btn-text');
      const errorDiv = document.getElementById('step-4-error');

      if (!username || !email || !password || !confirmPassword) {
        alert('Please fill out all administrator fields.');
        return;
      }

      if (password.length < 6) {
        alert('Password must be at least 6 characters.');
        return;
      }

      if (password !== confirmPassword) {
        alert('Passwords do not match.');
        return;
      }

      errorDiv.style.display = 'none';
      btn.disabled = true;
      spinner.style.display = 'inline-block';
      btnText.textContent = 'Creating Account...';

      ajaxPost({
        action: 'create_admin',
        admin_username: username,
        admin_email: email,
        admin_password: password,
        admin_confirm_password: confirmPassword
      }, function(err, res) {
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Finalize Setup';

        if (err) {
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Network Error:</strong> ${err.error}`;
          document.getElementById('step-4-log-text').value = err.raw;
          return;
        }

        if (res.success) {
          cleanupResult = res.cleanup;
          gotoStep(5);
        } else {
          errorDiv.style.display = 'block';
          errorDiv.querySelector('.error-msg').innerHTML = `<strong>Admin Setup Failed:</strong> ${res.error}`;
          document.getElementById('step-4-log-text').value = res.log || res.error;
        }
      });
    }

    // Clean up directories (Step 5)
    function runCleanup() {
      const msgDiv = document.getElementById('cleanup-message');
      
      setTimeout(function() {
        if (!cleanupResult) {
          msgDiv.className = "alert alert-danger";
          msgDiv.innerHTML = "<strong>Security Warning:</strong> Could not rename/delete the installer directory. <strong>Please manually delete the install/ folder</strong> before launching the application.";
          return;
        }

        if (cleanupResult.renamed) {
          msgDiv.className = "alert alert-success";
          msgDiv.innerHTML = "<strong>Security Recommendation:</strong> The <code>install/</code> folder has been successfully renamed and disabled automatically. You are ready to log in.";
        } else {
          msgDiv.className = "alert alert-danger";
          msgDiv.innerHTML = "<strong>Security Warning:</strong> " + cleanupResult.message;
        }
      }, 1000);
    }
  </script>
</body>
</html>
