<?php
/**
 * Database configuration (PDO / MySQL / MariaDB).
 */
return [
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => (int) env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'icotas_db'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];
