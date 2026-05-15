<?php
/**
 * bulk_import_worker.php — Processes ONE "bulk import" subfolder per AJAX call.
 *
 * POST body (JSON): { "folder": "Folder Name Here" }
 * Returns JSON:     { "status": "ok|skipped|ai_fail|error|rate_limit",
 *                     "item_id": N, "name": "...", "message": "..." }
 */

// ── Safety-first setup ────────────────────────────────────────────────────────
ob_start();
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');
set_time_limit(120);

// Shutdown function: catches PHP fatal errors / OOM that bypass try-catch
register_shutdown_function(function () {
    $err = error_get_last();
    $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($err && in_array($err['type'], $fatals)) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Server crash: ' . $err['message'] . ' (line ' . $err['line'] . ')',
        ]);
    }
});

header('Content-Type: application/json; charset=utf-8');

// ── Helper: flush JSON and exit ───────────────────────────────────────────────
function json_out(array $data): never {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($data);
    exit;
}

// ── Requires ──────────────────────────────────────────────────────────────────
try {
    require 'db.php';
    require 'ai_helper.php';
    require 'image_helper.php';
} catch (\Throwable $e) {
    json_out(['status' => 'error', 'message' => 'Startup error: ' . $e->getMessage()]);
}

session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['authenticated'])) {
    http_response_code(403);
    json_out(['status' => 'error', 'message' => 'Not authenticated.']);
}

