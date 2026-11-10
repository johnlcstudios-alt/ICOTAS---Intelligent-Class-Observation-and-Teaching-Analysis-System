<?php
/** @var int $status */
/** @var string $message */
$titles = [
    401 => 'Sign-in required',
    403 => 'Access restricted',
    404 => 'Page not found',
    405 => 'Method not allowed',
    419 => 'Security token expired',
    500 => 'System error',
];
$icons = [
    401 => 'bi-person-lock',
    403 => 'bi-shield-lock',
    404 => 'bi-signpost-split',
    405 => 'bi-slash-circle',
    419 => 'bi-arrow-clockwise',
    500 => 'bi-exclamation-octagon',
];
$status = (int) ($status ?? 500);
$title = $titles[$status] ?? 'Unexpected error';
$icon = $icons[$status] ?? 'bi-exclamation-octagon';
?>
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card mt-4">
            <div class="card-body text-center py-5">
                <span class="brand-mark brand-mark-lg mx-auto mb-3"><i class="bi <?= e($icon) ?>"></i></span>
                <span class="eyebrow">Error <?= $status ?></span>
                <h2 class="mt-1 mb-2"><?= e($title) ?></h2>
                <p class="text-muted mb-4"><?= e($message !== '' ? $message : 'The requested action could not be completed.') ?></p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php if (auth_check()): ?>
                        <a class="btn btn-primary" href="<?= url(auth_role() === 'student' ? 'student/dashboard' : (auth_role() === 'instructor' ? 'instructor/dashboard' : 'dean/dashboard')) ?>">
                            <i class="bi bi-house"></i> Return to dashboard
                        </a>
                    <?php else: ?>
                        <a class="btn btn-primary" href="<?= url('login') ?>"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="history.back()"><i class="bi bi-arrow-left"></i> Go back</button>
                </div>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">If this problem continues, contact the ICOTAS administrator and quote error <?= $status ?>.</p>
    </div>
</div>
