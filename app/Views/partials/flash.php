<?php
$types = [
    'success' => 'success',
    'error' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
foreach ($types as $flashKey => $alertType):
    $message = flash($flashKey);
    if ($message === null || $message === '') {
        continue;
    }
    ?>
    <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show app-alert" role="alert">
        <i class="bi <?= $alertType === 'danger' ? 'bi-exclamation-octagon' : ($alertType === 'warning' ? 'bi-exclamation-triangle' : ($alertType === 'success' ? 'bi-check-circle' : 'bi-info-circle')) ?>"></i>
        <span><?= e($message) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
