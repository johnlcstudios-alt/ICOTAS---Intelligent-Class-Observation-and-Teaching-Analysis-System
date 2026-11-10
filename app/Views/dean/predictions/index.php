<?php
/**
 * @var array $config
 * @var array $predictions
 * @var array $instructors
 * @var bool $demo
 */
$latestAt = null;
foreach ($predictions as $prediction) {
    if ($latestAt === null || strtotime((string) $prediction['generated_at']) > strtotime((string) $latestAt)) {
        $latestAt = $prediction['generated_at'];
    }
}
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Transparent trend indicators</span>
        <h2>Predictive analytics</h2>
        <p>Rule-based trend indicators built from historical period averages. Every result states its data sufficiency, method and thresholds. No validated machine-learning accuracy is claimed.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/recommendations') ?>"><i class="bi bi-lightbulb"></i> Recommendations</a>
        <form method="post" action="<?= url('dean/predictions/generate') ?>" data-confirm="Refresh trend indicators for all instructors and regenerate linked recommendations?">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Generate all</button>
        </form>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> Indicators below are computed from generated records and are not institutional predictions.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert">
    <i class="bi bi-info-circle"></i>
    <span><strong>Interpretation rule.</strong> These are descriptive forecasts based on a weighted moving average and least-squares slope. They are not machine-learning predictions, do not establish causality, and must not be used as the sole basis for personnel decisions.</span>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-people"></i></span>
        <div class="stat-label">Indicators available</div>
        <div class="stat-value"><?= count($predictions) ?></div>
        <div class="stat-note">Latest result per instructor</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div class="stat-label">Needs attention</div>
        <div class="stat-value"><?= count(array_filter($predictions, static fn ($row) => $row['risk_level'] === 'needs_attention')) ?></div>
        <div class="stat-note">Threshold-triggered indicator</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
        <div class="stat-label">Improving</div>
        <div class="stat-value"><?= count(array_filter($predictions, static fn ($row) => $row['risk_level'] === 'improving')) ?></div>
        <div class="stat-note">Positive slope or forecast change</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-database-check"></i></span>
        <div class="stat-label">Sufficient data</div>
        <div class="stat-value"><?= count(array_filter($predictions, static fn ($row) => $row['data_status'] === 'sufficient')) ?></div>
        <div class="stat-note">Meets period and response minimums</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><strong>Generate for one instructor</strong><span class="text-muted small">Creates a new indicator and linked recommendations</span></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/predictions/generate') ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-8">
                        <label class="form-label" for="predictionInstructor">Instructor</label>
                        <select class="form-select" id="predictionInstructor" name="instructor_id" required>
                            <option value="">— Select instructor —</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= (int) $instructor['id'] ?>"><?= e(full_name($instructor)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-activity"></i> Generate indicator</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><strong>Configuration</strong><span class="text-muted small">From Settings</span></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1"><span>Minimum responses</span><strong><?= (int) $config['min_responses'] ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span>Minimum periods</span><strong><?= (int) $config['min_periods'] ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span>Window periods</span><strong><?= (int) $config['window_periods'] ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span>Improving slope</span><strong>≥ <?= e(number_format((float) $config['improving_slope'], 2)) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span>Declining slope</span><strong>≤ <?= e(number_format((float) $config['declining_slope'], 2)) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Negative-comment ratio</span><strong>≥ <?= e(number_format((float) $config['negative_ratio'] * 100, 0)) ?>%</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="section-title">
    <h3>Latest indicators</h3>
    <span class="text-muted small"><?= $latestAt ? 'Newest generated ' . e(time_ago($latestAt)) : 'Not yet generated' ?></span>
</div>

