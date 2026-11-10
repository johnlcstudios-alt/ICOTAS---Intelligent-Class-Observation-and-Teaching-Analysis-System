<?php
/**
 * @var array $data
 * @var array $filters
 * @var bool $demo
 */
use App\Core\View;

$summary = $data['summary'];
$sentiment = $data['sentiment'];
$themes = $data['themes'];
$categories = $data['categories'];
$comments = $data['comments'];
$counts = $data['counts'];
$rating = $data['rating'];
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);
$sentimentLabels = array_map(static fn ($r) => ucfirst($r['label']), $sentiment);
$sentimentValues = array_map(static fn ($r) => (int) $r['count'], $sentiment);
$sentimentTotal = array_sum($sentimentValues);
$dominantTone = in_array($summary['dominant_sentiment'], ['positive'], true) ? 'success' : (in_array($summary['dominant_sentiment'], ['negative'], true) ? 'danger' : (in_array($summary['dominant_sentiment'], ['mixed'], true) ? 'warning' : 'secondary'));
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Decision support</span>
        <h2>Rule-Based AI-Assisted Sentiment and Theme Analysis</h2>
        <p>A transparent, deterministic text-analysis engine that flags sentiment and recurring themes in anonymous comments. It does not use an external AI service and does not make personnel judgments.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('dean/analytics') ?>"><i class="bi bi-graph-up"></i> Numeric analytics</a>
        <a class="btn btn-outline-secondary" href="<?= url('dean/recommendations') ?>"><i class="bi bi-lightbulb"></i> Recommendations</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> Insights describe generated comments only and are not institutional findings.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert">
    <i class="bi bi-info-circle"></i>
    <span><strong>How to read this page.</strong> Sentiment labels are produced by a fixed lexicon and clause-scoring rules. Mixed labels identify comments containing both positive and negative evidence. Always verify signals against the underlying aggregates and professional context before acting.</span>
</div>

