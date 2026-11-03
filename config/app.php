<?php
/**
 * Core application configuration.
 * Values are read from the environment (.env) with safe defaults.
 */
return [
    'name'         => env('APP_NAME', 'ICOTAS'),
    'env'          => env('APP_ENV', 'production'),
    'debug'        => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url'          => env('APP_URL', ''),
    'key'          => env('APP_KEY', ''),

    // ACLC College Tacloban operates on Philippine Standard Time.
    'timezone'     => 'Asia/Manila',
    'locale'       => 'en_PH',

    'session' => [
        'name'              => 'ICOTAS_SESSID',
        'idle_minutes'      => 30,
        'absolute_minutes'  => 480,
        'same_site'         => 'Lax',
    ],

    // Predictive engine threshold (Part 13.6 of the project specification).
    'prediction_min_periods' => 5,

    // Report / display defaults.
    'per_page'     => 12,
    'app_version'  => '1.0.0',
];
