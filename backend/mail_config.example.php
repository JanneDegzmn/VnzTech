<?php

/*
 * Copy this file to mail_config.local.php and replace the example values.
 * Use a Google App Password, not your regular Gmail password.
 * Set VNZ_APP_BASE_URL on the host to the public HTTPS application URL.
 * Local requests may use the configured base URL and local server port.
 */
return [
    'base_url' => 'http://localhost/VNZ-TECH',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-gmail-address@gmail.com',
    'smtp_password' => 'your-16-character-google-app-password',
    'from_email' => 'your-gmail-address@gmail.com',
    'from_name' => 'VNZ TECH',
    'admin_notification_email' => 'replace-with-admin-recipient@example.com'
];
