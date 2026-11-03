<?php
/** Dean faculty history report. */

$institution = $institution ?? (new \App\Services\ReportingService())->institution();
$labels = rating_labels();
?>
<div class="report-shell">
    <div class="report-shell__head">
        <div>
            <span class="eyebrow">Faculty history</span>
            <h1><?= e($pageTitle) ?></h1>
            <p class="muted small"><?= e($pageSubtitle) ?></p>
        </div>
        <div class="report-shell__tools">
            <a class="btn btn--ghost" href="<?= e(url('/dean/reports')) ?>"><?= icon('list', 16) ?> Reports list</a>
        </div>
    </div>

    <div class="report-body__header">
        <div class="report-body__info">
            <div>
                <h3><?= e($faculty['full_name']) ?></h3>
                <div class="muted small">
                    <?= e($faculty['employee_no'] ?? '') ?>
                    · <?= e($faculty['department_name'] ?? '—') ?>
                    · Member since <?= e(fmt_date($faculty['created_at'])) ?>
                </div>
            </div>
            <div class="report-body__scores" style="margin-top:14px">
                <div class="stat stat--flat">
                    <div class="stat__label">Published evaluations</div>
                    <div class="stat__value"><?= (int) $stats['total_published'] ?></div>
                </div>
                <div class="stat stat--flat">
                    <div class="stat__label">Overall average</div>
                    <div class="stat__value"><?= e(score($stats['avg_overall'])) ?></div>
                    <div class="stat__meta">Teaching <?= e(score($stats['avg_teaching'])) ?> · Personality <?= e(score($stats['avg_personality'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($trend)): ?>
        <section class="card">
            <div class="card__head"><h2>Published results over time</h2></div>
            <div class="card__body">
                <div class="chart-box"><canvas data-chart-type="line" data-chart-score data-chart-data="<?= e(json_encode([
                    'type' => 'line',
                    'data' => [
                        'labels' => array_column($trend, 'label'),
                        'datasets' => [
                            [
                                'label' => 'Teaching Competence',
                                'data' => array_column($trend, 'teaching'),
                                'borderColor' => '#7a2232',
                                'backgroundColor' => 'rgba(122,34,50,.08)',
                                'tension' => 0.3,
                                'fill' => false,
                                'pointRadius' => 4,
                            ],
                            [
                                'label' => 'Personality',
                                'data' => array_column($trend, 'personality'),
                                'borderColor' => '#b8913f',
                                'backgroundColor' => 'rgba(184,145,63,.08)',
                                'tension' => 0.3,
                                'fill' => false,
                                'pointRadius' => 4,
                            ],
                            [
                                'label' => 'Overall',
                                'data' => array_column($trend, 'overall'),
                                'borderColor' => '#172033',
                                'borderDash' => [5, 4],
                                'tension' => 0.3,
                                'fill' => false,
                                'pointRadius' => 0,
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="card__head"><h2>Published results over time</h2></div>
            <div class="card__body">
                <p class="muted">No published observations for this faculty member yet.</p>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="card__head"><h2>Published evaluations</h2></div>
        <div class="card__body card__body--flush">
            <?php if ($observations === []): ?>
                <div class="table-empty"><strong>No published evaluations on record</strong></div>
            <?php else: ?>
                <div class="table-wrap table-wrap--short">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Subject / topic</th>
                                <th>Date</th>
                                <th class="num">Teaching</th>
                                <th class="num">Personality</th>
                                <th class="num">Overall</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($observations as $row): ?>
                                <tr>
                                    <td>
                                        <span class="row-title"><?= e($row['subject_code']) ?> · <?= e($row['section_name']) ?></span>
                                        <span class="row-sub"><?= e(str_excerpt($row['lesson_topic'], 60)) ?></span>
                                    </td>
                                    <td class="nowrap"><?= e(fmt_date($row['observation_date'])) ?></td>
                                    <td class="num"><?= e(score($row['teaching_competence_avg'])) ?></td>
                                    <td class="num"><?= e(score($row['personality_avg'])) ?></td>
                                    <td class="num"><?= e(score($row['overall_avg'])) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/reports/observations/' . (int) $row['observation_id'])) ?>">Report</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
