<?php
/** Faculty evaluation detail. */

use App\Models\Scenario as ScenarioModel;
use App\Services\FormService;
use App\Services\ResultService;

$minNoteLength = FormService::MIN_NOTE_LENGTH;
$maxNoteLength = FormService::MAX_NOTE_LENGTH;
$scenario = $scenario ?? null;
$results = $results ?? [];
$scenarioStatus = $scenarioStatus ?? 'not_started';
$scenarioTitle = $scenarioTitle ?? '';
$scenarioDescription = $scenarioDescription ?? '';
$dueAt = $dueAt ?? null;
$scenarioStatusBadge = scenarioStatusBadge($scenarioStatus);
$isOverdue = false;
if ($dueAt && $scenarioStatus !== 'approved' && $scenarioStatus !== 'done') {
    try {
        $isOverdue = strtotime($dueAt) < time();
    } catch (\Throwable $e) {
        $isOverdue = false;
    }
}

$canSubmit = false;
if ($scenario && $scenarioStatus !== 'done' && $scenarioStatus !== 'approved') {
    $canSubmit = true;
}

$noteField = $noteField ?? null;
?>
<div class="page-tools">
    <a class="btn btn--ghost" href="<?= e(url('/faculty/scenarios')) ?>"><?= icon('list', 16) ?> Scenarios</a>
    <a class="btn btn--ghost" href="<?= e(url('/faculty/scenarios/' . (int) ($scenario['id'] ?? 0))) ?>"><?= icon('arrow-left', 16) ?> Back</a>
</div>

<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <div>
                    <h2><?= e($scenarioTitle) ?></h2>
                    <p class="small"><?= e($scenarioDescription) ?></p>
                </div>
                <div class="flex">
                    <span class="badge badge--neutral"><?= e($scenarioStatusBadge) ?></span>
                    <?php if ($dueAt): ?>
                        <span class="badge <?= $isOverdue ? 'badge--danger' : 'badge--neutral' ?>">
                            <?= icon('clock', 12) ?> Due <?= e(fmt_date($dueAt)) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($canSubmit && empty($results)): ?>
            <section class="card">
                <div class="card__head"><h2>Start your result</h2></div>
                <div class="card__body">
                    <p class="muted small">Record your scenario result below.</p>
                    <form method="post" action="<?= e(url('/faculty/scenarios/' . (int) ($scenario['id'] ?? 0) . '/result')) ?>" class="form-grid form-grid--md">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="result">Result <?= $minNoteLength ? '<span class="required">*</span>' : '' ?></label>
                            <textarea
                                id="result"
                                name="result"
                                class="form-control<?= $minNoteLength ? ' required' : '' ?>"
                                rows="4"
                                maxlength="<?= (int) ($maxNoteLength ?: 2000) ?>"
                                placeholder="<?= e($scenario['prompt'] ?? 'Describe your scenario result here.') ?>"
                            ><?= e(old('result')) ?></textarea>
                            <?php if ($minNoteLength): ?>
                                <div class="form-note"><?= e($minNoteLength) ?>–<?= e($maxNoteLength ?: 2000) ?> characters expected.</div>
                            <?php endif; ?>
                            <?php if (old('result') && mb_strlen(old('result')) < $minNoteLength): ?>
                                <?= message('error', "Result must be at least {$minNoteLength} characters.") ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn--primary"><?= icon('save', 16) ?> Submit</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php elseif ($canSubmit): ?>
            <section class="card">
                <div class="card__head"><h2>Edit your result</h2></div>
                <div class="card__body">
                    <p class="muted small">You can revise your result before it is approved.</p>
                    <form method="post" action="<?= e(url('/faculty/scenarios/' . (int) ($scenario['id'] ?? 0) . '/result')) ?>" class="form-grid form-grid--md">
                        <?= csrf_field() ?>
                        <?php if (!empty($results)): ?>
                            <?php
                                $existing = $results[0] ?? null;
                                $existingId = $existing['id'] ?? null;
                                $existingText = $existing['text'] ?? '';
                            ?>
                            <div class="field">
                                <label for="result">Current result</label>
                                <textarea
                                    id="result"
                                    name="result"
                                    class="form-control"
                                    rows="4"
                                    maxlength="<?= (int) ($maxNoteLength ?: 2000) ?>"
                                ><?= e($existingText) ?></textarea>
                                <?php if ($existingId): ?>
                                    <input type="hidden" name="existing_id" value="<?= (int) $existingId ?>">
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="field">
                                <label for="result">Result</label>
                                <textarea
                                    id="result"
                                    name="result"
                                    class="form-control required"
                                    rows="4"
                                    maxlength="<?= (int) ($maxNoteLength ?: 2000) ?>"
                                ><?= e(old('result')) ?></textarea>
                            </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn--primary"><?= icon('save', 16) ?> Save</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <section class="card">
                <div class="card__head"><h2>Your result</h2></div>
                <div class="card__body">
                    <?php foreach ($results as $r): ?>
                        <div class="scenario-result">
                            <div class="scenario-result__meta">
                                <span class="scenario-result__author"><?= e($r['author'] ?? 'You') ?></span>
                                <span class="scenario-result__date muted small"><?= e($r['created_at'] ? fmt_datetime($r['created_at']) : '') ?></span>
                            </div>
                            <div class="scenario-result__body">
                                <p><?= e($r['text']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Scenario info</h2></div>
            <div class="card__body">
                <div class="meta-list">
                    <li><span>Scenario</span><strong><?= e($scenario->title()) ?></strong></li>
                    <li><span>Type</span><strong><?= e($scenario->type_label()) ?></strong></li>
                    <li><span>Status</span><strong><?= e($scenarioStatusBadge) ?></strong></li>
                    <?php if ($dueAt): ?>
                        <li><span>Due</span><strong><?= e(fmt_date($dueAt)) ?></strong></li>
                        <?php if ($isOverdue): ?>
                            <li class="text-danger"><span><?= icon('alert', 14) ?> Overdue</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($scenario['prompt'])): ?>
                    <div class="mt-2">
                        <strong>Prompt</strong>
                        <div class="scenario-prompt"><?= e($scenario['prompt']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
