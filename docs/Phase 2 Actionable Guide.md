# **Phase 2: Comprehensive Dashboard & Data Entry**

In this phase, we build the interface to interact with your inventory table.

### **Task 2.1: Full Entry Form (add\_item.php)**

This file allows you to manually enter parts. It includes all fields defined in your database schema.

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { header("Location: index.php"); exit; }

if ($\_SERVER\['REQUEST\_METHOD'\] \== 'POST') {  
    $stmt \= $pdo-\>prepare("INSERT INTO inventory (name, model, category, quantity, status, specs, location) VALUES (?, ?, ?, ?, ?, ?, ?)");  
    $stmt-\>execute(\[  
        $\_POST\['name'\],  
        $\_POST\['model'\],  
        $\_POST\['category'\],  
        $\_POST\['quantity'\],  
        $\_POST\['status'\],  
        $\_POST\['specs'\],  
        $\_POST\['location'\]  
    \]);  
    header("Location: dashboard.php");  
    exit;  
}  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Add Component\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto; }  
        input, select, textarea { width: 100%; padding: 8px; margin: 10px 0; display: block; }  
        button { padding: 10px 20px; background: \#28a745; color: white; border: none; cursor: pointer; }  
    \</style\>  
\</head\>  
\<body\>  
    \<h1\>Add New Item\</h1\>  
    \<form method="post"\>  
        \<input type="text" name="name" placeholder="Item Name (e.g. ESP32)" required\>  
        \<input type="text" name="model" placeholder="Model/Part Number"\>  
        \<input type="text" name="category" placeholder="Category (e.g. Microcontroller)"\>  
        \<input type="number" name="quantity" placeholder="Quantity" value="1"\>  
        \<select name="status"\>  
            \<option value="New"\>New\</option\>  
            \<option value="Used"\>Used\</option\>  
            \<option value="Refurbished"\>Refurbished\</option\>  
        \</select\>  
        \<textarea name="specs" placeholder="Technical Specifications / Notes" rows="4"\>\</textarea\>  
        \<input type="text" name="location" placeholder="Physical Location (e.g. Bin A1)"\>  
        \<button type="submit"\>Save Component\</button\>  
        \<a href="dashboard.php"\>Back to Dashboard\</a\>  
    \</form\>  
\</body\>  
\</html\>

### **Task 2.2: Dashboard View (dashboard.php)**

This is your main control center. It lists everything in your inventory.

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { header("Location: index.php"); exit; }

$stmt \= $pdo-\>query("SELECT \* FROM inventory ORDER BY id DESC");  
$items \= $stmt-\>fetchAll();  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Lab Dashboard\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 20px; }  
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }  
        th, td { border: 1px solid \#ddd; padding: 12px; text-align: left; }  
        th { background-color: \#f8f9fa; }  
        .action-links a { margin-right: 10px; color: \#d9534f; text-decoration: none; }  
        .btn-add { padding: 10px 15px; background: \#007bff; color: white; text-decoration: none; border-radius: 4px; }  
    \</style\>  
\</head\>  
\<body\>  
    \<h1\>DIY Lab Inventory\</h1\>  
    \<a href="add\_item.php" class="btn-add"\>+ Add New Item\</a\>  
      
    \<table\>  
        \<thead\>  
            \<tr\>  
                \<th\>Name\</th\>  
                \<th\>Model\</th\>  
                \<th\>Category\</th\>  
                \<th\>Qty\</th\>  
                \<th\>Status\</th\>  
                \<th\>Location\</th\>  
                \<th\>Actions\</th\>  
            \</tr\>  
        \</thead\>  
        \<tbody\>  
            \<?php foreach ($items as $item): ?\>  
            \<tr\>  
                \<td\>\<strong\>\<?php echo htmlspecialchars($item\['name'\]); ?\>\</strong\>\</td\>  
                \<td\>\<?php echo htmlspecialchars($item\['model'\]); ?\>\</td\>  
                \<td\>\<?php echo htmlspecialchars($item\['category'\]); ?\>\</td\>  
                \<td\>\<?php echo htmlspecialchars($item\['quantity'\]); ?\>\</td\>  
                \<td\>\<?php echo htmlspecialchars($item\['status'\]); ?\>\</td\>  
                \<td\>\<?php echo htmlspecialchars($item\['location'\]); ?\>\</td\>  
                \<td class="action-links"\>  
                    \<a href="delete\_item.php?id=\<?php echo $item\['id'\]; ?\>" onclick="return confirm('Are you sure?')"\>Delete\</a\>  
                \</td\>  
            \</tr\>  
            \<?php endforeach; ?\>  
        \</tbody\>  
    \</table\>  
\</body\>  
\</html\>

### **Task 2.3: Image-Aware Delete (delete\_item.php)**

This script removes the record. We have included a placeholder for physical file deletion which we will activate in Phase 3\.

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { exit; }

if (isset($\_GET\['id'\])) {  
    $id \= $\_GET\['id'\];

    // Placeholder for Phase 3: Fetch image\_paths and delete physical files from /uploads  
    // $stmt \= $pdo-\>prepare("SELECT image\_paths FROM inventory WHERE id \= ?");  
    // ... logic to unlink() files ...

    $stmt \= $pdo-\>prepare("DELETE FROM inventory WHERE id \= ?");  
    $stmt-\>execute(\[$id\]);  
}

header("Location: dashboard.php");  
exit;

### **Phase 2 Test Case**

1. Open add\_item.php.  
2. Fill out all fields (e.g., Name: "Arduino Nano", Status: "New", Location: "Desk Drawer").  
3. Click "Save Component".  
4. You should be redirected to dashboard.php and see your new item in the list.  
5. Click "Delete" to ensure the removal logic works.