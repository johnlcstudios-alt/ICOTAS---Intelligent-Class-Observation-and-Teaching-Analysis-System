<?php
/**
 * ICOTAS application configuration.
 * Local values are written by install.php into config/local.php.
 */

$local = [];
if (is_file(__DIR__ . '/local.php')) {
    $loaded = require __DIR__ . '/local.php';
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

$config = [
    'name'              => 'ICOTAS',
    'institution'       => 'ACLC College Tacloban City',
    'tagline'           => 'Intelligent Class Observation & Teaching Analytics System',
    'version'           => '2.0.0',
    'timezone'          => 'Asia/Manila',
    'key'               => 'icotas-development-key-change-on-install',
    'base_url'          => '',
    'debug'             => false,
    'environment'       => 'production',
    'session_name'      => 'icotas_session',
    'session_lifetime'  => 7200,
    'upload_max_bytes'  => 2097152,
    'csv_max_bytes'     => 5242880,
    'default_session_minutes' => 120,
];

if (isset($local['app']) && is_array($local['app'])) {
    $config = array_replace($config, $local['app']);
}

return $config;
