<?php
/** Faculty trends index. */

use App\Services\AnalyticsService;

$facultyId = (int) ($facultyId ?? 0);
$faculty = Faculty::find($facultyId);
if (!$faculty) {
    abort(404, 'Faculty not found.');
}
$analytics = AnalyticsService::forFaculty($facultyId);
$chart = $analytics->lineChart();

$stats = $stats ?? [];
$chartData = $chartData ?? $chart['data'] ?? [];
$period = $period ?? null;
$periods = $periods ?? [];
$selectedPeriod = (int) ($selectedPeriod ?? 0);
$facultyId = $facultyId;

$labels = rating_labels();
?>
<div class="page-tools">
    <a class="btn btn--ghost" href="<?= e(url('/faculty/profile')) ?>"><?= icon('user', 16) ?> Profile</a>
</div>

<div class="trends-page">
    <header class="trends-page__header">
        <div>
            <h1>Observation trends</h1>
            <p class="muted small"><?= e($pageSubtitle) ?></p>
        </div>
        <div class="trends-page__filters">
            <form method="get" action="<?= e(url('/faculty/trends')) ?>" class="form-inline">
                <?php if ($facultyId > 0): ?>
                    <input type="hidden" name="faculty_id" value="<?= (int) $facultyId ?>">
                <?php endif; ?>
                <div class="field field--compact">
                    <label for="period_id" class="muted small">Period</label>
                    <select id="period_id" name="period_id" class="select select--sm">
                        <option value="0"><?= e($pageSubtitle) ?></option>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($selectedPeriod ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['label_full']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--ghost btn--sm">Filter</button>
            </form>
        </div>
    </header>

    <div class="trends-page__body">
        <div class="trends-page__main">
            <?php if (!$chart || empty($chartData['labels'])): ?>
                <section class="card">
                    <div class="card__head"><h2>Trend data</h2></div>
                    <div class="card__body">
                        <p class="muted"><?= e($pageSubtitle) ?></p>
                        <p class="muted small mt-2">Select a period to see your observation trends.</p>
                    </div>
                </section>
            <?php else: ?>
                <section class="card">
                    <div class="card__head"><h2><?= e($chart['title']) ?></h2></div>
                    <div class="card__body">
                        <div class="chart-box chart-box--fluid">
                            <canvas id="trends-chart"></canvas>
                        </div>
                        <p class="small muted mt-2"><?= e($chart['description']) ?></p>
                    </div>
                </section>

                <section class="card">
                    <div class="card__head"><h2>Trend statistics</h2></div>
                    <div class="card__body">
                        <div class="stat-grid" style="grid-template-columns:repeat(auto-fill, minmax(140px, 1fr))">
                            <div class="stat stat--flat">
                                <div class="stat__label">Latest observation score</div>
                                <div class="stat__value"><?= e(score($stats['latest_score'] ?? 0)) ?></div>
                                <div class="stat__meta">out of <?= e(score($chart['max'] ?? 5)) ?></div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Average across periods</div>
                                <div class="stat__value"><?= e(score($stats['average_score'] ?? 0)) ?></div>
                                <div class="stat__meta"><?= e($stats['periods_count'] ?? 0) ?> periods</div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Trend direction</div>
                                <div class="stat__value"><?= e($stats['trend_direction'] ?? '—') ?></div>
                                <div class="stat__meta"><?= e($stats['trend_description'] ?? '') ?></div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Number of observations</div>
                                <div class="stat__value"><?= (int) ($stats['observations_count'] ?? 0) ?></div>
                                <div class="stat__meta">published only</div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($recentObservations)): ?>
                <section class="card">
                    <div class="card__head"><h2>Recent observations</h2></div>
                    <div class="card__body card__body--flush">
                        <div class="table-wrap">
                            <table class="table table--compact">
                                <thead>
                                    <tr>
                                        <th class="num">#</th>
                                        <th>Date</th>
                                        <th class="num">Period</th>
                                        <th class="num">Overall</th>
                                        <th class="num">Teaching</th>
                                        <th class="num">Personality</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentObservations as $obs): ?>
                                        <tr>
                                            <td class="num"><?= (int) $obs['id'] ?></td>
                                            <td class="nowrap"><?= e(fmt_date($obs['observation_date'])) ?></td>
                                            <td class="num"><?= e($obs['period_label']) ?></td>
                                            <td class="num"><?= e(score($obs['overall_avg'])) ?></td>
                                            <td class="num"><?= e(score($obs['teaching_competence_avg'])) ?></td>
                                            <td class="num"><?= e(score($obs['personality_avg'])) ?></td>
                                            <td class="text-right">
                                                <a class="btn btn--ghost btn--xs" href="<?= e(url('/faculty/evaluations/' . (int) $obs['id'])) ?>"><?= icon('eye', 16) ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="trends-page__meta">
            <section class="card">
                <div class="card__head"><h2>Quick facts</h2></div>
                <div class="card__body">
                    <dl class="small">
                        <dt>Faculty</dt>
                        <dd><?= e($faculty->full_name) ?></dd>
                        <dt>ID</dt>
                        <dd><?= e($faculty->id) ?></dd>
                        <dt>Department</dt>
                        <dd><?= e($faculty->department_name) ?></dd>
                        <dt>Role</dt>
                        <dd><?= e($faculty->role) ?></dd>
                    </dl>
                </div>
            </section>

            <?php if (!empty($chartData) && !empty($chart['title'])): ?>
                <section class="card">
                    <div class="card__head"><h2>Chart summary</h2></div>
                    <div class="card__body">
                        <ul class="meta-list small">
                            <li><strong>Chart:</strong> <?= e($chart['title']) ?></li>
                            <li><strong>Periods:</strong> <?= count($chartData['labels']) ?></li>
                            <li><strong>Latest:</strong> <?= e(score($stats['latest_score'] ?? 0)) ?></li>
                            <li><strong>Average:</strong> <?= e(score($stats['average_score'] ?? 0)) ?></li>
                            <li><strong>Trend:</strong> <?= e($stats['trend_direction'] ?? '—') ?></li>
                        </ul>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
