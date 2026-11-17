<?php
/** @var array $administrators */
/** @var array $counts */
/** @var int $instructorCount */
/** @var int $studentCount */
/** @var array $departments */
/** @var array|null $generatedCredentials */
/** @var bool $isDean */
/** @var bool $demo */
use App\Core\Auth;

$deans = (int) ($counts['dean'] ?? 0);
$heads = (int) ($counts['academic_head'] ?? 0);
$defaultRole = $isDean ? 'dean' : 'academic_head';
$selectedRole = (string) old('role', $defaultRole);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account governance</span>
        <h2>Administrator accounts</h2>
        <p>Create and manage administrative accounts directly — no invitation codes. <?= $isDean ? 'A Dean may create either administrative role.' : 'As an Academic Head you may create and manage Academic Head accounts only.' ?> Every change here is audited.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/users') ?>"><i class="bi bi-people"></i> All users</a>
    </div>
</div>

<div class="alert alert-info app-alert">
    <i class="bi bi-shield-lock"></i>
    <span><strong>Administrative authority.</strong> A Dean account can manage every record in the institution. Grant it only to the person who holds that office, and deactivate it the moment the appointment ends.<?= $isDean ? '' : ' Dean accounts are hidden from this module and cannot be created or managed by an Academic Head.' ?></span>
</div>

<div class="stat-grid mb-4">
    <?php if ($isDean): ?>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi bi-mortarboard"></i></span>
            <div class="stat-label">Dean accounts</div>
            <div class="stat-value"><?= $deans ?></div>
            <div class="stat-note"><?= e($demo ? 'Demo scope' : 'Live scope') ?></div>
        </div>
    <?php endif; ?>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-badge"></i></span>
        <div class="stat-label">Academic Heads</div>
        <div class="stat-value"><?= $heads ?></div>
        <div class="stat-note">Department-level administration</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-workspace"></i></span>
        <div class="stat-label">Instructors</div>
        <div class="stat-value"><?= (int) $instructorCount ?></div>
        <div class="stat-note">Evaluated teaching staff</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= (int) $studentCount ?></div>
        <div class="stat-note">Registered respondents</div>
    </div>
</div>

