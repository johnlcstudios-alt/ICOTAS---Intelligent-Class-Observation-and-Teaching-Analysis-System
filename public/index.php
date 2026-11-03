<?php
/**
 * ICOTAS front controller.
 * The public/ directory is the only web-exposed application directory.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;

// --- Security headers -------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('X-Permitted-Cross-Domain-Policies: none');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$nonce = csp_nonce();
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'nonce-{$nonce}'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: blob:; " .
    "font-src 'self' data:; " .
    "connect-src 'self'; " .
    "media-src 'self' blob: data:; " .
    "worker-src 'self' blob:; " .
    "frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
);

if (App\Core\Session::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
