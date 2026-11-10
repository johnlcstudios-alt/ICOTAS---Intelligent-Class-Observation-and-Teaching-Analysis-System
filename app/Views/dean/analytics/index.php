<?php
/**
 * @var array $data
 * @var array $filters
 * @var bool $demo
 */
use App\Core\View;

$counts = $data['counts'];
$rating = $data['rating'];
$categories = $data['categories'];
$sentiment = $data['sentiment'];
$themes = $data['themes'];
$distribution = $data['distribution'];
$sessionTrend = $data['session_trend'];
$semesterTrend = $data['semester_trend'];
$instructors = $data['instructors'];
$departments = $data['departments'];
$comments = $data['comments'];
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);

$semesterLabels = array_map(static fn ($r) => $r['academic_year'] . ' · ' . $r['semester'], $semesterTrend);
$semesterValues = array_map(static fn ($r) => $r['average'] !== null ? (float) $r['average'] : 0, $semesterTrend);
$sessionLabels = array_map(static fn ($r) => $r['session_code'], $sessionTrend);
$sessionValues = array_map(static fn ($r) => $r['average'] !== null ? (float) $r['average'] : 0, $sessionTrend);
$sentimentLabels = array_map(static fn ($r) => ucfirst($r['label']), $sentiment);
$sentimentValues = array_map(static fn ($r) => (int) $r['count'], $sentiment);
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map(static fn ($r) => $r['average'] !== null ? (float) $r['average'] : 0, $categories);
$distributionLabels = array_map(static fn ($r) => number_format((float) $r['rating'], 1), $distribution);
$distributionValues = array_map(static fn ($r) => (int) $r['count'], $distribution);
$sentimentTotal = array_sum($sentimentValues);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Evidence and interpretation</span>
        <h2>Teaching analytics</h2>
        <p>Descriptive aggregates across completed QR evaluation sessions. Filters apply consistently to ratings, sentiment, themes and comments.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('dean/ai-insights') ?>"><i class="bi bi-stars"></i> AI-assisted insights</a>
        <a class="btn btn-outline-secondary" href="<?= url('dean/reports') ?>"><i class="bi bi-file-earmark-text"></i> Reports</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> These figures are generated for testing and must not be reported as institutional findings.</span></div>
<?php endif; ?>

