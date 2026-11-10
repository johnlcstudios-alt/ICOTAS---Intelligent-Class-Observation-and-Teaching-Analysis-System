<?php
/**
 * @var array $data
 * @var array|null $prediction
 * @var bool $demo
 * @var int $unread
 */
use App\Core\Auth;

$categories = $data['categories'] ?? [];
$sentiment = $data['sentiment'] ?? [];
$themes = $data['themes'] ?? [];
$trend = $data['trend'] ?? [];
$semesterTrend = $data['semester_trend'] ?? [];
$distribution = $data['rating_distribution'] ?? [];
$sessions = $data['sessions'] ?? [];
$recommendations = $data['recommendations'] ?? [];
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);

$sentimentTotal = array_sum(array_map(static fn ($row) => (int) $row['count'], $sentiment));
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $categories);
$semesterLabels = array_map(static fn ($row) => $row['academic_year'] . ' · ' . $row['semester'], $semesterTrend);
$semesterValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $semesterTrend);
$trendLabels = array_map(static fn ($row) => $row['session_code'], array_slice($trend, -10));
$trendValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, array_slice($trend, -10));
$sentimentLabels = array_map(static fn ($row) => ucfirst($row['label']), $sentiment);
$sentimentValues = array_map(static fn ($row) => (int) $row['count'], $sentiment);
$distributionLabels = array_map(static fn ($row) => number_format((float) $row['rating'], 1), $distribution);
$distributionValues = array_map(static fn ($row) => (int) $row['count'], $distribution);
$sessionPreview = array_slice($sessions, 0, 6);
$risk = $prediction['risk_level'] ?? null;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2>Welcome, <?= e(Auth::user()['first_name'] ?? 'Instructor') ?></h2>
        <p>Your teaching evaluation summary. Only you and authorized academic administrators can view these aggregates; student identities are never shown.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('instructor/analytics') ?>"><i class="bi bi-graph-up"></i> Full analytics</a>
        <a class="btn btn-outline-secondary" href="<?= url('instructor/reports') ?>"><i class="bi bi-file-earmark-text"></i> My reports</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> These results are generated for testing and are not real student feedback.</span></div>
<?php endif; ?>

