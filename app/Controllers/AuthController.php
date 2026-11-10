<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Session;

final class AuthController extends BaseController
{
    public function showLogin(): void
    {
        $this->view('auth/login', [
            'pageTitle' => 'Sign in',
            'loginMode'   => 'signin',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->requirePost();
        // Network-level ceiling guards against credential-stuffing floods. Per-account
        // lockout in Auth::attempt() remains strict (5 failures / 15 min) and this
        // ceiling is deliberately high enough for a shared campus NAT.
        if (RateLimiter::tooMany('auth.login', 100, 900)) {
            $this->error('Too many sign-in attempts from this network. Please wait 15 minutes.', 'login');
        }

        $login = $this->postString('login');
        $password = (string) $this->input('password', '');

        if ($login === '' || $password === '') {
            Session::set('_old', ['login' => $login]);
            $this->error('Enter your username or email and password.', 'login');
        }

        [$ok, $message] = Auth::attempt($login, $password);
        if (!$ok) {
            Session::set('_old', ['login' => $login]);
            $this->error($message, 'login');
        }

        $intended = (string) Session::pull('_intended', '');
        if ($intended !== '' && strpos($intended, '//') !== 0 && strpos($intended, ':') === false) {
            flash('success', $message);
            redirect(ltrim($intended, '/'));
        }

        flash('success', $message);
        redirect(Auth::dashboardPath());
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect('login');
    }
}
