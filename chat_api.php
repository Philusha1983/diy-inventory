<?php
/**
 * chat_api.php — Context-Aware Chat Backend (Phase 8)
 * Receives a user message, prepends inventory context, calls AI, returns JSON.
 */
require 'db.php';
require 'ai_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['reply' => 'Session expired. Please log in again.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$user_msg = trim($input['message'] ?? '');

if ($user_msg === '') {
    echo json_encode(['reply' => 'No message received.']);
    exit;
}

// Get current provider
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';

// Build inventory context
$stmt  = $pdo->query("SELECT name, model, category, quantity, location, specs FROM inventory ORDER BY category, name");
$items = $stmt->fetchAll();

$total_types = count($items);
$total_qty   = array_sum(array_column($items, 'quantity'));

if ($total_types > 0) {
    $inventory_context = "Current lab inventory ({$total_types} component types, {$total_qty} total units):\n";
    foreach ($items as $i) {
        $inventory_context .= "- {$i['name']} ({$i['model']}) — Category: {$i['category']}, Qty: {$i['quantity']}, Location: {$i['location']}, Specs: {$i['specs']}\n";
    }
} else {
    $inventory_context = "The lab inventory is currently empty.";
}

$prompt = <<<PROMPT
You are the DIY Lab Planning Assistant — a knowledgeable, enthusiastic electronics and maker expert.

Here is the user's current lab inventory:
$inventory_context

The user says: "$user_msg"

Instructions:
- Answer helpfully and technically. Reference specific components from the inventory when relevant.
- If suggesting a project, mention which exact components from the inventory to use.
- Keep answers concise but thorough. Use markdown formatting (headers, code blocks, bullet lists).
- If asked about code, provide real, runnable examples tailored to their specific hardware.
- Tone: technical but friendly and encouraging.
PROMPT;

$response = call_ai_api($prompt);
$reply    = extract_ai_text($response, $provider);

if (str_starts_with($reply, '[AI Error]')) {
    echo json_encode(['reply' => '❌ ' . $reply . "\n\nPlease check your API key in [Settings](settings.php)."]);
    exit;
}

echo json_encode(['reply' => $reply]);
