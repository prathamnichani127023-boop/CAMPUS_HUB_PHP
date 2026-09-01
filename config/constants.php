<?php

// Base URL - change this to your server IP or domain
define('BASE_URL',    'http://localhost/university_portal');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/university_portal/assets/uploads/');
define('UPLOAD_URL',  BASE_URL . '/assets/uploads/');

// App info
define('APP_NAME',    'Campus Hub');
define('APP_VERSION', '1.0.0');
define('ACADEMIC_YEAR', '2026-2027');

// Session timeout (seconds)
define('SESSION_TIMEOUT', 3600);

// Pagination
define('RECORDS_PER_PAGE', 10);

// Allowed file types for uploads
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB


define('GOOGLE_CLIENT_ID',     'PASTE_YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'PASTE_YOUR_GOOGLE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI',  BASE_URL . '/google_callback.php');


define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USERNAME',   'your.email@gmail.com');
define('SMTP_PASSWORD',   'PASTE_YOUR_GMAIL_APP_PASSWORD_HERE');
define('SMTP_FROM_EMAIL', 'your.email@gmail.com');
define('SMTP_FROM_NAME',  APP_NAME);

// Password reset link validity (seconds)
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour
