<?php
/**
 * Flash messages and validation summary.
 *
 * @var array $flashMessages
 * @var array $validationErrors
 */
if (($flashMessages ?? []) === [] && ($validationErrors ?? []) === []) {
    return;
}
?>
<div class="flash-stack" aria-live="polite">
    <?php foreach (($flashMessages ?? []) as $message): ?>
        <?php
        $type = (string) ($message['type'] ?? 'info');
        $tone = match ($type) {
            'success' => 'success',
            'error'   => 'danger',
            'warning' => 'warning',
            default   => 'info',
        };
        $iconName = match ($type) {
            'success' => 'check',
            'error'   => 'alert',
            'warning' => 'alert',
            default   => 'info',
        };
        ?>
        <div class="alert alert--<?= e($tone) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
            <span class="alert__icon"><?= icon($iconName, 18) ?></span>
            <div class="alert__body"><?= nl2br(e($message['message'] ?? '')) ?></div>
            <button type="button" class="alert__close" data-dismiss aria-label="Dismiss message"><?= icon('x', 15) ?></button>
        </div>
    <?php endforeach; ?>

    <?php if (($validationErrors ?? []) !== []): ?>
        <div class="alert alert--danger" role="alert">
            <span class="alert__icon"><?= icon('alert', 18) ?></span>
            <div class="alert__body">
                <strong>Please review the following:</strong>
                <ul class="alert__list">
                    <?php foreach ($validationErrors as $field => $error): ?>
                        <li>
                            <?= e(is_array($error) ? (string) reset($error) : (string) $error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="alert__close" data-dismiss aria-label="Dismiss message"><?= icon('x', 15) ?></button>
        </div>
    <?php endif; ?>
</div>
