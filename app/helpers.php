<?php
/**
 * Global helper functions used by controllers, services and views.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Setting;
use App\Core\Session;

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        static $cache = [];
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!array_key_exists($file, $cache)) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            $cache[$file] = is_file($path) ? require $path : [];
        }

        $value = $cache[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $configured = trim((string) config('app.base_url', ''));
        if ($configured !== '') {
            return $cached = rtrim($configured, '/');
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/.');
        if ($dir === '/' || $dir === '\\') {
            $dir = '';
        }

        return $cached = $scheme . '://' . $host . $dir;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return base_url() . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('back')) {
    function back(string $fallback = ''): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($referer !== '' && $host !== '' && strpos($referer, $host) !== false) {
            header('Location: ' . $referer);
            exit;
        }
        redirect($fallback);
    }
}

if (!function_exists('abort')) {
    function abort(int $status = 404, string $message = ''): void
    {
        throw new App\Core\HttpException($status, $message);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, $value = null)
    {
        // Preserve setter/getter semantics: Session::flash() treats a second
        // argument as a write, so do not forward the default null on reads.
        return func_num_args() === 1
            ? Session::flash($key)
            : Session::flash($key, $value);
    }
}

if (!function_exists('bootstrap_alert')) {
    function bootstrap_alert(string $type, string $message): string
    {
        return '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
            . e($message)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        $old = Session::get('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return Auth::check();
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        return Auth::id();
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): string
    {
        return Auth::role();
    }
}

if (!function_exists('is_dean')) {
    function is_dean(): bool
    {
        return Auth::isAdmin();
    }
}

if (!function_exists('is_instructor')) {
    function is_instructor(): bool
    {
        return Auth::role() === 'instructor';
    }
}

if (!function_exists('is_student')) {
    function is_student(): bool
    {
        return Auth::role() === 'student';
    }
}

if (!function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('full_name')) {
    function full_name(?array $user, bool $withMiddle = false): string
    {
        if (!$user) {
            return 'Unknown user';
        }
        $parts = [$user['first_name'] ?? '', $withMiddle ? ($user['middle_name'] ?? '') : '', $user['last_name'] ?? ''];
        $name = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, static fn ($p) => trim((string) $p) !== ''))));
        if (!empty($user['suffix'])) {
            $name .= ', ' . $user['suffix'];
        }
        return $name !== '' ? $name : ($user['username'] ?? 'Unknown user');
    }
}

if (!function_exists('initials')) {
    function initials(?array $user): string
    {
        if (!$user) {
            return '?';
        }
        $first = mb_substr(trim((string) ($user['first_name'] ?? '')), 0, 1);
        $last = mb_substr(trim((string) ($user['last_name'] ?? '')), 0, 1);
        return mb_strtoupper($first . $last) ?: '?';
    }
}

if (!function_exists('format_date')) {
    function format_date($date, string $fallback = '—'): string
    {
        if (!$date) {
            return $fallback;
        }
        $ts = strtotime((string) $date);
        return $ts ? date('M j, Y', $ts) : $fallback;
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($date, string $fallback = '—'): string
    {
        if (!$date) {
            return $fallback;
        }
        $ts = strtotime((string) $date);
        return $ts ? date('M j, Y g:i A', $ts) : $fallback;
    }
}

if (!function_exists('time_ago')) {
    function time_ago($date): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime((string) $date);
        if (!$ts) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        $units = [31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
        foreach ($units as $seconds => $label) {
            if ($diff >= $seconds) {
                $n = (int) floor($diff / $seconds);
                return $n . ' ' . $label . ($n === 1 ? '' : 's') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('format_rating')) {
    function format_rating($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals);
    }
}

if (!function_exists('format_percent')) {
    function format_percent($value, int $decimals = 1): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals) . '%';
    }
}

if (!function_exists('rating_tone')) {
    function rating_tone($value): string
    {
        if ($value === null || $value === '') {
            return 'muted';
        }
        $value = (float) $value;
        if ($value >= 4.5) return 'excellent';
        if ($value >= 3.5) return 'good';
        if ($value >= 2.5) return 'fair';
        return 'poor';
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'active' => 'success', 'published' => 'success', 'submitted' => 'success', 'improving' => 'success',
            'completed' => 'success', 'stable' => 'info', 'draft' => 'secondary', 'scheduled' => 'info',
            'expired' => 'warning', 'needs_attention' => 'danger', 'closed' => 'dark', 'archived' => 'secondary',
            'inactive' => 'secondary', 'pending' => 'warning', 'open' => 'warning', 'in_progress' => 'info',
            'resolved' => 'success', 'acknowledged' => 'info', 'invited' => 'info', 'sufficient' => 'success',
            'insufficient_data' => 'secondary', 'revoked' => 'danger', 'demo' => 'purple',
        ];
        $class = $map[strtolower($status)] ?? 'secondary';
        $label = ucwords(str_replace('_', ' ', $status));
        return '<span class="badge status-badge badge-' . e($class) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('sentiment_badge')) {
    function sentiment_badge(?string $label): string
    {
        $map = ['positive' => 'success', 'negative' => 'danger', 'neutral' => 'secondary', 'mixed' => 'warning'];
        $label = $label ?: 'unrated';
        $class = $map[strtolower($label)] ?? 'secondary';
        return '<span class="badge badge-' . $class . '">' . e(ucfirst($label)) . '</span>';
    }
}

if (!function_exists('active_nav')) {
    function active_nav(string $prefix): string
    {
        $path = trim(App\Core\Request::path(), '/');
        $prefix = trim($prefix, '/');
        return ($path === $prefix || strpos($path . '/', $prefix . '/') === 0) ? 'active' : '';
    }
}

if (!function_exists('query_url')) {
    function query_url(array $params = []): string
    {
        $query = $_GET;
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        $qs = http_build_query($query);
        $path = App\Core\Request::path();
        return url($path) . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 120): string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }
}

if (!function_exists('selected')) {
    function selected($a, $b): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }
}

if (!function_exists('checked')) {
    function checked($value): string
    {
        return $value ? ' checked' : '';
    }
}

if (!function_exists('display_demo_badge')) {
    function display_demo_badge($isDemo): string
    {
        return !empty($isDemo) ? '<span class="badge badge-demo ms-1">Demo</span>' : '';
    }
}

if (!function_exists('interpret_uat')) {
    function interpret_uat($mean): string
    {
        if ($mean === null || $mean === '') {
            return 'No data';
        }
        $mean = (float) $mean;
        if ($mean >= 4.50) return 'Very Satisfactory';
        if ($mean >= 3.50) return 'Satisfactory';
        if ($mean >= 2.50) return 'Fair';
        if ($mean >= 1.50) return 'Poor';
        return 'Very Poor';
    }
}
