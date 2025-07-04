
📄 README — Prescription Management System
==========================================

**Submitted to:** Xiteb  
**Submitted by:** Sameera Jayakodi  
**Date:** 4th July 2025  
**Task Duration:** Approximately [insert hours] hours

🔧 Installation Instructions
----------------------------

### 1. 📥 Requirements
- XAMPP (PHP ≥ 7.4, MySQL)
- A modern browser (e.g., Chrome, Firefox)

### 2. ⚙️ Project Setup Steps

#### Step 1: Configure PHP Mail for SMTP (Email Functionality)
1. Open XAMPP Control Panel.
2. Click **Config** > **PHP (php.ini)**  
   Find and modify the following lines:
   ```
   SMTP=smtp.gmail.com
   smtp_port=587
   sendmail_from=your-email@gmail.com
   sendmail_path = "C:\xampp\sendmail\sendmail.exe -t"
   ```
3. Open `sendmail.ini` (found in `C:\xampp\sendmail\`) and configure:
   ```
   smtp_server=smtp.gmail.com
   smtp_port=587
   smtp_ssl=tls
   auth_username=your-email@gmail.com
   auth_password=your-app-password
   ```

✅ *Use an App Password for Gmail if 2FA is enabled.*

#### Step 2: Create the Database
1. Open **phpMyAdmin**.
2. Click **Import** and select the SQL file:
   - `prescription_system.sql`
3. The database and all tables will be created automatically.

#### Step 3: Project Folder Setup
1. Extract the project zip into:  
   `C:\xampp\htdocs\prescription_system`

2. Open the file:  
   `config/database.php`  
   Make sure these values are correct:
   ```
   private $host = "localhost";
   private $db_name = "prescription_system";
   private $username = "root";
   private $password = "";
   ```

#### Step 4: Run the Project
1. Start **Apache** and **MySQL** in XAMPP.
2. In your browser, go to:  
   `http://localhost/prescription_system/`

🔐 Login Credentials
--------------------

> There are **no default login credentials**.

- Users must **register** using their **own email address** and create a password.
- The system supports:
  - Patient Registration/Login
  - Pharmacy Registration/Login

📹 Demo Video
-------------

A video demonstration showing the system features, functionality, and flow is available at:  
[OneDrive Demo Video Link](https://nsbm365-my.sharepoint.com/:v:/g/personal/jcsameera_students_nsbm_ac_lk/EfqU5rZA0URNqJINS6qETf0BTmV2xzPii9_9-Kug--J7BQ?nav=eyJyZWZlcnJhbEluZm8iOnsicmVmZXJyYWxBcHAiOiJPbmVEcml2ZUZvckJ1c2luZXNzIiwicmVmZXJyYWxBcHBQbGF0Zm9ybSI6IldlYiIsInJlZmVycmFsTW9kZSI6InZpZXciLCJyZWZlcnJhbFZpZXciOiJNeUZpbGVzTGlua0NvcHkifX0&e=X8hY9w)

✅ Features Implemented
------------------------

- Patient uploads prescriptions
- Pharmacies send medicine quotations
- Patient views and accepts quotations
- Pharmacy dashboard with quotation tracking
- Email notifications on quotation activity
- Full registration and login system with role-based access

📝 Notes
--------

- Make sure your Gmail SMTP is properly configured.
- All emails (such as quotation notifications) are sent via the configured mail system.
- Use real or test Gmail credentials with App Password for testing.