<?php if (empty($predictions)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-activity"></i>
            <h3>No trend indicators yet</h3>
            <p class="text-muted">Generate indicators after at least two academic periods contain completed evaluation data.</p>
            <form method="post" action="<?= url('dean/predictions/generate') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Generate all indicators</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($predictions as $prediction): ?>
            <?php
            $metrics = json_decode((string) ($prediction['supporting_metrics'] ?? '{}'), true) ?: [];
            $periods = $metrics['period_averages'] ?? [];
            $riskLabel = $prediction['risk_level'];
            $riskBadge = $riskLabel === 'needs_attention' ? 'danger' : ($riskLabel === 'improving' ? 'success' : ($riskLabel === 'stable' ? 'info' : 'secondary'));
            ?>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <strong><?= e(full_name($prediction)) ?></strong>
                            <div class="text-muted small">Generated <?= e(format_datetime($prediction['generated_at'])) ?></div>
                        </div>
                        <span class="badge badge-<?= e($riskBadge) ?>"><?= e(ucwords(str_replace('_', ' ', $riskLabel))) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-tiny text-uppercase text-muted">Current</div>
                                    <div class="fs-5 fw-semibold"><?= e(format_rating($prediction['current_average'])) ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-tiny text-uppercase text-muted">Forecast</div>
                                    <div class="fs-5 fw-semibold"><?= e(format_rating($prediction['forecast_average'])) ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-tiny text-uppercase text-muted">Slope / period</div>
                                    <div class="fs-5 fw-semibold"><?= e($prediction['slope'] !== null ? number_format((float) $prediction['slope'], 3) : '—') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mb-2">
                            <span class="badge <?= $prediction['data_status'] === 'sufficient' ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $prediction['data_status']))) ?></span>
                            <span class="ms-2"><?= (int) $prediction['record_count'] ?> period(s)</span>
                            <span class="ms-2"><?= (int) $prediction['response_count'] ?> response(s)</span>
                        </div>
                        <p class="small mb-2"><?= e($prediction['explanation']) ?></p>
                        <div class="text-tiny text-muted mb-0"><i class="bi bi-gear"></i> <?= e($prediction['method']) ?></div>

                        <?php if (!empty($periods)): ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Period</th><th class="text-center">Average</th><th class="text-center">Responses</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($periods as $period): ?>
                                        <tr>
                                            <td class="small"><?= e($period['academic_year'] ?? '') ?> · <?= e($period['semester'] ?? '') ?></td>
                                            <td class="text-center"><?= e(format_rating($period['average'] ?? null)) ?></td>
                                            <td class="text-center"><?= (int) ($period['response_count'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($metrics['lowest_categories'])): ?>
                            <div class="mt-3">
                                <div class="text-tiny text-uppercase text-muted mb-1">Lowest categories</div>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($metrics['lowest_categories'] as $category): ?>
                                        <span class="badge badge-soft"><?= e($category['category']) ?> · <?= e(format_rating($category['average'] ?? null)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($metrics['negative_ratio'])): ?>
                            <div class="text-tiny text-muted mt-2">Negative written feedback ratio: <?= e(format_percent((float) $metrics['negative_ratio'] * 100)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mt-4">
    <div class="card-header"><strong>Method and safeguards</strong></div>
    <div class="card-body small text-muted">
        <div class="row g-3">
            <div class="col-md-4">
                <h4 class="h6 text-body">Calculation</h4>
                <p class="mb-0">Period averages are ordered chronologically. A weighted moving average of up to <?= (int) $config['window_periods'] ?> recent periods produces the forecast. A least-squares slope describes direction. Consecutive declines and the negative-comment ratio can trigger a needs-attention flag.</p>
            </div>
            <div class="col-md-4">
                <h4 class="h6 text-body">Data sufficiency</h4>
                <p class="mb-0">At least <?= (int) $config['min_periods'] ?> comparable periods and <?= (int) $config['min_responses'] ?> responses are required. When these are not met, the system records an <em>insufficient data</em> result and does not infer a risk level.</p>
            </div>
            <div class="col-md-4">
                <h4 class="h6 text-body">Limitations</h4>
                <p class="mb-0">Historical patterns may not continue. Course difficulty, class size, instrument changes and self-selection can influence ratings. Indicators are screening aids for supportive review, not proof of teaching quality or validated predictive accuracy.</p>
            </div>
        </div>
    </div>
</div>
