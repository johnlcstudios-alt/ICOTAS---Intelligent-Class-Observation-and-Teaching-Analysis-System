<?php
use App\Core\Setting;
/** @var string $pageTitle */
$demoMode = Setting::demoMode();
?>
<div class="mb-4">
    <span class="eyebrow">Secure institutional access</span>
    <h2 class="mt-1">Sign in to your workspace</h2>
    <p class="text-muted">Use the account issued by your Dean or academic office. Access is limited by role.</p>
</div>

<form method="post" action="<?= url('login') ?>" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="login">Username or email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="login" name="login" value="<?= e(old('login')) ?>" autocomplete="username" required autofocus>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
</form>

<div class="disclaimer mt-4">
    <i class="bi bi-shield-lock"></i>
    Student evaluation responses are stored without student identifiers. Only one response is accepted per evaluation session.
</div>

<?php if ($demoMode): ?>
    <div class="demo-accounts mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="bi bi-database-check"></i> Demonstration accounts</strong>
            <span class="badge badge-demo">Demo mode</span>
        </div>
        <p class="mb-2 text-muted">Synthetic demo data is active. These accounts are fictional and must never be used for live institutional records.</p>
        <ul class="list-clean mb-2">
            <li><span>Dean / Academic Head</span><code>demo.dean</code></li>
            <li><span>Instructor</span><code>demo.instructor.01</code></li>
            <li><span>Student</span><code>demo.student.01</code></li>
        </ul>
        <p class="mb-0">Password for every demo account: <code>Demo@12345</code></p>
    </div>
<?php endif; ?>

<p class="text-center text-muted small mt-4 mb-0">
    <i class="bi bi-info-circle"></i> Accounts are created by the academic office. There is no public self-registration.
</p>
