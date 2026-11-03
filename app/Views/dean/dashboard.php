<?php
/**
 * Dean administrative overview.
 *
 * @var array $dean
 * @var array $summary
 * @var array $statusCounts
 * @var array $departments
 * @var array $periodTrend
 * @var array $recentObservations
 * @var int $pendingReview
 * @var array $pendingAssignments
 * @var int $pendingAssignmentCount
 * @var int $facultyTotal
 * @var int $activeClasses
 * @var int $activeQr
 * @var array $classStats
 * @var array $recordsByPeriod
 * @var array $recentAudit
 * @var array|null $currentPeriod
 * @var bool $cofVerified
 * @var array $cofStructure
 * @var int $unreadCount
 */

$periodChart = [
    'type' => 'line',
    'data' => [
        'labels' => array_column($periodTrend, 'label'),
        'datasets' => [
            [
                'label' => 'Teaching Competence',
                'data' => array_column($periodTrend, 'teaching'),
                'borderColor' => '#7a2232',
                'backgroundColor' => 'rgba(122,34,50,.10)',
                'tension' => .3,
                'fill' => true,
                'pointRadius' => 3,
            ],
            [
                'label' => 'Personality',
                'data' => array_column($periodTrend, 'personality'),
                'borderColor' => '#b8913f',
                'backgroundColor' => 'rgba(184,145,63,.10)',
                'tension' => .3,
                'fill' => true,
                'pointRadius' => 3,
            ],
            [
                'label' => 'Overall',
                'data' => array_column($periodTrend, 'overall'),
                'borderColor' => '#172033',
                'borderDash' => [5, 4],
                'tension' => .3,
                'pointRadius' => 0,
            ],
        ],
    ],
];

$departmentChart = [
    'type' => 'bar',
    'data' => [
        'labels' => array_column($departments, 'department_code'),
        'datasets' => [
            [
                'label' => 'Overall average',
                'data' => array_map(static fn (array $r): ?float => $r['avg_overall'] !== null ? (float) $r['avg_overall'] : null, $departments),
                'backgroundColor' => '#7a2232',
                'borderRadius' => 5,
                'maxBarThickness' => 40,
            ],
        ],
    ],
];
?>
<div class="page-tools" style="margin-bottom:16px">
    <a class="btn btn--primary" href="<?= e(url('/dean/observations/create')) ?>"><?= icon('plus', 16) ?> New observation</a>
    <a class="btn btn--secondary" href="<?= e(url('/dean/assignments')) ?>"><?= icon('checklist', 16) ?> Plan assignment</a>
    <a class="btn btn--secondary" href="<?= e(url('/dean/reports')) ?>"><?= icon('print', 16) ?> Reports</a>
    <?php if ($currentPeriod !== null): ?>
        <span class="badge badge--gold"><?= icon('calendar', 13) ?> Current: <?= e($currentPeriod['academic_year']) ?> · <?= e(semester_abbr((string) $currentPeriod['semester'])) ?></span>
    <?php endif; ?>
</div>

<?php if (!$cofVerified): ?>
    <div class="alert alert--warning" role="alert">
        <span class="alert__icon"><?= icon('alert', 18) ?></span>
        <div class="alert__body">
            <strong>The COF wording is provisional.</strong>
            The seeded 20 criteria (<?= (int) $cofStructure['teaching'] ?> Teaching Competence + <?= (int) $cofStructure['personality'] ?> Personality)
            must be checked against the official ACLC Classroom Observation Form (Version 1, Series 2012).
            <a href="<?= e(url('/dean/cof')) ?>">Review and verify the COF</a> before using results for official decisions.
        </div>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat stat--maroon">
        <div class="stat__label"><?= icon('clipboard', 14) ?> Published observations</div>
        <div class="stat__value"><?= (int) $summary['published'] ?></div>
        <div class="stat__meta">of <?= (int) $summary['total'] ?> total records</div>
    </div>
    <div class="stat stat--gold">
        <div class="stat__label"><?= icon('users', 14) ?> Faculty accounts</div>
        <div class="stat__value"><?= (int) $facultyTotal ?></div>
        <div class="stat__meta"><?= (int) $summary['faculty_count'] ?> with published results</div>
    </div>
    <div class="stat">
        <div class="stat__label"><?= icon('checklist', 14) ?> Awaiting action</div>
        <div class="stat__value"><?= (int) $pendingReview + (int) $pendingAssignmentCount ?></div>
        <div class="stat__meta"><?= (int) $pendingReview ?> submitted · <?= (int) $pendingAssignmentCount ?> assignments</div>
    </div>
    <div class="stat">
        <div class="stat__label"><?= icon('chart', 14) ?> Institutional average</div>
        <div class="stat__value"><?= e(score($summary['avg_overall'])) ?></div>
        <div class="stat__meta">Teaching <?= e(score($summary['avg_teaching'])) ?> · Personality <?= e(score($summary['avg_personality'])) ?></div>
    </div>
</div>

