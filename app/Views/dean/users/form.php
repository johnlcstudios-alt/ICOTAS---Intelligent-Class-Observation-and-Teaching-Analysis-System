<?php
/** Dean user form (create/edit). */

$isEdit = !empty($user);
$title = $isEdit ? 'Edit user' : 'Add user';
$action = $isEdit
    ? url('/dean/users/' . (int) $user['id'])
    : url('/dean/users');
$method = $isEdit ? 'put' : 'post';
$user = $user ?: [];

$usedEmail = $user['email'] ?? '';
$usedEmployeeId = $user['employee_id'] ?? '';
$usedDepartmentId = (int) ($user['department_id'] ?? 0);
$usedRole = $user['role'] ?? 'faculty';
$usedActive = !empty($user['is_active']);
$usedOnLeave = !empty($user['on_leave']);
$usedFullname = $user['full_name'] ?? '';
$usedPassword = $user['password'] ?? '';
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2><?= e($title) ?></h2><p class="small"><?= e($isEdit ? 'Update the user details below.' : 'Create a new user account for the institution.') ?></p></div>
            <div class="card__body">
                <form method="post" action="<?= e($action) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="_method" value="<?= e($method) ?>">
                    <?php endif; ?>

                    <div class="section">
                        <span class="eyebrow">Identity</span>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="full_name">Full name <span class="required">*</span></label>
                                <input class="input<?= has_error('full_name') ? ' is-invalid' : '' ?>" type="text" id="full_name" name="full_name" maxlength="150" value="<?= e(old('full_name', $usedFullname)) ?>" required>
                                <?php if (has_error('full_name')): ?><span class="field-error"><?= e(error('full_name')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="email">Email <span class="required">*</span></label>
                                <input class="input<?= has_error('email') ? ' is-invalid' : '' ?>" type="email" id="email" name="email" maxlength="255" value="<?= e(old('email', $usedEmail)) ?>" required>
                                <?php if (has_error('email')): ?><span class="field-error"><?= e(error('email')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="employee_id">Employee ID</label>
                                <input class="input<?= has_error('employee_id') ? ' is-invalid' : '' ?>" type="text" id="employee_id" name="employee_id" maxlength="60" value="<?= e(old('employee_id', $usedEmployeeId)) ?>">
                                <?php if (has_error('employee_id')): ?><span class="field-error"><?= e(error('employee_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="select">
                                    <option value="">— None —</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= $usedDepartmentId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('department_id')): ?><span class="field-error"><?= e(error('department_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="role">Role <span class="required">*</span></label>
                                <select id="role" name="role" class="select" required>
                                    <option value="faculty" <?= $usedRole === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                    <option value="dean" <?= $usedRole === 'dean' ? 'selected' : '' ?>>Dean</option>
                                </select>
                                <?php if (has_error('role')): ?><span class="field-error"><?= e(error('role')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow"><?= $isEdit ? 'Change password (leave blank to keep current)' : 'Password' ?></span>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="password">Password <?= $isEdit ? '(optional)' : '<span class="required">*</span>' ?></label>
                                <input class="input<?= has_error('password') ? ' is-invalid' : '' ?>" type="password" id="password" name="password" minlength="8" maxlength="128" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                                <?php if ($isEdit): ?>
                                    <p class="muted small">Leave blank to keep the current password.</p>
                                <?php endif; ?>
                                <?php if (has_error('password')): ?><span class="field-error"><?= e(error('password')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="password_confirmation">Confirm password</label>
                                <input class="input<?= has_error('password_confirmation') ? ' is-invalid' : '' ?>" type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password">
                                <?php if (has_error('password_confirmation')): ?><span class="field-error"><?= e(error('password_confirmation')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Account state</span>
                        <div class="form-grid">
                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox" name="is_active" value="1" <?= $usedActive ? 'checked' : '' ?>>
                                    <span>Account active</span>
                                </label>
                                <?php if (has_error('is_active')): ?><span class="field-error"><?= e(error('is_active')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox" name="on_leave" value="1" <?= $usedOnLeave ? 'checked' : '' ?>>
                                    <span>On leave</span>
                                </label>
                                <?php if (has_error('on_leave')): ?><span class="field-error"><?= e(error('on_leave')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <?php if ($isEdit): ?>
                            <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Update user</button>
                            <a class="btn btn--ghost" href="<?= e(url('/dean/users')) ?>">Cancel</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn--primary"><?= icon('plus', 16) ?> Create user</button>
                            <a class="btn btn--ghost" href="<?= e(url('/dean/users')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Notes</h2></div>
            <div class="card__body">
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li>Full name and email are required for every user.</li>
                    <li>Employee ID is optional and used only for display and references.</li>
                    <li>Dean accounts can access all dean-only workspaces. Faculty accounts see only their own published results.</li>
                    <li>Passwords are hashed on save. Written passwords are never stored in plain text.</li>
                </ul>
            </div>
        </section>
    </div>
</div>
