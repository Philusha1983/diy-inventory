<?php
require 'db.php';

$api_key = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'api_key'")->fetchColumn();
if (!$api_key) {
    die("No API key found in DB.\n");
}

$newKeys = json_decode(file_get_contents(__DIR__ . '/dynamic_keys.json'), true);
if (!$newKeys) {
    die("No dynamic_keys.json found.\n");
}

// Convert newKeys to a simple array to send
$stringsToTranslate = [];
foreach ($newKeys as $key => $val) {
    $stringsToTranslate[] = [
        'id' => $key,
        'text' => $val
    ];
}

$languages = [
    'es' => 'Spanish',
    'he' => 'Hebrew',
    'uk' => 'Ukrainian'
];

foreach ($languages as $code => $langName) {
    echo "Translating to $langName...\n";
    $filePath = __DIR__ . "/../assets/locales/{$code}.json";
    $dict = json_decode(file_get_contents($filePath), true) ?: [];

    // Chunk into 50 items per request to avoid huge payloads
    $chunks = array_chunk($stringsToTranslate, 50);

    foreach ($chunks as $index => $chunk) {
        echo "  Chunk " . ($index + 1) . "/" . count($chunks) . "...\n";
        
        $prompt = "You are a professional translator for a web application called 'DIY Lab Inventory System'. Translate the following JSON array of strings into $langName. Maintain the 'id' field exactly, and put the translated text in the 'text' field. Do not translate code, HTML tags, or placeholders like {{var}} or <?= \$var ?>.\n\n" . json_encode($chunk, JSON_UNESCAPED_UNICODE);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($api_key);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            echo "API Error: " . print_r($data['error'], true) . "\n";
            continue;
        }

        $translatedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
        $translatedArr = json_decode($translatedText, true) ?: [];

        foreach ($translatedArr as $item) {
            if (isset($item['id']) && isset($item['text'])) {
                $parts = explode('.', $item['id'], 2);
                if (count($parts) === 2) {
                    $group = $parts[0];
                    $key = $parts[1];
                    if (!isset($dict[$group])) {
                        $dict[$group] = [];
                    }
                    $dict[$group][$key] = $item['text'];
                }
            }
        }
        
        // Save after each chunk
        file_put_contents($filePath, json_encode($dict, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

echo "Translation complete!\n";