<div class="hero-panel mb-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow">Anonymous student evaluation</span>
            <h2>Mean rating <?= e(format_rating($data['average'])) ?> / 5.00</h2>
            <p class="mb-2">Based on <?= (int) $data['rating_count'] ?> numeric ratings from <?= (int) $data['responses'] ?> anonymous responses across completed sessions.</p>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge badge-soft"><?= (int) $data['completed_sessions'] ?> completed session(s)</span>
                <span class="badge badge-soft"><?= (int) $data['open_sessions'] ?> open</span>
                <span class="badge badge-soft"><?= (int) $data['upcoming_sessions'] ?> scheduled</span>
                <span class="badge badge-soft"><?= e(format_percent($data['participation'])) ?> participation</span>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="hero-metric"><?= e(format_rating($data['average'])) ?><span class="fs-6 text-muted"> / 5</span></div>
            <div class="hero-sub">Descriptive mean · normalized ratings</div>
            <div class="rating-bar mt-3"><span style="width: <?= max(0, min(100, (float) $data['average'] / 5 * 100)) ?>%"></span></div>
        </div>
    </div>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-inbox"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $data['responses'] ?></div>
        <div class="stat-note"><?= (int) $data['submitted'] ?> of <?= (int) $data['eligible'] ?> eligible</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-check"></i></span>
        <div class="stat-label">Participation</div>
        <div class="stat-value"><?= e(format_percent($data['participation'])) ?></div>
        <div class="stat-note">Across your evaluation sessions</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-chat-square-text"></i></span>
        <div class="stat-label">Written comments</div>
        <div class="stat-value"><?= $sentimentTotal ?></div>
        <div class="stat-note">Rule-based sentiment screening</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-activity"></i></span>
        <div class="stat-label">Trend indicator</div>
        <div class="stat-value fs-5"><?= e($risk ? ucwords(str_replace('_', ' ', $risk)) : 'Not generated') ?></div>
        <div class="stat-note"><?= $prediction ? 'Rule-based estimate' : 'Generate from the trend page' ?></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Category averages</strong><span class="text-muted small">Across your sessions</span></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No numeric evaluation data yet.</div>
                <?php else: ?>
                    <div class="chart-box mb-3"><canvas id="instructorCategoryChart" data-chart="bar" aria-label="Teaching category averages"></canvas></div>
                    <?php foreach ($categories as $category): ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom">
                            <span><?= e($category['category']) ?></span>
                            <span><strong class="<?= e('rating-tone-' . rating_tone($category['average'])) ?>"><?= e(format_rating($category['average'])) ?></strong> <span class="text-muted">· n=<?= (int) $category['response_count'] ?></span></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><strong>Comment sentiment</strong><span class="badge badge-soft ms-auto">AI-assisted</span></div>
            <div class="card-body">
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No written feedback analyzed yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorSentimentChart" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">Mixed labels identify comments containing both positive and negative evidence. This is a screening signal, not a definitive judgment.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><strong>Recurring themes</strong></div>
            <div class="card-body">
                <?php if (empty($themes)): ?>
                    <p class="text-muted small mb-0">No recurring themes detected in written feedback.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (array_slice($themes, 0, 12) as $theme): ?>
                            <span class="badge <?= $theme['polarity'] === 'negative' ? 'badge-danger' : ($theme['polarity'] === 'positive' ? 'badge-success' : 'badge-secondary') ?>"><?= e($theme['theme']) ?> · <?= (int) $theme['count'] ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Recent session trend</strong><span class="text-muted small">Mean rating per session</span></div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="empty-state"><i class="bi bi-activity"></i>No completed session trend yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorTrendChart" data-chart="line" aria-label="Mean rating by session"></canvas></div>
                    <p class="data-note mt-2 mb-0">Session labels are QR session codes. Small sessions should not be used for high-stakes conclusions.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Rating distribution</strong><span class="text-muted small">All numeric answers</span></div>
            <div class="card-body">
                <?php if (empty($distribution)): ?>
                    <div class="empty-state"><i class="bi bi-distribute-vertical"></i>No ratings yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorDistributionChart" data-chart="bar" aria-label="Rating distribution"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><strong>My evaluation sessions</strong><a class="ms-auto btn btn-sm btn-outline-primary" href="<?= url('instructor/evaluations') ?>">View all</a></div>
            <div class="card-body p-0">
                <?php if (empty($sessionPreview)): ?>
                    <div class="empty-state"><i class="bi bi-clipboard-data"></i>No evaluation sessions are assigned to you yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Session</th><th>Class</th><th>Opened</th><th>Status</th><th class="text-end">Results</th></tr></thead>
                            <tbody>
                            <?php foreach ($sessionPreview as $session): ?>
                                <tr>
                                    <td><code><?= e($session['session_code']) ?></code></td>
                                    <td><?= e($session['course_code']) ?><div class="text-tiny text-muted"><?= e($session['section_code']) ?></div></td>
                                    <td class="text-tiny"><?= e(format_date($session['opens_at'])) ?></td>
                                    <td><?= status_badge($session['effective_status'] ?? $session['status']) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('instructor/evaluations/' . (int) $session['id']) ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong>Recommended next steps</strong><a class="ms-auto btn btn-sm btn-outline-primary" href="<?= url('instructor/recommendations') ?>">All</a></div>
            <div class="card-body">
                <?php if (empty($recommendations)): ?>
                    <p class="text-muted small mb-0">No open recommendations. Generate your trend indicator to refresh evidence-linked suggestions.</p>
                <?php else: ?>
                    <?php foreach (array_slice($recommendations, 0, 3) as $recommendation): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between gap-2">
                                <strong class="small"><?= e($recommendation['theme']) ?></strong>
                                <span class="badge <?= $recommendation['priority'] === 'high' ? 'badge-danger' : 'badge-warning' ?>"><?= e(ucfirst($recommendation['priority'])) ?></span>
                            </div>
                            <p class="small mb-1"><?= e(str_limit($recommendation['recommendation'], 150)) ?></p>
                            <div class="text-tiny text-muted"><?= e($recommendation['supporting_metric']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="instructorCategoryChart_data"><?= json_encode(['labels' => $categoryLabels, 'values' => $categoryValues, 'label' => 'Category mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorSentimentChart_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorTrendChart_data"><?= json_encode(['labels' => $trendLabels, 'values' => $trendValues, 'label' => 'Session mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorDistributionChart_data"><?= json_encode(['labels' => $distributionLabels, 'values' => $distributionValues, 'label' => 'Answers'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
