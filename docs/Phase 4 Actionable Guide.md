# **Phase 4: UI-Based AI Configuration**

In this phase, we build the interface to manage your API keys and the logic to communicate with external AI models (like Gemini or OpenAI).

### **Task 4.1: Settings Interface (settings.php)**

This page allows you to save your API provider preference and your secret key securely in the database.

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { header("Location: index.php"); exit; }

$message \= "";

if ($\_SERVER\['REQUEST\_METHOD'\] \== 'POST') {  
    $provider \= $\_POST\['ai\_provider'\];  
    $api\_key \= $\_POST\['api\_key'\];

    // Update or Insert Provider  
    $stmt \= $pdo-\>prepare("INSERT INTO settings (setting\_key, setting\_value) VALUES ('ai\_provider', ?) ON DUPLICATE KEY UPDATE setting\_value \= ?");  
    $stmt-\>execute(\[$provider, $provider\]);

    // Update or Insert API Key  
    $stmt \= $pdo-\>prepare("INSERT INTO settings (setting\_key, setting\_value) VALUES ('api\_key', ?) ON DUPLICATE KEY UPDATE setting\_value \= ?");  
    $stmt-\>execute(\[$api\_key, $api\_key\]);

    $message \= "Settings updated successfully\!";  
}

// Fetch current settings  
$stmt \= $pdo-\>query("SELECT \* FROM settings");  
$settings\_raw \= $stmt-\>fetchAll();  
$settings \= \[\];  
foreach ($settings\_raw as $row) { $settings\[$row\['setting\_key'\]\] \= $row\['setting\_value'\]; }  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>AI Configuration\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 40px; max-width: 500px; margin: auto; }  
        .form-group { margin-bottom: 20px; }  
        label { display: block; margin-bottom: 5px; font-weight: bold; }  
        select, input { width: 100%; padding: 10px; border: 1px solid \#ccc; border-radius: 4px; }  
        button { padding: 10px 20px; background: \#007bff; color: white; border: none; cursor: pointer; border-radius: 4px; }  
        .msg { color: green; margin-bottom: 20px; }  
    \</style\>  
\</head\>  
\<body\>  
    \<a href="dashboard.php"\>← Back to Dashboard\</a\>  
    \<h1\>AI Settings\</h1\>  
    \<?php if($message) echo "\<p class='msg'\>$message\</p\>"; ?\>  
      
    \<form method="post"\>  
        \<div class="form-group"\>  
            \<label\>AI Provider:\</label\>  
            \<select name="ai\_provider"\>  
                \<option value="gemini" \<?php if(($settings\['ai\_provider'\] ?? '') \== 'gemini') echo 'selected'; ?\>\>Google Gemini\</option\>  
                \<option value="openai" \<?php if(($settings\['ai\_provider'\] ?? '') \== 'openai') echo 'selected'; ?\>\>OpenAI (GPT-4o)\</option\>  
            \</select\>  
        \</div\>  
        \<div class="form-group"\>  
            \<label\>API Key:\</label\>  
            \<input type="password" name="api\_key" value="\<?php echo htmlspecialchars($settings\['api\_key'\] ?? ''); ?\>" placeholder="Enter your API Key"\>  
        \</div\>  
        \<button type="submit"\>Save Configuration\</button\>  
    \</form\>  
\</body\>  
\</html\>

### **Task 4.2: The AI Proxy (ai\_helper.php)**

This is a reusable background script. It doesn't display a page; instead, other files will "include" it to send data to the AI.

\<?php  
function call\_ai\_api($prompt, $image\_paths \= \[\]) {  
    global $pdo;

    // 1\. Get settings from DB  
    $stmt \= $pdo-\>query("SELECT \* FROM settings");  
    $settings \= \[\];  
    foreach ($stmt-\>fetchAll() as $row) { $settings\[$row\['setting\_key'\]\] \= $row\['setting\_value'\]; }

    $provider \= $settings\['ai\_provider'\] ?? 'gemini';  
    $api\_key \= $settings\['api\_key'\] ?? '';

    if (\!$api\_key) return \["error" \=\> "No API Key found."\];

    if ($provider \=== 'gemini') {  
        // Logic for Gemini Pro Vision API  
        $url \= "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api\_key;  
          
        $inline\_data \= \[\];  
        foreach ($image\_paths as $path) {  
            $type \= pathinfo($path, PATHINFO\_EXTENSION);  
            $data \= base64\_encode(file\_get\_contents($path));  
            $inline\_data\[\] \= \[  
                "mime\_type" \=\> "image/" . ($type \== 'jpg' ? 'jpeg' : $type),  
                "data" \=\> $data  
            \];  
        }

        $payload \= \[  
            "contents" \=\> \[\[  
                "parts" \=\> array\_merge(  
                    \[\["text" \=\> $prompt\]\],  
                    $inline\_data  
                )  
            \]\]  
        \];

        $ch \= curl\_init($url);  
        curl\_setopt($ch, CURLOPT\_HTTPHEADER, \['Content-Type: application/json'\]);  
        curl\_setopt($ch, CURLOPT\_POSTFIELDS, json\_encode($payload));  
        curl\_setopt($ch, CURLOPT\_RETURNTRANSFER, true);  
        $response \= curl\_exec($ch);  
        curl\_close($ch);

        return json\_decode($response, true);  
    }  
      
    // Future: Add OpenAI logic block here  
    return \["error" \=\> "Provider logic not implemented yet."\];  
}

### **Phase 4 Test Case**

1. Open settings.php.  
2. Select "Google Gemini" and paste your actual API key.  
3. Click "Save Configuration".  
4. Go back to settings.php to ensure the key is still there (it should be masked as a password).  
5. *Internal Success:* You have now bridged your local code with the cloud AI intelligence.