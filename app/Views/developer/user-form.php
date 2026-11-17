<?php
/**
 * @var string $developerKey
 * @var array|null $user
 * @var array $roles
 * @var array $departments
 * @var array $programs
 * @var array $sections
 * @var bool $demoMode
 * @var int $activeDeans
 * @var array|null $generatedCredentials
 */
$isEdit = !empty($user);
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$action = $isEdit ? $console('users/' . (int) $user['id']) : $console('users');
$currentRole = (string) old('role', $user['role'] ?? 'instructor');
$generatedCredentials = $generatedCredentials ?? null;
$passwordMode = (string) old('password_mode', 'generate');
$lastActiveDean = $isEdit && ($user['role'] ?? '') === 'dean' && (string) ($user['status'] ?? '') === 'active' && $activeDeans <= 1;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Identity and access</span>
        <h2><?= $isEdit ? 'Edit account' : 'New account' ?></h2>
        <p><?= $isEdit ? 'Repair account details, change the role, replace the password or remove the record entirely.' : 'Create any account directly, including Dean and Academic Head. No invitation code is required.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $console('users') ?>"><i class="bi bi-arrow-left"></i> Back to users</a>
    </div>
</div>

<?php if (!empty($generatedCredentials)): ?>
    <div class="card border-success mb-3">
        <div class="card-header bg-success-subtle"><strong><i class="bi bi-key"></i> Temporary password — copy it now</strong></div>
        <div class="card-body">
            <p class="mb-2">Give these credentials to <strong><?= e($generatedCredentials['username']) ?></strong>. The plaintext password is shown only on this page load<?= !empty($generatedCredentials['kind']) && $generatedCredentials['kind'] === 'temporary' ? ' and must be changed at first sign-in' : '' ?>.</p>
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
                    <span class="badge badge-warning w-100 py-2">Shown once</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isEdit && !empty($user['is_demo'])): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration account.</strong> Changes stay inside the synthetic demo dataset.</span></div>
<?php endif; ?>

