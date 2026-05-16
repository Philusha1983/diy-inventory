<?php
/**
 * bulk_import_wizard_worker.php — Handles a single image group from the wizard.
 * Receives multipart form data: images[] + optional name_hint.
 * Saves images to a temp upload folder, calls AI, inserts item, returns JSON.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

header('Content-Type: application/json');

// Load API settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gemini_api_key','gemini_model')");
$settings = [];
foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];

$api_key = $settings['gemini_api_key'] ?? '';
$model   = $settings['gemini_model']   ?? 'gemini-1.5-flash-latest';

if (!$api_key) { echo json_encode(['status'=>'error','message'=>'No Gemini API key configured.']); exit; }

$name_hint = trim($_POST['name_hint'] ?? '');
$images    = $_FILES['images'] ?? null;

if (!$images || empty($images['tmp_name'])) {
    echo json_encode(['status'=>'error','message'=>'No images received.']); exit;
}

// ── Save uploaded images to a permanent folder ────────────────────────────────
$upload_dir = __DIR__ . '/uploads/wizard/' . uniqid('w_');
mkdir($upload_dir, 0755, true);

$image_paths  = [];
$image_parts  = []; // For Gemini multimodal payload

$names    = $images['name'];
$tmps     = $images['tmp_name'];
$types    = $images['type'];
$errors   = $images['error'];

for ($i = 0; $i < count($tmps); $i++) {
    if ($errors[$i] !== UPLOAD_ERR_OK) continue;
    $ext  = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

    $dest = $upload_dir . '/image_' . str_pad($i+1, 2, '0', STR_PAD_LEFT) . '.' . $ext;
    if (move_uploaded_file($tmps[$i], $dest)) {
        $image_paths[] = str_replace(__DIR__ . '/', '', $dest);

        // Encode for Gemini
        $b64      = base64_encode(file_get_contents($dest));
        $mime     = $types[$i] ?: 'image/jpeg';
        $image_parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => $b64]];
    }
}

if (empty($image_parts)) {
    echo json_encode(['status'=>'error','message'=>'No valid images could be saved.']); exit;
}

// ── Build Gemini prompt ───────────────────────────────────────────────────────
$hint_text = $name_hint ? "The user suggests this might be: \"$name_hint\". " : '';
$prompt = "{$hint_text}Analyse these photos of an electronics/DIY component. Return ONLY valid JSON with these fields:
{
  \"name\": \"full component name\",
  \"model\": \"model or part number, or null\",
  \"category\": \"single category word e.g. Microcontroller, Sensor, Resistor\",
  \"quantity\": 1,
  \"specs\": \"key specs as a short string, or null\",
  \"notes\": \"any other useful notes, or null\"
}";

$payload = [
    'contents' => [[
        'parts' => array_merge($image_parts, [['text' => $prompt]])
    ]],
    'generationConfig' => ['temperature'=>0.2, 'maxOutputTokens'=>1024]
];

// ── Call Gemini API ───────────────────────────────────────────────────────────
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ── Parse response ────────────────────────────────────────────────────────────
$ai_data = [];
$ai_ok   = false;

if ($code === 200) {
    $resp = json_decode($raw, true);
    $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Strip markdown fences
    $text = preg_replace('/^```(?:json)?\s*/i','', trim($text));
    $text = preg_replace('/\s*```$/','', $text);
    $parsed = json_decode($text, true);
    if ($parsed && isset($parsed['name'])) {
        $ai_data = $parsed;
        $ai_ok   = true;
    }
}

// ── Insert into DB ────────────────────────────────────────────────────────────
$name     = $ai_data['name']     ?? ($name_hint ?: 'Unknown Component');
$model_v  = $ai_data['model']    ?? null;
$category = $ai_data['category'] ?? 'Uncategorised';
$qty      = max(1, (int)($ai_data['quantity'] ?? 1));
$specs    = $ai_data['specs']    ?? null;
$notes    = $ai_data['notes']    ?? null;

$stmt = $pdo->prepare("INSERT INTO inventory (name, model, category, quantity, status, specs, notes, image_paths)
    VALUES (:name, :model, :category, :quantity, 'New', :specs, :notes, :image_paths)");
$stmt->execute([
    ':name'        => $name,
    ':model'       => $model_v,
    ':category'    => $category,
    ':quantity'    => $qty,
    ':specs'       => $specs,
    ':notes'       => $notes,
    ':image_paths' => json_encode($image_paths),
]);
$item_id = $pdo->lastInsertId();

echo json_encode([
    'status'   => $ai_ok ? 'ok' : 'ai_fail',
    'item_id'  => $item_id,
    'name'     => $name,
    'category' => $category,
    'images'   => count($image_paths),
    'message'  => $ai_ok ? null : 'AI parse failed; item saved with placeholder name.',
]);
