<?php
/**
 * ai_helper.php — Centralised AI API Proxy
 * Include this after db.php. Provides call_ai_api($prompt, $image_paths).
 * Fetches the API key and provider from the settings table — never hardcoded.
 */

function call_ai_api(string $prompt, array $image_paths = []): array {
    global $pdo;

    // Fetch all settings from DB
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $provider = $settings['ai_provider'] ?? 'gemini';
    $api_key  = $settings['api_key']     ?? '';

    if (!$api_key) {
        return ['error' => 'No API key configured. Please visit Settings.'];
    }

    // ─── GEMINI ────────────────────────────────────────────────────────────
    if ($provider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($api_key);

        // Build parts: start with the text prompt
        $parts = [['text' => $prompt]];

        // Attach images as inline base64 blobs
        foreach ($image_paths as $path) {
            if (!file_exists($path)) continue;
            $ext       = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime_type = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $data      = base64_encode(file_get_contents($path));
            $parts[]   = [
                'inline_data' => [
                    'mime_type' => $mime_type,
                    'data'      => $data,
                ],
            ];
        }

        $payload = [
            'contents' => [[
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'temperature'     => 0.4,
                'maxOutputTokens' => 2048,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) return ['error' => 'cURL error: ' . $err];

        $decoded = json_decode($response, true);
        return $decoded ?? ['error' => 'Invalid JSON from Gemini.'];
    }

    // ─── OPENAI ────────────────────────────────────────────────────────────
    if ($provider === 'openai') {
        $url = 'https://api.openai.com/v1/chat/completions';

        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($image_paths as $path) {
            if (!file_exists($path)) continue;
            $ext       = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime_type = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $data      = base64_encode(file_get_contents($path));
            $content[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => "data:{$mime_type};base64,{$data}"],
            ];
        }

        $payload = [
            'model'      => 'gpt-4o',
            'messages'   => [['role' => 'user', 'content' => $content]],
            'max_tokens' => 2048,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) return ['error' => 'cURL error: ' . $err];

        $decoded = json_decode($response, true);
        return $decoded ?? ['error' => 'Invalid JSON from OpenAI.'];
    }

    return ['error' => "Unknown AI provider: $provider"];
}

/**
 * Helper: extract the text content from an AI response regardless of provider.
 */
function extract_ai_text(array $response, string $provider = 'gemini'): string {
    if (isset($response['error'])) {
        return '[AI Error] ' . $response['error'];
    }

    if ($provider === 'openai') {
        return $response['choices'][0]['message']['content'] ?? '';
    }

    // Gemini
    return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

/**
 * Helper: strip markdown code fences and return clean JSON string.
 */
function clean_json_response(string $text): string {
    // Remove ```json ... ``` and ``` ... ``` wrappers
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/^```\s*/i',     '', $text);
    $text = preg_replace('/```\s*$/i',     '', $text);
    return trim($text);
}
