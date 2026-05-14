# **Phase 9: Web Server Migration**

This final phase transitions your local project to a live hosting environment. This allows you to access your lab assistant from your phone or tablet while you are physically working at your workbench.

### **Task 9.1: Database Export**

Before moving files, you need to "pack up" your data.

1. Open http://localhost/phpmyadmin.  
2. Select your diy\_lab\_db.  
3. Click the **Export** tab and then click **Go**. This will download a .sql file to your computer.

### **Task 9.2: Server Setup & Import**

1. **Log in to your hosting control panel** (e.g., cPanel, DirectAdmin, or a VPS).  
2. Create a new MySQL Database and a Database User. **Note down the new database name, username, and password.**  
3. Open the server's **phpMyAdmin**, select your new database, and click the **Import** tab. Upload the .sql file you exported in Task 9.1.

### **Task 9.3: File Migration & Configuration**

1. **Connect via FTP/SFTP** (using a tool like FileZilla) or use your host's File Manager.  
2. Upload all files from your local diy\_lab/ folder to your server's web directory (usually public\_html).  
3. **Update db.php:** Change the $host, $db, $user, and $pass variables to match the live credentials you created in Task 9.2.

### **Task 9.4: Permissions & Directory Setup**

1. Ensure the uploads/ folder exists on the server.  
2. **Set Permissions:** Right-click the uploads/ folder in your FTP client and set permissions (CHMOD) to 755 (or 777 if 755 fails). This allows PHP to save your component photos.

### **Task 9.5: Security Audit (The "Shield" Check)**

Since your site is now public, security is critical.

1. **Enable SSL (HTTPS):** Most hosts provide "Let's Encrypt" for free. Ensure your URL starts with https://. This encrypts your API key during transmission.  
2. **Password Gate:** Verify that index.php is still working and that you cannot access dashboard.php directly without logging in.  
3. **Disable Debugging:** In db.php, ensure you aren't echoing database errors to the screen, as this can reveal your server's structure to hackers.

### **Phase 9 Test Case (The "World-Wide" Test)**

1. Open your browser and navigate to your live URL (e.g., https://yourdomain.com/diy\_lab/).  
2. Log in with your password.  
3. **Upload a new item** with a photo to verify that file permissions are correct.  
4. **Run a project discovery** to ensure your API keys and outgoing CURL requests are working on the live server.  
5. *Success:* You now have a fully portable, AI-powered lab orchestrator in your pocket.