<?php View::partial('dean.partials.analytics-filters', ['filters' => $filters, 'filter_options' => $data['filter_options'], 'action' => 'dean/ai-insights']); ?>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div><strong>Assisted summary</strong><div class="text-muted small"><?= e($summary['engine']) ?></div></div>
                <span class="badge badge-<?= e($dominantTone) ?>"><?= e(ucfirst($summary['dominant_sentiment'])) ?> signal</span>
            </div>
            <div class="card-body">
                <p class="lead fs-6"><?= e($summary['narrative']) ?></p>
                <div class="row g-2 mt-3">
                    <div class="col-sm-4">
                        <div class="border rounded p-2 h-100">
                            <div class="text-tiny text-uppercase text-muted">Analyzed comments</div>
                            <div class="fs-4 fw-semibold"><?= (int) $summary['analyzed_comments'] ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded p-2 h-100">
                            <div class="text-tiny text-uppercase text-muted">Confidence indicator</div>
                            <div class="fs-4 fw-semibold"><?= (int) $summary['confidence'] ?>%</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded p-2 h-100">
                            <div class="text-tiny text-uppercase text-muted">Privacy threshold</div>
                            <div class="fs-4 fw-semibold"><?= (int) $data['privacy_threshold'] ?></div>
                            <div class="text-tiny text-muted">minimum responses for comments</div>
                        </div>
                    </div>
                </div>
                <?php if (isset($summary['comparison']) && $summary['comparison'] !== null): ?>
                    <p class="small text-muted mt-3 mb-0">Period-over-period rating difference: <strong><?= $summary['comparison'] > 0 ? '+' : '' ?><?= e(number_format((float) $summary['comparison'], 2)) ?></strong> points on the five-point scale.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><strong>Sentiment distribution</strong></div>
            <div class="card-body">
                <?php if ($sentimentTotal === 0): ?>
                    <div class="empty-state"><i class="bi bi-chat-square-text"></i>No analyzed comments match these filters.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas id="aiSentimentChart" data-chart="doughnut" aria-label="Sentiment distribution"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Recurring strengths</strong><span class="text-muted small">Positive theme evidence</span></div>
            <div class="card-body">
                <?php if (empty($summary['strengths'])): ?>
                    <p class="text-muted mb-0">No positive themes were detected in the analyzed comments.</p>
                <?php else: ?>
                    <?php foreach ($summary['strengths'] as $theme => $count): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span><i class="bi bi-arrow-up-right-circle text-success"></i> <?= e($theme) ?></span>
                            <span class="badge badge-success"><?= (int) $count ?> mention(s)</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Recurring concerns</strong><span class="text-muted small">Negative theme evidence</span></div>
            <div class="card-body">
                <?php if (empty($summary['concerns'])): ?>
                    <p class="text-muted mb-0">No negative themes were detected in the analyzed comments.</p>
                <?php else: ?>
                    <?php foreach ($summary['concerns'] as $theme => $count): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span><i class="bi bi-exclamation-circle text-danger"></i> <?= e($theme) ?></span>
                            <span class="badge badge-danger"><?= (int) $count ?> mention(s)</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($summary['recommendations'])): ?>
    <div class="section-title"><h3>Evidence-linked suggestions</h3><span class="text-muted small">Rule-based, not prescriptive</span></div>
    <div class="row g-3 mb-4">
        <?php foreach ($summary['recommendations'] as $recommendation): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><?= e($recommendation['theme']) ?></strong>
                        <span class="badge badge-<?= $recommendation['priority'] === 'high' ? 'danger' : 'warning' ?>"><?= e(ucfirst($recommendation['priority'])) ?> priority</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><?= e($recommendation['concern']) ?></p>
                        <div class="small text-muted mb-2"><i class="bi bi-bar-chart"></i> <?= e($recommendation['supporting_metric']) ?></div>
                        <p class="mb-2"><strong>Suggested action:</strong> <?= e($recommendation['recommendation']) ?></p>
                        <p class="small text-muted mb-0"><strong>Why:</strong> <?= e($recommendation['reason']) ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Theme evidence</strong><span class="text-muted small">Counts per polarity</span></div>
            <div class="card-body p-0">
                <?php if (empty($themes)): ?>
                    <div class="empty-state"><i class="bi bi-tags"></i>No themes detected.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Theme</th><th>Polarity</th><th class="text-center">Comments</th></tr></thead>
                            <tbody>
                            <?php foreach ($themes as $theme): ?>
                                <tr>
                                    <td><?= e($theme['theme']) ?></td>
                                    <td><?= sentiment_badge($theme['polarity']) ?></td>
                                    <td class="text-center"><?= (int) $theme['count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong>Lowest-rated categories</strong><span class="text-muted small">From numeric answers</span></div>
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No numeric category data.</div>
                <?php else: ?>
                    <?php foreach (array_slice(array_reverse($categories), 0, 6) as $category): ?>
                        <div class="px-3 py-2 border-bottom">
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
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Anonymized comment evidence</strong>
        <span class="text-muted small"><?= count($comments) ?> excerpt(s) shown</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($comments)): ?>
            <div class="empty-state"><i class="bi bi-chat-square-quote"></i>
                <?php if ((int) $counts['responses'] < (int) $data['privacy_threshold']): ?>
                    Withheld: the response count is below the privacy threshold of <?= (int) $data['privacy_threshold'] ?>.
                <?php else: ?>
                    No written comments match these filters.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="list-clean">
                <?php foreach ($comments as $comment): ?>
                    <li class="d-block">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <span><?= sentiment_badge($comment['sentiment_label']) ?> <span class="badge badge-soft"><?= e($comment['course_code']) ?> · <?= e($comment['section_code']) ?></span></span>
                            <span class="text-tiny text-muted"><?= e(time_ago($comment['submitted_on'])) ?></span>
                        </div>
                        <p class="mb-1 small"><?= e($comment['excerpt']) ?></p>
                        <div class="text-tiny text-muted">Session <?= e($comment['session_code']) ?> · engine confidence <?= (int) $comment['confidence'] ?>%</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Methodology and limitations</strong></div>
    <div class="card-body small text-muted">
        <div class="row g-3">
            <div class="col-md-6">
                <h4 class="h6 text-body">What the engine does</h4>
                <ul class="ps-3 mb-0">
                    <li>Tokenizes each comment and scores positive and negative lexicon terms.</li>
                    <li>Handles negation, intensifiers and contrast clauses ("but", "however", "pero").</li>
                    <li>Assigns themes from fixed keyword groups and records polarity and weight.</li>
                    <li>Reports <strong>mixed</strong> when both positive and negative evidence exceed the threshold.</li>
                    <li>Redacts likely names and identifying details from displayed excerpts.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4 class="h6 text-body">What it cannot do</h4>
                <ul class="ps-3 mb-0">
                    <li>It is not a trained language model and has no semantic world knowledge.</li>
                    <li>Sarcasm, irony, code-switching and highly contextual comments can be misclassified.</li>
                    <li>Confidence is an evidence-volume indicator, not a statistical probability.</li>
                    <li>Theme counts do not establish causation or measure teaching quality.</li>
                    <li>Results must be triangulated with ratings, observations and human review.</li>
                </ul>
            </div>
        </div>
        <div class="mt-3 pt-3 border-top">
            Engine version <?= e($summary['engine']) ?>. Processing is local to the application; no comment is sent to an external AI provider. The engine is deterministic: the same text and version produce the same label.
        </div>
    </div>
</div>

<script type="application/json" id="aiSentimentChart_data"><?= json_encode(['labels' => $sentimentLabels, 'values' => $sentimentValues, 'label' => 'Comments'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
