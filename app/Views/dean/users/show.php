<?php
/** Dean user show. */
$labels = rating_labels();

$isSelf = $isSelf ?? false;
$canEdit = $canEdit ?? false;
$canEditUrl = $canEdit ? url('/dean/users/' . (int) $user['id'] . '/edit') : null;

$observations = $observations ?? [];

$teachingAvg = $stats['teaching_avg'] ?? null;
$personalityAvg = $stats['personality_avg'] ?? null;
$overallAvg = $stats['overall_avg'] ?? null;

$periodScores = $periodScores ?? [];

$costChartData = null;
if (count($periodScores) > 1) {
    $costChartData = [
        'type' => 'line',
        'data' => [
            'labels' => array_column($periodScores, 'label'),
            'datasets' => [
                [
                    'label' => 'Teaching Competence',
                    'data' => array_column($periodScores, 'teaching'),
                    'borderColor' => 'rgba(122,34,50,.9)',
                    'backgroundColor' => 'rgba(122,34,50,.12)',
                    'pointBackgroundColor' => 'rgba(122,34,50,.9)',
                    'pointRadius' => 4,
                    'fill' => false,
                ],
                [
                    'label' => 'Personality',
                    'data' => array_column($periodScores, 'personality'),
                    'borderColor' => 'rgba(200,150,80,.9)',
                    'backgroundColor' => 'rgba(200,150,80,.12)',
                    'pointBackgroundColor' => 'rgba(200,150,80,.9)',
                    'pointRadius' => 4,
                    'fill' => false,
                ],
                [
                    'label' => 'Overall',
                    'data' => array_column($periodScores, 'overall'),
                    'borderColor' => 'rgba(23,32,51,.9)',
                    'borderDash' => [5, 4],
                    'backgroundColor' => 'rgba(23,32,51,.12)',
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
        ],
    ];
}

$clusterOptions = $clusterOptions ?? [];

$autocompleteSubjectOptions = $autocompleteSubjectOptions ?? [];
$autocompleteSubjectText = '';
foreach ($autocompleteSubjectOptions as $opt) {
    if (empty($autocompleteSubjectText)) {
        $autocompleteSubjectText = $opt['text'];
        break;
    }
}
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2><?= e($user['full_name']) ?></h2>
                <div class="card__head__meta">
                    <span class="badge badge--neutral"><?= e($user['role']) ?></span>
                    <?php if (!empty($user['department_name'])): ?>
                        <span class="muted small"><?= e($user['department_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($user['employee_id'])): ?>
                        <span class="muted small">ID <?= e($user['employee_id']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card__body">
                <div class="meta-list">
                    <li><span>Email</span><strong class="truncate"><?= e($user['email']) ?></strong></li>
                    <?php if (!empty($user['employee_id'])): ?>
                        <li><span>Employee ID</span><strong><?= e($user['employee_id']) ?></strong></li>
                    <?php endif; ?>
                    <li><span>Role</span><strong><?= e($user['role']) ?></strong></li>
                    <?php if (!empty($user['department_name'])): ?>
                        <li><span>Department</span><strong><?= e($user['department_name']) ?></strong></li>
                    <?php endif; ?>
                    <li><span><?= e($user['is_active'] ? 'Active' : 'Inactive') ?></span><strong><?= e(status_badge($user['is_active'] ? 'Active' : 'Inactive')) ?></strong></li>
                    <?php if (!empty($user['on_leave'])): ?>
                        <li><span>On leave</span><strong class="text-warning"><?= icon('clock', 14) ?> Yes</strong></li>
                    <?php endif; ?>
                    <li><span>Created</span><strong><?= e(fmt_datetime($user['created_at'])) ?></strong></li>
                    <li><span>Updated</span><strong><?= e(fmt_datetime($user['updated_at'])) ?></strong></li>
                    <?php if (!empty($user['last_active'])): ?>
                        <li><span>Last active</span><strong><?= e(fmt_datetime($user['last_active'])) ?></strong></li>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($observations)): ?>
            <section class="card">
                <div class="card__head"><h2>Performance over time</h2><p class="small">Average COF scores by observation period.</p></div>
                <div class="card__body">
                    <div class="chart-box"><canvas data-chart-type="line" data-chart-score data-chart-data="<?= e(json_encode($costChartData, JSON_UNESCAPED_SLASHES)) ?>" style="height:260px"></canvas></div>
                    <p class="muted small mt-2">Based on the observation periods below.</p>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><h2>Average scores</h2></div>
            <div class="card__body">
                <div class="stat-grid stat-grid--wide mt-3">
                    <div class="stat stat--flat">
                        <div class="stat__label">Overall average</div>
                        <div class="stat__value"><?= $overallAvg !== null ? e(score($overallAvg)) : '—' ?></div>
                        <div class="stat__meta">of <?= e(score(5)) ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Teaching Competence</div>
                        <div class="stat__value"><?= $teachingAvg !== null ? e(score($teachingAvg)) : '—' ?></div>
                        <div class="stat__meta">across observations</div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Personality</div>
                        <div class="stat__value"><?= $personalityAvg !== null ? e(score($personalityAvg)) : '—' ?></div>
                        <div class="stat__meta">across observations</div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Observation periods</div>
                        <div class="stat__value"><?= count($periodScores) ?></div>
                        <div class="stat__meta">with published results</div>
                    </div>
                </div>

                <div class="stat-grid mt-3" style="grid-template-columns:1fr 1fr">
                    <div class="stat stat--flat">
                        <div class="stat__label">Published observations</div>
                        <div class="stat__value"><?= (int) $stats['published_count'] ?></div>
                        <div class="stat__meta">finalized only</div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Latest observation</div>
                        <div class="stat__value small"><?= $latestObservation ? e(fmt_date($latestObservation['observation_date'])) . ' — ' . e($latestObservation['subject_code']) . ' · ' . e($latestObservation['section_name']) : '—' ?></div>
                        <div class="stat__meta muted"><?= $latestObservation ? e(score($latestObservation['overall_avg'])) . ' overall' : '' ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($periodScores)): ?>
            <section class="card">
                <div class="card__head"><h2>Observations by period</h2></div>
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th class="num">Teaching</th>
                                    <th class="num">Personality</th>
                                    <th class="num">Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($periodScores as $period): ?>
                                    <tr>
                                        <td><?= e($period['label']) ?></td>
                                        <td class="num"><?= e(score($period['teaching'] ?? 0)) ?></td>
                                        <td class="num"><?= e(score($period['personality'] ?? 0)) ?></td>
                                        <td class="num"><?= e(score($period['overall'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Actions</h2></div>
            <div class="card__body">
                <?php if ($canEditUrl): ?>
                    <a class="btn btn--secondary" href="<?= e($canEditUrl) ?>"><?= icon('pencil', 16) ?> Edit user</a>
                <?php endif; ?>
                <a class="btn btn--ghost" href="<?= e(url('/dean/users')) ?>"><?= icon('list', 16) ?> All users</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Notes</h2></div>
            <div class="card__body">
                <?php if ($isSelf): ?>
                    <p class="small muted">You are viewing your own account.</p>
                <?php else: ?>
                    <p class="small muted">You are viewing another user's account.</p>
                <?php endif; ?>
                <p class="small">Scores are computed from finalized observation results and are shown only when published.</p>
            </div>
        </section>
    </div>
</div>
