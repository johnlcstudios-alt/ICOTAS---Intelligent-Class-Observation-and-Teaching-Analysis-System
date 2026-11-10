<?php
/**
 * Database configuration. config/local.php is written by install.php.
 */

$local = [];
if (is_file(__DIR__ . '/local.php')) {
    $loaded = require __DIR__ . '/local.php';
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

$config = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'icotas',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];

if (isset($local['database']) && is_array($local['database'])) {
    $config = array_replace($config, $local['database']);
}

// Optional environment overrides keep automated tests and staging databases
// isolated from the configured production schema. Shared hosts normally leave
// these unset; config/local.php remains the canonical source when they are.
foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
    $env = getenv('ICOTAS_DB_' . strtoupper($key));
    if ($env !== false && $env !== '') {
        $config[$key] = $key === 'port' ? (int) $env : $env;
    }
}

return $config;
