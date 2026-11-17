<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Academic;
use App\Models\User;
use App\Services\AuditService;

/**
 * Administrator (Dean / Academic Head) account provisioning.
 *
 * Both administrative roles can create accounts directly. A Dean may create
 * either administrative role; an Academic Head may create Academic Head
 * accounts only. The ceiling is enforced on the server, not just in the form,
 * so a forged role cannot be used to escalate privileges.
 *
 * No invitation codes exist: the creator supplies the profile and ICOTAS
 * issues either a generated temporary password (change required at first
 * sign-in) or an explicit password. Every action is audit-logged.
 */
final class AdministratorController extends BaseController
{
    private User $users;
    private Academic $academic;

    public function __construct()
    {
        $this->users = new User();
        $this->academic = new Academic();
    }

    public function index(): void
    {
        Auth::requireAdmin();

        $demo = $this->demo();
        $isDean = Auth::isDean();
        $generatedCredentials = Session::pull('_administrator_credentials');
        $administrators = $this->users->administrators($demo);

        // An Academic Head must never see Dean records, even though both roles
        // share this module.
        if (!$isDean) {
            $administrators = array_values(array_filter(
                $administrators,
                static fn (array $row): bool => (string) $row['role'] === 'academic_head'
            ));
        }

        $counts = $this->users->countAdministrators($demo);
        if (!$isDean) {
            $counts['dean'] = 0;
        }

        $this->view('dean/administrators/index', [
            'pageTitle' => 'Administrator Accounts',
            'administrators' => $administrators,
            'counts' => $counts,
            'instructorCount' => $this->users->countByRole('instructor', $demo),
            'studentCount' => $this->users->countByRole('student', $demo),
            'departments' => $this->academic->departments($demo, true),
            'generatedCredentials' => is_array($generatedCredentials) ? $generatedCredentials : null,
            'isDean' => $isDean,
            'demo' => $demo,
        ]);
    }

    /**
     * Create an administrative account directly. Invitation codes are gone;
     * the creator owns the credential hand-off.
     */
    public function store(): void
    {
        $this->requirePost();
        Auth::requireAdmin();

        if (RateLimiter::tooMany('administrator.create', 30, 3600)) {
            $this->error('Too many administrator accounts were created from this network. Try again later.', 'dean/administrators');
        }

        $isDean = Auth::isDean();
        $input = [
            'first_name' => $this->postString('first_name'),
            'middle_name' => $this->postString('middle_name'),
            'last_name' => $this->postString('last_name'),
            'suffix' => $this->postString('suffix'),
            'username' => strtolower($this->postString('username')),
            'email' => strtolower($this->postString('email')),
            'employee_number' => $this->postString('employee_number'),
            'phone' => $this->postString('phone'),
            'role' => $this->postString('role', $isDean ? 'dean' : 'academic_head'),
            'status' => $this->postString('status', 'active'),
            'password_mode' => $this->postString('password_mode', 'generate'),
        ];
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

        if ($input['role'] === 'dean' && !$isDean) {
            $this->rememberInput($input);
            abort(403, 'Only a Dean may create another Dean account.');
        }
        if (!in_array($input['role'], ['dean', 'academic_head'], true)) {
            $this->rememberInput($input);
            $this->error('Choose a valid administrative role.', 'dean/administrators');
        }

        $validator = Validator::make(array_merge($input, [
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]), [
            'first_name' => 'required|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name' => 'required|max:80',
            'suffix' => 'nullable|max:20',
            'username' => 'required|min:3|max:80|regex:/^[A-Za-z0-9._-]+$/',
            'email' => 'required|email|max:190',
            'employee_number' => 'nullable|max:40',
            'phone' => 'nullable|max:30',
            'role' => 'required|in:dean,academic_head',
            'status' => 'required|in:active,inactive',
            'password_mode' => 'required|in:generate,manual',
        ]);

        if ($validator->fails()) {
            $this->rememberInput($input);
            $this->error((string) $validator->firstError(), 'dean/administrators');
        }

        if ($input['password_mode'] === 'manual') {
            $problem = Security::passwordProblem($password);
            if ($problem !== null) {
                $this->rememberInput($input);
                $this->error($problem, 'dean/administrators');
            }
            if ($password !== $confirmation) {
                $this->rememberInput($input);
                $this->error('The password confirmation does not match.', 'dean/administrators');
            }
        }

        if ($this->valueExists('username', $input['username']) || $this->valueExists('email', $input['email'])) {
            $this->rememberInput($input);
            $this->error('That username or email address is already registered.', 'dean/administrators');
        }
        if ($input['employee_number'] !== '' && $this->valueExists('employee_number', $input['employee_number'])) {
            $this->rememberInput($input);
            $this->error('That employee number is already registered.', 'dean/administrators');
        }

        $roleId = $this->users->roleId($input['role']);
        if ($roleId === null) {
            $this->error('That administrative role is not available in this installation.', 'dean/administrators');
        }

        $generated = $input['password_mode'] === 'generate';
        $plainPassword = $generated ? Security::temporaryPassword() : $password;

        try {
            $id = Database::transaction(function () use ($input, $roleId, $plainPassword, $generated): int {
                return $this->users->createAccount([
                    'role_id' => $roleId,
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                    'first_name' => $input['first_name'],
                    'middle_name' => $input['middle_name'] !== '' ? $input['middle_name'] : null,
                    'last_name' => $input['last_name'],
                    'suffix' => $input['suffix'] !== '' ? $input['suffix'] : null,
                    'employee_number' => $input['employee_number'] !== '' ? $input['employee_number'] : null,
                    'department_id' => $this->intInput('department_id') ?: null,
                    'phone' => $input['phone'] !== '' ? $input['phone'] : null,
                    'status' => $input['status'],
                    'is_demo' => $this->demo() ? 1 : 0,
                    'must_change_password' => $generated ? 1 : 0,
                ]);
            });
        } catch (\Throwable $e) {
            error_log('Administrator account creation failed: ' . $e->getMessage());
            $this->rememberInput($input);
            $this->error('The administrator account could not be created. The username, email, or employee number may already be in use.', 'dean/administrators');
        }

        Session::remove('_old');
        $label = $input['role'] === 'dean' ? 'Dean' : 'Academic Head';
        (new AuditService())->record(
            'user.administrator_create',
            'user',
            $id,
            'Created ' . $label . ' account ' . $input['username']
            . ($generated ? ' with a generated temporary password' : ' with an explicit password')
        );

        if ($generated) {
            Session::set('_administrator_credentials', [
                'user_id' => $id,
                'username' => $input['username'],
                'password' => $plainPassword,
                'role' => $label,
            ]);
            $this->success($label . ' account created. Copy the temporary password now — it is shown only once.', 'dean/administrators');
        }

        $this->success($label . ' account created successfully.', 'dean/administrators');
    }

    private function rememberInput(array $input): void
    {
        unset($input['password_mode']);
        Session::set('_old', $input);
    }

    private function valueExists(string $column, string $value): bool
    {
        if (!in_array($column, ['username', 'email', 'employee_number'], true)) {
            return false;
        }
        return (int) Database::scalar('SELECT COUNT(*) FROM users WHERE ' . $column . ' = ?', [$value], 0) > 0;
    }
}
