<?php
/**
 * Printable report sheet.
 *
 * @var array $report
 */
use App\Core\Auth;

$base = Auth::isAdmin() ? 'dean' : 'instructor';
$charts = $report['charts'] ?? [];
$sections = $report['sections'] ?? [];
$summary = $report['summary'] ?? [];
$narrative = $report['narrative'] ?? [];
$generated = $report['generated_at'] ?? date('Y-m-d H:i:s');
?>
<div class="report-actions no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url($base . '/reports') ?>"><i class="bi bi-arrow-left"></i> Back to reports</a>
    <form method="post" action="<?= url($base . '/reports/export') ?>" class="d-flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($report['type']) ?>">
        <?php foreach (($report['filters'] ?? []) as $key => $value): ?>
            <?php if ($value !== null && $value !== ''): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button class="btn btn-sm btn-outline-primary" type="submit" name="format" value="csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>
        <button class="btn btn-sm btn-outline-primary" type="submit" name="format" value="pdf"><i class="bi bi-filetype-pdf"></i> Export PDF</button>
    </form>
</div>

<header class="report-header">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <div class="report-institution"><?= e($report['institution'] ?? setting('institution_name', 'ACLC College Tacloban City')) ?></div>
            <h1><?= e($report['title'] ?? 'Report') ?></h1>
            <div class="report-meta">
                <span><strong>Period:</strong> <?= e($report['period'] ?? 'All recorded academic periods') ?></span>
                <span><strong>Generated:</strong> <?= e(format_datetime($generated)) ?></span>
                <span><strong>System:</strong> <?= e(setting('system_name', 'ICOTAS')) ?></span>
            </div>
        </div>
        <div class="text-end">
            <span class="badge <?= !empty($report['demo']) ? 'badge-demo' : 'badge-soft' ?>"><?= !empty($report['demo']) ? 'Demo data' : 'Live data' ?></span>
            <div class="report-code"><?= e(strtoupper(str_replace('_', ' ', $report['type'] ?? ''))) ?></div>
        </div>
    </div>
    <p class="report-data-note mb-0"><?= e($report['data_note'] ?? '') ?></p>
</header>

<?php if (!empty($summary)): ?>
    <h2>Summary</h2>
    <dl class="report-summary">
        <?php foreach ($summary as $label => $value): ?>
            <div>
                <dt><?= e($label) ?></dt>
                <dd><?= e($value !== '' && $value !== null ? $value : '—') ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>

<?php if (!empty($charts)): ?>
    <h2>Visual overview</h2>
    <div class="report-charts">
        <?php foreach ($charts as $chart): ?>
            <?php
            $values = array_map('floatval', $chart['values'] ?? []);
            $labels = $chart['labels'] ?? [];
            if (empty($values)) {
                continue;
            }
            $declaredMax = (float) ($chart['max'] ?? 0);
            $scaleMax = $declaredMax > 0 ? $declaredMax : max(1.0, max($values));
            $isRating = $declaredMax > 0;
            ?>
            <section class="report-chart">
                <h3><?= e($chart['title'] ?? 'Chart') ?></h3>
                <div class="report-chart-rows">
                    <?php foreach ($values as $index => $value): ?>
                        <div class="report-chart-row">
                            <span class="report-chart-label"><?= e($labels[$index] ?? 'Item ' . ($index + 1)) ?></span>
                            <span class="report-chart-track" aria-hidden="true"><span style="width: <?= max(0, min(100, $scaleMax > 0 ? ($value / $scaleMax) * 100 : 0)) ?>%"></span></span>
                            <span class="report-chart-value"><?= e($isRating ? format_rating($value) : (string) (int) $value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($isRating): ?>
                    <div class="report-chart-scale">Scale: 1.00 – <?= e(format_rating($scaleMax)) ?></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sections)): ?>
    <?php foreach ($sections as $section): ?>
        <section class="report-section">
            <h2><?= e($section['title'] ?? 'Results') ?></h2>
            <?php if (empty($section['rows'])): ?>
                <p class="report-empty">No records matched this report and its filters.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <?php foreach (($section['columns'] ?? []) as $column): ?>
                            <th><?= e($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($section['rows'] as $row): ?>
                        <tr>
                            <?php foreach (array_values($row) as $cell): ?>
                                <td><?= e($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($narrative)): ?>
    <section class="report-section">
        <h2>Findings and interpretation</h2>
        <div class="narrative">
            <?php foreach ($narrative as $paragraph): ?>
                <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="report-section report-method">
    <h2>Method and limitations</h2>
    <ul>
        <li>Numeric ratings are normalized to a five-point scale before aggregation. Means and population standard deviations are descriptive; they do not establish causation.</li>
        <li>No student identity is included in response data. Written comments are anonymized excerpts and may have been privacy-filtered.</li>
        <li>Rule-Based AI-Assisted Sentiment and Theme Analysis is a keyword and pattern method. It can misclassify sarcasm or context and does not replace human review.</li>
        <li>Predictive indicators use a weighted moving average and least-squares linear slope only. They are not validated machine-learning forecasts and must not drive automatic personnel decisions.</li>
        <li>Institutional policy withholds small-group results elsewhere in the system. Aggregate comparisons should account for class size, course difficulty and response bias.</li>
        <?php if (!empty($report['demo'])): ?>
            <li><strong>This report uses synthetic demonstration data and is not institutional research evidence.</strong></li>
        <?php endif; ?>
    </ul>
</section>

<footer class="report-footer">
    <span><?= e(setting('system_name', 'ICOTAS')) ?> · <?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></span>
    <span>Generated <?= e(format_datetime($generated)) ?> · Human judgment required</span>
</footer>
