<?php
/** Dean analytics index. */

use App\Models\COFCriterion;

$labels = rating_labels();
?>
<div class="page-tools">
    <a class="btn btn--primary" href="<?= e(url('dean/analytics/export')) ?>"><?= icon('download', 16) ?> Export CSV</a>
</div>

<?php if (!empty($filters)): ?>
    <div class="callout callout--info">
        <strong>Filtered view.</strong> The analytics below respect the current filter set. Clear filters to see the full institution.
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat stat--maroon">
        <div class="stat__label">Published observations</div>
        <div class="stat__value"><?= (int) $summary['published'] ?></div>
        <div class="stat__meta">of <?= (int) $summary['total'] ?> total</div>
    </div>
    <div class="stat stat--gold">
        <div class="stat__label">Institutional overall</div>
        <div class="stat__value"><?= e(score($summary['avg_overall'])) ?></div>
        <div class="stat__meta">Teaching <?= e(score($summary['avg_teaching'])) ?> · Personality <?= e(score($summary['avg_personality'])) ?></div>
    </div>
    <div class="stat">
        <div class="stat__label">Faculty with results</div>
        <div class="stat__value"><?= (int) $summary['faculty_count'] ?></div>
        <div class="stat__meta"><?= count($byFaculty) ?> listed below</div>
    </div>
    <div class="stat">
        <div class="stat__label">Ready for publication</div>
        <div class="stat__value"><?= (int) $summary['pending'] ?></div>
        <div class="stat__meta"><?= (int) $summary['submitted'] ?> submitted · <?= (int) $summary['draft'] ?> drafts</div>
    </div>
</div>

<div class="grid grid--sidebar mt-3">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Faculty comparison</h2></div>
            <div class="card__body card__body--flush">
                <?php if ($byFaculty === []): ?>
                    <div class="table-empty"><strong>No published results yet</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Faculty</th>
                                    <th>Department</th>
                                    <th class="num">Observations</th>
                                    <th class="num">Teaching</th>
                                    <th class="num">Personality</th>
                                    <th class="num">Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byFaculty as $row): ?>
                                    <tr>
                                        <td><?= e($row['full_name']) ?></td>
                                        <td><?= e($row['department_code'] ?? '—') ?></td>
                                        <td class="num"><?= (int) $row['observation_count'] ?></td>
                                        <td class="num"><?= e(score($row['avg_teaching'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_personality'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_overall'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>By department</h2></div>
            <div class="card__body card__body--flush">
                <?php if ($byDepartment === []): ?>
                    <div class="table-empty"><strong>No department results yet</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="num">Observations</th>
                                    <th class="num">Faculty</th>
                                    <th class="num">Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byDepartment as $row): ?>
                                    <tr>
                                        <td><?= e($row['department_name']) ?></td>
                                        <td class="num"><?= (int) $row['observation_count'] ?></td>
                                        <td class="num"><?= (int) $row['faculty_count'] ?></td>
                                        <td class="num"><?= e(score($row['avg_overall'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>By period</h2></div>
            <div class="card__body card__body--flush">
                <?php if ($byPeriod === []): ?>
                    <div class="table-empty"><strong>No published periods yet</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th class="num">Observations</th>
                                    <th class="num">Teaching</th>
                                    <th class="num">Personality</th>
                                    <th class="num">Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byPeriod as $row): ?>
                                    <tr>
                                        <td><?= e($row['academic_year']) ?> · <?= e(semester_abbr((string) $row['semester'])) ?></td>
                                        <td class="num"><?= (int) $row['observation_count'] ?></td>
                                        <td class="num"><?= e(score($row['avg_teaching'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_personality'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_overall'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>By academic year</h2></div>
            <div class="card__body card__body--flush">
                <?php if ($byYear === []): ?>
                    <div class="table-empty"><strong>No yearly results yet</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Academic year</th>
                                    <th class="num">Observations</th>
                                    <th class="num">Teaching</th>
                                    <th class="num">Personality</th>
                                    <th class="num">Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byYear as $row): ?>
                                    <tr>
                                        <td><?= e($row['academic_year']) ?></td>
                                        <td class="num"><?= (int) $row['observation_count'] ?></td>
                                        <td class="num"><?= e(score($row['avg_teaching'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_personality'])) ?></td>
                                        <td class="num"><?= e(score($row['avg_overall'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="card mt-3">
    <div class="card__head"><h2>Workflow status</h2></div>
    <div class="card__body">
        <ul class="meta-list">
            <?php foreach (['Draft', 'Submitted', 'Published', 'Returned for Correction', 'Archived'] as $status): ?>
                <li>
                    <span><?= status_badge($status) ?></span>
                    <strong><?= (int) ($statusCounts[$status] ?? 0) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