<?php if (!empty($generatedCredentials)): ?>
    <div class="card border-success mb-4">
        <div class="card-header bg-success-subtle"><strong><i class="bi bi-key"></i> New <?= e($generatedCredentials['role']) ?> credentials — copy them now</strong></div>
        <div class="card-body">
            <p class="mb-2">Hand these credentials to <strong><?= e($generatedCredentials['username']) ?></strong>. The temporary password is shown only on this page load, and the account must choose a new password at first sign-in.</p>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="generatedAdminUsername">Username</label>
                    <div class="input-group">
                        <input class="form-control font-monospace" id="generatedAdminUsername" type="text" value="<?= e($generatedCredentials['username']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" data-copy="#generatedAdminUsername"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="generatedAdminPassword">Temporary password</label>
                    <div class="input-group">
                        <input class="form-control font-monospace" id="generatedAdminPassword" type="text" value="<?= e($generatedCredentials['password']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" data-copy="#generatedAdminPassword"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <span class="badge badge-warning w-100 py-2">Must change</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong>Create an administrator account</strong><span class="text-muted small"><?= $isDean ? 'Dean or Academic Head' : 'Academic Head only' ?></span></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/administrators') ?>" data-single-submit>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="adminFirstName">First name <span class="text-danger">*</span></label>
                            <input class="form-control" id="adminFirstName" name="first_name" value="<?= e(old('first_name', '')) ?>" maxlength="80" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminLastName">Last name <span class="text-danger">*</span></label>
                            <input class="form-control" id="adminLastName" name="last_name" value="<?= e(old('last_name', '')) ?>" maxlength="80" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminMiddleName">Middle name</label>
                            <input class="form-control" id="adminMiddleName" name="middle_name" value="<?= e(old('middle_name', '')) ?>" maxlength="80">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminSuffix">Suffix</label>
                            <input class="form-control" id="adminSuffix" name="suffix" value="<?= e(old('suffix', '')) ?>" maxlength="20" placeholder="Jr., III">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminUsername">Username <span class="text-danger">*</span></label>
                            <input class="form-control" id="adminUsername" name="username" value="<?= e(old('username', '')) ?>" maxlength="80" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminEmail">Email address <span class="text-danger">*</span></label>
                            <input class="form-control" type="email" id="adminEmail" name="email" value="<?= e(old('email', '')) ?>" maxlength="190" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminRole">Administrative role <span class="text-danger">*</span></label>
                            <select class="form-select" id="adminRole" name="role" required>
                                <?php if ($isDean): ?>
                                    <option value="dean" <?= selected($selectedRole, 'dean') ?>>Dean — college-wide administration</option>
                                <?php endif; ?>
                                <option value="academic_head" <?= selected($selectedRole, 'academic_head') ?>>Academic Head — restricted administration</option>
                            </select>
                            <div class="form-text"><?= $isDean ? 'You may create either role. At least one active Dean must remain.' : 'Only a Dean may create or manage Dean accounts.' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminDepartment">Department</label>
                            <select class="form-select" id="adminDepartment" name="department_id">
                                <option value="">— None —</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= selected(old('department_id', ''), $department['id']) ?>><?= e($department['code'] . ' — ' . $department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminEmployeeNumber">Employee number</label>
                            <input class="form-control" id="adminEmployeeNumber" name="employee_number" value="<?= e(old('employee_number', '')) ?>" maxlength="40">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminPhone">Phone</label>
                            <input class="form-control" id="adminPhone" name="phone" value="<?= e(old('phone', '')) ?>" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminStatus">Status</label>
                            <select class="form-select" id="adminStatus" name="status">
                                <option value="active" <?= selected(old('status', 'active'), 'active') ?>>Active — can sign in immediately</option>
                                <option value="inactive" <?= selected(old('status', ''), 'inactive') ?>>Inactive — sign-in blocked</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adminPasswordMode">Password handling</label>
                            <select class="form-select" id="adminPasswordMode" name="password_mode">
                                <option value="generate" <?= selected(old('password_mode', 'generate'), 'generate') ?>>Generate a temporary password</option>
                                <option value="manual" <?= selected(old('password_mode', ''), 'manual') ?>>Set the password myself</option>
                            </select>
                        </div>

                        <div class="col-12" id="adminManualPassword" hidden>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="adminPassword">Password <span class="text-danger">*</span></label>
                                    <input class="form-control" type="password" id="adminPassword" name="password" minlength="8" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminPasswordConfirmation">Confirm password <span class="text-danger">*</span></label>
                                    <input class="form-control" type="password" id="adminPasswordConfirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                                </div>
                                <div class="col-12"><div class="form-text">At least 8 characters with a letter and a number.</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary app-alert mt-3 mb-3 py-2" id="adminGenerateNote">
                        <i class="bi bi-magic"></i>
                        <span>A temporary password is generated and shown once. The account must choose a new password at first sign-in.</span>
                    </div>

                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus"></i> Create administrator account</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body small text-muted">
                <div class="mb-2"><strong>Direct provisioning</strong></div>
                <p class="mb-2">Administrator accounts are created here, fully formed, by an authorized administrator. There are no invitation codes to generate, send, redeem, or revoke.</p>
                <p class="mb-0">When a generated password is used, hand it over privately. The account is forced to choose a new password at first sign-in, and every creation or status change is recorded in the audit log.</p>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header">
                <strong>Administrative accounts</strong>
                <span class="text-muted small"><?= count($administrators) ?> record(s) in the <?= $demo ? 'demonstration' : 'live' ?> scope</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($administrators)): ?>
                    <div class="empty-state"><i class="bi bi-shield-check"></i>No administrator accounts in this scope.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last sign-in</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($administrators as $row): ?>
                                <?php
                                // A Dean may manage either role; an Academic Head may
                                // manage Academic Head records only. Dean records never
                                // reach this list for an Academic Head.
                                $canManage = $isDean || (string) $row['role'] === 'academic_head';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-sm"><?= e(initials($row)) ?></span>
                                            <span class="min-w-0">
                                                <strong class="d-block text-truncate"><?= e(full_name($row)) ?></strong>
                                                <span class="text-tiny text-muted d-block text-truncate"><?= e($row['email']) ?><?= !empty($row['department_code']) ? ' · ' . e($row['department_code']) : '' ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $row['role'] === 'dean' ? 'badge-info' : 'badge-soft' ?>"><?= e($row['role_name'] ?? $row['role']) ?></span>
                                        <?= display_demo_badge($row['is_demo'] ?? 0) ?>
                                    </td>
                                    <td>
                                        <?= status_badge((string) $row['status']) ?>
                                        <?php if ((int) ($row['must_change_password'] ?? 0) === 1): ?>
                                            <span class="badge badge-warning" title="Must choose a new password at next sign-in">Temp password</span>
                                        <?php endif; ?>
                                        <?php if ((int) $row['id'] === (int) Auth::id()): ?>
                                            <span class="badge badge-info">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= e(format_datetime($row['last_login_at'] ?? null)) ?></td>
                                    <td class="text-end">
                                        <?php if ($canManage): ?>
                                            <div class="table-actions">
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/users/' . (int) $row['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Manage</a>
                                                <?php if ((int) $row['id'] !== (int) Auth::id()): ?>
                                                    <form method="post" action="<?= url('dean/users/' . (int) $row['id'] . '/status') ?>" class="d-inline" data-confirm="Change the status of this administrator account?">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                                            <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Dean-governed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$isDean): ?>
                <div class="card-footer data-note">Dean accounts are not listed here. Only the Dean governing this installation can review or change them.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const mode = document.getElementById('adminPasswordMode');
    const manual = document.getElementById('adminManualPassword');
    const note = document.getElementById('adminGenerateNote');
    if (!mode || !manual || !note) { return; }
    const password = document.getElementById('adminPassword');
    const confirmation = document.getElementById('adminPasswordConfirmation');

    function sync() {
        const isManual = mode.value === 'manual';
        manual.hidden = !isManual;
        note.hidden = isManual;
        if (password) { password.required = isManual; }
        if (confirmation) { confirmation.required = isManual; }
        if (!isManual && password && confirmation) {
            password.value = '';
            confirmation.value = '';
        }
    }

    mode.addEventListener('change', sync);
    sync();
})();
</script>
