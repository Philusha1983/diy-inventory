# **Phase 8: Interactive Assistant**

In this phase, we add a conversational "Brainstorming" mode. Unlike the Blueprint Generator, which is one-way, this assistant allows for back-and-forth dialogue about your hardware.

### **Task 8.1: The Chat Interface (chat.php)**

This page provides a simple messaging UI. It sends your message and your inventory context to a background API.

\<?php  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { exit; }  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Lab Assistant\</title\>  
    \<style\>  
        body { font-family: sans-serif; background: \#f4f7f6; display: flex; flex-direction: column; height: 100vh; margin: 0; }  
        \#chat-window { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }  
        .msg { max-width: 70%; padding: 12px; border-radius: 15px; line-height: 1.4; }  
        .user { align-self: flex-end; background: \#007bff; color: white; border-bottom-right-radius: 2px; }  
        .ai { align-self: flex-start; background: \#e9ecef; color: \#333; border-bottom-left-radius: 2px; }  
        \#input-area { background: white; padding: 20px; border-top: 1px solid \#ddd; display: flex; gap: 10px; }  
        input { flex: 1; padding: 12px; border: 1px solid \#ddd; border-radius: 5px; }  
        button { padding: 10px 20px; background: \#28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }  
    \</style\>  
\</head\>  
\<body\>  
    \<div style="padding: 15px; background: \#333; color: white;"\>  
        \<a href="dashboard.php" style="color: \#ccc; text-decoration: none;"\>← Dashboard\</a\> | 🤖 Lab Assistant  
    \</div\>

    \<div id="chat-window"\>  
        \<div class="msg ai"\>Hello\! I've analyzed your inventory. What would you like to build or troubleshoot today?\</div\>  
    \</div\>

    \<div id="input-area"\>  
        \<input type="text" id="user-input" placeholder="e.g., 'What can I build for my kid with an ESP32?'" onkeypress="if(event.key==='Enter') sendMessage()"\>  
        \<button onclick="sendMessage()"\>Send\</button\>  
    \</div\>

    \<script\>  
        async function sendMessage() {  
            const input \= document.getElementById('user-input');  
            const window \= document.getElementById('chat-window');  
            const text \= input.value.trim();  
            if (\!text) return;

            // Add user message to UI  
            window.innerHTML \+= \`\<div class="msg user"\>${text}\</div\>\`;  
            input.value \= '';  
            window.scrollTop \= window.scrollHeight;

            const response \= await fetch('chat\_api.php', {  
                method: 'POST',  
                headers: { 'Content-Type': 'application/json' },  
                body: JSON.stringify({ message: text })  
            });  
            const data \= await response.json();  
              
            // Add AI response to UI  
            window.innerHTML \+= \`\<div class="msg ai"\>${data.reply || 'Sorry, I hit an error.'}\</div\>\`;  
            window.scrollTop \= window.scrollHeight;  
        }  
    \</script\>  
\</body\>  
\</html\>

### **Task 8.2: Context-Aware Logic (chat\_api.php)**

This script bridges the chat UI and the AI. It pulls your inventory so the AI knows exactly what's on your shelves.

\<?php  
require 'db.php';  
require 'ai\_helper.php';  
session\_start();

header('Content-Type: application/json');  
$input \= json\_decode(file\_get\_contents('php://input'), true);  
$user\_msg \= $input\['message'\] ?? '';

if (\!$user\_msg) { echo json\_encode(\['reply' \=\> 'No message received.'\]); exit; }

// 1\. Fetch Inventory for Context  
$stmt \= $pdo-\>query("SELECT name, quantity, specs FROM inventory");  
$items \= $stmt-\>fetchAll();  
$inventory\_context \= "Current Stock:\\n";  
foreach ($items as $i) {  
    $inventory\_context .= "- {$i\['name'\]} (Qty: {$i\['quantity'\]}): {$i\['specs'\]}\\n";  
}

// 2\. Construct the Prompt  
$prompt \= "You are the DIY Lab Planning Assistant. You have access to my current inventory:\\n$inventory\_context\\n  
           The user says: '$user\_msg'.   
           Respond helpfully. If they want to build something, suggest specific parts from their stock.   
           Keep the tone technical but encouraging.";

// 3\. Call AI  
$ai\_response \= call\_ai\_api($prompt);  
$reply \= $ai\_response\['candidates'\]\[0\]\['content'\]\['parts'\]\[0\]\['text'\] ?? 'I am having trouble connecting to the AI.';

echo json\_encode(\['reply' \=\> $reply\]);

### **Phase 8 Test Case (The "Gift Idea" Test)**

1. Open chat.php.  
2. Type: *"I want to make a small interactive gift for a friend using parts I already have. Any ideas?"*  
3. **Check the result:**  
   * Does the AI mention specific sensors or LEDs you actually have in your inventory table?  
   * Does it explain *how* they connect?  
4. Type a follow-up: *"I like the LED idea, but do I have enough resistors for that?"*  
5. *Success:* The AI should confirm your resistor stock and offer a circuit suggestion.