<?php
/**
 * Account profile.
 *
 * @var array $user
 */
?>
<div class="grid grid--sidebar">
    <section class="card">
        <div class="card__head">
            <div><h2>Profile details</h2><p>Keep your contact information current. Usernames and roles are managed by the Dean.</p></div>
        </div>
        <div class="card__body">
            <form method="post" action="<?= e(url('/account')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field field--full">
                        <label for="full_name">Full name <span class="required">*</span></label>
                        <input class="input<?= has_error('full_name') ? ' is-invalid' : '' ?>" type="text" id="full_name" name="full_name"
                               value="<?= e(old('full_name', $user['full_name'])) ?>" maxlength="150" required>
                        <?php if (has_error('full_name')): ?><span class="field-error"><?= e(error('full_name')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">Email address <span class="required">*</span></label>
                        <input class="input<?= has_error('email') ? ' is-invalid' : '' ?>" type="email" id="email" name="email"
                               value="<?= e(old('email', $user['email'])) ?>" maxlength="190" required>
                        <?php if (has_error('email')): ?><span class="field-error"><?= e(error('email')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="contact_no">Contact number</label>
                        <input class="input<?= has_error('contact_no') ? ' is-invalid' : '' ?>" type="text" id="contact_no" name="contact_no"
                               value="<?= e(old('contact_no', $user['contact_no'] ?? '')) ?>" maxlength="30" inputmode="tel">
                        <?php if (has_error('contact_no')): ?><span class="field-error"><?= e(error('contact_no')) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Save profile</button>
                    <a class="btn btn--secondary" href="<?= e(url('/account/password')) ?>"><?= icon('lock', 16) ?> Change password</a>
                </div>
            </form>
        </div>
    </section>

    <aside class="stack">
        <section class="card">
            <div class="card__body">
                <div class="row">
                    <span class="avatar" aria-hidden="true" style="width:48px;height:48px;font-size:15px"><?= e(initials((string) $user['full_name'])) ?></span>
                    <div>
                        <strong><?= e($user['full_name']) ?></strong>
                        <div class="muted small"><?= e($user['role']) ?></div>
                    </div>
                </div>
                <hr class="divider">
                <ul class="meta-list">
                    <li><span>Username</span><strong class="mono"><?= e($user['username']) ?></strong></li>
                    <li><span>Department</span><strong><?= e($user['department_name'] ?? 'Institution-wide') ?></strong></li>
                    <li><span>Employee no.</span><strong><?= e($user['employee_no'] ?? '—') ?></strong></li>
                    <li><span>Account status</span><strong><?= status_badge($user['account_status']) ?></strong></li>
                    <li><span>Last sign-in</span><strong><?= e(fmt_datetime($user['last_login_at'])) ?></strong></li>
                    <li><span>Member since</span><strong><?= e(fmt_date($user['created_at'])) ?></strong></li>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card__body">
                <h3>Security</h3>
                <p class="small">Your password is stored only as a one-way hash. Use at least 10 characters with uppercase, lowercase, a number and a symbol.</p>
                <a class="btn btn--secondary btn--block" href="<?= e(url('/account/password')) ?>"><?= icon('lock', 16) ?> Update password</a>
            </div>
        </section>
    </aside>
</div>
