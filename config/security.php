<?php
/**
 * Security configuration.
 */
return [
    'password' => [
        // PASSWORD_DEFAULT follows PHP's current recommended algorithm (bcrypt).
        'algo' => PASSWORD_DEFAULT,
        'min_length' => 10,
    ],

    'login' => [
        'max_attempts'   => 5,
        'window_minutes' => 15,
    ],

    'qr' => [
        'token_bytes' => 32,
        'default_expiry_days' => 365,
        'max_verification_attempts' => 10,
        'verification_window_minutes' => 15,
    ],

    'csrf' => [
        'session_key' => '_csrf_token',
        'input_name'  => '_token',
        'header_name' => 'HTTP_X_CSRF_TOKEN',
    ],
];
