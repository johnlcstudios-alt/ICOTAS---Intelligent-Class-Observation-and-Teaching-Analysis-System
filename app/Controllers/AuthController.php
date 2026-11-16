<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\Setting;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InviteService;

final class AuthController extends BaseController
{
    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    public function showLogin(): void
    {
        $this->view('auth/login', [
            'pageTitle' => 'Sign in',
            'loginMode'   => 'signin',
            'signupEnabled' => $this->signupEnabled(),
            'deanSignupEnabled' => $this->deanSignupEnabled(),
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

        if (Auth::mustChangePassword()) {
            flash('warning', 'Your account is using a temporary password. Choose a new password to continue.');
            redirect('account/password');
        }

        $intended = (string) Session::pull('_intended', '');
        if ($intended !== '' && strpos($intended, '//') !== 0 && strpos($intended, ':') === false) {
            flash('success', $message);
            redirect(ltrim($intended, '/'));
        }

        flash('success', $message);
        redirect(Auth::dashboardPath());
    }

    public function showSignup(): void
    {
        if (!$this->signupEnabled()) {
            $this->signupClosedMessage();
        }

        $this->view('auth/signup', [
            'pageTitle' => 'Create student account',
            'loginMode' => 'signup',
        ], 'layouts/auth');
    }

    public function signup(): void
    {
        $this->requirePost();

        if (!$this->signupEnabled()) {
            $this->signupClosedMessage();
        }
        if (RateLimiter::tooMany('auth.signup', 8, 3600)) {
            $this->error('Too many account creation attempts from this network. Please try again later.', 'signup');
        }

        // Honeypot: real students never see or fill this field.
        if ($this->postString('website') !== '') {
            flash('success', 'Account created. Sign in with your username or email to continue.');
            redirect('login');
        }

        $input = [
            'first_name' => $this->postString('first_name'),
            'middle_name' => $this->postString('middle_name'),
            'last_name' => $this->postString('last_name'),
            'suffix' => $this->postString('suffix'),
            'username' => strtolower($this->postString('username')),
            'email' => strtolower($this->postString('email')),
            'student_number' => $this->postString('student_number'),
        ];
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');
        $accepted = $this->postString('accept_terms');

        $validator = Validator::make(array_merge($input, [
            'password' => $password,
            'password_confirmation' => $confirmation,
            'accept_terms' => $accepted,
        ]), [
            'first_name' => 'required|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name' => 'required|max:80',
            'suffix' => 'nullable|max:20',
            'username' => 'required|min:3|max:80|regex:/^[A-Za-z0-9._-]+$/',
            'email' => 'required|email|max:190',
            'student_number' => 'required|max:40',
            'password' => 'required|min:8|max:200|confirmed',
            'accept_terms' => 'required|in:1',
        ]);

        if ($validator->fails()) {
            $this->rememberSignupInput($input);
            $this->error((string) $validator->firstError(), 'signup');
        }

        $problem = Security::passwordProblem($password);
        if ($problem !== null) {
            $this->rememberSignupInput($input);
            $this->error($problem, 'signup');
        }

        if ($this->valueExists('username', $input['username']) || $this->valueExists('email', $input['email'])) {
            $this->rememberSignupInput($input);
            $this->error('That username or email address is already registered. Try another or sign in.', 'signup');
        }
        if ($this->valueExists('student_number', $input['student_number'])) {
            $this->rememberSignupInput($input);
            $this->error('That student number is already registered. Sign in or contact the academic office.', 'signup');
        }

        $roleId = $this->users->roleId('student');
        if ($roleId === null) {
            $this->error('Student registration is not available right now. Contact the academic office.', 'signup');
        }

        try {
            $id = $this->users->createAccount([
                'role_id' => $roleId,
                'username' => $input['username'],
                'email' => $input['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] !== '' ? $input['middle_name'] : null,
                'last_name' => $input['last_name'],
                'suffix' => $input['suffix'] !== '' ? $input['suffix'] : null,
                'student_number' => $input['student_number'],
                'status' => 'active',
                'is_demo' => 0,
            ]);
        } catch (\Throwable $e) {
            error_log('Student self-registration failed: ' . $e->getMessage());
            $this->rememberSignupInput($input);
            $this->error('The account could not be created. The username, email, or student number may already be in use.', 'signup');
        }

        Session::remove('_old');
        (new AuditService())->record(
            'user.signup',
            'user',
            $id,
            'Self-registered student account ' . $input['username'] . ' (student number ' . $input['student_number'] . ')',
            $id
        );

        flash('success', 'Account created. Sign in with your username or email to continue.');
        redirect('login');
    }

    public function showDeanSignup(): void
    {
        if (!$this->deanSignupEnabled()) {
            $this->deanSignupClosedMessage();
        }

        $this->view('auth/dean-signup', [
            'pageTitle' => 'Dean registration',
            'loginMode' => 'dean-signup',
        ], 'layouts/auth');
    }

    public function deanSignup(): void
    {
        $this->requirePost();

        if (!$this->deanSignupEnabled()) {
            $this->deanSignupClosedMessage();
        }
        if (RateLimiter::tooMany('auth.dean_signup', 5, 3600)) {
            $this->error('Too many Dean registration attempts from this network. Please try again later.', 'dean-signup');
        }

        // Honeypot: real applicants never see or fill this field.
        if ($this->postString('website') !== '') {
            flash('success', 'Dean account created. Sign in with your username or email to continue.');
            redirect('login');
        }

        $input = [
            'first_name' => $this->postString('first_name'),
            'middle_name' => $this->postString('middle_name'),
            'last_name' => $this->postString('last_name'),
            'suffix' => $this->postString('suffix'),
            'username' => strtolower($this->postString('username')),
            'email' => strtolower($this->postString('email')),
            'employee_number' => $this->postString('employee_number'),
            'invitation_code' => strtoupper($this->postString('invitation_code')),
        ];
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');
        $accepted = $this->postString('accept_terms');

        $validator = Validator::make(array_merge($input, [
            'password' => $password,
            'password_confirmation' => $confirmation,
            'accept_terms' => $accepted,
        ]), [
            'first_name' => 'required|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name' => 'required|max:80',
            'suffix' => 'nullable|max:20',
            'username' => 'required|min:3|max:80|regex:/^[A-Za-z0-9._-]+$/',
            'email' => 'required|email|max:190',
            'employee_number' => 'nullable|max:40',
            'invitation_code' => 'required|min:8|max:40|regex:/^[A-Za-z0-9-]+$/',
            'password' => 'required|min:8|max:200|confirmed',
            'accept_terms' => 'required|in:1',
        ]);

        if ($validator->fails()) {
            $this->rememberDeanSignupInput($input);
            $this->error((string) $validator->firstError(), 'dean-signup');
        }

        $problem = Security::passwordProblem($password);
        if ($problem !== null) {
            $this->rememberDeanSignupInput($input);
            $this->error($problem, 'dean-signup');
        }

        if ($this->valueExists('username', $input['username']) || $this->valueExists('email', $input['email'])) {
            $this->rememberDeanSignupInput($input);
            $this->error('That username or email address is already registered. Try another or sign in.', 'dean-signup');
        }
        if ($input['employee_number'] !== '' && $this->valueExists('employee_number', $input['employee_number'])) {
            $this->rememberDeanSignupInput($input);
            $this->error('That employee number is already registered. Contact the system administrator.', 'dean-signup');
        }

        $roleId = $this->users->roleId('dean');
        if ($roleId === null) {
            $this->error('Dean registration is not available right now. Contact the system administrator.', 'dean-signup');
        }

        try {
            $inviteService = new InviteService();
            $id = Database::transaction(function () use ($input, $password, $roleId, $inviteService): int {
                $invite = $inviteService->lockRedeemable($input['invitation_code'], 'dean');
                $id = $this->users->createAccount([
                    'role_id' => $roleId,
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name' => $input['first_name'],
                    'middle_name' => $input['middle_name'] !== '' ? $input['middle_name'] : null,
                    'last_name' => $input['last_name'],
                    'suffix' => $input['suffix'] !== '' ? $input['suffix'] : null,
                    'employee_number' => $input['employee_number'] !== '' ? $input['employee_number'] : null,
                    'status' => 'active',
                    'is_demo' => 0,
                    'must_change_password' => 0,
                ]);
                $inviteService->consume((int) $invite['id']);
                return $id;
            });
        } catch (\InvalidArgumentException $e) {
            $this->rememberDeanSignupInput($input);
            $this->error('That invitation code is invalid or no longer available. Ask the system developer for a new code.', 'dean-signup');
        } catch (\Throwable $e) {
            error_log('Dean self-registration failed: ' . $e->getMessage());
            $this->rememberDeanSignupInput($input);
            $this->error('The Dean account could not be created. The username, email, or employee number may already be in use.', 'dean-signup');
        }

        Session::remove('_old');
        (new AuditService())->record(
            'user.dean_signup',
            'user',
            $id,
            'Self-registered Dean account ' . $input['username'] . ' using an invitation code',
            $id
        );

        flash('success', 'Dean account created. Sign in with your username or email to continue.');
        redirect('login');
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect('login');
    }

    private function signupEnabled(): bool
    {
        // Self-registration creates live accounts, so it is closed in demo mode.
        return (string) Setting::get('allow_student_signup', '1') === '1' && !Setting::demoMode();
    }

    private function signupClosedMessage(): void
    {
        if (Setting::demoMode()) {
            $this->error('Student self-registration is unavailable while demonstration mode is active.', 'login');
        }
        $this->error('Student self-registration is currently closed. Contact the academic office for an account.', 'login');
    }

    private function deanSignupEnabled(): bool
    {
        // Invitation-gated Dean signup is still live account creation, so demo
        // mode always closes it. The setting lets a Dean disable the endpoint.
        return (string) Setting::get('allow_dean_signup', '1') === '1' && !Setting::demoMode();
    }

    private function deanSignupClosedMessage(): void
    {
        if (Setting::demoMode()) {
            $this->error('Dean self-registration is unavailable while demonstration mode is active.', 'login');
        }
        $this->error('Dean self-registration is currently closed. Contact the system administrator for an invitation.', 'login');
    }

    private function rememberDeanSignupInput(array $input): void
    {
        Session::set('_old', $input);
    }

    private function rememberSignupInput(array $input): void
    {
        Session::set('_old', $input);
    }

    private function valueExists(string $column, string $value): bool
    {
        if (!in_array($column, ['username', 'email', 'student_number', 'employee_number'], true)) {
            return false;
        }
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM users WHERE ' . $column . ' = ?',
            [$value],
            0
        ) > 0;
    }
}
