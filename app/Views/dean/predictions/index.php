<?php
/** Dean predictive analytics. */

$labels = rating_labels();
?>
<div class="page-tools">
    <a class="btn btn--primary" href="<?= e(url('/dean/predictions/generate')) ?>" id="generate-btn"><?= icon('plus', 16) ?> Generate prediction</a>
</div>

<div class="grid grid--sidebar mt-3">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Select faculty</h2></div>
            <div class="card__body">
                <form method="get" action="<?= e(url('/dean/predictions')) ?>" class="form-grid">
                    <div class="field field--full">
                        <label for="faculty_id">Faculty member</label>
                        <select id="faculty_id" name="faculty_id" class="select">
                            <option value="0">— Select a faculty member —</option>
                            <?php foreach ($facultyOptions as $option): ?>
                                <option value="<?= (int) $option['user_id'] ?>" <?= (int) $selectedId === (int) $option['user_id'] ? 'selected' : '' ?>>
                                    <?= e($option['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary btn--sm">View analysis</button>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($selectedFaculty !== null): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Prediction summary — <?= e($selectedFaculty['full_name']) ?></h2></div>
                </div>
                <div class="card__body">
                    <?php if ($analysis === null): ?>
                        <p class="muted">Select a faculty member to compute the forecast.</p>
                    <?php else: ?>
                        <div class="stat-grid" style="grid-template-columns:1fr 1fr">
                            <div class="stat stat--flat">
                                <div class="stat__label">Status</div>
                                <div class="stat__value"><?= status_badge($analysis['status']) ?></div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Model</div>
                                <div class="stat__value small"><?= e($analysis['model_name']) ?></div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Historical periods</div>
                                <div class="stat__value"><?= (int) $analysis['period_count'] ?></div>
                                <div class="stat__meta">of <?= (int) $analysis['observation_count'] ?> observations</div>
                            </div>
                            <div class="stat stat--flat">
                                <div class="stat__label">Minimum required</div>
                                <div class="stat__value"><?= (int) $minPeriods ?> periods</div>
                            </div>
                            <?php if ($analysis['sufficient']): ?>
                                <div class="stat stat--gold">
                                    <div class="stat__label">Predicted next-period score</div>
                                    <div class="stat__value"><?= e(score($analysis['predicted_score'])) ?>   <small>/ 5</small></div>
                                    <div class="stat__meta">Period <?= (int) $analysis['next_period'] ?> · <?= $analysis['clamped'] ? 'Clamped to 1–5 range' : 'Within scale' ?></div>
                                </div>
                                <div class="stat stat--flat">
                                    <div class="stat__label">Validation RMSE</div>
                                    <div class="stat__value"><?= e($analysis['rmse'] === null ? '—' : $analysis['rmse']) ?></div>
                                    <div class="stat__meta">MAE <?= e($analysis['mae'] === null ? '—' : $analysis['mae']) ?></div>
                                </div>
                            <?php else: ?>
                                <div class="stat stat--flat">
                                    <div class="stat__label">Reason</div>
                                    <div class="stat__value small"><?= e($analysis['reason']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($analysis !== null && $analysis['sufficient']): ?>
                <section class="card">
                    <div class="card__head"><h2>Regression trend</h2></div>
                    <div class="card__body">
                        <div class="chart-box"><canvas data-chart-type="line" data-chart-score data-chart-data="<?= e(json_encode([
                            'type' => 'line',
                            'data' => [
                                'labels' => array_column($analysis['points'], 'label'),
                                'datasets' => [
                                    [
                                        'label' => 'Period overall average',
                                        'data' => array_column($analysis['points'], 'overall_avg'),
                                        'borderColor' => '#172033',
                                        'backgroundColor' => 'rgba(23,32,51,.08)',
                                        'tension' => 0,
                                        'pointRadius' => 4,
                                    ],
                                    [
                                        'label' => 'Regression line ' . $analysis['model_name'],
                                        'data' => array_map(static fn (array $p): float => $p['y'], $trendLine),
                                        'borderColor' => '#b8913f',
                                        'borderDash' => [6, 4],
                                        'pointRadius' => 0,
                                        'fill' => false,
                                    ],
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></canvas></div>
                        <p class="chart-note">Observed period averages and the fitted Simple Linear Regression line. The final point is the forecast for the next period.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="card">
                <div class="card__head"><h2>Period data used</h2></div>
                <div class="card__body card__body--flush">
                    <?php if (empty($analysis['points'])): ?>
                        <p class="muted">No published observation periods yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table table--compact">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="num">Obs</th>
                                        <th class="num">Teaching</th>
                                        <th class="num">Personality</th>
                                        <th class="num">Overall</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analysis['points'] as $point): ?>
                                        <tr>
                                            <td><?= e($point['label']) ?></td>
                                            <td class="num"><?= (int) $point['observation_count'] ?></td>
                                            <td class="num"><?= e(score($point['teaching_avg'])) ?></td>
                                            <td class="num"><?= e(score($point['personality_avg'])) ?></td>
                                            <td class="num"><?= e(score($point['overall_avg'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2>Generated runs</h2></div>
                <div class="card__body card__body--flush">
                    <?php if ($history === []): ?>
                        <p class="muted">No prediction runs have been persisted yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table table--compact">
                                <thead>
                                    <tr>
                                        <th>Generated</th>
                                        <th class="num">Periods</th>
                                        <th class="num">Predicted</th>
                                        <th class="num">MAE</th>
                                        <th class="num">RMSE</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $run): ?>
                                        <tr>
                                            <td><?= e(fmt_datetime($run['generated_at'])) ?></td>
                                            <td class="num"><?= (int) $run['historical_period_count'] ?></td>
                                            <td class="num"><?= e(score($run['predicted_score'])) ?></td>
                                            <td class="num"><?= e($run['mae'] !== null ? $run['mae'] : '—') ?></td>
                                            <td class="num"><?= e($run['rmse'] !== null ? $run['rmse'] : '—') ?></td>
                                            <td><?= status_badge($run['model_status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Latest runs (all faculty)</h2></div>
            <div class="card__body card__body--flush">
                <?php if ($latestRuns === []): ?>
                    <p class="muted">No prediction runs yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Faculty</th>
                                    <th>Department</th>
                                    <th class="num">Periods</th>
                                    <th class="num">Predicted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latestRuns as $run): ?>
                                    <tr>
                                        <td><?= e($run['faculty_name']) ?></td>
                                        <td><?= e($run['department_name'] ?? '—') ?></td>
                                        <td class="num"><?= (int) $run['historical_period_count'] ?></td>
                                        <td class="num"><?= e(score($run['predicted_score'])) ?></td>
                                        <td><?= status_badge($run['model_status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Interpretation</h2></div>
            <div class="card__body">
                <blockquote class="small"><?= e($modelName) ?> models <?= e($selectedFaculty !== null ? $selectedFaculty['full_name'] : 'the selected faculty member') ?> using at least <?= (int) $minPeriods ?> distinct published observation periods.</blockquote>
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li>Predictions are estimates, not guarantees.</li>
                    <li>They must be interpreted by the Dean alongside professional judgement.</li>
                    <li>They must never be used to penalise faculty automatically.</li>
                    <li>Insufficient data results in no forecast being produced.</li>
                </ul>
            </div>
        </section>
    </div>
</div>
