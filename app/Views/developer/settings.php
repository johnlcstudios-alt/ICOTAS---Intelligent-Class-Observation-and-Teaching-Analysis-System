<?php
/**
 * @var string $developerKey
 * @var array $settings
 * @var array $grouped
 * @var bool $demoMode
 */
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$groupMeta = [
    'general' => ['label' => 'Institution identity', 'icon' => 'bi-building'],
    'academic' => ['label' => 'Academic period', 'icon' => 'bi-calendar3'],
    'privacy' => ['label' => 'Privacy and anonymity', 'icon' => 'bi-shield-lock'],
    'prediction' => ['label' => 'Prediction thresholds', 'icon' => 'bi-activity'],
    'evaluation' => ['label' => 'Evaluation access', 'icon' => 'bi-ui-checks'],
    'demo' => ['label' => 'Demonstration mode', 'icon' => 'bi-database'],
    'uat' => ['label' => 'UAT interpretation', 'icon' => 'bi-patch-check'],
];
$booleanKeys = ['demo_mode', 'privacy_show_comments', 'allow_student_self_scan', 'allow_student_signup', 'maintenance_mode'];
$existingGroups = array_keys($grouped);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Configuration</span>
        <h2>System settings</h2>
        <p>Edit the raw key/value configuration used by every module. Unknown keys are allowed; malformed values can disable features or change analytic thresholds.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= $console('data/system_settings') ?>"><i class="bi bi-table"></i> Browse as rows</a>
        <a class="btn btn-outline-secondary" href="<?= $console('audit?action=developer.settings') ?>"><i class="bi bi-shield-check"></i> Settings audit trail</a>
    </div>
</div>

<div class="alert alert-warning app-alert">
    <i class="bi bi-exclamation-triangle"></i>
    <span><strong>Direct configuration access.</strong> Values are stored as text and interpreted by application code. There is no type validation here, so a wrong value may break sign-in, evaluations, analytics or the public site. Every save and deletion is audited.</span>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-sliders"></i></span>
        <div class="stat-label">Settings</div>
        <div class="stat-value"><?= count($settings) ?></div>
        <div class="stat-note">Across <?= count($grouped) ?> group(s)</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-database"></i></span>
        <div class="stat-label">Data scope</div>
        <div class="stat-value fs-4"><?= $demoMode ? 'Demo' : 'Live' ?></div>
        <div class="stat-note">demo_mode = <?= $demoMode ? '1' : '0' ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-unlock"></i></span>
        <div class="stat-label">Open editing</div>
        <div class="stat-value fs-4">Raw</div>
        <div class="stat-note">No schema-level type checks</div>
    </div>
</div>

<form id="settingsUpdateForm" method="post" action="<?= $console('settings') ?>">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save all settings</button>
    </div>
    <?php foreach ($grouped as $groupKey => $groupSettings): ?>
        <?php $meta = $groupMeta[$groupKey] ?? ['label' => ucwords(str_replace(['_', '-'], ' ', (string) $groupKey)), 'icon' => 'bi-folder']; ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong><i class="bi <?= e($meta['icon']) ?>"></i> <?= e($meta['label']) ?></strong>
                <code class="text-tiny"><?= e($groupKey) ?></code>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                        <tr>
                            <th style="width: 28%;">Key</th>
                            <th style="width: 42%;">Value</th>
                            <th>Last change</th>
                            <th class="text-end">Remove</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groupSettings as $setting): ?>
                            <?php
                            $id = (int) $setting['id'];
                            $key = (string) $setting['setting_key'];
                            $value = (string) ($setting['setting_value'] ?? '');
                            $fieldId = 'setting_' . $id;
                            ?>
                            <tr>
                                <td>
                                    <label class="form-label mb-0" for="<?= e($fieldId) ?>"><code><?= e($key) ?></code></label>
                                    <div class="text-tiny text-muted">ID <?= $id ?></div>
                                </td>
                                <td>
                                    <?php if (in_array($key, $booleanKeys, true)): ?>
                                        <select class="form-select form-select-sm" id="<?= e($fieldId) ?>" name="settings[<?= $id ?>]">
                                            <option value="1" <?= selected($value, '1') ?>>1 — On / Yes</option>
                                            <option value="0" <?= selected($value, '0') ?>>0 — Off / No</option>
                                        </select>
                                    <?php elseif (mb_strlen($value) > 120 || str_contains($value, "\n")): ?>
                                        <textarea class="form-control form-control-sm" id="<?= e($fieldId) ?>" name="settings[<?= $id ?>]" rows="3" maxlength="5000"><?= e($value) ?></textarea>
                                    <?php else: ?>
                                        <input class="form-control form-control-sm" id="<?= e($fieldId) ?>" name="settings[<?= $id ?>]" type="text" value="<?= e($value) ?>" maxlength="5000">
                                    <?php endif; ?>
                                </td>
                                <td class="text-tiny text-muted">
                                    <?= e(format_datetime($setting['updated_at'] ?? null)) ?>
                                    <div>@<?= e($setting['updated_by_username'] ?? 'system') ?></div>
                                </td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        type="submit"
                                        formaction="<?= $console('settings/' . $id . '/delete') ?>"
                                        formmethod="post"
                                        data-confirm="Delete the setting <?= e($key) ?>? Application code will fall back to its default or an empty value.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($grouped)): ?>
        <div class="card mb-4"><div class="empty-state"><i class="bi bi-sliders"></i>No settings exist in this installation.</div></div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save all settings</button>
        <a class="btn btn-outline-secondary" href="<?= $console('settings') ?>">Reset unsaved changes</a>
    </div>
</form>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><strong>Create a setting</strong><span class="text-muted small">Key must be unique</span></div>
            <div class="card-body">
                <form method="post" action="<?= $console('settings/create') ?>" class="row g-3" data-single-submit>
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label" for="newSettingKey">Key</label>
                        <input class="form-control" id="newSettingKey" name="setting_key" maxlength="80" pattern="[a-z0-9][a-z0-9_.-]*" placeholder="feature_flag" required>
                        <div class="form-text">Lowercase letters, numbers, dots, dashes and underscores. Maximum 80 characters.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="newSettingGroup">Group</label>
                        <input class="form-control" id="newSettingGroup" name="setting_group" maxlength="40" list="settingGroups" value="general">
                        <datalist id="settingGroups">
                            <?php foreach ($existingGroups as $groupKey): ?><option value="<?= e($groupKey) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="newSettingValue">Value</label>
                        <input class="form-control" id="newSettingValue" name="setting_value" maxlength="5000">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-plus-circle"></i> Create setting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong>Common settings</strong></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li><code>system_name</code><span class="text-muted">Interface and report name</span></li>
                    <li><code>institution_name</code><span class="text-muted">Institution shown in headers</span></li>
                    <li><code>demo_mode</code><span class="text-muted">1 = synthetic demo workspace</span></li>
                    <li><code>current_academic_year_id</code><span class="text-muted">FK to academic_years</span></li>
                    <li><code>current_semester_id</code><span class="text-muted">FK to semesters</span></li>
                    <li><code>privacy_min_responses</code><span class="text-muted">Minimum group size</span></li>
                    <li class="border-0"><code>prediction_min_responses</code><span class="text-muted">Prediction threshold</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="data-note mt-3">Deleting a setting does not delete code that reads it. Prefer setting a safe value, or create the key again with a corrected value.</div>
