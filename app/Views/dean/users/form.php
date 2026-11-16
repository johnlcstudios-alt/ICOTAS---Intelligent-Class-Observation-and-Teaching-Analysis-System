<?php
/** @var array|null $user */
/** @var array $roles */
/** @var array $departments */
/** @var array $programs */
/** @var array $sections */
/** @var bool $demo */
/** @var array|null $generatedCredentials */
$isEdit = !empty($user);
$action = $isEdit ? url('dean/users/' . (int) $user['id']) : url('dean/users');
$currentRole = old('role', $user['role'] ?? 'instructor');
$generatedCredentials = $generatedCredentials ?? null;
$mustChange = $isEdit && (int) ($user['must_change_password'] ?? 0) === 1;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account governance</span>
        <h2><?= $isEdit ? 'Edit user' : 'New user' ?></h2>
        <p><?= $isEdit ? 'Update account details, role, assignment and status.' : 'Create a single account. Use CSV import for bulk account creation.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/users') ?>"><i class="bi bi-arrow-left"></i> Back to users</a>
    </div>
</div>

<?php if ($isEdit && !empty($user['is_demo'])): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration account.</strong> Changes stay inside the synthetic demo dataset.</span></div>
<?php endif; ?>

<?php if (!empty($generatedCredentials)): ?>
    <div class="card border-success mb-3">
        <div class="card-header bg-success-subtle"><strong><i class="bi bi-key"></i> Temporary instructor password — copy it now</strong></div>
        <div class="card-body">
            <p class="mb-2">Give these credentials to <strong><?= e($generatedCredentials['username']) ?></strong>. The password is shown only on this page load and must be changed at first sign-in.</p>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="generatedUsername">Username</label>
                    <div class="input-group">
                        <input class="form-control font-monospace" id="generatedUsername" type="text" value="<?= e($generatedCredentials['username']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" data-copy="#generatedUsername"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="generatedPassword">Temporary password</label>
                    <div class="input-group">
                        <input class="form-control font-monospace" id="generatedPassword" type="text" value="<?= e($generatedCredentials['password']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" data-copy="#generatedPassword"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <span class="badge badge-warning w-100 py-2">Must change</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><strong>Account details</strong><span class="text-muted small">Fields marked * are required</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="firstName">First name <span class="text-danger">*</span></label>
                        <input class="form-control" id="firstName" name="first_name" value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>" maxlength="80" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="middleName">Middle name</label>
                        <input class="form-control" id="middleName" name="middle_name" value="<?= e(old('middle_name', $user['middle_name'] ?? '')) ?>" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="lastName">Last name <span class="text-danger">*</span></label>
                        <input class="form-control" id="lastName" name="last_name" value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>" maxlength="80" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="suffix">Suffix</label>
                        <input class="form-control" id="suffix" name="suffix" value="<?= e(old('suffix', $user['suffix'] ?? '')) ?>" maxlength="20" placeholder="Jr., III">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="email">Email address <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                        <input class="form-control" id="username" name="username" value="<?= e(old('username', $user['username'] ?? '')) ?>" maxlength="80" required autocomplete="off">
                        <div class="form-text">Used to sign in. Letters, numbers, dots, dashes and underscores are recommended.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e($role['slug']) ?>" <?= selected($currentRole, $role['slug']) ?>><?= e($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= selected(old('status', $user['status'] ?? 'active'), 'active') ?>>Active</option>
                            <option value="inactive" <?= selected(old('status', $user['status'] ?? 'active'), 'inactive') ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><strong>Academic assignment</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="departmentId">Department</label>
                        <select class="form-select" id="departmentId" name="department_id">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= selected(old('department_id', $user['department_id'] ?? ''), $department['id']) ?>><?= e($department['code'] . ' — ' . $department['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="programId">Program</label>
                        <select class="form-select" id="programId" name="program_id">
                            <option value="">— None —</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= (int) $program['id'] ?>" <?= selected(old('program_id', $user['program_id'] ?? ''), $program['id']) ?>><?= e($program['code'] . ' — ' . $program['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sectionId">Section</label>
                        <select class="form-select" id="sectionId" name="section_id">
                            <option value="">— None —</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>" <?= selected(old('section_id', $user['section_id'] ?? ''), $section['id']) ?>><?= e($section['code'] . (!empty($section['program_code']) ? ' — ' . $section['program_code'] : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="employeeNumber">Employee number</label>
                        <input class="form-control" id="employeeNumber" name="employee_number" value="<?= e(old('employee_number', $user['employee_number'] ?? '')) ?>" maxlength="40">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="studentNumber">Student number</label>
                        <input class="form-control" id="studentNumber" name="student_number" value="<?= e(old('student_number', $user['student_number'] ?? '')) ?>" maxlength="40">
                        <div class="form-text">Required for student accounts.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="yearLevel">Year level</label>
                        <select class="form-select" id="yearLevel" name="year_level">
                            <option value="">— None —</option>
                            <?php for ($year = 1; $year <= 5; $year++): ?>
                                <option value="<?= $year ?>" <?= selected(old('year_level', $user['year_level'] ?? ''), $year) ?>>Year <?= $year ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <strong><?= $isEdit ? 'Password' : 'Initial password' ?></strong>
                <?php if ($mustChange): ?><span class="badge badge-warning">Temporary · change required</span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($isEdit): ?>
                    <p class="text-muted small mb-0">Leave the current password untouched. To replace it, use the password reset panel below. A reset signs the account out and requires a new password at next sign-in.</p>
                <?php else: ?>
                    <div id="instructorPasswordNote" class="alert alert-info app-alert py-2 mb-3" hidden>
                        <i class="bi bi-magic"></i>
                        <span>A temporary password will be generated automatically and shown once after creation. The instructor must change it at first sign-in.</span>
                    </div>
                    <div id="manualPasswordFields">
                        <div class="mb-3">
                            <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="passwordConfirmation">Confirm password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" id="passwordConfirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="form-text">At least 8 characters with a letter and a number. Instructor accounts do not use this field.</div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body border-top">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Create account' ?></button>
                <a class="btn btn-ghost w-100 mt-2" href="<?= url('dean/users') ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const role = document.getElementById('role');
    const manual = document.getElementById('manualPasswordFields');
    const note = document.getElementById('instructorPasswordNote');
    if (!role || !manual || !note) { return; }
    const password = document.getElementById('password');
    const confirmation = document.getElementById('passwordConfirmation');

    function syncPasswordFields() {
        const isInstructor = role.value === 'instructor';
        manual.hidden = isInstructor;
        note.hidden = !isInstructor;
        if (password) { password.required = !isInstructor; }
        if (confirmation) { confirmation.required = !isInstructor; }
        if (isInstructor && password && confirmation) {
            password.value = '';
            confirmation.value = '';
        }
    }

    role.addEventListener('change', syncPasswordFields);
    syncPasswordFields();
})();
</script>

<?php if ($isEdit): ?>
<div class="row g-3 mt-1">
    <div class="col-xl-4 offset-xl-8">
            <div class="card mt-3">
                <div class="card-header"><strong>Reset password</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= url('dean/users/' . (int) $user['id'] . '/reset-password') ?>" data-confirm="Reset this password and sign the account out of all sessions? The account must choose a new password at next sign-in.">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label" for="resetPassword">Temporary password</label>
                            <input class="form-control" type="password" id="resetPassword" name="password" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resetPasswordConfirmation">Confirm temporary password</label>
                            <input class="form-control" type="password" id="resetPasswordConfirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-key"></i> Reset password</button>
                    </form>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body small text-muted">
                    <div><strong>Created:</strong> <?= e(format_datetime($user['created_at'] ?? null)) ?></div>
                    <div><strong>Last password change:</strong> <?= e(format_datetime($user['password_changed_at'] ?? null)) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
