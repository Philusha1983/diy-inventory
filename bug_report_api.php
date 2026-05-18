<?php
/**
 * bug_report_api.php — Handles incoming bug reports with screenshots
 */
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON input']));
}

$description = trim($input['description'] ?? '');
$destination = $input['destination'] ?? 'admin';
$email       = trim($input['email'] ?? '');
$image_data  = $input['image'] ?? ''; // base64 string

if (empty($description)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Description is required']));
}

// 1. Save Image
$image_url = '';
if (!empty($image_data)) {
    // Extract base64
    if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
        $image_data = substr($image_data, strpos($image_data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif

        if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid image type']));
        }

        $image_data = base64_decode($image_data);

        if ($image_data === false) {
            http_response_code(400);
            exit(json_encode(['error' => 'Base64 decode failed']));
        }

        $upload_dir = 'uploads/bugs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate a random, unguessable filename so it's pseudo-secure
        $filename = 'bug_' . bin2hex(random_bytes(16)) . '.' . $type;
        $filepath = $upload_dir . $filename;

        file_put_contents($filepath, $image_data);

        // Determine base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        
        $image_url = "$protocol://$host$path/$filepath";
    }
}

// Fetch settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$github_repo = $settings['github_repo'] ?? '';
$github_pat  = $settings['github_pat'] ?? '';
$admin_email = $settings['admin_email'] ?? 'admin@example.com'; // fallback if not set

$response_msg = "Bug report submitted successfully.";
$ticket_link = null;

// 2. Handle Destination
if ($destination === 'github') {
    if (empty($github_repo) || empty($github_pat)) {
        http_response_code(400);
        exit(json_encode(['error' => 'GitHub integration is not configured. Please set github_repo and github_pat in the settings table.']));
    }

    $issue_title = "User Bug Report: " . mb_substr($description, 0, 50) . "...";
    $issue_body  = "**Description:**\n$description\n\n";
    if ($image_url) {
        $issue_body .= "**Screenshot:**\n![Screenshot]($image_url)\n\n";
    }
    if ($email) {
        $issue_body .= "*Submitted by: $email*";
    }

    // Call GitHub API
    $ch = curl_init("https://api.github.com/repos/$github_repo/issues");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: DIY-Inventory-App',
        'Authorization: token ' . $github_pat,
        'Accept: application/vnd.github.v3+json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'title' => $issue_title,
        'body' => $issue_body,
        'labels' => ['bug', 'user-report']
    ]));

    $gh_response = curl_exec($ch);
    $gh_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($gh_status === 201) {
        $gh_data = json_decode($gh_response, true);
        $ticket_link = $gh_data['html_url'];
        $response_msg = "Bug report submitted to GitHub.";
    } else {
        http_response_code(500);
        exit(json_encode(['error' => "GitHub API error ($gh_status). Please check your PAT and repository settings."]));
    }
} else {
    // Save to local database (optional, creating a simple bug_reports table if it doesn't exist)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bug_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        description TEXT,
        image_url VARCHAR(255),
        reporter_email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->prepare("INSERT INTO bug_reports (description, image_url, reporter_email) VALUES (?, ?, ?)");
    $stmt->execute([$description, $image_url, $email]);
    $response_msg = "Bug report submitted to Admin.";
}

// 3. Send Email Notification
if ($email) {
    $mail_to = $admin_email;
    $mail_subject = "New Bug Report from " . $email;
    $mail_body = "A new bug report was submitted.\n\nDescription:\n$description\n\n";
    if ($image_url) {
        $mail_body .= "Screenshot:\n$image_url\n\n";
    }
    if ($ticket_link) {
        $mail_body .= "GitHub Ticket:\n$ticket_link\n";
    }

    $headers = "From: noreply@diylab.local\r\n";
    // Using @ to suppress errors if mail server is not configured in local environments like MAMP
    @mail($mail_to, $mail_subject, $mail_body, $headers);
}

echo json_encode([
    'success' => true,
    'message' => $response_msg,
    'ticket_url' => $ticket_link
]);
