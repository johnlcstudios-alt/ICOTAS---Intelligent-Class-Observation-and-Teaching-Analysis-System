<?php
/**
 * @var array $data
 * @var bool $demo
 */
$categories = $data['categories'] ?? [];
$sentiment = $data['sentiment'] ?? [];
$themes = $data['themes'] ?? [];
$trend = $data['trend'] ?? [];
$semesterTrend = $data['semester_trend'] ?? [];
$distribution = $data['rating_distribution'] ?? [];
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);
$sentimentTotal = array_sum(array_map(static fn ($row) => (int) $row['count'], $sentiment));
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $categories);
$sentimentLabels = array_map(static fn ($row) => ucfirst($row['label']), $sentiment);
$sentimentValues = array_map(static fn ($row) => (int) $row['count'], $sentiment);
$semesterLabels = array_map(static fn ($row) => $row['academic_year'] . ' · ' . $row['semester'], $semesterTrend);
$semesterValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $semesterTrend);
$trendLabels = array_map(static fn ($row) => $row['session_code'], $trend);
$trendValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $trend);
$distributionLabels = array_map(static fn ($row) => number_format((float) $row['rating'], 1), $distribution);
$distributionValues = array_map(static fn ($row) => (int) $row['count'], $distribution);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2>My teaching analytics</h2>
        <p>Descriptive analysis of anonymous student evaluations for your classes only. Filters are not available here; comparative reports use the <a href="<?= url('instructor/reports') ?>">report builder</a>.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('instructor/reports') ?>"><i class="bi bi-file-earmark-text"></i> My reports</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> These analytics are for interface testing only.</span></div>
<?php endif; ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-star-half"></i></span>
        <div class="stat-label">Mean rating</div>
        <div class="stat-value"><?= e(format_rating($data['average'])) ?></div>
        <div class="stat-note"><?= (int) $data['rating_count'] ?> numeric ratings</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-inbox"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $data['responses'] ?></div>
        <div class="stat-note"><?= (int) $data['completed_sessions'] ?> completed session(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-check"></i></span>
        <div class="stat-label">Participation</div>
        <div class="stat-value"><?= e(format_percent($data['participation'])) ?></div>
        <div class="stat-note"><?= (int) $data['submitted'] ?> of <?= (int) $data['eligible'] ?> eligible</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-chat-square-text"></i></span>
        <div class="stat-label">Analyzed comments</div>
        <div class="stat-value"><?= $sentimentTotal ?></div>
        <div class="stat-note">Rule-based sentiment and themes</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Rating by academic period</strong></div>
            <div class="card-body">
                <?php if (empty($semesterTrend)): ?>
                    <div class="empty-state"><i class="bi bi-graph-up"></i>No completed period data yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorSemesterChart" data-chart="line" aria-label="Mean rating by academic period"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Rating distribution</strong></div>
            <div class="card-body">
                <?php if (empty($distribution)): ?>
                    <div class="empty-state"><i class="bi bi-distribute-vertical"></i>No ratings available.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorAnalyticsDistribution" data-chart="bar" aria-label="Rating distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">A concentration at the upper end is common in student evaluation instruments and does not by itself prove teaching effectiveness.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Category averages</strong><span class="text-muted small">5-point scale</span></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No category ratings yet.</div>
                <?php else: ?>
                    <div class="chart-box mb-3"><canvas id="instructorAnalyticsCategory" data-chart="bar" aria-label="Category averages"></canvas></div>
                    <?php foreach ($categories as $category): ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom">
                            <span><?= e($category['category']) ?></span>
                            <span><strong class="<?= e('rating-tone-' . rating_tone($category['average'])) ?>"><?= e(format_rating($category['average'])) ?></strong> <span class="text-muted">· SD <?= e(format_rating($category['std_dev'])) ?> · n=<?= (int) $category['response_count'] ?></span></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Session trend</strong><span class="text-muted small">Recent sessions</span></div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="empty-state"><i class="bi bi-activity"></i>No completed session data yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorAnalyticsTrend" data-chart="line" aria-label="Mean rating by session"></canvas></div>
                    <p class="data-note mt-2 mb-0">Small sessions should not be used for high-stakes conclusions or ranking.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Comment sentiment</strong><span class="badge badge-soft ms-auto">AI-assisted</span></div>
            <div class="card-body">
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No written feedback analyzed.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="instructorAnalyticsSentiment" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">Rule-Based AI-Assisted Sentiment and Theme Analysis detects positive and negative evidence in the same comment (mixed). It is a screening method, not a definitive judgment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Recurring themes</strong></div>
            <div class="card-body">
                <?php if (empty($themes)): ?>
                    <div class="empty-state"><i class="bi bi-tags"></i>No recurring themes detected.</div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($themes as $theme): ?>
                            <span class="badge <?= $theme['polarity'] === 'negative' ? 'badge-danger' : ($theme['polarity'] === 'positive' ? 'badge-success' : 'badge-secondary') ?>"><?= e($theme['theme']) ?> · <?= (int) $theme['count'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="data-note mt-3 mb-0">Counts are based on matched keywords and polarity per comment. They identify topics for review, not causes of rating changes.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="instructorSemesterChart_data"><?= json_encode(['labels' => $semesterLabels, 'values' => $semesterValues, 'label' => 'Mean rating', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorAnalyticsDistribution_data"><?= json_encode(['labels' => $distributionLabels, 'values' => $distributionValues, 'label' => 'Answers'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorAnalyticsCategory_data"><?= json_encode(['labels' => $categoryLabels, 'values' => $categoryValues, 'label' => 'Category mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorAnalyticsTrend_data"><?= json_encode(['labels' => $trendLabels, 'values' => $trendValues, 'label' => 'Session mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="instructorAnalyticsSentiment_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
