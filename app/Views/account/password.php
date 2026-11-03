<?php
/**
 * Change password.
 *
 * @var array $user
 * @var bool $forced
 */
?>
<div class="grid grid--sidebar">
    <section class="card">
        <div class="card__head">
            <div><h2>Change password</h2><p>Changing your password signs out other browser sessions only after they expire; this session is preserved.</p></div>
        </div>
        <div class="card__body">
            <?php if ($forced): ?>
                <div class="alert alert--warning" role="alert">
                    <span class="alert__icon"><?= icon('alert', 18) ?></span>
                    <div class="alert__body"><strong>Password change required.</strong> Your password was reset by the Dean. Set a new password before continuing.</div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/account/password')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field field--full">
                        <label for="current_password">Current password <span class="required">*</span></label>
                        <div class="password-field">
                            <input class="input<?= has_error('current_password') ? ' is-invalid' : '' ?>" type="password"
                                   id="current_password" name="current_password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="current_password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
                        </div>
                        <?php if (has_error('current_password')): ?><span class="field-error"><?= e(error('current_password')) ?></span><?php endif; ?>
                    </div>

                    <div class="field field--full">
                        <label for="password">New password <span class="required">*</span></label>
                        <div class="password-field">
                            <input class="input<?= has_error('password') ? ' is-invalid' : '' ?>" type="password"
                                   id="password" name="password" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
                        </div>
                        <span class="help">At least 10 characters with uppercase, lowercase, a number and a symbol. It must differ from your current password.</span>
                        <?php if (has_error('password')): ?><span class="field-error"><?= e(error('password')) ?></span><?php endif; ?>
                    </div>

                    <div class="field field--full">
                        <label for="password_confirmation">Confirm new password <span class="required">*</span></label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= icon('lock', 16) ?> Update password</button>
                    <a class="btn btn--ghost" href="<?= e(url('/account')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside class="card">
        <div class="card__body">
            <h3>Password guidance</h3>
            <ul class="small muted" style="padding-left:18px;line-height:1.7">
                <li>Use a unique password that you do not use on other systems.</li>
                <li>Prefer a long passphrase with intentional symbols and numbers.</li>
                <li>Never share your password. The Dean can reset it without knowing the old one.</li>
                <li>All password changes are recorded in the audit trail.</li>
            </ul>
        </div>
    </aside>
</div>
