<?php
/**
 * Printable report layout.
 *
 * @var string $content
 * @var string $pageTitle
 */
$institutionName = setting('institution_name', 'ACLC College Tacloban');
$institutionAddress = setting('institution_address', 'Tacloban City, Leyte, Philippines');
$systemName = setting('system_full_name', 'Intelligent Classroom Observation and Teaching Analysis System');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> | <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
    <a class="btn btn--ghost btn--sm" href="<?= e(url(is_dean() ? '/dean/reports' : '/faculty/evaluations')) ?>">
        <?= icon('chevron-left', 16) ?> Back
    </a>
    <div class="print-toolbar__actions">
        <span class="muted">Use your browser's print dialog to print or save as PDF.</span>
        <button type="button" class="btn btn--primary btn--sm" data-print>
            <?= icon('print', 16) ?> Print / Save PDF
        </button>
    </div>
</div>

<main class="print-sheet">
    <header class="print-header">
        <div class="print-header__crest" aria-hidden="true">IC</div>
        <div class="print-header__text">
            <span class="print-header__eyebrow">Republic of the Philippines</span>
            <h1><?= e($institutionName) ?></h1>
            <p><?= e($institutionAddress) ?></p>
            <p class="print-header__system"><?= e($systemName) ?></p>
        </div>
    </header>

    <div class="print-title">
        <h2><?= e($pageTitle) ?></h2>
        <span>Generated <?= e(date('F j, Y g:i A')) ?></span>
    </div>

    <?= $content ?>

    <footer class="print-footer">
        <div class="print-signature">
            <span>Prepared by</span>
            <strong><?= e(auth_user()['full_name'] ?? '') ?></strong>
            <small><?= e(auth_user()['role'] ?? '') ?></small>
        </div>
        <div class="print-footer__note">
            This report is generated from the ICOTAS database. Observation scores are professional judgements recorded through the
            <?= e(setting('system_name', 'ICOTAS')) ?> Classroom Observation Form and must be interpreted with professional discretion.
        </div>
    </footer>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
