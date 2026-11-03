<?php

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\Notification;
use App\Models\User;

/**
 * Login attempt verification with database-backed rate limiting.
 */
final class AuthenticationService
{
    public function attempt(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $ip = \App\Core\Request::ip();

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Enter your username/email and password.'];
        }

        $lock = $this->lockoutStatus($identifier, $ip);
        if ($lock['locked']) {
            LoginAttempt::record($identifier, false, $ip, 'rate-limited');
            return [
                'success' => false,
                'message' => 'Too many failed sign-in attempts. Please try again in ' . $lock['minutes'] . ' minute(s).',
            ];
        }

        $user = User::findByLogin($identifier);

        // Always perform a hash comparison to reduce obvious timing differences.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $verified = password_verify($password, $hash);

        if ($user === null || !$verified) {
            LoginAttempt::record($identifier, false, $ip, 'invalid-credentials');
            AuditLog::record($user !== null ? (int) $user['user_id'] : null, 'auth.login_failed', 'Authentication', 'login', 'Invalid credentials for identifier: ' . $identifier);
            $remaining = $this->remainingAttempts($identifier, $ip);
            $suffix = $remaining > 0 ? ' ' . $remaining . ' attempt(s) remaining before temporary lockout.' : '';
            return ['success' => false, 'message' => 'Invalid username/email or password.' . $suffix];
        }

        if (($user['account_status'] ?? '') !== 'Active') {
            LoginAttempt::record($identifier, false, $ip, 'inactive-account');
            AuditLog::record((int) $user['user_id'], 'auth.login_inactive', 'Authentication', 'login', 'Inactive account attempted to sign in.');
            return ['success' => false, 'message' => 'This account is inactive. Please contact the Dean.'];
        }

        if (!in_array($user['role'] ?? '', ['Dean', 'Faculty'], true)) {
            LoginAttempt::record($identifier, false, $ip, 'invalid-role');
            return ['success' => false, 'message' => 'This account is not permitted to sign in.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            User::setPassword((int) $user['user_id'], password_hash($password, PASSWORD_DEFAULT), (bool) $user['must_change_password']);
        }

        LoginAttempt::record($identifier, true, $ip, 'success');
        AuditLog::record((int) $user['user_id'], 'auth.login', 'Authentication', 'login', 'Successful sign-in.');

        return ['success' => true, 'message' => 'Signed in successfully.', 'user' => $user];
    }

    /**
     * @return array{locked:bool,minutes:int,failures:int}
     */
    public function lockoutStatus(string $identifier, ?string $ip = null): array
    {
        $windowMinutes = (int) setting('login_lockout_minutes', 15);
        $maxAttempts = (int) setting('login_max_attempts', 5);
        $windowMinutes = max(1, $windowMinutes);
        $maxAttempts = max(1, $maxAttempts);

        $failures = LoginAttempt::recentFailures($identifier, $ip, $windowMinutes);
        if ($failures < $maxAttempts) {
            return ['locked' => false, 'minutes' => 0, 'failures' => $failures];
        }

        $lastFailure = LoginAttempt::lastFailureAt($identifier, $ip);
        $elapsed = $lastFailure !== null ? (time() - strtotime($lastFailure)) : $windowMinutes * 60;
        $minutes = max(1, (int) ceil(($windowMinutes * 60 - $elapsed) / 60));

        return ['locked' => true, 'minutes' => $minutes, 'failures' => $failures];
    }

    public function remainingAttempts(string $identifier, ?string $ip = null): int
    {
        $maxAttempts = max(1, (int) setting('login_max_attempts', 5));
        $window = max(1, (int) setting('login_lockout_minutes', 15));
        return max(0, $maxAttempts - LoginAttempt::recentFailures($identifier, $ip, $window));
    }

    public function passwordIsStrong(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Generate a temporary password for Dean-managed resets.
     */
    public function temporaryPassword(int $length = 14): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%&*?';
        $all = $upper . $lower . $digits . $symbols;

        $password = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    public function notifyPasswordReset(int $userId): void
    {
        try {
            Notification::push(
                $userId,
                'Password reset by the Dean',
                'Your password was reset. Please change it after signing in.',
                '/account/password',
                'warning'
            );
        } catch (\Throwable $e) {
            app_log('warning', 'Password reset notification failed: ' . $e->getMessage());
        }
    }
}
