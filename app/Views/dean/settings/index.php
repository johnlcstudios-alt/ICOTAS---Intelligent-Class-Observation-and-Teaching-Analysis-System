<?php
/**
 * @var array $settings
 * @var array $definitions
 * @var array $years
 * @var array $semesters
 * @var bool $demo
 */
$groups = [
    'general' => ['label' => 'Institution identity', 'icon' => 'bi-building', 'note' => 'Names shown in the interface, reports and transaction pages.'],
    'academic' => ['label' => 'Current academic period', 'icon' => 'bi-calendar3', 'note' => 'Used as the default period for evaluation assignments and dashboards.'],
    'privacy' => ['label' => 'Privacy and anonymity', 'icon' => 'bi-shield-lock', 'note' => 'Controls when anonymized comment excerpts may be displayed.'],
    'prediction' => ['label' => 'Prediction thresholds', 'icon' => 'bi-activity', 'note' => 'Rule-based classification thresholds. Changing these values changes future indicators only.'],
    'evaluation' => ['label' => 'Evaluation access', 'icon' => 'bi-qr-code', 'note' => 'Controls student portal access and default QR session duration.'],
];
$grouped = [];
foreach ($definitions as $key => $definition) {
    $grouped[$definition['group']][$key] = $definition;
}
$currentValue = static function (string $key, string $default = '') use ($settings) {
    return array_key_exists($key, $settings) ? (string) $settings[$key] : $default;
};
$defaults = [
    'privacy_show_comments' => '1',
    'prediction_min_responses' => '5',
    'prediction_min_periods' => '2',
    'prediction_window' => '8',
    'prediction_improving_slope' => '0.08',
    'prediction_declining_slope' => '-0.08',
    'prediction_negative_ratio' => '0.30',
    'prediction_forecast_change' => '0.10',
    'allow_student_self_scan' => '1',
    'default_session_minutes' => '120',
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Governance</span>
        <h2>System settings</h2>
        <p>Configure institution identity, privacy thresholds, prediction parameters and evaluation access. Changes are recorded in the audit log.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/demo') ?>"><i class="bi bi-database-gear"></i> Demo data</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demo mode is ON.</strong> Analytics and management screens are scoped to synthetic records. Reset demo data to return to live records.</span></div>
<?php endif; ?>

<form method="post" action="<?= url('dean/settings') ?>" data-single-submit>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-9">
            <?php foreach ($groups as $groupKey => $group): ?>
                <?php if (empty($grouped[$groupKey])) { continue; } ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <strong><i class="bi <?= e($group['icon']) ?>"></i> <?= e($group['label']) ?></strong>
                        <span class="text-muted small"><?= e($group['note']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($grouped[$groupKey] as $key => $definition): ?>
                                <?php $value = $currentValue($key, $defaults[$key] ?? ''); ?>
                                <?php if ($definition['type'] === 'bool'): ?>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" id="setting_<?= e($key) ?>" name="<?= e($key) ?>" type="checkbox" value="1" <?= checked($value === '1') ?>>
                                            <label class="form-check-label" for="setting_<?= e($key) ?>"><?= e($definition['label']) ?></label>
                                        </div>
                                    </div>
                                <?php elseif ($definition['type'] === 'reference'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="setting_<?= e($key) ?>"><?= e($definition['label']) ?></label>
                                        <select class="form-select" id="setting_<?= e($key) ?>" name="<?= e($key) ?>">
                                            <option value="">Not set</option>
                                            <?php if ($key === 'current_academic_year_id'): ?>
                                                <?php foreach ($years as $year): ?>
                                                    <option value="<?= (int) $year['id'] ?>" <?= selected($value, $year['id']) ?>><?= e($year['name']) ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php foreach ($semesters as $semester): ?>
                                                    <option value="<?= (int) $semester['id'] ?>" <?= selected($value, $semester['id']) ?>><?= e($semester['year'] . ' — ' . $semester['name']) ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                <?php elseif ($definition['type'] === 'int'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="setting_<?= e($key) ?>"><?= e($definition['label']) ?></label>
                                        <input class="form-control" id="setting_<?= e($key) ?>" name="<?= e($key) ?>" type="number" step="1" min="<?= (int) $definition['min'] ?>" max="<?= (int) $definition['max'] ?>" value="<?= e($value) ?>">
                                        <div class="form-text">Allowed range: <?= (int) $definition['min'] ?>–<?= (int) $definition['max'] ?>.</div>
                                    </div>
                                <?php elseif ($definition['type'] === 'decimal'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="setting_<?= e($key) ?>"><?= e($definition['label']) ?></label>
                                        <input class="form-control" id="setting_<?= e($key) ?>" name="<?= e($key) ?>" type="number" step="0.001" min="<?= e($definition['min']) ?>" max="<?= e($definition['max']) ?>" value="<?= e($value) ?>">
                                        <div class="form-text">Allowed range: <?= e($definition['min']) ?> to <?= e($definition['max']) ?>.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="setting_<?= e($key) ?>"><?= e($definition['label']) ?></label>
                                        <input class="form-control" id="setting_<?= e($key) ?>" name="<?= e($key) ?>" type="text" maxlength="<?= (int) ($definition['max'] ?? 255) ?>" value="<?= e($value) ?>">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save settings</button>
                <a class="btn btn-outline-secondary" href="<?= url('dean/settings') ?>">Reset unsaved changes</a>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card mb-4">
                <div class="card-header"><strong>Current state</strong></div>
                <div class="card-body">
                    <dl class="mb-0 small">
                        <dt class="text-muted">System name</dt>
                        <dd><?= e($currentValue('system_name', 'ICOTAS')) ?></dd>
                        <dt class="text-muted">Data scope</dt>
                        <dd><?= $demo ? '<span class="badge badge-demo">Demo</span>' : '<span class="badge badge-success">Live</span>' ?></dd>
                        <dt class="text-muted">Demo mode</dt>
                        <dd>Toggle from the <a href="<?= url('dean/demo') ?>">Demo Data</a> page.</dd>
                        <dt class="text-muted">UAT interpretation</dt>
                        <dd class="mb-0">Fixed thresholds are documented in the UAT module and reports; 4.48 is Satisfactory.</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Integrity notes</strong></div>
                <div class="card-body">
                    <ul class="list-clean small">
                        <li class="d-block mb-2"><i class="bi bi-shield-check text-success"></i> Privacy thresholds apply to comment excerpts on instructor-facing screens.</li>
                        <li class="d-block mb-2"><i class="bi bi-sliders text-primary"></i> Prediction thresholds affect classification labels, not the underlying averages.</li>
                        <li class="d-block mb-2"><i class="bi bi-clock-history text-secondary"></i> Default session length applies to newly created QR sessions.</li>
                        <li class="d-block"><i class="bi bi-journal-check text-secondary"></i> Every save is recorded in the audit log.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
