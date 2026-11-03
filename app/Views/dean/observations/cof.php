<?php
/** Dean observation COF (Classroom Observation Form) page. */
$labels = rating_labels();

$criteriaGrouped = $criteriaGrouped ?? [];

$groupSummary = [];
foreach ($criteriaGrouped as $g => $items):
    if (empty($items)) { continue; }
    $itemScores = array_column($items, 'score');
    $minScore = min($itemScores);
    $maxScore = max($itemScores);
    $avgScore = array_sum($itemScores) / count($itemScores);
    $groupSummary[$g] = [
        'min' => $minScore,
        'max' => $maxScore,
        'avg' => $avgScore,
    ];
endforeach;

$overallAvg = null;
if (!empty($groupSummary)) {
    $allAvgs = array_column($groupSummary, 'avg');
    $overallAvg = array_sum($allAvgs) / count($allAvgs);
}
?>
<div class="page-tools">
    <a class="btn btn--ghost" href="<?= e(url('/dean/observations')) ?>"><?= icon('arrow-left', 16) ?> Back to observations</a>
    <?php if ($canEdit ?? false): ?>
        <a class="btn btn--secondary" href="<?= e(url('/dean/observations/' . (int) ($observation['id'] ?? 0) . '/edit')) ?>"><?= icon('pencil', 16) ?> Edit observation</a>
    <?php endif; ?>
</div>

<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <div class="row">
                    <div class="col">
                        <h2><?= e($title) ?></h2>
                        <span class="badge badge--neutral"><?= e($observation['status'] ?? '—') ?></span>
                    </div>
                    <div class="col col--auto">
                        <div class="meta-list nowrap">
                            <li><span>Subject</span><strong><?= e($observation['subject_code'] ?? '') ?></strong></li>
                            <li><span>Section</span><strong><?= e($observation['section_name'] ?? '') ?></strong></li>
                            <li><span>Academic year</span><strong><?= e($observation['academic_year'] ?? '') ?></strong></li>
                            <li><span>Semester</span><strong><?= e(semester_abbr((string) ($observation['semester'] ?? ''))) ?></strong></li>
                            <li><span>Observation date</span><strong><?= e(fmt_date($observation['observation_date'] ?? '')) ?></strong></li>
                            <li><span>Lesson topic</span><strong><?= e($observation['lesson_topic'] ?? '') ?></strong></li>
                            <li><span>Observer</span><strong><?= e($observation['observer_name'] ?? '') ?></strong></li>
                            <li><span>Created</span><strong><?= e(fmt_datetime($observation['created_at'] ?? '')) ?></strong></li>
                            <li><span>Updated</span><strong><?= e(fmt_datetime($observation['updated_at'] ?? '')) ?></strong></li>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card__body">
                <div class="cof-form-block">
                    <?php foreach ($criteriaGrouped as $group => $items):
                        if (empty($items)) { continue; }
                        $summary = $groupSummary[$group] ?? null;
                    ?>
                        <div class="cof-form-group">
                            <div class="cof-form-group__head">
                                <span><?= e($group) ?></span>
                                <?php if ($summary): ?>
                                    <span class="cof-form-group__summary">
                                        <span class="muted small">Average <?= e(score($summary['avg'])) ?></span>
                                        <span class="muted small">Range <?= e(score($summary['min'])) ?>–<?= e(score($summary['max'])) ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <table class="table table--compact table--nofluid">
                                <thead>
                                    <tr>
                                        <th class="num">#</th>
                                        <th>Criterion</th>
                                        <th class="num">Score</th>
                                        <th class="num">Label</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="num"><?= (int) $item['number'] ?></td>
                                            <td><?= e($item['text']) ?></td>
                                            <td class="num"><?= e(score($item['score'])) ?></td>
                                            <td class="num"><?= e($item['label'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($groupSummary)): ?>
                    <div class="section-card section-card--static mt-3">
                        <div class="section-card__head"><h2>Overall averages</h2></div>
                        <div class="section-card__body">
                            <div class="stat-grid">
                                <?php if (!empty($groupSummary['Teaching Competence'])): ?>
                                    <div class="stat">
                                        <div class="stat__label">Teaching Competence average</div>
                                        <div class="stat__value"><?= e(score($groupSummary['Teaching Competence']['avg'])) ?></div>
                                        <div class="stat__meta">Range <?= e(score($groupSummary['Teaching Competence']['min'])) ?>–<?= e(score($groupSummary['Teaching Competence']['max'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($groupSummary['Personality'])): ?>
                                    <div class="stat">
                                        <div class="stat__label">Personality average</div>
                                        <div class="stat__value"><?= e(score($groupSummary['Personality']['avg'])) ?></div>
                                        <div class="stat__meta">Range <?= e(score($groupSummary['Personality']['min'])) ?>–<?= e(score($groupSummary['Personality']['max'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="stat stat--flat">
                                    <div class="stat__label">Overall average (all criteria)</div>
                                    <div class="stat__value"><?= e($overallAvg !== null ? score($overallAvg) : '—') ?></div>
                                    <div class="stat__meta"><?= count($criteriaGrouped) ?> groups</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Reading the form</h2></div>
            <div class="card__body">
                <p class="small muted">Each criterion is rated <?= e(score(1)) ?>–<?= e(score(5)) ?> against the observation period’s rating scale. Grouping is by competence type; teaching and personality are scored separately.</p>
                <p class="small">
                    <strong>Criterion score</strong> — the rating recorded for that criterion.<br>
                    <strong>Label</strong> — the verbal anchor matched to the score by the current rating labels.<br>
                    <strong>Group average</strong> — mean of the group’s criterion scores, rounded to two decimals.<br>
                    <strong>Overall average</strong> — mean of all group averages.
                </p>
                <?php if (!empty($criteriaGrouped)): ?>
                    <div class="tc-labels legend">
                        <div class="legend__row">
                            <span class="legend__label">Score range</span>
                            <span class="legend__cell"><?= e(score(1)) ?></span>
                            <span class="legend__divider">–</span>
                            <span class="legend__cell"><?= e(score(5)) ?></span>
                        </div>
                        <div class="legend__row legend__row--labels">
                            <span class="legend__label muted small">Labels</span>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="legend__cell"><?= e($labels[$i]) ?></span>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
