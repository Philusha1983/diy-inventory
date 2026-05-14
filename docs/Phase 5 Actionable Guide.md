# **Phase 4: UI-Based AI Configuration**

In this phase, we build the interface to manage your API keys and the logic to communicate with external AI models (like Gemini or OpenAI).

### **Task 5.1: The Identification Endpoint (identify\_api.php)**

This file acts as the middleman. It receives images from the browser, sends them to your ai\_helper.php with a specific prompt, and returns the AI's "thoughts" as JSON.

\<?php  
require 'db.php';  
require 'ai\_helper.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { exit; }

if ($\_SERVER\['REQUEST\_METHOD'\] \== 'POST' && \!empty($\_FILES\['images'\])) {  
    $temp\_paths \= \[\];  
      
    // Temporarily save images to process them  
    foreach ($\_FILES\['images'\]\['tmp\_name'\] as $key \=\> $tmp\_name) {  
        $temp\_path \= 'uploads/temp\_' . $\_FILES\['images'\]\['name'\]\[$key\];  
        move\_uploaded\_file($tmp\_name, $temp\_path);  
        $temp\_paths\[\] \= $temp\_path;  
    }

    $prompt \= "Analyse these photos of a DIY lab component. Identify its type, model, and specs.   
               Respond ONLY with a JSON object:   
               { \\"name\\": \\"...\\", \\"model\\": \\"...\\", \\"category\\": \\"...\\", \\"specs\\": \\"...\\" }";

    $ai\_response \= call\_ai\_api($prompt, $temp\_paths);

    // Clean up temp files  
    foreach ($temp\_paths as $path) { if (file\_exists($path)) unlink($path); }

    // Extract the JSON string from the AI's response  
    // (Note: This assumes the AI returns text. You might need to parse the Gemini structure)  
    $text \= $ai\_response\['candidates'\]\[0\]\['content'\]\['parts'\]\[0\]\['text'\] ?? '';  
      
    // Strip markdown code blocks if the AI includes them  
    $json\_clean \= trim(str\_replace(\['\`\`\`json', '\`\`\`'\], '', $text));  
      
    header('Content-Type: application/json');  
    echo $json\_clean;  
}

### **Task 5.2: Form Integration (Update add\_item.php)**

We need to add an "Auto-Identify" button and the JavaScript to handle the "magic" auto-fill.

**Add this script and button to your add\_item.php:**

\<\!-- Place this button inside your form, near the file input \--\>  
\<button type="button" id="btn-ai" style="background:\#6f42c1; margin-bottom:10px;"\>✨ Auto-Identify with AI\</button\>  
\<span id="ai-status" style="font-size: 0.8em; color: \#666;"\>\</span\>

\<script\>  
document.getElementById('btn-ai').onclick \= async function() {  
    const fileInput \= document.querySelector('input\[type="file"\]');  
    const status \= document.getElementById('ai-status');  
      
    if (fileInput.files.length \=== 0\) {  
        alert("Please select images first.");  
        return;  
    }

    status.innerText \= "Analyzing images... please wait.";  
    this.disabled \= true;

    const formData \= new FormData();  
    for (let file of fileInput.files) {  
        formData.append('images\[\]', file);  
    }

    try {  
        const response \= await fetch('identify\_api.php', {  
            method: 'POST',  
            body: formData  
        });  
        const data \= await response.json();

        // Auto-fill the form fields  
        document.querySelector('input\[name="name"\]').value \= data.name || '';  
        document.querySelector('input\[name="model"\]').value \= data.model || '';  
        document.querySelector('input\[name="category"\]').value \= data.category || '';  
        document.querySelector('textarea\[name="specs"\]').value \= data.specs || '';  
          
        status.innerText \= "Identification complete\!";  
    } catch (e) {  
        status.innerText \= "Error identifying component.";  
        console.error(e);  
    } finally {  
        this.disabled \= false;  
    }  
};  
\</script\>

### **Phase 5 Test Case**

1. Go to add\_item.php.  
2. Click "Choose Files" and select 2-3 photos of a known component (like an Arduino or a specific sensor).  
3. Click the **✨ Auto-Identify with AI** button.  
4. Wait 5-10 seconds.  
5. *Success:* The Name, Model, and Specs fields should "ghost-write" themselves based on what the AI saw in your photos.