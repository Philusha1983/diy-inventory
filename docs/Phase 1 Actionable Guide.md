# **Phase 1: The Foundation — Actionable Steps**

Follow these steps to establish your database and secure your entry point.

### **Task 1.1 & 1.2: Database Setup**

1. **Open phpMyAdmin:** Go to http://localhost/phpmyadmin in your browser.  
2. **Create Database:** Create a new database named diy\_lab\_db.  
3. **Run SQL:** Click the "SQL" tab and execute the following command to create both the inventory and settings tables:

CREATE TABLE \`inventory\` (  
  \`id\` INT(11) NOT NULL AUTO\_INCREMENT,  
  \`name\` VARCHAR(255) NOT NULL,  
  \`model\` VARCHAR(255) DEFAULT NULL,  
  \`category\` VARCHAR(100) DEFAULT NULL,  
  \`quantity\` INT(11) DEFAULT 0,  
  \`status\` ENUM('New', 'Used', 'Refurbished') DEFAULT 'New',  
  \`specs\` TEXT DEFAULT NULL,  
  \`image\_paths\` TEXT DEFAULT NULL,  
  \`location\` VARCHAR(255) DEFAULT NULL,  
  PRIMARY KEY (\`id\`)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE \`settings\` (  
  \`id\` INT(11) NOT NULL AUTO\_INCREMENT,  
  \`setting\_key\` VARCHAR(100) NOT NULL UNIQUE,  
  \`setting\_value\` TEXT DEFAULT NULL,  
  PRIMARY KEY (\`id\`)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

### **Task 1.3: Connectivity & Password Gate**

Create these files in your diy\_lab/ folder.

**File A: db.php**

This file handles the connection to your database.

\<?php  
$host \= 'localhost';  
$db   \= 'diy\_lab\_db';  
$user \= 'root';  
$pass \= ''; // Default XAMPP password is empty  
$charset \= 'utf8mb4';

$dsn \= "mysql:host=$host;dbname=$db;charset=$charset";  
$options \= \[  
    PDO::ATTR\_ERRMODE            \=\> PDO::ERRMODE\_EXCEPTION,  
    PDO::ATTR\_DEFAULT\_FETCH\_MODE \=\> PDO::FETCH\_ASSOC,  
    PDO::ATTR\_EMULATE\_PREPARES   \=\> false,  
\];

try {  
     $pdo \= new PDO($dsn, $user, $pass, $options);  
} catch (\\PDOException $e) {  
     throw new \\PDOException($e-\>getMessage(), (int)$e-\>getCode());  
}  
?\>

**File B: index.php**

This is your password gate. It uses a session to keep you logged in.

\<?php  
session\_start();  
$password \= "1234"; // Set your password here

if (isset($\_POST\['login'\])) {  
    if ($\_POST\['pass'\] \=== $password) {  
        $\_SESSION\['authenticated'\] \= true;  
        header("Location: dashboard.php");  
        exit;  
    } else {  
        $error \= "Incorrect Password";  
    }  
}  
?\>  
\<\!DOCTYPE html\>  
\<html\>  
\<head\>  
    \<title\>DIY Lab Login\</title\>  
    \<style\>  
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: \#f4f4f4; }  
        form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }  
        input { display: block; margin: 10px 0; padding: 10px; width: 200px; }  
        button { width: 100%; padding: 10px; background: \#333; color: white; border: none; cursor: pointer; }  
    \</style\>  
\</head\>  
\<body\>  
    \<form method="post"\>  
        \<h2\>DIY Lab Inventory\</h2\>  
        \<?php if(isset($error)) echo "\<p style='color:red'\>$error\</p\>"; ?\>  
        \<input type="password" name="pass" placeholder="Enter Password" required\>  
        \<button type="submit" name="login"\>Enter Lab\</button\>  
    \</form\>  
\</body\>  
\</html\>

### **Phase 1 Test Case**

1. Open http://localhost/diy\_lab/index.php.  
2. Enter "1234".  
3. You should be redirected to dashboard.php. Since that file doesn't exist yet, you will get a 404 error—**this is a success\!** It means your logic is working.