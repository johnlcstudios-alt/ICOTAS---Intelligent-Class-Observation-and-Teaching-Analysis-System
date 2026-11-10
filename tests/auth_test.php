<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Session;

test('demo credentials satisfy the stored password hash and active role', function (): void {
    $user = Database::fetch(
        'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.username = ? LIMIT 1',
        ['demo.dean']
    );
    assertTrue($user !== null, 'demo.dean must exist after seeding');
    assertTrue(password_verify('Demo@12345', $user['password_hash']), 'demo password must verify');
    assertSame('dean', $user['role']);
    assertSame('active', strtolower((string) $user['status']));
});

test('authentication rejects a wrong password', function (): void {
    Database::execute('DELETE FROM login_attempts WHERE username = ?', ['demo.dean']);
    RateLimiter::clear('auth.login');

    [$ok, $message] = Auth::attempt('demo.dean', 'definitely-wrong');
    assertFalse($ok);
    assertContains('Invalid credentials', $message);
});

test('five failures lock the account/network pair for fifteen minutes', function (): void {
    Database::execute('DELETE FROM login_attempts WHERE username = ?', ['demo.dean']);
    RateLimiter::clear('auth.login');

    for ($i = 0; $i < 5; $i++) {
        [$ok] = Auth::attempt('demo.dean', 'wrong-password-' . $i);
        assertFalse($ok);
    }
    [$ok, $message] = Auth::attempt('demo.dean', 'Demo@12345');
    assertFalse($ok, 'the sixth attempt must be blocked even with the correct password');
    assertContains('Too many failed sign-in attempts', $message);

    Database::execute('DELETE FROM login_attempts WHERE username = ?', ['demo.dean']);
    RateLimiter::clear('auth.login');
});

test('valid credentials establish the session identity and role', function (): void {
    Database::execute('DELETE FROM login_attempts WHERE username = ?', ['demo.dean']);
    RateLimiter::clear('auth.login');

    [$ok, $message] = Auth::attempt('demo.dean', 'Demo@12345');
    assertTrue($ok, $message);
    assertContains('Welcome back', $message);

    $user = Auth::user();
    assertTrue($user !== null);
    assertSame('dean', Auth::role());
    assertSame('dean/dashboard', Auth::dashboardPath());
    assertTrue(Auth::isAdmin());
});

test('logout clears the authenticated identity', function (): void {
    $id = Auth::id();
    assertTrue($id !== null);

    Auth::logout();
    assertFalse(Auth::check());
    assertSame('', Auth::role());
    assertSame('login', Auth::dashboardPath());

    // Restore the session wrapper for any later CLI test.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        Session::start();
    }
});
