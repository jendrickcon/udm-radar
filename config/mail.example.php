<?php
// config/mail.example.php — SMTP configuration TEMPLATE
//
// Copy this file to config/mail.php and fill in real values if/when email
// sending is implemented. config/mail.php is gitignored — this .example
// file is the one that ships in the repo.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');   // use a Gmail App Password, never your real account password
define('SMTP_FROM', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'UdM-RADAR');
?>
