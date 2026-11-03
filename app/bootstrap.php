<?php
/**
 * ICOTAS application bootstrap.
 * Loads environment, registers autoloading, configures error handling
 * and starts the hardened session.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, 4);
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/Helpers/functions.php';

App\Core\Env::load(BASE_PATH . '/.env');

// The timezone is an editable institution setting. Fall back to the
// configuration default when the database is not reachable yet (for example
// during the very first installation).
$timezone = (string) App\Core\Settings::get('timezone', config('app.timezone', 'Asia/Manila'));
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = (string) config('app.timezone', 'Asia/Manila');
}
date_default_timezone_set($timezone);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');

if (!is_file(BASE_PATH . '/database/schema.sql')) {
    throw new RuntimeException('ICOTAS schema file is missing: database/schema.sql');
}

try {
    App\Core\Database::connection();
} catch (\RuntimeException $e) {
    app_log('error', 'Database unavailable: ' . $e->getMessage(), ['schema' => BASE_PATH . '/database/schema.sql']);
}

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        app_log('deprecation', $message, ['file' => $file, 'line' => $line]);
        return true;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    $reference = strtoupper(bin2hex(random_bytes(4)));
    app_log('error', $e->getMessage(), [
        'reference' => $reference,
        'type'      => get_class($e),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
        'trace'     => $e->getTraceAsString(),
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, '[' . $reference . '] ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    if (App\Core\Request::wantsJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Reference: ' . $reference,
        ]);
        exit;
    }

    $errorCode = 500;
    $errorMessage = 'An unexpected error occurred. Please try again or contact the system administrator.';
    $errorReference = $reference;
    require BASE_PATH . '/app/Views/errors/error.php';
    exit;
});

App\Core\Session::start();
