# **Phase 7: Creative Engine & Acquisition Planning**

In this phase, we unlock the true power of your inventory. We will create a discovery interface that sends your stock list to the AI and parses project ideas, including complexity levels and shopping links.

### **Task 7.1: The Discovery Interface (projects.php)**

This page fetches every item in your database, cleans the data, and asks the AI for project ideas.

\<?php  
require 'db.php';  
require 'ai\_helper.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { exit; }

// 1\. Fetch all inventory for context  
$stmt \= $pdo-\>query("SELECT name, model, category, quantity, specs FROM inventory");  
$items \= $stmt-\>fetchAll();

$inventory\_context \= "";  
foreach ($items as $i) {  
    $inventory\_context .= "- {$i\['name'\]} ({$i\['model'\]}), Qty: {$i\['quantity'\]}, Specs: {$i\['specs'\]}\\n";  
}

$ai\_results \= null;

if (isset($\_POST\['discover'\])) {  
    $prompt \= "Review this DIY lab inventory:\\n" . $inventory\_context . "\\n  
               Suggest 3 creative projects. For each, provide:   
               Title, Complexity (Beginner/Int/Expert), Duration, Stock Used, and Missing Parts.  
               For missing parts, include a search link for Amazon and AliExpress.  
               Respond ONLY in JSON format:   
               \[{\\"title\\":\\"...\\", \\"complexity\\":\\"...\\", \\"duration\\":\\"...\\", \\"stock\\":\\"...\\", \\"missing\\":\[{\\"part\\":\\"...\\", \\"links\\":\\"...\\"}\]}\]";

    $response \= call\_ai\_api($prompt);  
    $text \= $response\['candidates'\]\[0\]\['content'\]\['parts'\]\[0\]\['text'\] ?? '\[\]';  
    $json\_clean \= trim(str\_replace(\['\`\`\`json', '\`\`\`'\], '', $text));  
    $ai\_results \= json\_decode($json\_clean, true);  
}  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Creative Engine\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 40px; background: \#f4f7f6; }  
        .project-card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }  
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; color: white; }  
        .bg-easy { background: \#28a745; }  
        .bg-med { background: \#ffc107; color: \#000; }  
        .bg-hard { background: \#dc3545; }  
        .links a { font-size: 0.8em; margin-right: 10px; color: \#007bff; }  
    \</style\>  
\</head\>  
\<body\>  
    \<h1\>Project Discovery\</h1\>  
    \<form method="post"\>  
        \<button type="submit" name="discover" style="padding:15px 30px; font-size:1.1em; cursor:pointer;"\>🚀 Brainstorm Projects\</button\>  
    \</form\>

    \<?php if ($ai\_results): ?\>  
        \<div style="margin-top: 30px;"\>  
            \<?php foreach ($ai\_results as $project): ?\>  
                \<div class="project-card"\>  
                    \<h2\>\<?php echo htmlspecialchars($project\['title'\]); ?\>\</h2\>  
                    \<p\>  
                        \<span class="badge bg-med"\>\<?php echo $project\['complexity'\]; ?\>\</span\>  
                        \<strong\>Duration:\</strong\> \<?php echo $project\['duration'\]; ?\>  
                    \</p\>  
                    \<p\>\<strong\>Using:\</strong\> \<?php echo $project\['stock'\]; ?\>\</p\>  
                      
                    \<?php if (\!empty($project\['missing'\])): ?\>  
                        \<div class="links"\>  
                            \<strong\>Missing:\</strong\>  
                            \<?php foreach ($project\['missing'\] as $m): ?\>  
                                \<div\>\<?php echo $m\['part'\]; ?\> (\<?php echo $m\['links'\]; ?\>)\</div\>  
                            \<?php endforeach; ?\>  
                        \</div\>  
                    \<?php endif; ?\>  
                      
                    \<form action="project\_blueprint.php" method="post"\>  
                        \<input type="hidden" name="title" value="\<?php echo htmlspecialchars($project\['title'\]); ?\>"\>  
                        \<button type="submit" style="margin-top:10px; background:\#6f42c1; color:white; border:none; padding:8px 12px; cursor:pointer;"\>Generate Full Blueprint & Code\</button\>  
                    \</form\>  
                \</div\>  
            \<?php endforeach; ?\>  
        \</div\>  
    \<?php endif; ?\>  
\</body\>  
\</html\>

### **Task 7.2: The Blueprint Generator (project\_blueprint.php)**

This page handles the deep-dive: generating the step-by-step assembly guide and the code.

\<?php  
require 'db.php';  
require 'ai\_helper.php';  
session\_start();

if ($\_SERVER\['REQUEST\_METHOD'\] \== 'POST' && isset($\_POST\['title'\])) {  
    $project\_title \= $\_POST\['title'\];  
      
    // Fetch context again for the prompt  
    $stmt \= $pdo-\>query("SELECT name, specs FROM inventory");  
    $items \= $stmt-\>fetchAll();  
    $context \= ""; foreach ($items as $i) { $context .= "- {$i\['name'\]}: {$i\['specs'\]}\\n"; }

    $prompt \= "Generate a technical blueprint for: $project\_title.   
               Use these available parts where possible: $context.  
               Provide: 1\. Step-by-step assembly. 2\. A complete code block (C++ or Python).   
               Format as Markdown.";

    $response \= call\_ai\_api($prompt);  
    $guide\_markdown \= $response\['candidates'\]\[0\]\['content'\]\['parts'\]\[0\]\['text'\] ?? 'Error generating guide.';  
}  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Project Blueprint\</title\>  
    \<style\>  
        body { font-family: serif; line-height: 1.6; padding: 50px; max-width: 800px; margin: auto; background: \#fff; }  
        pre { background: \#f4f4f4; padding: 15px; overflow-x: auto; border-left: 4px solid \#6f42c1; }  
    \</style\>  
\</head\>  
\<body\>  
    \<a href="projects.php"\>← Back to Discovery\</a\>  
    \<div class="guide"\>  
        \<?php   
            // In a real app, use a library like Parsedown. For now, we display raw or basic nl2br.  
            echo nl2br(htmlspecialchars($guide\_markdown));   
        ?\>  
    \</div\>  
\</body\>  
\</html\>

### **Phase 7 Test Case (The "Idea to Blueprint" Test)**

1. Go to projects.php.  
2. Click **🚀 Brainstorm Projects**.  
3. **Wait:** The AI is analyzing your entire database (this may take 10-15 seconds).  
4. Verify that 3 cards appear with specific parts you actually own.  
5. Check the "Missing Parts" links—do they search for the right items?  
6. Click **Generate Full Blueprint & Code** on one project.  
7. *Success:* You receive a detailed manual and a block of code tailored to your hardware.