<?php
/**
 * @var array $session
 * @var string $status
 * @var array $counts
 * @var array $rating
 * @var array $categories
 * @var array $sentiment
 * @var array $themes
 * @var array $comments
 * @var array $questions
 * @var int $privacyThreshold
 * @var bool $demo
 */
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);
$sentimentTotal = array_sum(array_map(static fn ($row) => (int) $row['count'], $sentiment));
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $categories);
$sentimentLabels = array_map(static fn ($row) => ucfirst($row['label']), $sentiment);
$sentimentValues = array_map(static fn ($row) => (int) $row['count'], $sentiment);
$commentsEnabled = (string) setting('privacy_show_comments', '1') === '1';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2><?= e($session['course_code'] . ' · ' . $session['section_code']) ?></h2>
        <p class="mb-1"><?= e($session['survey_title']) ?> · version <?= (int) $session['version_number'] ?> · <?= e($session['academic_year_name'] . ' ' . $session['semester_name']) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge badge-soft"><?= e($session['session_code']) ?></span>
            <?= status_badge($status) ?>
            <span class="badge badge-soft">Opened <?= e(format_datetime($session['opens_at'])) ?></span>
            <span class="badge badge-soft">Closed <?= e(format_datetime($session['expires_at'])) ?></span>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('instructor/evaluations') ?>"><i class="bi bi-arrow-left"></i> My evaluations</a>
        <a class="btn btn-outline-primary" href="<?= url('instructor/analytics') ?>"><i class="bi bi-graph-up"></i> Compare sessions</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration results.</strong> Do not treat these figures as real student feedback.</span></div>
<?php endif; ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-star-half"></i></span>
        <div class="stat-label">Session mean</div>
        <div class="stat-value"><?= e(format_rating($rating['average'])) ?></div>
        <div class="stat-note"><?= (int) $rating['rating_count'] ?> numeric rating(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-inbox"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $counts['responses'] ?></div>
        <div class="stat-note">No student identifiers retained</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-check"></i></span>
        <div class="stat-label">Participation</div>
        <div class="stat-value"><?= e(format_percent($counts['participation_rate'])) ?></div>
        <div class="stat-note"><?= (int) $counts['submitted'] ?> of <?= (int) $counts['eligible'] ?> eligible</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-chat-square-text"></i></span>
        <div class="stat-label">Analyzed comments</div>
        <div class="stat-value"><?= $sentimentTotal ?></div>
        <div class="stat-note">Rule-based sentiment screening</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Category averages</strong></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No numeric responses for this session.</div>
                <?php else: ?>
                    <div class="chart-box mb-3"><canvas id="sessionCategoryChart" data-chart="bar" aria-label="Category averages"></canvas></div>
                    <?php foreach ($categories as $category): ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom">
                            <span><?= e($category['category']) ?></span>
                            <span><strong><?= e(format_rating($category['average'])) ?></strong> <span class="text-muted">· SD <?= e(format_rating($category['std_dev'])) ?> · n=<?= (int) $category['response_count'] ?></span></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Comment sentiment</strong><span class="badge badge-soft ms-auto">AI-assisted</span></div>
            <div class="card-body">
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No analyzable written feedback for this session.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="sessionSentimentChart" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">Mixed comments contain both positive and negative evidence. Sentiment is a screening signal, not a definitive judgment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Question-level results</strong><span class="text-muted small">Descriptive statistics only</span></div>
    <div class="card-body p-0">
        <?php if (empty($questions)): ?>
            <div class="empty-state"><i class="bi bi-list-check"></i>No questionnaire statistics are available.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>#</th><th>Statement</th><th>Category</th><th class="text-center">N</th><th class="text-center">Mean</th><th class="text-center">SD</th><th class="text-center">Agree %</th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?= (int) $question['order_index'] ?></td>
                            <td class="small"><?= e($question['question_text']) ?></td>
                            <td><span class="badge badge-soft"><?= e($question['category']) ?></span></td>
                            <td class="text-center"><?= (int) $question['response_count'] ?></td>
                            <td class="text-center"><?= e(format_rating($question['average'])) ?></td>
                            <td class="text-center"><?= e(format_rating($question['std_dev'])) ?></td>
                            <td class="text-center"><?= (int) $question['response_count'] > 0 ? e(format_percent($question['agreement_percentage'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>Recurring themes</strong></div>
            <div class="card-body">
                <?php if (empty($themes)): ?>
                    <p class="text-muted small mb-0">No recurring themes detected for this session.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($themes as $theme): ?>
                            <span class="badge <?= $theme['polarity'] === 'negative' ? 'badge-danger' : ($theme['polarity'] === 'positive' ? 'badge-success' : 'badge-secondary') ?>"><?= e($theme['theme']) ?> · <?= (int) $theme['count'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="data-note mb-0">Theme counts are keyword matches. They identify topics for reflection, not causes of ratings.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>Anonymized comments</strong><span class="text-muted small">Privacy-filtered excerpts</span></div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                    <div class="empty-state"><i class="bi bi-shield-lock"></i>
                        <?php if ((int) $counts['responses'] < $privacyThreshold): ?>
                            Comments are withheld because this session has <?= (int) $counts['responses'] ?> response(s), below the configured privacy threshold of <?= (int) $privacyThreshold ?>.
                        <?php elseif (!$commentsEnabled): ?>
                            Anonymized comment display is disabled in system settings.
                        <?php else: ?>
                            No written comments are available for this session.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($comments as $comment): ?>
                            <li class="d-block">
                                <div class="d-flex justify-content-between gap-2 mb-1">
                                    <?= sentiment_badge($comment['sentiment_label']) ?>
                                    <span class="text-tiny text-muted"><?= e(format_date($comment['submitted_on'])) ?> · confidence <?= (int) $comment['confidence'] ?>%</span>
                                </div>
                                <p class="small mb-0"><?= e($comment['excerpt']) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer data-note">Comments are stripped of likely names and identifying details before display. They are never linked to a student identity.</div>
        </div>
    </div>
</div>

<script type="application/json" id="sessionCategoryChart_data"><?= json_encode(['labels' => $categoryLabels, 'values' => $categoryValues, 'label' => 'Category mean', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="sessionSentimentChart_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
