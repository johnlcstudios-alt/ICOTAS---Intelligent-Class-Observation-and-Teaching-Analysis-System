<?php
/**
 * @var array|null $prediction
 * @var array $periods
 * @var array $config
 * @var bool $demo
 */
$metrics = $prediction['supporting_metrics'] ?? [];
$thresholds = array_merge($config, is_array($prediction['threshold_config'] ?? null) ? $prediction['threshold_config'] : []);
$risk = $prediction['risk_level'] ?? null;
$hasSufficient = $prediction && ($prediction['data_status'] ?? '') === 'sufficient';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2>My trend indicator</h2>
        <p>A transparent, rule-based estimate computed from your historical period averages. It is not a validated machine-learning forecast.</p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= url('instructor/predictions/generate') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> <?= $prediction ? 'Recalculate indicator' : 'Generate indicator' ?></button>
        </form>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> This indicator is generated from demo records and is not a real teaching assessment.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert"><i class="bi bi-info-circle"></i><span><strong>How to read this:</strong> the indicator compares recent academic-period averages and counts negative written feedback. It is designed to prompt reflection and professional conversation — never automatic personnel action. Insufficient data produces no estimate.</span></div>

<?php if (!$prediction): ?>
    <div class="card mb-4">
        <div class="empty-state"><i class="bi bi-activity"></i>
            <p class="mt-2 mb-2">No trend indicator has been generated yet.</p>
            <form method="post" action="<?= url('instructor/predictions/generate') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-activity"></i> Generate my indicator</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Current indicator</strong>
                    <span class="ms-auto"><?= status_badge($risk) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="report-summary" style="margin:0">
                                <div>
                                    <dt>Current period average</dt>
                                    <dd><?= e(format_rating($prediction['current_average'])) ?></dd>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="report-summary" style="margin:0">
                                <div>
                                    <dt>Weighted estimate</dt>
                                    <dd><?= e(format_rating($prediction['forecast_average'])) ?></dd>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mb-2"><?= e($prediction['explanation']) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge badge-soft"><?= (int) $prediction['record_count'] ?> period(s) used</span>
                        <span class="badge badge-soft"><?= (int) $prediction['response_count'] ?> response(s)</span>
                        <span class="badge badge-soft">Slope <?= $prediction['slope'] !== null ? e(number_format((float) $prediction['slope'], 3)) : '—' ?></span>
                        <span class="badge badge-soft">Generated <?= e(format_datetime($prediction['generated_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header"><strong>Data sufficiency</strong></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge <?= $hasSufficient ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucwords(str_replace('_', ' ', (string) $prediction['data_status']))) ?></span>
                        <span class="small text-muted"><?= $hasSufficient ? 'Estimate available' : 'Estimate withheld' ?></span>
                    </div>
                    <ul class="small mb-0 ps-3">
                        <li class="mb-1">Minimum responses: <strong><?= (int) $thresholds['min_responses'] ?></strong></li>
                        <li class="mb-1">Minimum periods: <strong><?= (int) $thresholds['min_periods'] ?></strong></li>
                        <li class="mb-1">Window: last <strong><?= (int) $thresholds['window_periods'] ?></strong> period(s)</li>
                        <li class="mb-1">Declining slope threshold: <strong><?= e(number_format((float) $thresholds['declining_slope'], 3)) ?></strong></li>
                        <li>Negative feedback threshold: <strong><?= e(number_format((float) $thresholds['negative_ratio'] * 100, 1)) ?>%</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($metrics['lowest_categories'])): ?>
        <div class="card mb-4">
            <div class="card-header"><strong>Lowest category averages</strong><span class="text-muted small">Inputs to advisory recommendations</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Category</th><th class="text-center">Average</th><th class="text-center">Ratings</th></tr></thead>
                        <tbody>
                        <?php foreach ($metrics['lowest_categories'] as $category): ?>
                            <tr>
                                <td><?= e($category['category']) ?></td>
                                <td class="text-center"><?= e(format_rating($category['average'])) ?></td>
                                <td class="text-center"><?= (int) $category['response_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><strong>Comparable academic periods</strong><span class="text-muted small">Actual averages used for the trend</span></div>
    <div class="card-body p-0">
        <?php if (empty($periods)): ?>
            <div class="empty-state"><i class="bi bi-calendar3"></i>No completed evaluation periods with numeric ratings are available yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Academic year</th><th>Semester</th><th class="text-center">Average</th><th class="text-center">Responses</th><th class="text-center">Sessions</th></tr></thead>
                    <tbody>
                    <?php foreach ($periods as $period): ?>
                        <tr>
                            <td><?= e($period['academic_year']) ?></td>
                            <td><?= e($period['semester']) ?></td>
                            <td class="text-center"><strong><?= e(format_rating($period['average'])) ?></strong></td>
                            <td class="text-center"><?= (int) $period['response_count'] ?></td>
                            <td class="text-center"><?= (int) $period['session_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Method and limitations</strong></div>
    <div class="card-body">
        <p class="small mb-2">The estimate uses a weighted moving average of recent academic-period means plus a least-squares linear slope. A declining slope, two or more consecutive declines, or a high negative-comment ratio can classify the indicator as needing attention. The calculation is deterministic and uses only recorded aggregates.</p>
        <ul class="small mb-0 ps-3">
            <li class="mb-1">Not a validated machine-learning model; no accuracy claim is made.</li>
            <li class="mb-1">Small numbers of responses or periods are explicitly treated as insufficient.</li>
            <li class="mb-1">Sentiment is rule-based and can misread sarcasm or context.</li>
            <li class="mb-1">The indicator does not adjust for course difficulty, class size or response bias.</li>
            <li>It must not be used as the sole basis for employment or disciplinary decisions.</li>
        </ul>
    </div>
</div>
