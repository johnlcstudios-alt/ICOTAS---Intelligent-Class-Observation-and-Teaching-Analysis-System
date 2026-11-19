<?php
/** @var array|null $user */
/** @var string|null $developerKey */
/** @var string|null $consoleBase */
use App\Core\Auth;

$consoleBase = $consoleBase ?? ('developer/' . rawurlencode((string) ($developerKey ?? '')));
$console = static fn (string $path = ''): string => url($consoleBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));

$isEdit = !empty($user);
$action = $isEdit ? $console('users/' . (int) $user['id']) : $console('users');
$generatedCredentials = $generatedCredentials ?? null;
$mustChange = $isEdit && (int) ($user['must_change_password'] ?? 0) === 1;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account governance</span>
        <h2><?= $isEdit ? 'Edit account' : 'New account' ?></h2>
        <p><?= $isEdit ? 'Update account details, role, and status.' : 'Create any account directly — Dean, Academic Head, Developer, Instructor, or Student. No invitation code is required.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $console('users') ?>"><i class="bi bi-arrow-left"></i> Back to users</a>
    </div>
</div>

<?php if (!empty($generatedCredentials)): ?>
    <div class="card border-success mb-3">
        <div class="card-header bg-success-subtle"><strong><i class="bi bi-key"></i> Temporary password — copy it now</strong></div>
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
                        <label class="form-label" for="lastName">Last name <span class="text-danger">*</span></label>
                        <input class="form-control" id="lastName" name="last_name" value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>" maxlength="80" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="middleName">Middle name</label>
                        <input class="form-control" id="middleName" name="middle_name" value="<?= e(old('middle_name', $user['middle_name'] ?? '')) ?>" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="suffix">Suffix</label>
                        <input class="form-control" id="suffix" name="suffix" value="<?= e(old('suffix', $user['suffix'] ?? '')) ?>" maxlength="20" placeholder="Jr., III">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                        <input class="form-control" id="username" name="username" value="<?= e(old('username', $user['username'] ?? '')) ?>" maxlength="80" required autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email address <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="employeeNumber">Employee number</label>
                        <input class="form-control" id="employeeNumber" name="employee_number" value="<?= e(old('employee_number', $user['employee_number'] ?? '')) ?>" maxlength="40">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="studentNumber">Student number</label>
                        <input class="form-control" id="studentNumber" name="student_number" value="<?= e(old('student_number', $user['student_number'] ?? '')) ?>" maxlength="40">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e($role['slug']) ?>" <?= selected(old('role', $user['role'] ?? 'student'), $role['slug']) ?>><?= e($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="department">Department</label>
                        <select class="form-select" id="department" name="department_id">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= selected(old('department_id', $user['department_id'] ?? ''), $department['id']) ?>><?= e($department['code'] . ' — ' . $department['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="program">Program</label>
                        <select class="form-select" id="program" name="program_id">
                            <option value="">— None —</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= (int) $program['id'] ?>" <?= selected(old('program_id', $user['program_id'] ?? ''), $program['id']) ?>><?= e($program['code'] . ' — ' . $program['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="section">Section</label>
                        <select class="form-select" id="section" name="section_id">
                            <option value="">— None —</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>" <?= selected(old('section_id', $user['section_id'] ?? ''), $section['id']) ?>><?= e($section['code'] . ' — ' . $section['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="yearLevel">Year level</label>
                        <input class="form-control" id="yearLevel" name="year_level" value="<?= e(old('year_level', $user['year_level'] ?? '')) ?>" type="number" min="1" max="99">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= selected(old('status', $user['status'] ?? 'active'), 'active') ?>>Active</option>
                            <option value="inactive" <?= selected(old('status', ''), 'inactive') ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="isDemo">Demonstration account</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isDemo" name="is_demo" value="1" <?= old('is_demo', $user['is_demo'] ?? 0) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="isDemo">Flag as synthetic demonstration data</label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary app-alert mt-3 mb-3 py-2" id="generatePasswordNote">
                    <i class="bi bi-magic"></i>
                    <span>A temporary password is generated and shown once. The account must choose a new password at first sign-in.</span>
                </div>

                <div class="col-12" id="manualPasswordFields" hidden>
                    <div class="card card-bg-light mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                                    <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                                    <div class="form-text">At least 8 characters with a letter and a number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="passwordConfirmation">Confirm password <span class="text-danger">*</span></label>
                                    <input class="form-control" type="password" id="passwordConfirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="mustChangePassword" name="must_change_password" value="1">
                                        <label class="form-check-label" for="mustChangePassword">Require password change at first sign-in</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><strong>Password handling</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="passwordMode">Choose how to set the password</label>
                    <select class="form-select" id="passwordMode" name="password_mode">
                        <option value="generate" <?= selected(old('password_mode', 'generate'), 'generate') ?>>Generate a temporary password</option>
                        <option value="manual" <?= selected(old('password_mode', ''), 'manual') ?>>Set the password myself</option>
                    </select>
                </div>
                <div class="form-text">
                    Generated passwords satisfy the system policy (8+ characters, letter and number) and are shown once.
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <div class="card mt-3">
                <div class="card-body small text-muted">
                    <div><strong>Created:</strong> <?= e(format_datetime($user['created_at'] ?? null)) ?></div>
                    <div><strong>Last password change:</strong> <?= e(format_datetime($user['password_changed_at'] ?? null)) ?></div>
                    <div><strong>Last sign-in:</strong> <?= e(format_datetime($user['last_login_at'] ?? null)) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</form>

<script>
(function () {
    const mode = document.getElementById('passwordMode');
    const manual = document.getElementById('manualPasswordFields');
    const note = document.getElementById('generatePasswordNote');
    if (!mode || !manual || !note) { return; }

    function sync() {
        const isManual = mode.value === 'manual';
        manual.hidden = !isManual;
        note.hidden = isManual;
    }

    mode.addEventListener('change', sync);
    sync();
})();
</script>
