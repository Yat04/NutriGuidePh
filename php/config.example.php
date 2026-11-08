<?php
// ── COPY THIS FILE TO config.php AND FILL IN YOUR CREDENTIALS ──
// macOS/Linux: cp config.example.php config.php
// Windows (PowerShell): Copy-Item .\config.example.php .\config.php
// Windows (File Explorer): copy the file into the same folder and rename it to config.php

// ── Database ────────────────────────────────────────────────
// XAMPP: host=localhost, user=root, pass=(empty)
// Production: use a dedicated MySQL user with a STRONG password
define('DB_HOST', getenv('NUTRIGUIDE_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('NUTRIGUIDE_DB_USER') ?: 'root');
define('DB_PASS', getenv('NUTRIGUIDE_DB_PASS') ?: '');
define('DB_NAME', getenv('NUTRIGUIDE_DB_NAME') ?: 'myDB');
// Set a unique password of at least 16 characters to create the initial admin account.
define('INITIAL_ADMIN_PASSWORD', getenv('NUTRIGUIDE_INITIAL_ADMIN_PASSWORD') ?: '');

// ── SMTP (Gmail) ────────────────────────────────────────────
// 1. Enable 2-Step Verification on your Google Account
// 2. Go to Security > App Passwords > generate one for "Mail"
// 3. Set NUTRIGUIDE_SMTP_PASS in the server environment
//    Use the 16-character App Password; spaces are removed by the mailer.
define('SMTP_HOST', getenv('NUTRIGUIDE_SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int) (getenv('NUTRIGUIDE_SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('NUTRIGUIDE_SMTP_USER') ?: 'your-email@gmail.com');
define('SMTP_PASS', getenv('NUTRIGUIDE_SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('NUTRIGUIDE_SMTP_FROM') ?: 'your-email@gmail.com');
define('SMTP_NAME', getenv('NUTRIGUIDE_SMTP_NAME') ?: 'NutriPh Guide');
define('SMTP_ENCRYPTION', getenv('NUTRIGUIDE_SMTP_ENCRYPTION') ?: 'tls'); // Use 'ssl' with port 465
define('SMTP_TIMEOUT', (int) (getenv('NUTRIGUIDE_SMTP_TIMEOUT') ?: 15));

// ── PATHS ───────────────────────────────────────────────────
$projectRoot = rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR . '/');
define('APP_ROOT', $projectRoot);
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos');
define('LOG_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'logs');

// ── PRODUCTION CHECKLIST ────────────────────────────────────
// 1. Use a strong DB password (not root with empty password)
// 2. Deploy with HTTPS (free SSL from hosting or Cloudflare)
// 3. Run db_setup.php once, then DELETE or restrict access to it
// 4. Set NUTRIGUIDE_INITIAL_ADMIN_PASSWORD before first setup; use a unique password of at least 16 characters
// 5. Set proper file permissions (755 for dirs, 644 for files)
// 6. Restrict access to /logs/ directory via .htaccess
?>
