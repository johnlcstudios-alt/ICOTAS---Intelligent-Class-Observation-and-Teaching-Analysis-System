<?php
/**
 * Faculty overview. Published results only.
 *
 * @var array $faculty
 * @var array $stats
 * @var array $trend
 * @var array $distribution
 * @var array|null $latest
 * @var array $recent
 * @var array $classes
 * @var int $acknowledged
 * @var int $commentCount
 * @var int $unreadCount
 */

$trendChart = [
    'type' => 'line',
    'data' => [
        'labels' => array_column($trend, 'label'),
        'datasets' => [
            [
                'label' => 'Teaching Competence',
                'data' => array_column($trend, 'teaching'),
                'borderColor' => '#7a2232',
                'backgroundColor' => 'rgba(122,34,50,.08)',
                'tension' => .3,
                'fill' => true,
                'pointRadius' => 3,
            ],
            [
                'label' => 'Personality',
                'data' => array_column($trend, 'personality'),
                'borderColor' => '#b8913f',
                'backgroundColor' => 'rgba(184,145,63,.08)',
                'tension' => .3,
                'fill' => true,
                'pointRadius' => 3,
            ],
            [
                'label' => 'Overall',
                'data' => array_column($trend, 'overall'),
                'borderColor' => '#172033',
                'borderDash' => [5, 4],
                'tension' => .3,
                'pointRadius' => 0,
            ],
        ],
    ],
];

$labels = array_map(static fn (int $score): string => (string) $score, range(1, 5));
$distributionChart = [
    'type' => 'bar',
    'data' => [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Teaching Competence ratings',
                'data' => array_values($distribution['Teaching Competence'] ?? []),
                'backgroundColor' => '#7a2232',
                'borderRadius' => 4,
            ],
            [
                'label' => 'Personality ratings',
                'data' => array_values($distribution['Personality'] ?? []),
                'backgroundColor' => '#c9a24a',
                'borderRadius' => 4,
            ],
        ],
    ],
];
?>
<div class="page-tools" style="margin-bottom:16px">
    <a class="btn btn--primary" href="<?= e(url('/faculty/evaluations')) ?>"><?= icon('clipboard', 16) ?> My evaluations</a>
    <a class="btn btn--secondary" href="<?= e(url('/faculty/trends')) ?>"><?= icon('trend', 16) ?> Performance trends</a>
    <a class="btn btn--secondary" href="<?= e(url('/faculty/qr')) ?>"><?= icon('qr', 16) ?> Verify QR credential</a>
</div>

<?php if ($stats['last_date'] !== null): ?>
    <div class="callout callout--info">
        <strong>Published results only.</strong>
        Your dashboard shows <?= (int) $stats['total_published'] ?> published evaluation(s), from <?= e(fmt_date($stats['first_date'])) ?>
        to <?= e(fmt_date($stats['last_date'])) ?>. Drafts and submissions under review are not visible to faculty.
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat stat--maroon">
        <div class="stat__label"><?= icon('star', 14) ?> Overall average</div>
        <div class="stat__value"><?= e(score($stats['avg_overall'])) ?></div>
        <div class="stat__meta">COF scale 1–5 · <?= (int) $stats['total_published'] ?> published</div>
    </div>
    <div class="stat stat--gold">
        <div class="stat__label"><?= icon('clipboard', 14) ?> Teaching Competence</div>
        <div class="stat__value"><?= e(score($stats['avg_teaching'])) ?></div>
        <div class="stat__meta">13 criteria per observation</div>
    </div>
    <div class="stat">
        <div class="stat__label"><?= icon('user', 14) ?> Personality</div>
        <div class="stat__value"><?= e(score($stats['avg_personality'])) ?></div>
        <div class="stat__meta">7 criteria per observation</div>
    </div>
    <div class="stat">
        <div class="stat__label"><?= icon('shield', 14) ?> Acknowledged</div>
        <div class="stat__value"><?= (int) $acknowledged ?></div>
        <div class="stat__meta"><?= (int) $commentCount ?> comment(s) submitted</div>
    </div>
</div>

