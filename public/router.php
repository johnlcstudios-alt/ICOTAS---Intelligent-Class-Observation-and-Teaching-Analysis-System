<?php
/**
 * Router for PHP's built-in development server:
 *   php -S 127.0.0.1:8099 -t public public/router.php
 * Existing files are served directly; everything else reaches the front controller.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$candidate = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($uri));

if ($uri !== '/' && is_file($candidate)) {
    return false;
}

require __DIR__ . '/index.php';
