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
use App\Services\InviteService;

/**
 * Administrator (Dean / Academic Head) account provisioning.
 *
 * Only an authenticated Dean may reach this module: creating an administrative
 * account is a privilege-granting action, so an Academic Head cannot use it to
 * escalate. Two provisioning paths are offered:
 *
 *   1. Direct creation — the Dean supplies the profile and ICOTAS issues either
 *      a generated temporary password (change required at first sign-in) or an
 *      explicit password.
 *   2. Invitation — the Dean issues a single-use, hashed invitation code that
 *      the recipient redeems at /dean-signup, choosing their own password.
 *
 * Every action is audit-logged and refused while its prerequisites are absent.
 */
final class AdministratorController extends BaseController
{
    private User $users;
    private Academic $academic;
    private InviteService $invites;

    public function __construct()
    {
        $this->users = new User();
        $this->academic = new Academic();
        $this->invites = new InviteService();
    }

    public function index(): void
    {
        Auth::requireDean();

        $demo = $this->demo();
        $generatedCredentials = Session::pull('_administrator_credentials');
        $generatedInvite = Session::pull('_administrator_invite');

        $this->view('dean/administrators/index', [
            'pageTitle' => 'Administrator Accounts',
            'administrators' => $this->users->administrators($demo),
            'counts' => $this->users->countAdministrators($demo),
            'instructorCount' => $this->users->countByRole('instructor', $demo),
            'studentCount' => $this->users->countByRole('student', $demo),
            'invites' => $this->deanInvites(),
            'departments' => $this->academic->departments($demo, true),
            'generatedCredentials' => is_array($generatedCredentials) ? $generatedCredentials : null,
            'generatedInvite' => is_array($generatedInvite) ? $generatedInvite : null,
            'deanSignupEnabled' => (string) setting('allow_dean_signup', '1') === '1' && !$demo,
            'demo' => $demo,
        ]);
    }

    /**
     * Create an administrative account directly, without an invitation.
     */
    public function store(): void
    {
        $this->requirePost();
        Auth::requireDean();

        if (RateLimiter::tooMany('administrator.create', 30, 3600)) {
            $this->error('Too many administrator accounts were created from this network. Try again later.', 'dean/administrators');
        }

        $input = [
            'first_name' => $this->postString('first_name'),
            'middle_name' => $this->postString('middle_name'),
            'last_name' => $this->postString('last_name'),
            'suffix' => $this->postString('suffix'),
            'username' => strtolower($this->postString('username')),
            'email' => strtolower($this->postString('email')),
            'employee_number' => $this->postString('employee_number'),
            'phone' => $this->postString('phone'),
            'role' => $this->postString('role', 'dean'),
            'status' => $this->postString('status', 'active'),
            'password_mode' => $this->postString('password_mode', 'generate'),
        ];
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

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

    /**
     * Issue a single-use invitation code that the recipient redeems at
     * /dean-signup to choose their own password.
     */
    public function storeInvite(): void
    {
        $this->requirePost();
        Auth::requireDean();

        if (RateLimiter::tooMany('administrator.invite', 30, 3600)) {
            $this->error('Too many invitation codes were requested from this network. Try again later.', 'dean/administrators');
        }

        $maxUses = max(1, min(InviteService::MAX_USES_LIMIT, $this->intInput('max_uses', 1)));
        $validDays = max(1, min(InviteService::MAX_VALID_DAYS, $this->intInput('valid_days', 7)));
        $note = $this->postString('note');
        if (mb_strlen($note) > 190) {
            $this->error('The note is too long. Keep it under 190 characters.', 'dean/administrators');
        }

        try {
            $invite = $this->invites->create('dean', $maxUses, $validDays, $note, Auth::id());
        } catch (\Throwable $e) {
            error_log('Dean invitation creation failed: ' . $e->getMessage());
            $this->error('The invitation code could not be created.', 'dean/administrators');
        }

        (new AuditService())->record(
            'invite.create',
            'registration_invite',
            (int) $invite['id'],
            'Dean invitation code created (' . $maxUses . ' use(s), expires ' . $invite['expires_at'] . ')'
        );

        Session::set('_administrator_invite', $invite);
        $this->success('Dean invitation code created. Copy it now — it is shown only once.', 'dean/administrators');
    }

    public function revokeInvite(string $id): void
    {
        $this->requirePost();
        Auth::requireDean();

        $invite = $this->invites->find((int) $id);
        if (!$invite) {
            abort(404);
        }
        if (!$this->isDeanInvite($invite)) {
            $this->error('That invitation code does not belong to this module.', 'dean/administrators');
        }
        if ($this->invites->revoke((int) $id)) {
            (new AuditService())->record(
                'invite.revoke',
                'registration_invite',
                (int) $id,
                'Revoked Dean invitation code ending ' . (string) $invite['code_hint']
            );
            $this->success('Invitation code revoked.', 'dean/administrators');
        }
        $this->error('That invitation code was already revoked or is unavailable.', 'dean/administrators');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function deanInvites(int $limit = 50): array
    {
        $deanRoleId = $this->users->roleId('dean');
        return array_values(array_filter(
            $this->invites->all($limit),
            static fn (array $invite): bool => $deanRoleId !== null && (int) $invite['role_id'] === $deanRoleId
        ));
    }

    private function isDeanInvite(array $invite): bool
    {
        $deanRoleId = $this->users->roleId('dean');
        return $deanRoleId !== null && (int) $invite['role_id'] === $deanRoleId;
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
