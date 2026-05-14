# **Phase 3: Multi-Angle Image Handling**

In this phase, we move beyond text by adding the ability to upload multiple photos for a single component and view them in a dedicated detail page.

### **Task 3.1 & 3.2: Bulk Upload Logic (Updating add\_item.php)**

You need to update your form to accept files. Note the addition of enctype="multipart/form-data" in the \<form\> tag and the PHP logic to process the array of images.

**Update your add\_item.php or replace it with this version:**

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { header("Location: index.php"); exit; }

if ($\_SERVER\['REQUEST\_METHOD'\] \== 'POST') {  
    $uploaded\_paths \= \[\];  
    $upload\_dir \= 'uploads/';  
      
    // Ensure upload directory exists  
    if (\!is\_dir($upload\_dir)) { mkdir($upload\_dir, 0777, true); }

    // Handle Multiple File Uploads  
    if (\!empty($\_FILES\['images'\]\['name'\]\[0\])) {  
        foreach ($\_FILES\['images'\]\['tmp\_name'\] as $key \=\> $tmp\_name) {  
            $file\_name \= time() . '\_' . $\_FILES\['images'\]\['name'\]\[$key\];  
            $target\_file \= $upload\_dir . basename($file\_name);  
              
            if (move\_uploaded\_file($tmp\_name, $target\_file)) {  
                $uploaded\_paths\[\] \= $target\_file;  
            }  
        }  
    }

    $image\_json \= json\_encode($uploaded\_paths);

    $stmt \= $pdo-\>prepare("INSERT INTO inventory (name, model, category, quantity, status, specs, location, image\_paths) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");  
    $stmt-\>execute(\[  
        $\_POST\['name'\],  
        $\_POST\['model'\],  
        $\_POST\['category'\],  
        $\_POST\['quantity'\],  
        $\_POST\['status'\],  
        $\_POST\['specs'\],  
        $\_POST\['location'\],  
        $image\_json  
    \]);  
    header("Location: dashboard.php");  
    exit;  
}  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>Add Component with Photos\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto; }  
        input, select, textarea { width: 100%; padding: 8px; margin: 10px 0; display: block; }  
        .file-input { border: 1px dashed \#ccc; padding: 20px; text-align: center; background: \#fafafa; }  
        button { padding: 10px 20px; background: \#28a745; color: white; border: none; cursor: pointer; }  
    \</style\>  
\</head\>  
\<body\>  
    \<h1\>Add New Item\</h1\>  
    \<form method="post" enctype="multipart/form-data"\>  
        \<input type="text" name="name" placeholder="Item Name" required\>  
        \<div class="file-input"\>  
            \<label\>Upload Component Photos (Multiple):\</label\>  
            \<input type="file" name="images\[\]" multiple accept="image/\*"\>  
        \</div\>  
        \<input type="text" name="model" placeholder="Model/Part Number"\>  
        \<input type="text" name="category" placeholder="Category"\>  
        \<input type="number" name="quantity" placeholder="Quantity" value="1"\>  
        \<select name="status"\>  
            \<option value="New"\>New\</option\>  
            \<option value="Used"\>Used\</option\>  
            \<option value="Refurbished"\>Refurbished\</option\>  
        \</select\>  
        \<textarea name="specs" placeholder="Specifications" rows="4"\>\</textarea\>  
        \<input type="text" name="location" placeholder="Physical Location"\>  
        \<button type="submit"\>Save Component\</button\>  
    \</form\>  
\</body\>  
\</html\>

### **Task 3.3: Item Detail Page (item\_details.php)**

This page fetches the specific item and decodes the JSON image paths to display a gallery.

\<?php  
require 'db.php';  
session\_start();  
if (\!isset($\_SESSION\['authenticated'\])) { exit; }

$id \= $\_GET\['id'\];  
$stmt \= $pdo-\>prepare("SELECT \* FROM inventory WHERE id \= ?");  
$stmt-\>execute(\[$id\]);  
$item \= $stmt-\>fetch();

if (\!$item) { die("Item not found."); }

$images \= json\_decode($item\['image\_paths'\], true) ?? \[\];  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>\<?php echo htmlspecialchars($item\['name'\]); ?\> \- Details\</title\>  
    \<style\>  
        body { font-family: sans-serif; padding: 40px; line-height: 1.6; }  
        .gallery { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }  
        .gallery img { width: 200px; height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid \#ddd; }  
        .spec-box { background: \#f9f9f9; padding: 20px; border-radius: 8px; border-left: 5px solid \#007bff; }  
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: \#007bff; }  
    \</style\>  
\</head\>  
\<body\>  
    \<a href="dashboard.php" class="back-link"\>← Back to Dashboard\</a\>  
    \<h1\>\<?php echo htmlspecialchars($item\['name'\]); ?\>\</h1\>  
    \<p\>\<strong\>Model:\</strong\> \<?php echo htmlspecialchars($item\['model'\]); ?\> | \<strong\>Location:\</strong\> \<?php echo htmlspecialchars($item\['location'\]); ?\>\</p\>

    \<div class="gallery"\>  
        \<?php foreach ($images as $path): ?\>  
            \<a href="\<?php echo $path; ?\>" target="\_blank"\>  
                \<img src="\<?php echo $path; ?\>" alt="Component Photo"\>  
            \</a\>  
        \<?php endforeach; ?\>  
    \</div\>

    \<div class="spec-box"\>  
        \<h3\>Technical Specifications\</h3\>  
        \<p\>\<?php echo nl2br(htmlspecialchars($item\['specs'\])); ?\>\</p\>  
    \</div\>  
\</body\>  
\</html\>

### **Phase 3 Test Case**

1. Go to your updated add\_item.php.  
2. Select **three different photos** of a single component (e.g., top, bottom, and side of a sensor).  
3. Fill in the name and save.  
4. On the dashboard (you may need to add a link to item\_details.php?id=X in your dashboard table), click on the item.  
5. Verify all three images appear in the gallery and the specifications are displayed correctly.