<div class="grid grid--sidebar mt-3">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Published performance by period</h2>
                    <p>Period averages from published COF results only.</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/analytics')) ?>">Open analytics <?= icon('arrow-right', 14) ?></a>
            </div>
            <div class="card__body">
                <?php if ($periodTrend === []): ?>
                    <div class="empty">
                        <span class="empty__icon"><?= icon('chart', 22) ?></span>
                        <strong>No published results yet</strong>
                        <p>Charts appear once at least one observation has been published to a faculty member.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-box"><canvas data-chart-type="line" data-chart-score data-chart-data="<?= e(json_encode($periodChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
                    <p class="chart-note">Scores use the official 1–5 COF scale. The chart is drawn by the vendored Chart.js library; all data is loaded from the database.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Recent observation activity</h2>
                    <p>Newest workflow changes across all statuses.</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/observations')) ?>">All observations</a>
            </div>
            <div class="card__body card__body--flush">
                <?php if ($recentObservations === []): ?>
                    <div class="table-empty"><strong>No observations recorded</strong>Create the first classroom observation to begin.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Faculty / class</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="num">Overall</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentObservations as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="row-title"><?= e($row['faculty_name']) ?></span>
                                            <span class="row-sub"><?= e($row['subject_code']) ?> · <?= e($row['section_name']) ?></span>
                                        </td>
                                        <td class="nowrap"><?= e(fmt_date($row['observation_date'])) ?></td>
                                        <td><?= status_badge($row['status']) ?></td>
                                        <td class="num"><?= e(score($row['overall_avg'])) ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/observations/' . (int) $row['observation_id'])) ?>">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="grid grid--2">
            <section class="card">
                <div class="card__head"><h2>Department averages</h2></div>
                <div class="card__body">
                    <?php if ($departments === []): ?>
                        <p class="muted small mb-0">No department has published results yet.</p>
                    <?php else: ?>
                        <div class="chart-box chart-box--short"><canvas data-chart-type="bar" data-chart-score data-chart-data="<?= e(json_encode($departmentChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
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
        </div>
    </div>

    <div class="stack">
        <section class="card card--maroon">
            <div class="card__body">
                <span class="eyebrow eyebrow--gold">Class records</span>
                <h2 class="mt-1">Academic performance</h2>
                <p class="small">Kept separate from COF observation scores, as required.</p>
                <div class="stat-grid" style="grid-template-columns:1fr 1fr;gap:10px">
                    <div class="stat stat--flat">
                        <div class="stat__label">Records</div>
                        <div class="stat__value"><?= (int) $classStats['record_count'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Passing rate</div>
                        <div class="stat__value"><?= e(score($classStats['passing_rate'], 1)) ?>%</div>
                    </div>
                </div>
                <div class="meta-list mt-2">
                    <li><span>Students tallied</span><strong><?= (int) $classStats['total_students'] ?></strong></li>
                    <li><span>Passed</span><strong><?= (int) $classStats['passed_students'] ?></strong></li>
                    <li><span>Failed</span><strong><?= (int) $classStats['failed_students'] ?></strong></li>
                    <li><span>Average grade</span><strong><?= e(score($classStats['average_grade'])) ?></strong></li>
                </div>
                <a class="btn btn--gold btn--block mt-2" href="<?= e(url('/dean/class-records')) ?>">Manage class records</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Pending assignments</h2><p>Planned observations not yet completed.</p></div>
                <span class="badge badge--warning"><?= (int) $pendingAssignmentCount ?></span>
            </div>
            <div class="card__body">
                <?php if ($pendingAssignments === []): ?>
                    <p class="muted small mb-0">No pending observation assignments.</p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($pendingAssignments as $assignment): ?>
                            <li>
                                <div class="timeline__title"><?= e($assignment['faculty_name']) ?></div>
                                <div class="timeline__meta"><?= e($assignment['subject_code']) ?> · <?= e($assignment['section_name']) ?> · due <?= e(fmt_date($assignment['due_date'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn--secondary btn--block mt-1" href="<?= e(url('/dean/assignments')) ?>">Open assignments</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Recent audit events</h2></div>
            <div class="card__body">
                <?php if ($recentAudit === []): ?>
                    <p class="muted small mb-0">No administrative activity logged yet.</p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($recentAudit as $event): ?>
                            <li>
                                <div class="timeline__title"><?= e(str_replace(['.', '_'], [' · ', ' '], (string) $event['action'])) ?></div>
                                <div class="timeline__meta"><?= e($event['user_name'] ?? 'System') ?> · <?= e(fmt_datetime($event['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn--ghost btn--block mt-1" href="<?= e(url('/dean/audit')) ?>">View audit trail</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Quick facts</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Active classes</span><strong><?= (int) $activeClasses ?></strong></li>
                    <li><span>Active QR credentials</span><strong><?= (int) $activeQr ?></strong></li>
                    <li><span>Unread notifications</span><strong><?= (int) $unreadCount ?></strong></li>
                    <li><span>COF structure</span><strong><?= $cofVerified ? 'Verified' : 'Provisional' ?></strong></li>
                </ul>
            </div>
        </section>
    </div>
</div>
