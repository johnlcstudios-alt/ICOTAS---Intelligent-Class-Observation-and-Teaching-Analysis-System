<?php
/**
 * Development router for the PHP built-in server.
 *
 * Usage:
 *   php -S 127.0.0.1:8090 tools/dev-router.php
 *
 * Apache uses .htaccess / mod_rewrite in production. This router only exists
 * to make local smoke testing convenient; tools/ is denied for web access.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
