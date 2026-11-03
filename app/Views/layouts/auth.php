<?php
/**
 * Split-screen authentication layout.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $bodyClass
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> | <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="auth-body <?= e($bodyClass) ?>">
<main class="auth-shell">
    <section class="auth-aside" aria-label="About ICOTAS">
        <div class="auth-aside__inner">
            <div class="brand brand--light">
                <span class="brand__crest" aria-hidden="true">IC</span>
                <span class="brand__text">
                    <strong><?= e(setting('system_name', 'ICOTAS')) ?></strong>
                    <small><?= e(setting('institution_name', 'ACLC College Tacloban')) ?></small>
                </span>
            </div>

            <div class="auth-aside__copy">
                <span class="eyebrow eyebrow--gold">Classroom observation · Teaching analytics</span>
                <h1>Evidence-led teaching improvement, in one secure workspace.</h1>
                <p>
                    Record the official 20-item Classroom Observation Form, publish results to faculty,
                    monitor teaching trends and forecast performance using transparent native-PHP statistics.
                </p>
                <ul class="auth-features">
                    <li><?= icon('clipboard', 17) ?> 13 Teaching Competence + 7 Personality indicators</li>
                    <li><?= icon('shield', 17) ?> Role-isolated Dean and Faculty workspaces</li>
                    <li><?= icon('qr', 17) ?> Hash-only QR credentials with expiration and revocation</li>
                    <li><?= icon('trend', 17) ?> Simple Linear Regression with honest data limits</li>
                </ul>
            </div>

            <p class="auth-aside__foot">
                <?= e(setting('system_full_name', 'Intelligent Classroom Observation and Teaching Analysis System')) ?>
            </p>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel__inner">
            <div class="auth-mobile-brand">
                <span class="brand__crest" aria-hidden="true">IC</span>
                <div>
                    <strong><?= e(setting('system_name', 'ICOTAS')) ?></strong>
                    <small><?= e(setting('institution_name', 'ACLC College Tacloban')) ?></small>
                </div>
            </div>

            <?php require BASE_PATH . '/app/Views/partials/test-banner.php'; ?>
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

            <?= $content ?>

            <p class="auth-legal">
                Authorized users only. Activity in this system is logged for accountability and security.
            </p>
        </div>
    </section>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
