<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthenticationService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (!User::deanExists()) {
            redirect('/setup');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Sign in',
            'bodyClass' => 'auth-body',
            'showSetupNotice' => false,
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $identifier = trim((string) Request::input('identifier', ''));
        $password = (string) Request::input('password', '');

        if (!User::deanExists()) {
            redirect('/setup');
        }

        $service = new AuthenticationService();
        $result = $service->attempt($identifier, $password);

        if (!$result['success']) {
            Flash::withErrors(['identifier' => $result['message']], ['identifier' => $identifier]);
            Flash::error($result['message']);
            redirect('/login');
        }

        $user = $result['user'];
        Auth::login($user);

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            Flash::warning('Your password was reset by the Dean. Please set a new password now.');
            redirect('/account/password');
        }

        $intended = Session::pull('intended_url');
        if (is_string($intended) && $intended !== '' && $intended !== '/login') {
            redirect($intended);
        }

        redirect(Auth::dashboardPath());
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user !== null) {
            AuditLog::record((int) $user['user_id'], 'auth.logout', 'Authentication', 'logout', 'User signed out.');
        }
        Auth::logout();
        Flash::success('You have been signed out.');
        redirect('/login');
    }

    /**
     * Lightweight session endpoint (JSON) for diagnostics and client checks.
     */
    public function session(): void
    {
        $user = Auth::user();
        if ($user === null) {
            json_response(['success' => false, 'message' => 'No active session.'], 401);
        }
        json_response([
            'success' => true,
            'data'    => [
                'user_id'   => (int) $user['user_id'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
                'dashboard' => Auth::dashboardPath(),
            ],
        ]);
    }
}
