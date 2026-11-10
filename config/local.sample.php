<?php
/**
 * Manual installation template.
 *
 * Copy this file to config/local.php, fill in real values, and never expose it
 * through the web server. Generate the application key with:
 *   php -r "echo bin2hex(random_bytes(32));"
 */
return [
    'app' => [
        'key' => 'replace-with-64-hex-characters',
        'debug' => false,
        'base_url' => '',
        'environment' => 'production',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'your_database_name',
        'username' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
];
