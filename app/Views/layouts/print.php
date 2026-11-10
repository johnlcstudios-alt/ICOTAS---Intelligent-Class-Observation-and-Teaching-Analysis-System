<?php
/** @var string $content */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Report') ?> · <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
    <a class="btn btn-sm btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Back</a>
    <button class="btn btn-sm btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
</div>
<main class="print-sheet">
    <?= $content ?>
</main>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
