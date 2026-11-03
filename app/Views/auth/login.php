<?php
/**
 * Sign-in form.
 *
 * @var bool $showSetupNotice
 */
?>
<div class="auth-card">
    <div class="auth-card__head">
        <span class="eyebrow">Welcome back</span>
        <h1>Sign in to <?= e(setting('system_name', 'ICOTAS')) ?></h1>
        <p>Use your institutional username or email address. Dean and Faculty accounts share this sign-in page.</p>
    </div>

    <?php if (!empty($showSetupNotice)): ?>
        <div class="alert alert--info" role="status">
            <span class="alert__icon"><?= icon('info', 18) ?></span>
            <div class="alert__body">Initial setup has not been completed yet. Use the one-time setup link provided by the administrator.</div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="field">
                <label for="identifier">Username or email</label>
                <input class="input<?= has_error('identifier') ? ' is-invalid' : '' ?>"
                       type="text" id="identifier" name="identifier"
                       value="<?= e(old('identifier')) ?>"
                       autocomplete="username" required autofocus
                       aria-describedby="identifier-help">
                <span class="help" id="identifier-help">For example: jdelacruz or jdelacruz@aclc.edu.ph</span>
                <?php if (has_error('identifier')): ?>
                    <span class="field-error"><?= e(error('identifier')) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="password-field">
                    <input class="input<?= has_error('password') ? ' is-invalid' : '' ?>"
                           type="password" id="password" name="password"
                           autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                        <?= icon('eye', 17) ?>
                    </button>
                </div>
                <?php if (has_error('password')): ?>
                    <span class="field-error"><?= e(error('password')) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">
                <?= icon('lock', 16) ?> Sign in securely
            </button>
        </div>
    </form>

    <p class="auth-legal">
        Sessions expire automatically after a period of inactivity. Repeated failed sign-in attempts are rate-limited and logged.
    </p>
</div>
