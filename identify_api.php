<?php
/**
 * identify_api.php — AI Component Identification Endpoint (Phase 5)
 * Accepts POST with images[], sends them to AI, returns JSON.
 */
ob_start();
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
require 'db.php';
require 'ai_helper.php';
session_start();

ob_clean();
header('Content-Type: application/json');

// ── Auth check ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['error' => 'Session expired. Please refresh the page and log in again.']);
    exit;
}

// ── Detect silent POST-too-large failure ─────────────────────────────────────
// When the POST body exceeds post_max_size, PHP empties $_FILES and $_POST silently.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && empty($_POST)) {
    $max = ini_get('post_max_size');
    echo json_encode([
        'error' => "Upload failed: the photo(s) exceed the server's {$max} limit. "
                 . "Please reduce the image size or compress the photo before uploading."
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (empty($_FILES['images'])) {
    echo json_encode(['error' => 'No images were received by the server. Please select at least one photo and try again.']);
    exit;
}

// ── Check API key is configured ───────────────────────────────────────────────
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';
$api_key  = $settings['api_key']     ?? '';

if (empty(trim($api_key))) {
    echo json_encode([
        'error' => 'No AI API key is configured. Please go to ⚙️ AI Settings, paste your Gemini or OpenAI API key, and save before using Auto-Identify.'
    ]);
    exit;
}

// ── Move uploaded files to temp location ─────────────────────────────────────
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['error' => "Server error: could not create the uploads/ directory. Check folder permissions."]);
        exit;
    }
}

$temp_paths   = [];
$upload_errors = [];

foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
    $err_code = $_FILES['images']['error'][$k];

    if ($err_code === UPLOAD_ERR_OK) {
        $ext  = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));
        $dest = $upload_dir . 'temp_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($tmp, $dest)) {
            $temp_paths[] = $dest;
        } else {
            $upload_errors[] = "Could not save '{$_FILES['images']['name'][$k]}' (disk write failed).";
        }
    } elseif ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
        $max = ini_get('upload_max_filesize');
        $upload_errors[] = "'{$_FILES['images']['name'][$k]}' is too large (limit: {$max}). Compress the photo before uploading.";
    } elseif ($err_code === UPLOAD_ERR_NO_FILE) {
        // silently skip — empty slot
    } else {
        $upload_errors[] = "'{$_FILES['images']['name'][$k]}' failed with PHP error code {$err_code}.";
    }
}

if (empty($temp_paths)) {
    $detail = !empty($upload_errors)
        ? implode(' | ', $upload_errors)
        : 'No valid image files were received. Make sure you select JPEG or PNG photos.';
    echo json_encode(['error' => $detail]);
    exit;
}

// ── Call AI ───────────────────────────────────────────────────────────────────
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

// Clean up temp files immediately after AI call
foreach ($temp_paths as $p) { if (file_exists($p)) @unlink($p); }

// ── Parse AI response ─────────────────────────────────────────────────────────
$text = extract_ai_text($ai_response, $provider);

if (str_starts_with($text, '[AI Error]')) {
    // Make the error human-friendly
    $raw = substr($text, strlen('[AI Error] '), 400);

    if (str_contains($raw, '429') || str_contains($raw, 'quota') || str_contains($raw, 'RESOURCE_EXHAUSTED')) {
        $msg = '⏳ API rate limit reached. Your free-tier daily quota is exhausted. '
             . 'Wait until midnight (Pacific time) for it to reset, or enable billing at '
             . 'console.cloud.google.com to get higher limits.';
    } elseif (str_contains($raw, '404') || str_contains($raw, 'not found')) {
        $msg = '🔧 The AI model could not be found. Your API key may be on a restricted plan. '
             . 'Please go to ⚙️ AI Settings and re-save your key, or try a different key from aistudio.google.com.';
    } elseif (str_contains($raw, '401') || str_contains($raw, 'API_KEY_INVALID') || str_contains($raw, 'invalid')) {
        $msg = '🔑 Invalid API key. Please go to ⚙️ AI Settings and check that your key is correct. '
             . 'Gemini keys start with "AIza…" and can be created at aistudio.google.com/app/apikey.';
    } elseif (str_contains($raw, 'No API key')) {
        $msg = '🔑 No API key configured. Please go to ⚙️ AI Settings and save your Gemini or OpenAI key first.';
    } else {
        $msg = "AI API error: {$raw}";
    }

    echo json_encode(['error' => $msg]);
    exit;
}

// ── Extract JSON from response ────────────────────────────────────────────────
$json_clean = clean_json_response($text);
$data = json_decode($json_clean, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    preg_match('/\{.*\}/s', $text, $matches);
    $data = $matches ? json_decode($matches[0], true) : null;
}

if (!$data) {
    echo json_encode([
        'error' => 'The AI responded but not in the expected format. Try uploading clearer or higher-contrast photos of the component labels.',
        'raw'   => substr($text, 0, 300),
    ]);
    exit;
}

// ── Return sanitised fields ───────────────────────────────────────────────────
echo json_encode([
    'name'     => strip_tags($data['name']     ?? ''),
    'model'    => strip_tags($data['model']    ?? ''),
    'category' => strip_tags($data['category'] ?? ''),
    'specs'    => strip_tags($data['specs']    ?? ''),
]);
