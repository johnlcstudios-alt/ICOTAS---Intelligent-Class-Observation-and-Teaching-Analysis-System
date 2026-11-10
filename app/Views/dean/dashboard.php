<?php
/** @var array $data */
/** @var array $recentSessions */
/** @var bool $demo */
/** @var int $unread */
use App\Core\Auth;

$counts = $data['counts'];
$categories = $data['categories'];
$sentiment = $data['sentiment'];
$semesterTrend = $data['semester_trend'];
$sessionTrend = $data['session_trend'];
$attention = $data['attention'];
$themes = $data['themes'];
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);
$semesterLabels = array_map(static fn ($r) => $r['academic_year'] . ' · ' . $r['semester'], $semesterTrend);
$semesterValues = array_map(static fn ($r) => $r['average'] !== null ? (float) $r['average'] : 0, $semesterTrend);
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map(static fn ($r) => $r['average'] !== null ? (float) $r['average'] : 0, $categories);
$sentimentLabels = array_map(static fn ($r) => ucfirst($r['label']), $sentiment);
$sentimentValues = array_map(static fn ($r) => (int) $r['count'], $sentiment);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Dean / Academic Head workspace</span>
        <h2>Good day, <?= e(Auth::user()['first_name'] ?? 'Colleague') ?></h2>
        <p>Institutional overview of teaching evaluation, participation, and evidence-linked decision support. <?= $demo ? 'This workspace currently shows synthetic demonstration data only.' : 'This workspace shows live institutional records.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('dean/reports') ?>"><i class="bi bi-file-earmark-text"></i> Reports</a>
        <a class="btn btn-primary" href="<?= url('dean/sessions/create') ?>"><i class="bi bi-qr-code"></i> Open QR session</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert" role="alert">
        <i class="bi bi-database-exclamation"></i>
        <span><strong>Demonstration dataset active.</strong> All figures below are synthetic and must not be reported as institutional findings. Use the Demo Data page to generate or safely remove demo records.</span>
    </div>
<?php endif; ?>

