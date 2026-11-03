<?php
/** Dean system settings. */
?>
<div class="grid grid--sidebar">
    <section class="card">
        <div class="card__head"><h2>System settings</h2><p class="small">Changes apply to new requests immediately.</p></div>
        <div class="card__body">
            <form method="post" action="<?= e(url('/dean/settings')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="section">
                    <span class="eyebrow">Institution</span>
                    <div class="form-grid">
                        <div class="field field--full">
                            <label for="institution_name">Institution name <span class="required">*</span></label>
                            <input class="input<?= has_error('institution_name') ? ' is-invalid' : '' ?>" type="text" id="institution_name" name="institution_name" maxlength="150" value="<?= e(old('institution_name', $settings['institution_name'] ?? '')) ?>" required>
                            <?php if (has_error('institution_name')): ?><span class="field-error"><?= e(error('institution_name')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="institution_address">Institution address <span class="required">*</span></label>
                            <input class="input<?= has_error('institution_address') ? ' is-invalid' : '' ?>" type="text" id="institution_address" name="institution_address" maxlength="255" value="<?= e(old('institution_address', $settings['institution_address'] ?? '')) ?>" required>
                            <?php if (has_error('institution_address')): ?><span class="field-error"><?= e(error('institution_address')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="system_name">System short name <span class="required">*</span></label>
                            <input class="input<?= has_error('system_name') ? ' is-invalid' : '' ?>" type="text" id="system_name" name="system_name" maxlength="60" value="<?= e(old('system_name', $settings['system_name'] ?? '')) ?>" required>
                            <?php if (has_error('system_name')): ?><span class="field-error"><?= e(error('system_name')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="system_full_name">System full name <span class="required">*</span></label>
                            <input class="input<?= has_error('system_full_name') ? ' is-invalid' : '' ?>" type="text" id="system_full_name" name="system_full_name" maxlength="200" value="<?= e(old('system_full_name', $settings['system_full_name'] ?? '')) ?>" required>
                            <?php if (has_error('system_full_name')): ?><span class="field-error"><?= e(error('system_full_name')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="timezone">Timezone <span class="required">*</span></label>
                            <select id="timezone" name="timezone" class="select" required>
                                <?php foreach (timezone_identifiers_list() as $tz): ?>
                                    <option value="<?= e($tz) ?>" <?= (string) ($settings['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('timezone')): ?><span class="field-error"><?= e(error('timezone')) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <span class="eyebrow">Security</span>
                    <div class="form-grid">
                        <div class="field">
                            <label for="session_timeout_minutes">Idle session timeout (minutes) <span class="required">*</span></label>
                            <input class="input<?= has_error('session_timeout_minutes') ? ' is-invalid' : '' ?>" type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="240" value="<?= e(old('session_timeout_minutes', $settings['session_timeout_minutes'] ?? '30')) ?>" required>
                            <?php if (has_error('session_timeout_minutes')): ?><span class="field-error"><?= e(error('session_timeout_minutes')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="session_absolute_minutes">Absolute session lifetime (minutes) <span class="required">*</span></label>
                            <input class="input<?= has_error('session_absolute_minutes') ? ' is-invalid' : '' ?>" type="number" id="session_absolute_minutes" name="session_absolute_minutes" min="30" max="1440" value="<?= e(old('session_absolute_minutes', $settings['session_absolute_minutes'] ?? '480')) ?>" required>
                            <?php if (has_error('session_absolute_minutes')): ?><span class="field-error"><?= e(error('session_absolute_minutes')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="qr_expiry_days">QR validity (days) <span class="required">*</span></label>
                            <input class="input<?= has_error('qr_expiry_days') ? ' is-invalid' : '' ?>" type="number" id="qr_expiry_days" name="qr_expiry_days" min="1" max="3650" value="<?= e(old('qr_expiry_days', $settings['qr_expiry_days'] ?? '365')) ?>" required>
                            <?php if (has_error('qr_expiry_days')): ?><span class="field-error"><?= e(error('qr_expiry_days')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="login_max_attempts">Login attempt limit <span class="required">*</span></label>
                            <input class="input<?= has_error('login_max_attempts') ? ' is-invalid' : '' ?>" type="number" id="login_max_attempts" name="login_max_attempts" min="3" max="20" value="<?= e(old('login_max_attempts', $settings['login_max_attempts'] ?? '5')) ?>" required>
                            <?php if (has_error('login_max_attempts')): ?><span class="field-error"><?= e(error('login_max_attempts')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="login_lockout_minutes">Lockout window (minutes) <span class="required">*</span></label>
                            <input class="input<?= has_error('login_lockout_minutes') ? ' is-invalid' : '' ?>" type="number" id="login_lockout_minutes" name="login_lockout_minutes" min="1" max="120" value="<?= e(old('login_lockout_minutes', $settings['login_lockout_minutes'] ?? '15')) ?>" required>
                            <?php if (has_error('login_lockout_minutes')): ?><span class="field-error"><?= e(error('login_lockout_minutes')) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <span class="eyebrow">COF rating labels</span>
                    <div class="form-grid">
                        <div class="field">
                            <label for="rating_label_1">Label 1 <span class="required">*</span></label>
                            <input class="input<?= has_error('rating_label_1') ? ' is-invalid' : '' ?>" type="text" id="rating_label_1" name="rating_label_1" maxlength="60" value="<?= e(old('rating_label_1', $settings['rating_label_1'] ?? '')) ?>" required>
                            <?php if (has_error('rating_label_1')): ?><span class="field-error"><?= e(error('rating_label_1')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="rating_label_2">Label 2 <span class="required">*</span></label>
                            <input class="input<?= has_error('rating_label_2') ? ' is-invalid' : '' ?>" type="text" id="rating_label_2" name="rating_label_2" maxlength="60" value="<?= e(old('rating_label_2', $settings['rating_label_2'] ?? '')) ?>" required>
                            <?php if (has_error('rating_label_2')): ?><span class="field-error"><?= e(error('rating_label_2')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="rating_label_3">Label 3 <span class="required">*</span></label>
                            <input class="input<?= has_error('rating_label_3') ? ' is-invalid' : '' ?>" type="text" id="rating_label_3" name="rating_label_3" maxlength="60" value="<?= e(old('rating_label_3', $settings['rating_label_3'] ?? '')) ?>" required>
                            <?php if (has_error('rating_label_3')): ?><span class="field-error"><?= e(error('rating_label_3')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="rating_label_4">Label 4 <span class="required">*</span></label>
                            <input class="input<?= has_error('rating_label_4') ? ' is-invalid' : '' ?>" type="text" id="rating_label_4" name="rating_label_4" maxlength="60" value="<?= e(old('rating_label_4', $settings['rating_label_4'] ?? '')) ?>" required>
                            <?php if (has_error('rating_label_4')): ?><span class="field-error"><?= e(error('rating_label_4')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="rating_label_5">Label 5 <span class="required">*</span></label>
                            <input class="input<?= has_error('rating_label_5') ? ' is-invalid' : '' ?>" type="text" id="rating_label_5" name="rating_label_5" maxlength="60" value="<?= e(old('rating_label_5', $settings['rating_label_5'] ?? '')) ?>" required>
                            <?php if (has_error('rating_label_5')): ?><span class="field-error"><?= e(error('rating_label_5')) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <span class="eyebrow">Class record grading rules</span>
                    <p class="small">These defaults are pending institutional verification.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label for="grading_min">Minimum grade <span class="required">*</span></label>
                            <input class="input<?= has_error('grading_min') ? ' is-invalid' : '' ?>" type="number" step="0.01" id="grading_min" name="grading_min" value="<?= e(old('grading_min', $settings['grading_min'] ?? '0')) ?>" required>
                            <?php if (has_error('grading_min')): ?><span class="field-error"><?= e(error('grading_min')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="grading_max">Maximum grade <span class="required">*</span></label>
                            <input class="input<?= has_error('grading_max') ? ' is-invalid' : '' ?>" type="number" step="0.01" id="grading_max" name="grading_max" value="<?= e(old('grading_max', $settings['grading_max'] ?? '100')) ?>" required>
                            <?php if (has_error('grading_max')): ?><span class="field-error"><?= e(error('grading_max')) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="passing_grade">Passing grade <span class="required">*</span></label>
                            <input class="input<?= has_error('passing_grade') ? ' is-invalid' : '' ?>" type="number" step="0.01" id="passing_grade" name="passing_grade" value="<?= e(old('passing_grade', $settings['passing_grade'] ?? '75')) ?>" required>
                            <?php if (has_error('passing_grade')): ?><span class="field-error"><?= e(error('passing_grade')) ?></span><?php endif; ?>
                        </div>
                        <div class="field" style="display:flex;align-items:center;margin-top:24px">
                            <label class="checkbox">
                                <input type="checkbox" name="class_record_require_complete" value="1" <?= (string) ($settings['class_record_require_complete'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span>Require passed + failed to equal total students for finalized records</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Save settings</button>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/settings')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Current configuration</h2></div>
            <div class="card__body">
                <p class="small muted">The values below reflect the saved configuration. The form above shows the posted (or currently saved) values.</p>
                <ul class="meta-list">
                    <?php if (!empty($settings)): ?>
                        <li><span>Institution</span><strong><?= e($settings['institution_name'] ?? '') ?></strong></li>
                        <li><span>Address</span><strong><?= e($settings['institution_address'] ?? '') ?></strong></li>
                        <li><span>System short name</span><strong><?= e($settings['system_name'] ?? '') ?></strong></li>
                        <li><span>Timezone</span><strong><?= e($settings['timezone'] ?? '') ?></strong></li>
                        <li><span>QR expiry</span><strong><?= (int) ($settings['qr_expiry_days'] ?? 365) ?> days</strong></li>
                        <li><span>Login limit</span><strong><?= (int) ($settings['login_max_attempts'] ?? 5) ?></strong></li>
                        <li><span>Lockout window</span><strong><?= (int) ($settings['login_lockout_minutes'] ?? 15) ?> min</strong></li>
                        <li><span>Passing grade</span><strong><?= e($settings['passing_grade'] ?? 75) ?></strong></li>
                    <?php else: ?>
                        <li><span class="muted">No configuration loaded.</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>COF verification</h2></div>
            <div class="card__body">
                <?php if ((string) ($settings['cof_verified'] ?? '0') === '1'): ?>
                    <div class="alert alert--success">
                        <span class="alert__icon"><?= icon('check', 18) ?></span>
                        <div class="alert__body">
                            <strong>COF wording verified.</strong>
                            Verified by <?= e($settings['cof_verified_by'] ?? '') ?> on <?= e(fmt_datetime($settings['cof_verified_at'] ?? '')) ?>.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert--warning">
                        <span class="alert__icon"><?= icon('alert', 18) ?></span>
                        <div class="alert__body">
                            <strong>COF wording is provisional.</strong>
                            The 20-item form must be verified against the official ACLC Classroom Observation Form (Version 1, Series 2012) before official use.
                        </div>
                    </div>
                <?php endif; ?>
                <a class="btn btn--secondary btn--block mt-2" href="<?= e(url('/dean/cof')) ?>">Manage the COF</a>
            </div>
        </section>
    </div>
</div>
