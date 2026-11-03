<?php
/** Dean observation summary report. */

use App\Models\COFCriterion;

$labels = rating_labels();
?>
<div class="report-shell">
    <div class="report-shell__head">
        <div>
            <span class="eyebrow">Printable report</span>
            <h1><?= e($pageTitle) ?></h1>
            <p class="muted small"><?= e($pageSubtitle) ?></p>
        </div>
        <div class="report-shell__tools">
            <a class="btn btn--ghost" href="<?= e(url('/dean/reports')) ?>"><?= icon('list', 16) ?> Reports list</a>
            <a class="btn btn--primary" href="<?= e(url('/dean/reports/observations/export')) ?>"><?= icon('download', 16) ?> Export CSV</a>
        </div>
    </div>

    <?php if (!empty($filters)): ?>
        <div class="report-shell__filters">
            <div><strong>Filters applied:</strong></div>
            <ul class="meta-list muted small">
                <?php foreach (['faculty_id', 'department_id', 'period_id', 'academic_year', 'semester', 'search'] as $key): ?>
                    <?php if (!empty($filters[$key])): ?>
                        <li><?= e(str_replace('_', ' ', $key)) ?>: <?= e($filters[$key]) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/reports/observations')) ?>">Clear filters</a>
        </div>
    <?php endif; ?>

    <div class="report-shell__stats">
        <div class="stat stat--flat">
            <div class="stat__label">Published observations</div>
            <div class="stat__value"><?= (int) $summary['published'] ?></div>
        </div>
        <div class="stat stat--flat">
            <div class="stat__label">Institutional overall</div>
            <div class="stat__value"><?= e(score($summary['avg_overall'])) ?></div>
        </div>
        <div class="stat stat--flat">
            <div class="stat__label">Departments</div>
            <div class="stat__value"><?= count($byDepartment) ?></div>
        </div>
        <div class="stat stat--flat">
            <div class="stat__label">Periods</div>
            <div class="stat__value"><?= count($byPeriod) ?></div>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <div>
                <h2>Published observation results</h2>
                <p class="small">Every row below is a published classroom observation.</p>
            </div>
            <div class="report-shell__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/reports/observations/export')) ?>"><?= icon('download', 16) ?> CSV</a>
            </div>
        </div>
        <div class="card__body card__body--flush">
            <?php if ($rows === []): ?>
                <div class="table-empty"><strong>No published observations yet</strong></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Subject / topic</th>
                                <th>Date</th>
                                <th>Department</th>
                                <th class="num">Teaching</th>
                                <th class="num">Personality</th>
                                <th class="num">Overall</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="row-title"><?= e($row['faculty_name']) ?></span>
                                        <span class="row-sub"><?= e($row['employee_no'] ?? '') ?></span>
                                    </td>
                                    <td>
                                        <span class="row-title"><?= e($row['subject_code']) ?> · <?= e($row['section_name']) ?></span>
                                        <span class="row-sub"><?= e(str_excerpt($row['lesson_topic'], 60)) ?></span>
                                    </td>
                                    <td class="nowrap"><?= e(fmt_date($row['observation_date'])) ?></td>
                                    <td><?= e($row['department_name']) ?></td>
                                    <td class="num"><?= e(score($row['teaching_competence_avg'])) ?></td>
                                    <td class="num"><?= e(score($row['personality_avg'])) ?></td>
                                    <td class="num"><?= e(score($row['overall_avg'])) ?></td>
                                    <td class="text-right"><a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/reports/observations/' . (int) $row['observation_id'])) ?>">Report</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($pagination['total'] > 0): ?>
        <div class="report-shell__pagination">
            <span class="muted small"><?= (int) $pagination['offset'] + 1 ?>–<?= min((int) $pagination['offset'] + (int) $pagination['per_page'], (int) $pagination['total']) ?> of <?= (int) $pagination['total'] ?></span>
            <?php if ((int) $pagination['page'] > 1): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/reports/observations?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Previous</a>
            <?php endif; ?>
            <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/reports/observations?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