<div class="hero-panel mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-4">
            <span class="eyebrow text-white-50">Institutional mean rating</span>
            <div class="d-flex align-items-baseline gap-2">
                <span class="hero-metric"><?= $data['average'] !== null ? e(format_rating($data['average'])) : '—' ?></span>
                <span class="fs-5">/ 5.00</span>
            </div>
            <p class="hero-note mb-0"><?= (int) $data['rating_count'] ?> numeric rating(s) across <?= (int) $data['analytics_sessions'] ?> completed evaluation session(s).</p>
        </div>
        <div class="col-lg-3">
            <span class="eyebrow text-white-50">Participation</span>
            <div class="fs-3 fw-semibold"><?= e(format_percent($data['participation'])) ?></div>
            <p class="hero-note mb-0"><?= (int) $data['submitted'] ?> submitted of <?= (int) $data['eligible'] ?> eligible student-session records.</p>
        </div>
        <div class="col-lg-3">
            <span class="eyebrow text-white-50">Open evaluation sessions</span>
            <div class="fs-3 fw-semibold"><?= (int) $counts['active_sessions'] ?></div>
            <p class="hero-note mb-0"><?= (int) $counts['pending'] ?> student(s) still have a pending response.</p>
        </div>
        <div class="col-lg-2 text-lg-end">
            <a class="btn btn-gold" href="<?= url('dean/analytics') ?>"><i class="bi bi-graph-up-arrow"></i> Explore analytics</a>
        </div>
    </div>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-people"></i></span>
        <div class="stat-label">Active instructors</div>
        <div class="stat-value"><?= (int) $counts['instructors'] ?></div>
        <div class="stat-note"><?= (int) $counts['departments'] ?> department(s) · <?= (int) $counts['programs'] ?> program(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-mortarboard"></i></span>
        <div class="stat-label">Active students</div>
        <div class="stat-value"><?= (int) $counts['students'] ?></div>
        <div class="stat-note"><?= (int) $counts['sections'] ?> section(s) · <?= (int) $counts['courses'] ?> course(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-clipboard-data"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $counts['responses'] ?></div>
        <div class="stat-note">Stored separately from participation records</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-ui-checks"></i></span>
        <div class="stat-label">Survey / assignment</div>
        <div class="stat-value"><?= (int) $counts['surveys'] ?> / <?= (int) $counts['assignments'] ?></div>
        <div class="stat-note"><?= (int) $counts['completed_sessions'] ?> completed session(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-lightbulb"></i></span>
        <div class="stat-label">Open recommendations</div>
        <div class="stat-value"><?= (int) $counts['open_recommendations'] ?></div>
        <div class="stat-note"><?= (int) $counts['observations'] ?> class observation(s) recorded</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">
                <div><strong>Institutional rating trend</strong><div class="text-muted small">Average numeric rating by academic period</div></div>
                <a class="btn btn-sm btn-ghost" href="<?= url('dean/analytics') ?>">Details</a>
            </div>
            <div class="card-body">
                <?php if (empty($semesterTrend)): ?>
                    <div class="empty-state"><i class="bi bi-graph-up"></i>No completed evaluation data yet. Open a QR session to begin collecting responses.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="semesterTrendChart" data-chart="line" aria-label="Average rating by academic period"></canvas></div>
                    <div class="mt-2 data-note">Interpretation is descriptive. Small response counts should not be used to rank faculty.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <div><strong>Comment sentiment</strong><div class="text-muted small">Rule-Based AI-Assisted Sentiment and Theme Analysis</div></div>
                <?php if (auth_role() === 'dean' || auth_role() === 'academic_head'): ?><a class="btn btn-sm btn-ghost" href="<?= url('dean/ai-insights') ?>">Open insights</a><?php endif; ?>
            </div>
            <div class="card-body">
                <?php $sentimentTotal = array_sum($sentimentValues); ?>
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No written feedback has been analyzed yet.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="sentimentChart" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                    <p class="data-note mt-2 mb-0">Analyzed comments: <?= $sentimentTotal ?>. Mixed sentiment is reported when a comment contains both positive and negative evidence.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Teaching category averages</strong><span class="badge badge-soft">5-point scale</span></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No category ratings are available.</div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= e($category['category']) ?></span>
                                <strong class="<?= e('rating-tone-' . rating_tone($category['average'])) ?>"><?= e(format_rating($category['average'])) ?></strong>
                            </div>
                            <div class="rating-bar"><span style="width: <?= max(0, min(100, (float) $category['average'] / 5 * 100)) ?>%"></span></div>
                            <div class="text-tiny text-muted mt-1"><?= (int) $category['response_count'] ?> rating(s)</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Instructors requiring attention</strong><span class="text-muted small">Below 3.60 with sufficient responses</span></div>
            <div class="card-body p-0">
                <?php if (empty($attention)): ?>
                    <div class="empty-state"><i class="bi bi-check2-circle"></i>No instructor currently meets the attention rule at the configured privacy threshold.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Instructor</th><th>Department</th><th>Average</th><th>Responses</th></tr></thead>
                            <tbody>
                            <?php foreach ($attention as $row): ?>
                                <tr>
                                    <td><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                                    <td><?= e($row['department_code']) ?></td>
                                    <td><span class="badge badge-danger"><?= e(format_rating($row['average'])) ?></span></td>
                                    <td><?= (int) $row['response_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 data-note">This is a screening signal for supportive review, not a personnel decision. Consider context, observation records, and response counts.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <strong>Recent QR sessions</strong>
                <a class="btn btn-sm btn-ghost" href="<?= url('dean/sessions') ?>">All sessions</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSessions)): ?>
                    <div class="empty-state"><i class="bi bi-qr-code"></i>No QR evaluation sessions have been opened yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Code</th><th>Class</th><th>Instructor</th><th>Opened</th><th>Responses</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($recentSessions as $session): ?>
                                <tr>
                                    <td><code><?= e($session['session_code']) ?></code></td>
                                    <td><?= e($session['course_code']) ?> · <?= e($session['section_code']) ?></td>
                                    <td><?= e(trim($session['first_name'] . ' ' . $session['last_name'])) ?></td>
                                    <td><?= e(format_date($session['opens_at'])) ?></td>
                                    <td><?= (int) $session['response_count'] ?></td>
                                    <td><?= status_badge($session['status']) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('dean/sessions/' . (int) $session['id']) ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><strong>Recent system activity</strong></div>
            <div class="card-body">
                <?php if (empty($data['recent_activities'])): ?>
                    <div class="empty-state"><i class="bi bi-clock-history"></i>No audit activity recorded.</div>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($data['recent_activities'] as $activity): ?>
                            <li class="d-block">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong class="small"><?= e($activity['description'] ?: $activity['action']) ?></strong>
                                    <span class="text-tiny text-muted text-nowrap"><?= e(time_ago($activity['created_at'])) ?></span>
                                </div>
                                <div class="text-tiny text-muted"><?= e($activity['action']) ?><?= !empty($activity['username']) ? ' · ' . e($activity['username']) : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="btn btn-sm btn-ghost w-100 mt-3" href="<?= url('dean/audit-logs') ?>">Review audit logs</a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($themes)): ?>
    <div class="section-title"><h3>Recurring feedback themes</h3><a class="small" href="<?= url('dean/ai-insights') ?>">Explain these signals</a></div>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (array_slice($themes, 0, 14) as $theme): ?>
            <span class="badge <?= $theme['polarity'] === 'negative' ? 'badge-danger' : ($theme['polarity'] === 'positive' ? 'badge-success' : 'badge-secondary') ?>">
                <?= e($theme['theme']) ?> · <?= (int) $theme['count'] ?>
            </span>
        <?php endforeach; ?>
    </div>
    <p class="disclaimer">Theme counts reflect rule-based keyword evidence in anonymized written comments. They indicate topics to review, not causal findings.</p>
<?php endif; ?>

<script type="application/json" id="semesterTrendChart_data"><?= json_encode(['labels' => $semesterLabels, 'values' => $semesterValues, 'label' => 'Average rating', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="sentimentChart_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
