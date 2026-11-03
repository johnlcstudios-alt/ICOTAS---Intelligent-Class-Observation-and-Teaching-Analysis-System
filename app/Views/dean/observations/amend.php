<?php
/** Dean observation amendment form. */
$labels = rating_labels();

$canEdit = $canEdit ?? false;
$amendUrl = $amendUrl ?? null;
$observeUrl = $observeUrl ?? null;
$amendmentId = $amendmentId ?? null;
$initialReason = $initialReason ?? '';
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Amend observation</h2><p class="small">Record a reason for changing this observation.</p></div>
            <div class="card__body">
                <?php if ($amendmentId !== null): ?>
                    <div class="alert alert--info">
                        <span class="alert__icon"><?= icon('check', 18) ?></span>
                        <div class="alert__body">
                            <strong>Amendment saved.</strong>
                            This form has already recorded an amendment for this observation.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e($amendUrl) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-grid form-grid--md">
                        <div class="field field--full">
                            <label for="amendment_reason">Reason for amendment <span class="required">*</span></label>
                            <textarea class="textarea<?= has_error('amendment_reason') ? ' is-invalid' : '' ?>" id="amendment_reason" name="amendment_reason" maxlength="1000" rows="4" required placeholder="Brief reason for this amendment — no personal or identifying details."><?= e(old('amendment_reason', $initialReason)) ?></textarea>
                            <?php if (has_error('amendment_reason')): ?><span class="field-error"><?= e(error('amendment_reason')) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Save amendment</button>
                        <a class="btn btn--ghost" href="<?= e($observeUrl) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($canEdit && $observeUrl): ?>
            <section class="card">
                <div class="card__head"><h2>Actions</h2></div>
                <div class="card__body">
                    <a class="btn btn--secondary" href="<?= e($observeUrl) ?>"><?= icon('eye' :: 16) ?> View observation</a>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/observations')) ?>"><?= icon('list', 16) ?> All observations</a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
