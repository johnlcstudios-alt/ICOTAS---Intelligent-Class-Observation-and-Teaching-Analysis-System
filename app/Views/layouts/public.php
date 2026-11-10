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
    <meta name="theme-color" content="#701c2b">
    <title><?= e($pageTitle ?? 'ICOTAS') ?> · <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="public-body">
<header class="public-header">
    <a class="public-brand" href="<?= url($user ? App\Core\Auth::dashboardPath() : 'login') ?>">
        <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
        <span><strong><?= e(setting('system_name', 'ICOTAS')) ?></strong><small><?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></small></span>
    </a>
    <div class="public-actions">
        <?php if ($user): ?>
            <span class="text-muted small d-none d-sm-inline"><?= e(full_name($user)) ?></span>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url(App\Core\Auth::dashboardPath()) ?>"><i class="bi bi-house"></i> Portal</a>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('login') ?>">Sign in</a>
        <?php endif; ?>
    </div>
</header>
<main class="public-main" id="main-content">
    <?php App\Core\View::partial('partials.flash'); ?>
    <?= $content ?>
</main>
<footer class="public-footer">
    <span><?= e(setting('system_name', 'ICOTAS')) ?> · <?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></span>
    <span>Privacy-preserving academic evaluation</span>
</footer>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $pageInlineScript ?? '' ?>
</body>
</html>