<?php if ($lastActiveDean): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-shield-lock"></i><span><strong>Last active Dean.</strong> The role cannot be changed, the account cannot be deactivated, and the record cannot be deleted while it is the only active Dean.</span></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="row g-3" data-single-submit>
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
                        <div class="form-text">Used to sign in. Letters, numbers, dots, dashes and underscores only.</div>
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
            <div class="card-header"><strong>Academic assignment</strong><span class="text-muted small">Optional for accounts outside a department</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="departmentId">Department</label>
                        <select class="form-select" id="departmentId" name="department_id">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= selected((string) old('department_id', $user['department_id'] ?? ''), (string) $department['id']) ?>><?= e($department['code'] . ' — ' . $department['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="programId">Program</label>
                        <select class="form-select" id="programId" name="program_id">
                            <option value="">— None —</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= (int) $program['id'] ?>" <?= selected((string) old('program_id', $user['program_id'] ?? ''), (string) $program['id']) ?>><?= e($program['code'] . ' — ' . $program['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sectionId">Section</label>
                        <select class="form-select" id="sectionId" name="section_id">
                            <option value="">— None —</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>" <?= selected((string) old('section_id', $user['section_id'] ?? ''), (string) $section['id']) ?>><?= e($section['code'] . ' — ' . $section['name']) ?></option>
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
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="yearLevel">Year level</label>
                        <select class="form-select" id="yearLevel" name="year_level">
                            <option value="">— None —</option>
                            <?php for ($year = 1; $year <= 5; $year++): ?>
                                <option value="<?= $year ?>" <?= selected((string) old('year_level', $user['year_level'] ?? ''), (string) $year) ?>>Year <?= $year ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$isEdit): ?>
            <div class="card mt-3">
                <div class="card-header"><strong>Initial password</strong><span class="text-muted small">A temporary password is safest</span></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="passwordMode">Password mode</label>
                            <select class="form-select" id="passwordMode" name="password_mode">
                                <option value="generate" <?= selected($passwordMode, 'generate') ?>>Generate a temporary password</option>
                                <option value="manual" <?= selected($passwordMode, 'manual') ?>>Enter a password manually</option>
                            </select>
                        </div>
                        <div class="col-md-7 d-flex align-items-end">
                            <div class="alert alert-info app-alert py-2 mb-0 w-100" id="generatedPasswordNote">
                                <i class="bi bi-magic"></i>
                                <span>A strong temporary password is generated and shown once after creation. The account must choose a new password at first sign-in.</span>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1" id="manualPasswordFields">
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="passwordConfirmation">Confirm password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" id="passwordConfirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" id="mustChangePassword" name="must_change_password" type="checkbox" value="1" <?= checked(old('must_change_password')) ?>>
                                <label class="form-check-label" for="mustChangePassword">Require a password change at next sign-in</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><strong>Save account</strong><?php if ($isEdit): ?><span class="badge badge-soft">#<?= (int) $user['id'] ?></span><?php endif; ?></div>
            <div class="card-body">
                <p class="text-muted small">Developer edits bypass the standard administrative role ceiling. Email, username, employee and student numbers must remain unique across all live and demo records.</p>
                <?php if (!$isEdit): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="isDemo" name="is_demo" type="checkbox" value="1" <?= checked(old('is_demo')) ?>>
                        <label class="form-check-label" for="isDemo">Create as demonstration data</label>
                        <div class="form-text">Demo accounts are scoped separately from live institutional records.</div>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Create account' ?></button>
                <a class="btn btn-ghost w-100 mt-2" href="<?= $console('users') ?>">Cancel</a>
            </div>
            <?php if ($isEdit): ?>
                <div class="card-body border-top small text-muted">
                    <div><strong>Created:</strong> <?= e(format_datetime($user['created_at'] ?? null)) ?></div>
                    <div><strong>Last sign-in:</strong> <?= e(format_datetime($user['last_login_at'] ?? null, 'Never')) ?></div>
                    <div><strong>Password changed:</strong> <?= e(format_datetime($user['password_changed_at'] ?? null)) ?></div>
                    <div><strong>Must change password:</strong> <?= (int) ($user['must_change_password'] ?? 0) === 1 ? 'Yes' : 'No' ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isEdit): ?>
            <div class="card mt-3">
                <div class="card-header"><strong>Password</strong><span class="text-muted small">Signs the account out</span></div>
                <div class="card-body">
                    <form method="post" action="<?= $console('users/' . (int) $user['id'] . '/password') ?>" data-confirm="Generate a new temporary password and sign this account out of every session?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="generate" value="1">
                        <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-magic"></i> Generate temporary password</button>
                    </form>
                </div>
                <div class="card-body border-top">
                    <form method="post" action="<?= $console('users/' . (int) $user['id'] . '/password') ?>" data-confirm="Replace this password and sign the account out of every session?">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label" for="resetPassword">New password</label>
                            <input class="form-control" type="password" id="resetPassword" name="password" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="resetPasswordConfirmation">Confirm password</label>
                            <input class="form-control" type="password" id="resetPasswordConfirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="resetMustChange" name="must_change_password" type="checkbox" value="1" checked>
                            <label class="form-check-label" for="resetMustChange">Require a change at next sign-in</label>
                        </div>
                        <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-key"></i> Set password</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3 developer-danger">
                <div class="card-header"><strong class="text-danger">Delete account</strong></div>
                <div class="card-body">
                    <p class="small text-muted">Deletion is permanent. Foreign-key constraints may block it when the account owns records; deactivate the account instead to preserve history.</p>
                    <form method="post" action="<?= $console('users/' . (int) $user['id'] . '/delete') ?>" data-confirm="Permanently delete @<?= e($user['username']) ?>? This cannot be undone.">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger w-100" type="submit" <?= $lastActiveDean ? 'disabled' : '' ?>><i class="bi bi-trash"></i> Delete account</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php if (!$isEdit): ?>
<script>
(function () {
    const mode = document.getElementById('passwordMode');
    const manual = document.getElementById('manualPasswordFields');
    const note = document.getElementById('generatedPasswordNote');
    const password = document.getElementById('password');
    const confirmation = document.getElementById('passwordConfirmation');
    if (!mode || !manual || !note) { return; }
    function sync() {
        const generated = mode.value === 'generate';
        manual.hidden = generated;
        note.hidden = !generated;
        if (password) { password.required = !generated; }
        if (confirmation) { confirmation.required = !generated; }
        if (generated && password && confirmation) {
            password.value = '';
            confirmation.value = '';
        }
    }
    mode.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
