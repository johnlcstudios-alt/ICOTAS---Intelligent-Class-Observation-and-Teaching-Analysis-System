<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Academic;
use App\Models\User;

final class UserController extends BaseController
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
        $filters = [
            'role' => $this->input('role', ''),
            'status' => $this->input('status', ''),
            'department_id' => $this->input('department_id', ''),
            'search' => $this->input('search', ''),
            'is_demo' => $this->input('is_demo', ''),
        ];
        if ($filters['is_demo'] === '') {
            $filters['is_demo'] = $this->demo() ? 1 : 0;
        }
        $pagination = $this->users->paginate($filters, $this->page(), 15);
        $roles = Database::fetchAll('SELECT id, slug, name FROM roles ORDER BY id');
        $counts = $this->users->countsByRole($this->demo());

        $this->view('dean/users/index', [
            'pageTitle' => 'User Management',
            'pagination' => $pagination,
            'filters' => $filters,
            'roles' => $roles,
            'counts' => $counts,
            'departments' => $this->academic->departmentOptions($this->demo()),
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $this->view('dean/users/form', $this->formData(null));
    }

    public function store(): void
    {
        $this->requirePost();
        $role = $this->postString('role');
        $data = $this->validatedUserData();
        $data['password_hash'] = password_hash($this->postString('password'), PASSWORD_DEFAULT);
        $data['role_id'] = $this->roleId($role);

        if ($this->exists('username', $data['username']) || $this->exists('email', $data['email'])) {
            Session::set('_old', $this->inputAll());
            $this->error('That username or email address is already registered.', 'dean/users/create');
        }
        if (!empty($data['student_number']) && $this->exists('student_number', $data['student_number'])) {
            Session::set('_old', $this->inputAll());
            $this->error('That student number is already registered.', 'dean/users/create');
        }
        if (!empty($data['employee_number']) && $this->exists('employee_number', $data['employee_number'])) {
            Session::set('_old', $this->inputAll());
            $this->error('That employee number is already registered.', 'dean/users/create');
        }

        $data['is_demo'] = $this->demo() ? 1 : 0;
        $data['status'] = $this->postString('status', 'active');
        $id = $this->users->createAccount($data);
        $this->audit('user.create', 'user', $id, 'Created account ' . $data['username'] . ' (' . $role . ')');
        $this->success('Account created successfully.', 'dean/users/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $user = $this->users->findWithRelations((int) $id);
        if (!$user) {
            abort(404);
        }
        $this->view('dean/users/form', $this->formData($user));
    }

    public function update(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $user = $this->users->findWithRelations($id);
        if (!$user) {
            abort(404);
        }
        $role = $this->postString('role');
        $data = $this->validatedUserData($id);
        $data['role_id'] = $this->roleId($role);
        $data['status'] = $this->postString('status', 'active');

        if ((int) Auth::id() === $id) {
            $data['role_id'] = (int) $user['role_id'];
            $data['status'] = 'active';
        }
        if ($this->exists('username', $data['username'], $id) || $this->exists('email', $data['email'], $id)) {
            Session::set('_old', $this->inputAll());
            $this->error('That username or email address belongs to another account.', 'dean/users/' . $id . '/edit');
        }
        if (!empty($data['student_number']) && $this->exists('student_number', $data['student_number'], $id)) {
            Session::set('_old', $this->inputAll());
            $this->error('That student number belongs to another account.', 'dean/users/' . $id . '/edit');
        }
        if (!empty($data['employee_number']) && $this->exists('employee_number', $data['employee_number'], $id)) {
            Session::set('_old', $this->inputAll());
            $this->error('That employee number belongs to another account.', 'dean/users/' . $id . '/edit');
        }
        if ($user['role'] === 'dean' && $role !== 'dean' && $this->activeDeans() <= 1) {
            $this->error('At least one active Dean account must remain.', 'dean/users/' . $id . '/edit');
        }

        $this->users->updateAccount($id, $data);
        $password = $this->postString('password');
        if ($password !== '') {
            $problem = Security::passwordProblem($password);
            if ($problem !== null) {
                $this->error($problem, 'dean/users/' . $id . '/edit');
            }
            $this->users->resetPassword($id, $password);
        }
        $this->audit('user.update', 'user', $id, 'Updated account ' . $data['username']);
        $this->success('Account updated.', 'dean/users/' . $id . '/edit');
    }

    public function toggleStatus(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $user = $this->users->findWithRelations($id);
        if (!$user) {
            abort(404);
        }
        if ($id === (int) Auth::id()) {
            $this->error('You cannot deactivate your own account.', 'dean/users');
        }
        if ($user['role'] === 'dean' && $user['status'] === 'active' && $this->activeDeans() <= 1) {
            $this->error('At least one active Dean account must remain.', 'dean/users');
        }
        $new = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->users->setStatus($id, $new);
        $this->audit('user.status', 'user', $id, 'Set account status to ' . $new);
        $this->success('Account marked ' . $new . '.', 'dean/users');
    }

    public function resetPassword(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $user = $this->users->findWithRelations($id);
        if (!$user) {
            abort(404);
        }
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');
        $problem = Security::passwordProblem($password);
        if ($problem !== null) {
            $this->error($problem, 'dean/users/' . $id . '/edit');
        }
        if ($password !== $confirmation) {
            $this->error('The password confirmation does not match.', 'dean/users/' . $id . '/edit');
        }
        $this->users->resetPassword($id, $password);
        $this->audit('user.password_reset', 'user', $id, 'Administrator reset the account password');
        $this->success('Password reset. Existing sessions for this account were signed out.', 'dean/users/' . $id . '/edit');
    }

    public function importForm(): void
    {
        $this->view('dean/users/import', [
            'pageTitle' => 'Import Users',
        ]);
    }

    public function importTemplate(): void
    {
        $filename = 'ICOTAS_user_import_template.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['username', 'email', 'password', 'first_name', 'middle_name', 'last_name', 'suffix', 'role', 'employee_number', 'student_number', 'department_code', 'program_code', 'section_code', 'year_level', 'phone']);
        fputcsv($out, ['juan.delacruz', 'juan.delacruz@example.edu', 'ChangeMe12345', 'Juan', 'Santos', 'Dela Cruz', '', 'instructor', 'EMP-1001', '', 'CCS', 'BSIT', '', '', '09170000000']);
        fclose($out);
        $this->audit('user.import_template', 'user', null, 'Downloaded the CSV import template');
    }

    public function import(): void
    {
        $this->requirePost();
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->error('Choose a CSV file to import.', 'dean/users/import');
        }
        if (($file['size'] ?? 0) > (int) config('app.csv_max_bytes', 5242880)) {
            $this->error('The CSV file exceeds the allowed size.', 'dean/users/import');
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $this->error('Only .csv files are accepted.', 'dean/users/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $this->error('The CSV file could not be read.', 'dean/users/import');
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->error('The CSV file is empty.', 'dean/users/import');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        $requiredColumns = ['username', 'email', 'password', 'first_name', 'last_name', 'role'];
        $missing = array_diff($requiredColumns, $header);
        if (!empty($missing)) {
            fclose($handle);
            $this->error('Missing required column(s): ' . implode(', ', $missing) . '.', 'dean/users/import');
        }

        $roleMap = [];
        foreach (Database::fetchAll('SELECT id, slug FROM roles') as $role) {
            $roleMap[$role['slug']] = (int) $role['id'];
        }
        $departmentMap = $this->codeMap('departments');
        $programMap = $this->codeMap('programs');
        $sectionMap = $this->codeMap('sections');

        $created = 0;
        $errors = [];
        $rowNumber = 1;
        $demo = $this->demo();
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $record = [];
            foreach ($header as $index => $column) {
                $record[$column] = trim((string) ($row[$index] ?? ''));
            }
            $validator = Validator::make($record, [
                'username' => 'required|max:80',
                'email' => 'required|email|max:190',
                'password' => 'required|min:8',
                'first_name' => 'required|max:80',
                'last_name' => 'required|max:80',
                'role' => 'required|in:dean,academic_head,instructor,student',
            ]);
            if ($validator->fails()) {
                $errors[] = 'Row ' . $rowNumber . ': ' . $validator->firstError();
                continue;
            }
            $data = $validator->validated();
            $problem = Security::passwordProblem($record['password']);
            if ($problem !== null) {
                $errors[] = 'Row ' . $rowNumber . ': ' . $problem;
                continue;
            }
            if ($this->exists('username', $record['username']) || $this->exists('email', $record['email'])) {
                $errors[] = 'Row ' . $rowNumber . ': username or email already exists.';
                continue;
            }
            if ($record['role'] === 'student' && $record['student_number'] === '') {
                $errors[] = 'Row ' . $rowNumber . ': student accounts require a student_number.';
                continue;
            }
            $payload = [
                'role_id' => $roleMap[$record['role']] ?? null,
                'username' => $record['username'],
                'email' => $record['email'],
                'password_hash' => password_hash($record['password'], PASSWORD_DEFAULT),
                'first_name' => $record['first_name'],
                'middle_name' => $record['middle_name'] ?? '',
                'last_name' => $record['last_name'],
                'suffix' => $record['suffix'] ?? '',
                'employee_number' => $record['employee_number'] ?? '',
                'student_number' => $record['student_number'] ?? '',
                'department_id' => $departmentMap[strtoupper($record['department_code'] ?? '')] ?? null,
                'program_id' => $programMap[strtoupper($record['program_code'] ?? '')] ?? null,
                'section_id' => $sectionMap[strtoupper($record['section_code'] ?? '')] ?? null,
                'year_level' => ($record['year_level'] ?? '') !== '' ? (int) $record['year_level'] : null,
                'phone' => $record['phone'] ?? '',
                'status' => 'active',
                'is_demo' => $demo ? 1 : 0,
            ];
            if (!$payload['role_id']) {
                $errors[] = 'Row ' . $rowNumber . ': unknown role.';
                continue;
            }
            try {
                $id = $this->users->createAccount($payload);
                $created++;
                $this->audit('user.import', 'user', $id, 'Imported account ' . $payload['username']);
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . $rowNumber . ': could not be saved (' . $e->getMessage() . ').';
            }
        }
        fclose($handle);

        Session::set('_import_errors', array_slice($errors, 0, 100));
        $message = $created . ' account(s) imported.';
        if (!empty($errors)) {
            flash('warning', $message . ' ' . count($errors) . ' row(s) skipped — review the import report below.');
        } else {
            flash('success', $message);
        }
        redirect('dean/users/import');
    }

    private function formData(?array $user): array
    {
        $demo = $user ? (int) $user['is_demo'] === 1 : $this->demo();
        return [
            'pageTitle' => $user ? 'Edit User' : 'New User',
            'user' => $user,
            'roles' => Database::fetchAll('SELECT id, slug, name FROM roles ORDER BY id'),
            'departments' => $this->academic->departments($demo, true),
            'programs' => $this->academic->programs($demo, null, true),
            'sections' => $this->academic->sections($demo, null, true),
            'demo' => $demo,
        ];
    }

    private function validatedUserData(?int $ignoreId = null): array
    {
        $data = [
            'username' => $this->postString('username'),
            'email' => $this->postString('email'),
            'first_name' => $this->postString('first_name'),
            'middle_name' => $this->postString('middle_name'),
            'last_name' => $this->postString('last_name'),
            'suffix' => $this->postString('suffix'),
            'employee_number' => $this->postString('employee_number') ?: null,
            'student_number' => $this->postString('student_number') ?: null,
            'department_id' => $this->intInput('department_id') ?: null,
            'program_id' => $this->intInput('program_id') ?: null,
            'section_id' => $this->intInput('section_id') ?: null,
            'year_level' => $this->intInput('year_level') ?: null,
            'phone' => $this->postString('phone'),
        ];
        $validator = Validator::make($data, [
            'username' => 'required|max:80',
            'email' => 'required|email|max:190',
            'first_name' => 'required|max:80',
            'last_name' => 'required|max:80',
            'phone' => 'max:30',
        ]);
        if ($validator->fails()) {
            $back = $ignoreId ? 'dean/users/' . $ignoreId . '/edit' : 'dean/users/create';
            Session::set('_old', $this->inputAll());
            $this->error((string) $validator->firstError(), $back);
        }
        if ($ignoreId === null) {
            $password = (string) $this->input('password', '');
            $problem = Security::passwordProblem($password);
            if ($problem !== null) {
                Session::set('_old', $this->inputAll());
                $this->error($problem, 'dean/users/create');
            }
            if ($password !== (string) $this->input('password_confirmation', '')) {
                Session::set('_old', $this->inputAll());
                $this->error('The password confirmation does not match.', 'dean/users/create');
            }
        }
        if ($this->roleId($this->postString('role')) === null) {
            Session::set('_old', $this->inputAll());
            $this->error('Choose a valid account role.', $ignoreId ? 'dean/users/' . $ignoreId . '/edit' : 'dean/users/create');
        }
        return $data;
    }

    private function roleId(string $slug): ?int
    {
        return $this->users->roleId($slug);
    }

    private function exists(string $column, string $value, ?int $ignoreId = null): bool
    {
        if ($value === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM users WHERE ' . $column . ' = ?';
        $params = [$value];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int) Database::scalar($sql, $params, 0) > 0;
    }

    private function activeDeans(): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'dean' AND u.status = 'active'",
            [],
            0
        );
    }

    private function inputAll(): array
    {
        $data = [];
        foreach (['username', 'email', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number', 'student_number', 'department_id', 'program_id', 'section_id', 'year_level', 'phone', 'role', 'status'] as $key) {
            $data[$key] = $this->input($key, '');
        }
        return $data;
    }

    private function codeMap(string $table): array
    {
        $map = [];
        $demo = $this->demo();
        if (in_array($table, ['departments', 'programs', 'sections'], true)) {
            $rows = Database::fetchAll('SELECT id, code FROM ' . $table . ' WHERE is_demo = ?', [$demo ? 1 : 0]);
        } else {
            $rows = Database::fetchAll('SELECT id, code FROM ' . $table);
        }
        foreach ($rows as $row) {
            $map[strtoupper((string) $row['code'])] = (int) $row['id'];
        }
        return $map;
    }
}
