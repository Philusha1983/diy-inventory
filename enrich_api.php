<?php
/**
 * enrich_api.php — Component Web Enrichment Endpoint
 *
 * POST body (JSON): { "item_id": 42 }
 *
 * Fetches product_url and/or datasheet_url for the given component,
 * extracts meaningful plain text (strips HTML, scripts, styles),
 * caches the result in enriched_data / enriched_at columns,
 * and returns a JSON summary of what was found.
 */
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$item_id = (int)($input['item_id'] ?? 0);

if (!$item_id) {
    echo json_encode(['ok' => false, 'error' => 'Missing item_id.']);
    exit;
}

// Load item
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Component not found.']);
    exit;
}

$urls = array_filter([
    'Product page'  => $item['product_url']   ?? '',
    'Datasheet'     => $item['datasheet_url']  ?? '',
]);

if (empty($urls)) {
    echo json_encode(['ok' => false, 'error' => 'No URLs saved for this component. Add a Product URL or Datasheet URL first.']);
    exit;
}

// ── Fetch and parse each URL ────────────────────────────────────────────────
function fetch_url_text(string $url, int $max_chars = 3000): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; DIYLabBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
    ]);
    $html     = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err)         return ['ok' => false, 'error' => "cURL: $err"];
    if ($http_code >= 400) return ['ok' => false, 'error' => "HTTP $http_code from $url"];
    if (!$html)       return ['ok' => false, 'error' => 'Empty response'];

    // Remove scripts, styles, nav, footer, forms
    $html = preg_replace('/<(script|style|nav|footer|header|form|noscript)[^>]*>.*?<\/\1>/is', '', $html);
    // Strip all remaining HTML tags
    $text = strip_tags($html);
    // Collapse whitespace
    $text = preg_replace('/\s{2,}/', "\n", $text);
    $text = trim($text);
    // Truncate to max_chars
    if (mb_strlen($text) > $max_chars) {
        $text = mb_substr($text, 0, $max_chars) . '…';
    }

    return ['ok' => true, 'text' => $text];
}

$sections  = [];
$log       = [];
$chars_per_url = intval(3000 / max(1, count($urls)));

foreach ($urls as $label => $url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $log[] = "⚠ Skipped {$label}: invalid URL";
        continue;
    }
    $result = fetch_url_text($url, $chars_per_url);
    if ($result['ok']) {
        $sections[] = "=== {$label} ({$url}) ===\n" . $result['text'];
        $log[]      = "✅ Fetched {$label} (" . mb_strlen($result['text']) . " chars)";
    } else {
        $log[] = "❌ {$label}: " . $result['error'];
    }
}

if (empty($sections)) {
    echo json_encode(['ok' => false, 'error' => 'Could not fetch any URLs. ' . implode('; ', $log)]);
    exit;
}

$enriched = implode("\n\n", $sections);

// Save to DB
$stmt = $pdo->prepare("UPDATE inventory SET enriched_data = ?, enriched_at = NOW() WHERE id = ?");
$stmt->execute([$enriched, $item_id]);

echo json_encode([
    'ok'      => true,
    'chars'   => mb_strlen($enriched),
    'sources' => count($sections),
    'log'     => $log,
    'preview' => mb_substr($enriched, 0, 300) . '…',
]);
