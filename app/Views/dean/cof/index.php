<?php
/** Dean Classroom Observation Form management index. */

$criteria = $criteria ?? [];
$labelRange = $labelRange ?? 5;
$labels = [];
for ($i = 1; $i <= $labelRange; $i++) {
    $labels[$i] = ($settings['rating_label_' . $i] ?? 'Label ' . $i);
}
$grouping = [];
foreach ($criteria as $c) {
    $grouping[$c['group']][] = $c;
}
ksort($grouping);
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Classroom Observation Form</h2><p class="small">The 20-item observation instrument used by faculty.</p></div>
            <div class="card__body">
                <?php if (!empty($settings) && (string) ($settings['cof_verified'] ?? '0') === '1'): ?>
                    <div class="alert alert--success">
                        <span class="alert__icon"><?= icon('check', 18) ?></span>
                        <div class="alert__body">
                            <strong>Verified against the official ACLC form.</strong>
                            Verified by <?= e($settings['cof_verified_by'] ?? '') ?> on <?= e(fmt_datetime($settings['cof_verified_at'] ?? '')) ?>.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert--warning">
                        <span class="alert__icon"><?= icon('alert', 18) ?></span>
                        <div class="alert__body">
                            <strong>COF wording is provisional.</strong>
                            The 20-item form below is a working draft and must be verified against the official ACLC Classroom Observation Form (Version 1, Series 2012) before official use. Edit criterion text below, then confirm verification when ready.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cof-form-grid">
                    <?php foreach ($grouping as $group => $rows): ?>
                        <div class="cof-group">
                            <div class="cof-group__head">
                                <span class="cof-group__title"><?= e($group) ?></span>
                                <span class="cof-group__count"><?= count($rows) ?> criteria</span>
                            </div>
                            <div class="cof-group__body">
                                <?php foreach ($rows as $row): ?>
                                    <div class="cof-row">
                                        <span class="cof-row__num"><?= (int) $row['number'] ?></span>
                                        <span class="cof-row__text"><?= e($row['text']) ?></span>
                                        <span class="cof-row__weights">
                                            <span class="cof-score cof-score--small">
                                                <?= e(escScoreRange($row['score_min'] ?? 1)) ?>–<?= e(escScoreRange($row['score_max'] ?? 5)) ?>
                                            </span>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Verification</h2></div>
            <div class="card__body">
                <?php if ((string) ($settings['cof_verified'] ?? '0') !== '1'): ?>
                    <?php if (!empty($verificationFormOpen)): ?>
                        <form method="post" action="<?= e($verificationFormAction ?: url('/dean/cof/verify')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="form-grid form-grid--sm">
                                <div class="field">
                                    <label for="cof_verified_by">Verified by <span class="required">*</span></label>
                                    <input class="input<?= has_error('cof_verified_by') ? ' is-invalid' : '' ?>" type="text" id="cof_verified_by" name="cof_verified_by" maxlength="150" value="<?= e(old('cof_verified_by', ($currentUser['full_name'] ?? '') ?: '')) ?>" required>
                                    <?php if (has_error('cof_verified_by')): ?><span class="field-error"><?= e(error('cof_verified_by')) ?></span><?php endif; ?>
                                </div>
                                <div class="field">
                                    <label>
                                        <input type="checkbox" name="cof_mark_verified" value="1" required>
                                        I confirm the above criteria have been verified against the official ACLC Classroom Observation Form (Version 1, Series 2012).
                                    </label>
                                </div>
                            </div>
                            <?php if (!empty($verificationError)): ?>
                                <div class="form-error"><?= e($verificationError) ?></div>
                            <?php endif; ?>
                            <div class="form-actions">
                                <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Confirm verification</button>
                                <a class="btn btn--ghost" href="<?= e(url('/dean/cof')) ?>">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <a class="btn btn--primary" href="<?= e(url('/dean/cof/verify')) ?>"><?= icon('check', 16) ?> Verify the COF</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="cof-verified">
                        <span class="cof-verified__label"><?= icon('check', 16) ?> Verified</span>
                        <div class="stack">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/cof/verify')) ?>">Review or re-verify</a>
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/cof')) ?>">Return to COF</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Rating scale</h2></div>
            <div class="card__body">
                <div class="rating-row">
                    <?php for ($i = 1; $i <= $labelRange; $i++): ?>
                        <div class="rating-cell">
                            <div class="rating-cell__score"><?= (int) $i ?></div>
                            <div class="rating-cell__label"><?= e($labels[$i]) ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Scale behaviour</h2></div>
            <div class="card__body">
                <p class="small muted">This scale applies uniformly to all 20 criteria. Each criterion carries the same minimum and maximum unless edited and re-saved.</p>
                <p class="small">
                    <strong>Score calculation.</strong> Each observation's score is the mean of its 20 criterion ratings, rounded to two decimal places on a 1–5 scale. Reports display teaching, personality and overall averages.
                </p>
                <p class="small">
                    <strong>Editing.</strong> Criterion text may be revised here without affecting historical scores. To change the instrument, coordinate with the faculty whose observations will be affected.
                </p>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Status</h2></div>
            <div class="card__body">
                <ul class="meta-list muted small">
                    <li><span>Group 1</span><strong>Teaching Competence</strong> — <?= count($grouping['Teaching Competence'] ?? []) ?> criteria</li>
                    <li><span>Group 2</span><strong>Personality</strong> — <?= count($grouping['Personality'] ?? []) ?> criteria</li>
                    <li><span>Total</span><strong>20 criteria</strong> — <?= (count($grouping['Teaching Competence'] ?? []) + count($grouping['Personality'] ?? [])) ?> recorded</li>
                </ul>
            </div>
        </section>
    </div>
</div>
