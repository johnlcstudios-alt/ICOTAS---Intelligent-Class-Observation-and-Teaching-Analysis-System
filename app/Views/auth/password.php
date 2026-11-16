<?php
/** @var bool $forced */
use App\Core\Auth;

$user = Auth::user();
?>
<div class="mb-4">
    <span class="eyebrow"><?= $forced ? 'Temporary password' : 'Account security' ?></span>
    <h2 class="mt-1"><?= $forced ? 'Choose a new password' : 'Change your password' ?></h2>
    <?php if ($forced): ?>
        <p class="text-muted mb-0">Your account was created with a temporary password. You must choose your own password before using ICOTAS.</p>
    <?php else: ?>
        <p class="text-muted mb-0">Use your current password to set a new one. Other signed-in devices will be signed out.</p>
    <?php endif; ?>
</div>

<?php if ($forced): ?>
    <div class="alert alert-warning app-alert">
        <i class="bi bi-key"></i>
        <span><strong>Required step.</strong> This page is the only part of ICOTAS available until the temporary password is replaced.</span>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('account/password') ?>" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="currentPassword"><?= $forced ? 'Temporary password' : 'Current password' ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="currentPassword" name="current_password" autocomplete="current-password" required autofocus>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#currentPassword" aria-label="Show or hide current password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="newPassword">New password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="newPassword" name="password" autocomplete="new-password" minlength="8" required>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#newPassword" aria-label="Show or hide new password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="confirmPassword">Confirm new password</label>
        <input type="password" class="form-control" id="confirmPassword" name="password_confirmation" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="form-text mb-3">At least 8 characters, including a letter and a number. Do not reuse the temporary password.</div>

    <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-shield-check"></i> Save new password</button>
</form>

<?php if (!$forced): ?>
    <p class="text-center small mt-4 mb-0">
        <a href="<?= url(Auth::dashboardPath()) ?>"><i class="bi bi-arrow-left"></i> Back to your workspace</a>
    </p>
<?php endif; ?>

<div class="disclaimer mt-4">
    <i class="bi bi-info-circle"></i>
    Signed in as <strong><?= e($user['username'] ?? '') ?></strong>. After saving, all other sessions for this account are signed out.
</div>
