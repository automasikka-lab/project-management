<?php
// =============================================
// Konfigurasi Email / SMTP
// =============================================

// Mailtrap (untuk testing)
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'bac15fe1e1f4a6');
define('SMTP_PASSWORD', '906848aae43727');
define('SMTP_FROM_EMAIL', 'test@mailtrap.io');
define('SMTP_FROM_NAME', 'PM Intelligence');
define('SMTP_ENCRYPTION', 'tls');

// Gmail SMTP (untuk production - uncomment dan isi credentials)
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'emailanda@gmail.com');
// define('SMTP_PASSWORD', 'abcdefghijklmnop');  // App Password
// define('SMTP_FROM_EMAIL', 'emailanda@gmail.com');
// define('SMTP_FROM_NAME', 'PM Intelligence');
// define('SMTP_ENCRYPTION', 'tls');
