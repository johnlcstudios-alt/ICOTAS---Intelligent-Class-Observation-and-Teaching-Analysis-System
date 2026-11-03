<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Acknowledgement;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Observation;
use App\Models\QRToken;
use App\Models\RateeComment;
use App\Models\User;
use App\Services\AuthenticationService;

final class UserController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters(['search', 'department_id', 'status']);
        $page = $this->pageNumber();

        $total = User::countFaculty($filters);
        $pagination = paginate($total, $page);
        $rows = User::paginateFaculty($filters, (int) $pagination['per_page'], (int) $pagination['offset']);

        $this->render('dean/users/index', [
            'pageTitle'    => 'Faculty Management',
            'pageSubtitle' => 'Create, update, deactivate and support faculty accounts.',
            'activeNav'    => 'users',
            'rows'         => $rows,
            'filters'      => $filters,
            'pagination'   => $pagination,
            'departments'  => Department::options(),
        ]);
    }

    public function create(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $this->render('dean/users/form', [
            'pageTitle'    => 'Add Faculty Account',
            'pageSubtitle' => 'Create a faculty account. No password is emailed automatically.',
            'activeNav'    => 'users',
            'user'         => null,
            'departments'  => Department::options(true),
        ]);
    }

    public function store(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $data = [
            'full_name'    => trim((string) Request::input('full_name', '')),
            'username'     => trim((string) Request::input('username', '')),
            'email'        => trim((string) Request::input('email', '')),
            'employee_no'  => trim((string) Request::input('employee_no', '')),
            'contact_no'   => trim((string) Request::input('contact_no', '')),
            'department_id'=> trim((string) Request::input('department_id', '')),
            'password'     => (string) Request::input('password', ''),
        ];

        $validated = $this->validate($data, [
            'full_name'     => 'required|max:150',
            'username'      => 'required|username|unique:users,username',
            'email'         => 'required|email|max:190|unique:users,email',
            'employee_no'   => 'nullable|max:40',
            'contact_no'    => 'nullable|max:30',
            'department_id' => 'required|integer|exists:departments,department_id',
            'password'      => 'required|password_strength|confirmed',
        ], [
            'full_name'     => 'Full name',
            'username'      => 'Username',
            'email'         => 'Email address',
            'employee_no'   => 'Employee number',
            'contact_no'    => 'Contact number',
            'department_id' => 'Department',
            'password'      => 'Temporary password',
        ], '/dean/users/create');

        $service = new AuthenticationService();
        $userId = User::create([
            'full_name'            => $validated['full_name'],
            'username'             => $validated['username'],
            'email'                => $validated['email'],
            'employee_no'          => $validated['employee_no'] !== '' ? $validated['employee_no'] : null,
            'contact_no'           => $validated['contact_no'] !== '' ? $validated['contact_no'] : null,
            'department_id'        => (int) $validated['department_id'],
            'password_hash'        => $service->hash($validated['password']),
            'role'                 => 'Faculty',
            'account_status'       => 'Active',
            'must_change_password' => 1,
        ]);

        AuditLog::record((int) $dean['user_id'], 'user.created', 'Faculty Management', 'user:' . $userId, 'Faculty account created.');
        try {
            Notification::push($userId, 'Welcome to ICOTAS', 'Your faculty account has been created. Please change your temporary password after signing in.', '/account/password', 'info');
        } catch (\Throwable $e) {
            app_log('warning', 'Welcome notification failed: ' . $e->getMessage());
        }

        Flash::success('Faculty account created. Share the temporary password through a secure channel.');
        redirect('/dean/users/' . $userId);
    }

    public function show(string $id): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }
        $facultyId = (int) $faculty['user_id'];

        $this->render('dean/users/show', [
            'pageTitle'    => $faculty['full_name'],
            'pageSubtitle' => 'Faculty profile, evaluation history and QR credential.',
            'activeNav'    => 'users',
            'faculty'      => $faculty,
            'stats'        => Observation::statsForFaculty($facultyId),
            'observations' => Observation::paginate(['faculty_id' => $facultyId], 8, 0),
            'classes'      => ClassModel::forFaculty($facultyId),
            'qrToken'      => QRToken::activeForFaculty($facultyId),
            'qrHistory'    => QRToken::forFaculty($facultyId),
            'acknowledged' => Acknowledgement::countForFaculty($facultyId),
            'comments'     => RateeComment::countForFaculty($facultyId),
        ]);
    }

    public function edit(string $id): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }

        $this->render('dean/users/form', [
            'pageTitle'    => 'Edit ' . $faculty['full_name'],
            'pageSubtitle' => 'Update account details. Usernames are permanent.',
            'activeNav'    => 'users',
            'user'         => $faculty,
            'departments'  => Department::options(),
        ]);
    }

    public function update(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }

        $data = [
            'full_name'     => trim((string) Request::input('full_name', '')),
            'email'         => trim((string) Request::input('email', '')),
            'employee_no'   => trim((string) Request::input('employee_no', '')),
            'contact_no'    => trim((string) Request::input('contact_no', '')),
            'department_id' => trim((string) Request::input('department_id', '')),
        ];

        $validated = $this->validate($data, [
            'full_name'     => 'required|max:150',
            'email'         => 'required|email|max:190|unique:users,email,' . (int) $faculty['user_id'] . ',user_id',
            'employee_no'   => 'nullable|max:40',
            'contact_no'    => 'nullable|max:30',
            'department_id' => 'required|integer|exists:departments,department_id',
        ], [
            'full_name'     => 'Full name',
            'email'         => 'Email address',
            'employee_no'   => 'Employee number',
            'contact_no'    => 'Contact number',
            'department_id' => 'Department',
        ], '/dean/users/' . $faculty['user_id'] . '/edit');

        User::updateById((int) $faculty['user_id'], [
            'full_name'     => $validated['full_name'],
            'email'         => $validated['email'],
            'employee_no'   => $validated['employee_no'] !== '' ? $validated['employee_no'] : null,
            'contact_no'    => $validated['contact_no'] !== '' ? $validated['contact_no'] : null,
            'department_id' => (int) $validated['department_id'],
        ]);

        AuditLog::record((int) $dean['user_id'], 'user.updated', 'Faculty Management', 'user:' . $faculty['user_id'], 'Faculty account details updated.');
        Flash::success('Faculty account updated.');
        redirect('/dean/users/' . $faculty['user_id']);
    }

    public function toggleStatus(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }

        $newStatus = $faculty['account_status'] === 'Active' ? 'Inactive' : 'Active';
        User::setStatus((int) $faculty['user_id'], $newStatus);

        if ($newStatus === 'Inactive') {
            QRToken::revokeAllForFaculty((int) $faculty['user_id']);
        }

        AuditLog::record(
            (int) $dean['user_id'],
            'user.status_changed',
            'Faculty Management',
            'user:' . $faculty['user_id'],
            'Account status changed to ' . $newStatus . '.'
        );
        Flash::success('Account status changed to ' . $newStatus . '.');
        redirect('/dean/users/' . $faculty['user_id']);
    }

    public function resetPassword(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }

        $service = new AuthenticationService();
        $temporary = $service->temporaryPassword(14);
        User::setPassword((int) $faculty['user_id'], $service->hash($temporary), true);

        AuditLog::record((int) $dean['user_id'], 'user.password_reset', 'Faculty Management', 'user:' . $faculty['user_id'], 'Password reset; user must change it at next sign-in.');

        try {
            Notification::push((int) $faculty['user_id'], 'Password reset by the Dean', 'Your password was reset. Sign in with the temporary password provided by the Dean and change it immediately.', '/account/password', 'warning');
        } catch (\Throwable $e) {
            app_log('warning', 'Password reset notification failed: ' . $e->getMessage());
        }

        // Shown once via flash; never stored in plaintext.
        Flash::warning('Temporary password for ' . $faculty['full_name'] . ': ' . $temporary . ' — share it securely; it will not be shown again.');
        redirect('/dean/users/' . $faculty['user_id']);
    }
}
