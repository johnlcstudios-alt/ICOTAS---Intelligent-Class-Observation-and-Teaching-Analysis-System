<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Security;
use App\Core\Session;
use App\Models\User;

final class AccountController extends BaseController
{
    public function show(): void
    {
        $this->view('auth/password', [
            'pageTitle' => Auth::mustChangePassword() ? 'Choose a new password' : 'Change password',
            'forced' => Auth::mustChangePassword(),
        ], 'layouts/auth');
    }

    public function update(): void
    {
        $this->requirePost();
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }

        $current = (string) $this->input('current_password', '');
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

        if ($current === '' || !password_verify($current, (string) $user['password_hash'])) {
            $this->error('Your current password is incorrect.', 'account/password');
        }
        $problem = Security::passwordProblem($password);
        if ($problem !== null) {
            $this->error($problem, 'account/password');
        }
        if ($password !== $confirmation) {
            $this->error('The new password confirmation does not match.', 'account/password');
        }
        if (password_verify($password, (string) $user['password_hash'])) {
            $this->error('Choose a password that is different from your current password.', 'account/password');
        }

        $id = (int) $user['id'];
        (new User())->changePassword($id, $password);

        // End every other session, then keep this browser signed in.
        Database::execute('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
        Session::regenerate();
        Csrf::rotate();
        Database::execute(
            'INSERT INTO user_sessions (user_id, session_hash, ip_hash, user_agent, last_activity)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_activity = NOW()',
            [$id, hash('sha256', Session::id()), Security::hashIp(), Request::userAgent()]
        );

        $this->audit('auth.password_change', 'user', $id, 'Changed their own password');
        $this->success('Password updated. You can now continue.', Auth::dashboardPath());
    }
}
