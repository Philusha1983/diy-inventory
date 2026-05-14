<?php
/**
 * identify_api.php — AI Component Identification Endpoint (Phase 5)
 * Accepts POST with images[], sends them to AI, returns JSON.
 */
require 'db.php';
require 'ai_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['images'])) {
    echo json_encode(['error' => 'No images received.']);
    exit;
}

// Get the current provider
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';

// Temporarily save uploaded images
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

$temp_paths = [];
foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
    if ($_FILES['images']['error'][$k] !== UPLOAD_ERR_OK) continue;
    $ext   = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));
    $temp  = $upload_dir . 'temp_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($tmp, $temp)) {
        $temp_paths[] = $temp;
    }
}

if (empty($temp_paths)) {
    echo json_encode(['error' => 'Failed to process uploaded images.']);
    exit;
}

$prompt = <<<PROMPT
Analyse these photos of a DIY lab electronic component.
Identify its type, model, and key specifications.
Respond ONLY with a valid JSON object — no markdown, no extra text — using these exact keys:
{
  "name": "short descriptive name (e.g. 'ESP32 Development Board')",
  "model": "exact model or part number if visible (e.g. 'ESP32-WROOM-32')",
  "category": "one of: Microcontroller, Sensor, Actuator, Display, Communication, Power, Passive, Connector, Tool, Other",
  "specs": "key specifications as a readable multi-line string (voltage, current, pins, frequency, etc.)"
}
PROMPT;

$ai_response = call_ai_api($prompt, $temp_paths);

// Clean up temp files
foreach ($temp_paths as $p) { if (file_exists($p)) @unlink($p); }

// Extract text from response
$text = extract_ai_text($ai_response, $provider);

if (str_starts_with($text, '[AI Error]')) {
    echo json_encode(['error' => $text]);
    exit;
}

// Clean JSON
$json_clean = clean_json_response($text);
$data = json_decode($json_clean, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    // Try to extract JSON from inside the text
    preg_match('/\{.*\}/s', $text, $matches);
    $data = $matches ? json_decode($matches[0], true) : null;
}

if (!$data) {
    echo json_encode(['error' => 'AI returned an unparseable response. Try different photos.', 'raw' => substr($text, 0, 500)]);
    exit;
}

// Sanitise and return
echo json_encode([
    'name'     => strip_tags($data['name']     ?? ''),
    'model'    => strip_tags($data['model']    ?? ''),
    'category' => strip_tags($data['category'] ?? ''),
    'specs'    => strip_tags($data['specs']    ?? ''),
]);
