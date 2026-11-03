<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthenticationService;

final class AccountController extends Controller
{
    public function profile(): void
    {
        $this->render('account/profile', [
            'pageTitle'    => 'My Account',
            'pageSubtitle' => 'Your ICOTAS account details and security settings.',
            'activeNav'    => 'account',
            'user'         => Auth::user(),
        ]);
    }

    public function updateProfile(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $data = [
            'full_name'  => trim((string) Request::input('full_name', '')),
            'email'      => trim((string) Request::input('email', '')),
            'contact_no' => trim((string) Request::input('contact_no', '')),
        ];

        $validated = $this->validate($data, [
            'full_name'  => 'required|max:150',
            'email'      => 'required|email|max:190|unique:users,email,' . (int) $user['user_id'] . ',user_id',
            'contact_no' => 'nullable|max:30',
        ], [
            'full_name'  => 'Full name',
            'email'      => 'Email address',
            'contact_no' => 'Contact number',
        ], '/account');

        User::updateById((int) $user['user_id'], $validated);
        AuditLog::record((int) $user['user_id'], 'account.profile_updated', 'My Account', 'user:' . $user['user_id'], 'Profile details updated.');
        Flash::success('Your profile has been updated.');
        redirect('/account');
    }

    public function passwordForm(): void
    {
        $this->render('account/password', [
            'pageTitle'    => 'Change Password',
            'pageSubtitle' => 'Choose a strong password you do not use elsewhere.',
            'activeNav'    => 'account',
            'user'         => Auth::user(),
            'forced'       => (bool) (Auth::user()['must_change_password'] ?? false),
        ]);
    }

    public function updatePassword(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $current = (string) Request::input('current_password', '');
        $new = (string) Request::input('password', '');

        $full = User::find((int) $user['user_id']);
        if ($full === null || !password_verify($current, (string) $full['password_hash'])) {
            Flash::withErrors(['current_password' => 'Your current password is incorrect.']);
            Flash::error('Your current password is incorrect.');
            redirect('/account/password');
        }

        $service = new AuthenticationService();
        if (!$service->passwordIsStrong($new)) {
            Flash::withErrors(['password' => 'The new password must be at least 10 characters and include uppercase, lowercase, number and symbol.']);
            Flash::error('The new password does not meet the strength requirement.');
            redirect('/account/password');
        }
        if ($new === $current) {
            Flash::withErrors(['password' => 'The new password must be different from your current password.']);
            redirect('/account/password');
        }

        User::setPassword((int) $user['user_id'], $service->hash($new), false);
        AuditLog::record((int) $user['user_id'], 'account.password_changed', 'My Account', 'user:' . $user['user_id'], 'Password changed by the account owner.');
        \App\Core\Session::regenerate();
        Flash::success('Your password has been updated.');
        redirect(\App\Core\Auth::dashboardPath());
    }
}