// ── Wrap all logic in try-catch ───────────────────────────────────────────────
try {

// ── Input validation ──────────────────────────────────────────────────────────
$raw_input = file_get_contents('php://input');
$input     = json_decode($raw_input, true);

// Do NOT trim() — some folder names legitimately end with a space on disk.
// Validate against path-traversal sequences instead.
$folder = $input['folder'] ?? '';

if (!$folder) {
    json_out(['status' => 'error', 'message' => 'No folder specified.']);
}
if (str_contains($folder, '..') || str_contains($folder, '/') || str_contains($folder, "\\")) {
    json_out(['status' => 'error', 'message' => 'Invalid folder name (path traversal rejected).']);
}

$base_dir = realpath(__DIR__ . '/bulk import');
if (!$base_dir) {
    json_out(['status' => 'error', 'message' => '"bulk import" directory not found.']);
}

$folder_path = realpath($base_dir . '/' . $folder);

// Secondary guard: resolved path must sit inside base_dir
if (!$folder_path || !str_starts_with($folder_path, $base_dir . DIRECTORY_SEPARATOR)) {
    json_out(['status' => 'error', 'message' => 'Folder not found: ' . basename($folder)]);
}
if (!is_dir($folder_path)) {
    json_out(['status' => 'error', 'message' => 'Not a directory: ' . basename($folder)]);
}

// ── Parse description.txt ─────────────────────────────────────────────────────
$desc_file    = $folder_path . '/description.txt';
$product_name = '';
$product_url  = '';

if (file_exists($desc_file)) {
    $desc = file_get_contents($desc_file);
    if ($desc !== false) {
        if (preg_match('/^Product Name\s*:\s*(.+)/mi', $desc, $m)) {
            $product_name = trim($m[1]);
        }
        if (preg_match('/^Final URL\s*:\s*(.+)/mi', $desc, $m)) {
            $product_url = trim($m[1]);
        } elseif (preg_match('/^Original URL\s*:\s*(.+)/mi', $desc, $m)) {
            $product_url = trim($m[1]);
        }
    }
}
if (!$product_name) {
    $product_name = $folder; // fallback to folder name
}

// ── Duplicate detection ───────────────────────────────────────────────────────
if ($product_url) {
    $dup = $pdo->prepare("SELECT id, name FROM inventory WHERE product_url = ? LIMIT 1");
    $dup->execute([$product_url]);
    if ($row = $dup->fetch()) {
        json_out([
            'status'  => 'skipped',
            'item_id' => $row['id'],
            'name'    => $row['name'],
            'message' => 'Already imported (URL match).',
        ]);
    }
}

// ── Collect image files ───────────────────────────────────────────────────────
$image_files = glob(
    $folder_path . '/image_*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}',
    GLOB_BRACE
) ?: [];
sort($image_files);

if (empty($image_files)) {
    json_out(['status' => 'error', 'name' => $product_name, 'message' => 'No images found in folder.']);
}

// ── Load AI settings ──────────────────────────────────────────────────────────
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';
$api_key  = $settings['api_key']     ?? '';

// ── AI Identification (first image only to save quota) ────────────────────────
$ai_name     = $product_name;
$ai_model    = '';
$ai_category = 'Other';
$ai_specs    = '';
$ai_status   = 'ok';
$ai_message  = '';

if (!$api_key) {
    $ai_status  = 'ai_fail';
    $ai_message = 'No API key — name taken from product listing.';
} else {
    $prompt = <<<PROMPT
Analyse this photo of a DIY lab electronic component.
Product listing title for context: "{$product_name}"
Respond ONLY with a valid JSON object — no markdown, no extra text:
{
  "name": "short descriptive name (e.g. 'ESP32 Development Board')",
  "model": "exact model or part number if visible, or empty string",
  "category": "one of: Microcontroller, Sensor, Actuator, Display, Communication, Power, Passive, Connector, Tool, Other",
  "specs": "key specs as a readable multi-line string (voltage, current, interface, etc.)"
}
PROMPT;

    $ai_response = call_ai_api($prompt, [$image_files[0]]);
    $text        = extract_ai_text($ai_response, $provider);

    if (str_starts_with($text, '[AI Error]')) {
        $raw = substr($text, strlen('[AI Error] '));
        if (str_contains($raw, '429') || str_contains($raw, 'quota') || str_contains($raw, 'RESOURCE_EXHAUSTED')) {
            json_out([
                'status'  => 'rate_limit',
                'name'    => $product_name,
                'message' => 'API rate limit — pausing before retry.',
            ]);
        }
        $ai_status  = 'ai_fail';
        $ai_message = 'AI error: ' . substr($raw, 0, 200);
    } else {
        $clean = clean_json_response($text);
        $parsed = json_decode($clean, true);
        if (!$parsed) {
            preg_match('/\{.*\}/s', $text, $mm);
            $parsed = !empty($mm[0]) ? json_decode($mm[0], true) : null;
        }
        if ($parsed && is_array($parsed)) {
            $ai_name     = strip_tags($parsed['name']     ?? $product_name);
            $ai_model    = strip_tags($parsed['model']    ?? '');
            $ai_category = strip_tags($parsed['category'] ?? 'Other');
            $ai_specs    = strip_tags($parsed['specs']    ?? '');
        } else {
            $ai_status  = 'ai_fail';
            $ai_message = 'AI response not parseable — name from listing used.';
        }
    }
}

// ── Process images through GD ─────────────────────────────────────────────────
$upload_dir  = 'uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$saved_paths  = [];
$image_errors = [];

foreach ($image_files as $img_path) {
    try {
        $base   = time() . '_' . uniqid();
        $result = process_image($img_path, $upload_dir, $base);
        if ($result) {
            $saved_paths[] = $result['full'];
        } else {
            $image_errors[] = basename($img_path) . ': GD processing failed';
        }
    } catch (\Throwable $imgErr) {
        $image_errors[] = basename($img_path) . ': ' . $imgErr->getMessage();
    }
    usleep(30000); // 30ms between images
}

// If ALL images failed, report but still insert with no images (don't block import)
if (empty($saved_paths)) {
    $ai_message = 'Image processing failed for all photos. '
                . implode('; ', array_slice($image_errors, 0, 3));
    // Don't hard-fail — still insert the item so user can manually add photos
}

// ── Insert into DB ────────────────────────────────────────────────────────────
$image_json = json_encode($saved_paths);
$notes_val  = implode("\n", array_filter([
    $ai_message,
    !empty($image_errors) ? 'Image errors: ' . implode('; ', array_slice($image_errors, 0, 3)) : '',
]));

$stmt = $pdo->prepare(
    "INSERT INTO inventory
       (name, model, category, quantity, status, specs, location, image_paths, product_url, notes)
     VALUES (?, ?, ?, 1, 'New', ?, 'Bulk Import', ?, ?, ?)"
);
$stmt->execute([
    $ai_name,
    $ai_model,
    $ai_category,
    $ai_specs,
    $image_json,
    $product_url ?: null,
    $notes_val ?: null,
]);
$item_id = (int)$pdo->lastInsertId();

json_out([
    'status'   => $ai_status,
    'item_id'  => $item_id,
    'name'     => $ai_name,
    'model'    => $ai_model,
    'category' => $ai_category,
    'images'   => count($saved_paths),
    'message'  => $ai_message,
]);

// ── Global catch ──────────────────────────────────────────────────────────────
} catch (\Throwable $e) {
    json_out([
        'status'  => 'error',
        'message' => 'Worker exception: ' . $e->getMessage()
                   . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']',
    ]);
}
