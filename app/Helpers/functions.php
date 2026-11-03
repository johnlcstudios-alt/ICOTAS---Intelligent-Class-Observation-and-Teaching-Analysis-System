<?php
/**
 * Global helpers shared by controllers, services and views.
 * Kept deliberately small; business logic belongs in services.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Settings;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $loaded = [];
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!array_key_exists($file, $loaded)) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            $loaded[$file] = is_file($path) ? require $path : [];
        }
        $value = $loaded[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

if (!function_exists('db')) {
    function db(): PDO
    {
        return Database::connection();
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        $current = $array;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $configured = trim((string) config('app.url', ''));
        if ($configured !== '') {
            $path = parse_url($configured, PHP_URL_PATH);
            $base = rtrim((string) ($path ?: ''), '/');
            return $base;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base = ($dir === '.' || $dir === '/') ? '' : $dir;
        return $base;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = app_base_path();
        if ($path === '') {
            return ($base !== '' ? $base : '') . '/';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $relative = 'assets/' . ltrim($path, '/');
        $full = BASE_PATH . '/public/' . $relative;
        $version = is_file($full) ? (string) filemtime($full) : config('app.app_version', '1');
        return url($relative) . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): void
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target, true, $status);
        exit;
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('abort')) {
    function abort(int $status, ?string $message = null): void
    {
        $errorCode = $status;
        $errorMessage = $message;
        $errorReference = null;
        http_response_code($status);

        if (Request::wantsJson()) {
            $messages = [
                400 => 'The request could not be processed.',
                403 => 'You do not have permission to perform this action.',
                404 => 'The requested page could not be found.',
                419 => 'Your security token expired. Refresh the page and try again.',
                429 => 'Too many requests. Please wait and try again.',
                503 => 'The service is temporarily unavailable.',
            ];
            json_response([
                'success' => false,
                'message' => $message ?? ($messages[$status] ?? 'The request could not be completed.'),
            ], $status);
        }

        require BASE_PATH . '/app/Views/errors/error.php';
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="' . e(config('security.csrf.input_name')) . '" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(18));
        }
        return $nonce;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Flash::old($key, $default);
    }
}

if (!function_exists('error')) {
    function error(?string $field = null): ?string
    {
        return Flash::firstError($field);
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return Flash::errors();
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return isset(Flash::errors()[$field]);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('is_dean')) {
    function is_dean(): bool
    {
        return Auth::isDean();
    }
}

if (!function_exists('is_faculty')) {
    function is_faculty(): bool
    {
        return Auth::isFaculty();
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('rating_labels')) {
    function rating_labels(): array
    {
        return Settings::ratingLabels();
    }
}

if (!function_exists('is_test_mode')) {
    function is_test_mode(): bool
    {
        return (string) Settings::get('seed_mode', 'production') === 'development';
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return $fallback;
        }
        $ts = strtotime($value);
        return $ts === false ? $fallback : date('M j, Y', $ts);
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return $fallback;
        }
        $ts = strtotime($value);
        return $ts === false ? $fallback : date('M j, Y g:i A', $ts);
    }
}

if (!function_exists('fmt_time')) {
    function fmt_time(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $ts = strtotime($value);
        return $ts === false ? $fallback : date('g:i A', $ts);
    }
}

if (!function_exists('score')) {
    function score(mixed $value, int $decimals = 2, string $fallback = '—'): string
    {
        return ($value === null || $value === '') ? $fallback : number_format((float) $value, $decimals);
    }
}

if (!function_exists('percentage')) {
    function percentage(int|float $part, int|float $total, int $decimals = 1): string
    {
        if ((float) $total <= 0) {
            return '0.0';
        }
        return number_format(((float) $part / (float) $total) * 100, $decimals);
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            if ($word !== '' && preg_match('/^./u', $word, $m)) {
                $letters .= strtoupper($m[0]);
            }
        }
        return $letters !== '' ? $letters : '?';
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $text, int $limit = 120, string $fallback = '—'): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return $fallback;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit
                ? rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…'
                : $text;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($chars) <= $limit) {
            return $text;
        }
        return rtrim(implode('', array_slice($chars, 0, $limit - 1))) . '…';
    }
}

if (!function_exists('paginate')) {
    function paginate(int $total, int $page, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) config('app.per_page', 12);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $current = min(max(1, $page), $pages);
        $offset = ($current - 1) * $perPage;

        return [
            'total'    => $total,
            'per_page' => $perPage,
            'current'  => $current,
            'pages'    => $pages,
            'offset'   => $offset,
            'from'     => $total === 0 ? 0 : $offset + 1,
            'to'       => min($offset + $perPage, $total),
            'has_prev' => $current > 1,
            'has_next' => $current < $pages,
        ];
    }
}

if (!function_exists('query_string')) {
    function query_string(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            }
        }
        return http_build_query($params);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'Draft'                   => ['Draft', 'neutral'],
            'Submitted'               => ['Submitted', 'info'],
            'Published'               => ['Published', 'success'],
            'Returned for Correction' => ['Returned for Correction', 'warning'],
            'Archived'                => ['Archived', 'muted'],
            'Active'                  => ['Active', 'success'],
            'Inactive'                => ['Inactive', 'muted'],
            'Current'                 => ['Current', 'success'],
            'Upcoming'                => ['Upcoming', 'info'],
            'Closed'                  => ['Closed', 'muted'],
            'Pending'                 => ['Pending', 'warning'],
            'Completed'               => ['Completed', 'success'],
            'Cancelled'               => ['Cancelled', 'muted'],
            'Finalized'               => ['Finalized', 'success'],
            'Revoked'                 => ['Revoked', 'danger'],
            'Expired'                 => ['Expired', 'muted'],
            'Production'              => ['Production', 'neutral'],
            'Test'                    => ['TEST DATA', 'warning'],
        ];
        [$label, $tone] = $map[$status ?? ''] ?? [$status ?: 'Unknown', 'neutral'];

        return '<span class="badge badge--' . e($tone) . '"><span class="badge__dot" aria-hidden="true"></span>' . e($label) . '</span>';
    }
}

if (!function_exists('semester_abbr')) {
    function semester_abbr(string $semester): string
    {
        return match ($semester) {
            'First Semester'  => '1st Sem',
            'Second Semester' => '2nd Sem',
            'Summer'          => 'Summer',
            default           => $semester,
        };
    }
}

if (!function_exists('icon')) {
    function icon(string $name, int $size = 18, string $class = ''): string
    {
        $paths = [
            'dashboard'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="4" rx="1.5"/><rect x="14" y="11" width="7" height="10" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'building'    => '<path d="M3 21h18M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/>',
            'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'book'        => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
            'clipboard'   => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h4"/>',
            'check'       => '<path d="m4 12.5 5 5L20 6.5"/>',
            'checklist'   => '<path d="m3 7 2 2 4-4M3 17l2 2 4-4M13 8h8M13 18h8"/>',
            'file'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
            'chart'       => '<path d="M3 3v18h18"/><path d="M7 15v3M12 9v9M17 5v13"/>',
            'trend'       => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
            'qr'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v2M14 20h2M19 19h2v2"/>',
            'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
            'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.36a1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.64 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.64h.09A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.36 9v.09A1.7 1.7 0 0 0 20.91 10H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1Z"/>',
            'bell'        => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'search'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            'trash'       => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',
            'chevron-left'=> '<path d="m15 18-6-6 6-6"/>',
            'chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
            'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
            'print'       => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
            'eye'         => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'alert'       => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
            'info'        => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
            'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
            'menu'        => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
            'camera'      => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/>',
            'refresh'     => '<path d="M21 2v6h-6M3 22v-6h6"/><path d="M21 8A9 9 0 0 0 6.3 5.7L3 8m18 8-3.3 2.3A9 9 0 0 1 3 16"/>',
            'archive'     => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/>',
            'star'        => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
            'award'       => '<circle cx="12" cy="8" r="6"/><path d="M8.2 13 7 22l5-3 5 3-1.2-9"/>',
            'graduation'  => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>',
            'filter'      => '<path d="M22 3H2l8 9.5V19l4 2v-8.5L22 3Z"/>',
            'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'link'        => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
            'sparkles'    => '<path d="m12 3 1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8Z"/><path d="m19 14 .8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8Z"/><path d="m5 14 .6 1.4L7 16l-1.4.6L5 18l-.6-1.4L3 16l1.4-.6Z"/>',
            'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'send'        => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'external'    => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>',
        ];
        $path = $paths[$name] ?? $paths['grid'];
        $classAttr = $class !== '' ? ' ' . e($class) : '';
        return '<svg class="icon' . $classAttr . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }
}

if (!function_exists('app_log')) {
    function app_log(string $level, string $message, array $context = []): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        $entry = [
            'time'    => date('c'),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('is_assoc')) {
    function is_assoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