<?php View::partial('dean.partials.analytics-filters', ['filters' => $filters, 'filter_options' => $data['filter_options'], 'action' => 'dean/analytics']); ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-star-half"></i></span>
        <div class="stat-label">Mean rating</div>
        <div class="stat-value"><?= e(format_rating($rating['average'])) ?></div>
        <div class="stat-note"><?= (int) $rating['rating_count'] ?> numeric rating(s) · 5-point scale</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-inbox"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $counts['responses'] ?></div>
        <div class="stat-note">Across <?= (int) $counts['sessions'] ?> completed session(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-check"></i></span>
        <div class="stat-label">Participation</div>
        <div class="stat-value"><?= e(format_percent($counts['participation_rate'])) ?></div>
        <div class="stat-note"><?= (int) $counts['submitted'] ?> submitted of <?= (int) $counts['eligible'] ?> eligible</div>
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
            <div class="card-header"><strong>Rating by academic period</strong><span class="text-muted small">Mean of normalized numeric answers</span></div>
            <div class="card-body">
                <?php if (empty($semesterTrend)): ?>
                    <div class="empty-state"><i class="bi bi-graph-up"></i>No completed evaluation data matches these filters.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="analyticsSemesterChart" data-chart="line" aria-label="Mean rating by academic period"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Comment sentiment</strong><span class="text-muted small">Rule-Based AI-Assisted</span></div>
            <div class="card-body">
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No analyzed written feedback matches these filters.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="analyticsSentimentChart" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">Mixed labels identify comments containing both positive and negative evidence. Sentiment is a screening signal, not a definitive judgment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Category averages</strong><span class="badge badge-soft">5-point scale</span></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No numeric responses match these filters.</div>
                <?php else: ?>
                    <div class="chart-box mb-3"><canvas id="analyticsCategoryChart" data-chart="bar" aria-label="Category averages"></canvas></div>
                    <?php foreach ($categories as $category): ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom">
                            <span><?= e($category['category']) ?></span>
                            <span>
                                <strong class="<?= e('rating-tone-' . rating_tone($category['average'])) ?>"><?= e(format_rating($category['average'])) ?></strong>
                                <span class="text-muted">· SD <?= e(format_rating($category['std_dev'])) ?> · n=<?= (int) $category['response_count'] ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Rating distribution</strong><span class="text-muted small">All numeric answers</span></div>
            <div class="card-body">
                <?php if (empty($distribution)): ?>
                    <div class="empty-state"><i class="bi bi-distribute-vertical"></i>No ratings available for a distribution.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="analyticsDistributionChart" data-chart="bar" aria-label="Rating distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">A concentration at the upper end is common in student evaluation instruments; it does not by itself prove teaching effectiveness.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Recent session trend</strong><span class="text-muted small">Up to 16 completed sessions</span></div>
            <div class="card-body">
                <?php if (empty($sessionTrend)): ?>
                    <div class="empty-state"><i class="bi bi-activity"></i>No session-level ratings yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="analyticsSessionChart" data-chart="line" aria-label="Average rating by QR session"></canvas></div>
                    <p class="data-note mt-2 mb-0">Session labels are QR session codes. Small sessions should not be used to rank faculty.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Recurring themes</strong><span class="text-muted small">Keyword evidence</span></div>
            <div class="card-body">
                <?php if (empty($themes)): ?>
                    <div class="empty-state"><i class="bi bi-tags"></i>No themes have been detected in written feedback.</div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (array_slice($themes, 0, 16) as $theme): ?>
                            <span class="badge <?= $theme['polarity'] === 'negative' ? 'badge-danger' : ($theme['polarity'] === 'positive' ? 'badge-success' : 'badge-secondary') ?>">
                                <?= e($theme['theme']) ?> · <?= (int) $theme['count'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="disclaimer mb-0">Counts are based on matched keywords and polarity per comment. They identify topics for review, not causes of rating changes.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Instructor comparison</strong><span class="text-muted small">Descriptive means with response counts</span></div>
    <div class="card-body p-0">
        <?php if (empty($instructors)): ?>
            <div class="empty-state"><i class="bi bi-people"></i>No instructor aggregates match these filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Instructor</th><th>Department</th><th class="text-center">Sessions</th><th class="text-center">Responses</th><th class="text-center">Mean rating</th><th>Profile</th></tr></thead>
                    <tbody>
                    <?php foreach ($instructors as $row): ?>
                        <tr>
                            <td><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                            <td><span class="badge badge-soft"><?= e($row['department_code']) ?></span></td>
                            <td class="text-center"><?= (int) $row['session_count'] ?></td>
                            <td class="text-center"><?= (int) $row['response_count'] ?></td>
                            <td class="text-center"><strong class="<?= e('rating-tone-' . rating_tone($row['average'])) ?>"><?= e(format_rating($row['average'])) ?></strong></td>
                            <td>
                                <div class="rating-bar" style="max-width:140px"><span style="width: <?= max(0, min(100, (float) $row['average'] / 5 * 100)) ?>%"></span></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 data-note">Institution privacy policy: aggregates below the configured minimum are withheld elsewhere; comparisons here are descriptive and do not adjust for course difficulty, class size or response bias.</div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Department comparison</strong></div>
            <div class="card-body p-0">
                <?php if (empty($departments)): ?>
                    <div class="empty-state"><i class="bi bi-building"></i>No department aggregates yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Department</th><th class="text-center">Instructors</th><th class="text-center">Responses</th><th class="text-center">Mean</th></tr></thead>
                            <tbody>
                            <?php foreach ($departments as $row): ?>
                                <tr>
                                    <td><?= e($row['code']) ?><div class="text-tiny text-muted"><?= e($row['name']) ?></div></td>
                                    <td class="text-center"><?= (int) $row['instructor_count'] ?></td>
                                    <td class="text-center"><?= (int) $row['response_count'] ?></td>
                                    <td class="text-center"><strong><?= e(format_rating($row['average'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Recent anonymized comments</strong><span class="text-muted small">AES/privacy-filtered excerpts</span></div>
            <div class="card-body p-0">
                <?php if (empty($comments)): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-quote"></i>No written comments match these filters, or the response count is below the privacy threshold.</div>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($comments as $comment): ?>
                            <li class="d-block">
                                <div class="d-flex justify-content-between gap-2 mb-1">
                                    <span><?= sentiment_badge($comment['sentiment_label']) ?> <span class="badge badge-soft"><?= e($comment['course_code']) ?> · <?= e($comment['section_code']) ?></span></span>
                                    <span class="text-tiny text-muted"><?= e(format_date($comment['submitted_on'])) ?></span>
                                </div>
                                <p class="mb-0 small"><?= e($comment['excerpt']) ?></p>
                                <div class="text-tiny text-muted">Session <?= e($comment['session_code']) ?> · confidence <?= (int) $comment['confidence'] ?>%</div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer data-note">Excerpts are stripped of likely names and identifying details before display. Original comments remain accessible only to authorized administrators for audit, never joined to a student identity.</div>
        </div>
    </div>
</div>

<script type="application/json" id="analyticsSemesterChart_data"><?= json_encode(['labels' => $semesterLabels, 'values' => $semesterValues, 'label' => 'Mean rating', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="analyticsSessionChart_data"><?= json_encode(['labels' => $sessionLabels, 'values' => $sessionValues, 'label' => 'Session mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="analyticsSentimentChart_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="analyticsCategoryChart_data"><?= json_encode(['labels' => $categoryLabels, 'values' => $categoryValues, 'label' => 'Category mean', 'max' => 5, 'horizontal' => true], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="analyticsDistributionChart_data"><?= json_encode(['labels' => $distributionLabels, 'values' => $distributionValues, 'label' => 'Answers'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
