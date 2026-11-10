<?php
/**
 * ICOTAS front controller. Works from the htdocs root on InfinityFree or from
 * a subdirectory on XAMPP. Application source lives outside /assets and is
 * protected by .htaccess on Apache.
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);

$localConfig = __DIR__ . '/config/local.php';
if (!is_file($localConfig)) {
    header('Location: install.php');
    exit;
}

require __DIR__ . '/app/bootstrap.php';

$appConfig = config('app');
if (!empty($appConfig['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', storage_path('logs/php-error.log'));
}

try {
    $router = new App\Core\Router();
    require __DIR__ . '/app/routes.php';
    $router->dispatch();
} catch (App\Core\HttpException $e) {
    $status = $e->getStatus();
    http_response_code($status);
    $message = $e->getMessage();
    try {
        if (App\Core\Request::wantsJson()) {
            json_response(['success' => false, 'message' => $message], $status);
        }
        App\Core\View::render('errors/error', [
            'pageTitle'   => $status . ' Error',
            'status'      => $status,
            'message'     => $message,
            'hideChrome'  => $status === 401,
        ], 'layouts/app');
    } catch (Throwable $inner) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>ICOTAS</title></head><body style="font-family:system-ui;padding:3rem">'
            . '<h1>ICOTAS</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="' . htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') . '">Sign in</a></body></html>';
    }
} catch (Throwable $e) {
    error_log('[ICOTAS] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $debug = !empty(config('app.debug'));
    $message = $debug ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'An unexpected system error occurred. Please try again or contact the administrator.';
    try {
        App\Core\View::render('errors/error', ['pageTitle' => 'System Error', 'status' => 500, 'message' => $message], 'layouts/app');
    } catch (Throwable $inner) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>ICOTAS Error</title></head><body style="font-family:system-ui;padding:3rem"><h1>ICOTAS</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    }
}