<div class="grid grid--sidebar mt-3">
    <div class="stack">
        <?php if ($latest !== null): ?>
            <section class="card card--accent">
                <div class="card__head">
                    <div>
                        <span class="eyebrow">Most recent published evaluation</span>
                        <h2 class="mt-1"><?= e($latest['subject_code']) ?> — <?= e($latest['section_name']) ?></h2>
                        <p><?= e($latest['lesson_topic']) ?> · observed <?= e(fmt_date($latest['observation_date'])) ?></p>
                    </div>
                    <div class="text-right">
                        <div class="score-chip"><?= e(score($latest['overall_avg'])) ?><small>/ 5</small></div>
                        <span class="muted tiny">Overall</span>
                    </div>
                </div>
                <div class="card__body">
                    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
                        <div class="stat stat--flat">
                            <div class="stat__label">Teaching</div>
                            <div class="stat__value"><?= e(score($latest['teaching_competence_avg'])) ?></div>
                        </div>
                        <div class="stat stat--flat">
                            <div class="stat__label">Personality</div>
                            <div class="stat__value"><?= e(score($latest['personality_avg'])) ?></div>
                        </div>
                        <div class="stat stat--flat">
                            <div class="stat__label">Published</div>
                            <div class="stat__value" style="font-size:16px"><?= e(fmt_date($latest['published_at'])) ?></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <a class="btn btn--primary btn--sm" href="<?= e(url('/faculty/evaluations/' . (int) $latest['observation_id'])) ?>">Read full evaluation</a>
                        <a class="btn btn--secondary btn--sm" href="<?= e(url('/faculty/evaluations/' . (int) $latest['observation_id'] . '/report')) ?>">Printable report</a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="card">
                <div class="card__body">
                    <div class="empty">
                        <span class="empty__icon"><?= icon('clipboard', 22) ?></span>
                        <strong>No published evaluations yet</strong>
                        <p>Your Dean publishes classroom observation results after review. Once published, results appear here automatically.</p>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head">
                <div><h2>Your performance over time</h2><p>Every published observation, in chronological order.</p></div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/faculty/trends')) ?>">Detailed trends</a>
            </div>
            <div class="card__body">
                <?php if ($trend === []): ?>
                    <p class="muted small mb-0">A trend line appears after your first published evaluation.</p>
                <?php else: ?>
                    <div class="chart-box"><canvas data-chart-type="line" data-chart-score data-chart-data="<?= e(json_encode($trendChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Recent published evaluations</h2><p>Only published evaluations are listed.</p></div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/faculty/evaluations')) ?>">View all</a>
            </div>
            <div class="card__body card__body--flush">
                <?php if ($recent === []): ?>
                    <div class="table-empty"><strong>Nothing published yet</strong>Check back after your next classroom observation.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Subject / topic</th>
                                    <th>Date</th>
                                    <th class="num">Overall</th>
                                    <th>Acknowledged</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="row-title"><?= e($row['subject_code']) ?> · <?= e($row['section_name']) ?></span>
                                            <span class="row-sub"><?= e(str_excerpt($row['lesson_topic'], 60)) ?></span>
                                        </td>
                                        <td class="nowrap"><?= e(fmt_date($row['observation_date'])) ?></td>
                                        <td class="num"><?= e(score($row['overall_avg'])) ?></td>
                                        <td><?= (int) $row['acknowledged'] > 0 ? '<span class="badge badge--success">Acknowledged</span>' : '<span class="badge badge--neutral">Pending</span>' ?></td>
                                        <td class="text-right"><a class="btn btn--ghost btn--xs" href="<?= e(url('/faculty/evaluations/' . (int) $row['observation_id'])) ?>">Open</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Rating distribution</h2></div>
            <div class="card__body">
                <div class="chart-box chart-box--short"><canvas data-chart-type="bar" data-chart-data="<?= e(json_encode($distributionChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
                <p class="chart-note">Counts of individual criterion ratings across all your published observations.</p>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>My classes</h2></div>
            <div class="card__body">
                <?php if ($classes === []): ?>
                    <p class="muted small mb-0">No classes are assigned to your account yet.</p>
                <?php else: ?>
                    <ul class="meta-list">
                        <?php foreach (array_slice($classes, 0, 5) as $class): ?>
                            <li>
                                <span><?= e($class['subject_code']) ?> · <?= e($class['section_name']) ?></span>
                                <strong><?= status_badge($class['status']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn--secondary btn--block mt-2" href="<?= e(url('/faculty/profile')) ?>">View all classes</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>What this means</h2></div>
            <div class="card__body">
                <p class="small">Ratings are professional judgements on the official 20-item Classroom Observation Form. They are intended to support teaching improvement, not to function as automatic penalties.</p>
                <?php if (!$stats['total_published']): ?>
                    <p class="small mb-0">Once results are published you can add comments and acknowledge receipt from each evaluation page.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
