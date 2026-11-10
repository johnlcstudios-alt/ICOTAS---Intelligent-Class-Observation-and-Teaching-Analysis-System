<?php
/**
 * Front controller bootstrap shared by web, installer and CLI tools.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (is_file(BASE_PATH . '/app/autoload.php')) {
    require_once BASE_PATH . '/app/autoload.php';
}

require_once BASE_PATH . '/app/helpers.php';

$appConfig = config('app');
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Manila');

if (PHP_SAPI !== 'cli') {
    // Security headers. Inline scripts/styles are required for the server-rendered
    // Chart.js configuration and the CSRF meta tag; external origins are denied.
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; media-src 'self' blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('X-Permitted-Cross-Domain-Policies: none');
}

if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    App\Core\Session::start();
}
