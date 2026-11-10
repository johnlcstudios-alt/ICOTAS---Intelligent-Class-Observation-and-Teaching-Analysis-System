<?php
/** @var string $content */
$user = App\Core\Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Sign in') ?> · <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
<main class="auth-shell">
    <section class="auth-brand-panel">
        <div class="auth-brand-inner">
            <span class="brand-mark brand-mark-lg"><i class="bi bi-mortarboard-fill"></i></span>
            <p class="auth-kicker"><?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></p>
            <h1><?= e(setting('system_name', 'ICOTAS')) ?></h1>
            <p class="auth-tagline"><?= e(setting('system_tagline', 'Intelligent Class Observation & Teaching Analytics System')) ?></p>
            <ul class="auth-points">
                <li><i class="bi bi-shield-lock"></i><span>Anonymous student feedback with separated identity records</span></li>
                <li><i class="bi bi-qr-code"></i><span>Secure QR evaluation sessions with hashed access tokens</span></li>
                <li><i class="bi bi-graph-up-arrow"></i><span>Transparent analytics, rule-based insights, and evidence-linked recommendations</span></li>
                <li><i class="bi bi-patch-check"></i><span>ISO/IEC 25010 user acceptance testing workflow</span></li>
            </ul>
            <p class="auth-footnote">For academic use. Predictions are rule-based estimates, not validated machine-learning forecasts.</p>
        </div>
    </section>
    <section class="auth-form-panel">
        <div class="auth-form-inner">
            <?php App\Core\View::partial('partials.flash'); ?>
            <?= $content ?>
            <p class="auth-copyright">© <?= date('Y') ?> <?= e(setting('institution_name', 'ACLC College Tacloban City')) ?>. All rights reserved.</p>
        </div>
    </section>
</main>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
