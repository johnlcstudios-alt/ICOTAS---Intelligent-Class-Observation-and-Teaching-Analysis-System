<?php

namespace App\Core;

/**
 * Authentication, session identity and role gates.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function attempt(string $login, string $password): array
    {
        $login = strtolower(trim($login));
        $ipHash = Security::hashIp();

        if ($login === '' || $password === '') {
            return [false, 'Enter your username or email and password.'];
        }

        $recentFailures = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = ? AND ip_hash = ? AND success = 0
               AND attempted_at > (NOW() - INTERVAL 15 MINUTE)',
            [$login, $ipHash],
            0
        );
        if ($recentFailures >= 5) {
            self::logAttempt($login, false);
            return [false, 'Too many failed sign-in attempts. Please wait 15 minutes and try again.'];
        }

        $user = Database::fetch(
            'SELECT u.*, r.slug AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE (LOWER(u.username) = ? OR LOWER(u.email) = ?) LIMIT 1',
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::logAttempt($login, false);
            return [false, 'Invalid credentials. Check your username/email and password.'];
        }

        if (strtolower((string) $user['status']) !== 'active') {
            self::logAttempt($login, false);
            return [false, 'This account has been deactivated. Contact the Dean or system administrator.'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::logAttempt($login, true);

        Session::regenerate();
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_role'] = $user['role'];
        $_SESSION['auth_boot'] = time();
        $_SESSION['_last_activity'] = time();

        // The request that submitted the form may have already cached the
        // guest state (for example, the guest middleware calls Auth::check()).
        // Clear that cache so role checks, forced-password handling and the
        // post-login redirect see the account that just signed in.
        self::$loaded = false;
        self::$user = null;

        Database::execute(
            'INSERT INTO user_sessions (user_id, session_hash, ip_hash, user_agent, last_activity)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_activity = NOW()',
            [(int) $user['id'], hash('sha256', Session::id()), $ipHash, Request::userAgent()]
        );

        self::audit((int) $user['id'], 'auth.login', 'user', (int) $user['id'], 'Signed in successfully');

        // Lazy housekeeping; no cron jobs are required.
        if (random_int(1, 10) === 1) {
            Database::execute('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)');
            Database::execute('DELETE FROM user_sessions WHERE last_activity < (NOW() - INTERVAL 30 DAY)');
        }

        return [true, 'Welcome back, ' . $user['first_name'] . '.'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($id <= 0) {
            self::$user = null;
            return null;
        }

        $user = Database::fetch(
            'SELECT u.*, r.slug AS role, r.name AS role_name,
                    d.name AS department_name, p.name AS program_name, s.code AS section_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN sections s ON s.id = u.section_id
             WHERE u.id = ? LIMIT 1',
            [$id]
        );

        if (!$user || strtolower((string) $user['status']) !== 'active') {
            self::$user = null;
            unset($_SESSION['auth_user_id'], $_SESSION['auth_role']);
            return null;
        }

        self::$user = $user;
        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): string
    {
        $user = self::user();
        return $user ? (string) $user['role'] : '';
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['dean', 'academic_head'], true);
    }

    public static function isDean(): bool
    {
        return self::role() === 'dean';
    }

    /**
     * Returns the department ID that scopes this user's data access.
     * Dean and Academic Head accounts are scoped to their department_id.
     * Developer accounts are unscoped (null).
     *
     * @return int|null
     */
    public static function scopeDepartmentId(): ?int
    {
        $user = self::user();
        if (!$user) {
            return null;
        }
        $role = (string) $user['role'];
        if (in_array($role, ['dean', 'academic_head'], true)) {
            $deptId = (int) ($user['department_id'] ?? 0);
            return $deptId > 0 ? $deptId : null;
        }
        return null;
    }

    /**
     * Returns a SQL WHERE clause fragment for department-scoped queries,
     * or null when the user is unscoped (developer, etc.).
     *
     * @param string $tableAlias Optional table alias prefix (e.g. 'u.', 'c.', 'd.')
     * @return string|null SQL fragment like "u.department_id = 5" or null
     */
    public static function scopeClause(string $tableAlias = ''): ?string
    {
        $deptId = self::scopeDepartmentId();
        if ($deptId === null) {
            return null;
        }
        return ($tableAlias !== '' ? $tableAlias : '') . 'department_id = ' . $deptId;
    }

    public static function mustChangePassword(): bool
    {
        $user = self::user();
        return $user !== null && (int) ($user['must_change_password'] ?? 0) === 1;
    }

    public static function requireRole($roles): void
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $user = self::user();
        if (!$user) {
            Session::set('_intended', Request::path());
            flash('error', 'Please sign in to continue.');
            redirect('login');
        }
        if (!in_array((string) $user['role'], $roles, true)) {
            abort(403);
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRole(['dean', 'academic_head']);
    }

    public static function isDeveloper(): bool
    {
        return self::role() === 'developer';
    }

    public static function requireDeveloper(): void
    {
        self::requireRole(['developer']);
    }

    public static function requireDean(): void
    {
        self::requireRole(['dean']);
    }

    public static function dashboardPath(): string
    {
        switch (self::role()) {
            case 'dean':
            case 'academic_head':
                return 'dean/dashboard';
            case 'instructor':
                return 'instructor/dashboard';
            case 'student':
                return 'student/dashboard';
            default:
                return 'login';
        }
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id) {
            Database::execute('DELETE FROM user_sessions WHERE session_hash = ?', [hash('sha256', Session::id())]);
            self::audit($id, 'auth.logout', 'user', $id, 'Signed out');
        }
        self::$user = null;
        self::$loaded = false;
        Session::destroy();
    }

    private static function logAttempt(string $login, bool $success): void
    {
        try {
            Database::execute(
                'INSERT INTO login_attempts (username, ip_hash, success, attempted_at) VALUES (?, ?, ?, NOW())',
                [mb_substr($login, 0, 190), Security::hashIp(), $success ? 1 : 0]
            );
        } catch (\Throwable $e) {
            error_log('login attempt log failed: ' . $e->getMessage());
        }
    }

    private static function audit(int $userId, string $action, string $entityType, int $entityId, string $description): void
    {
        try {
            Database::execute(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_hash, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$userId, $action, $entityType, $entityId, $description, Security::hashIp()]
            );
        } catch (\Throwable $e) {
            error_log('audit log failed: ' . $e->getMessage());
        }
    }
}
