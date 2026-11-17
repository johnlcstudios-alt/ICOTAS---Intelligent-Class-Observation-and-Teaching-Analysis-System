<?php
/**
 * @var string $developerKey
 * @var string $table
 * @var array|null $row
 * @var array $columns
 * @var array $foreignOptions
 * @var string|null $primaryKey
 */
$isEdit = $row !== null;
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$tableUrl = $console('data/' . rawurlencode($table));
$rowId = $isEdit && $primaryKey !== null ? ($row[$primaryKey] ?? null) : null;
$action = $isEdit && $rowId !== null
    ? $tableUrl . '/' . rawurlencode((string) $rowId)
    : $tableUrl;
$old = old('fields', []);
$old = is_array($old) ? $old : [];
$valueFor = static function (array $column) use ($isEdit, $row, $old) {
    if (array_key_exists($column['name'], $old)) {
        return $old[$column['name']];
    }
    return $isEdit ? ($row[$column['name']] ?? null) : $column['default'];
};
$displayStatic = static function (array $column, $value): string {
    if (!empty($column['is_sensitive'])) {
        return '<span class="text-muted"><i class="bi bi-lock"></i> protected value</span>';
    }
    if (!empty($column['is_binary'])) {
        return '<span class="badge badge-soft">&lt;binary&gt;</span>';
    }
    if ($value === null) {
        return '<span class="text-muted">NULL</span>';
    }
    if (($column['input_type'] ?? '') === 'boolean') {
        return (string) $value === '1' ? 'Yes' : 'No';
    }
    return '<code>' . e(str_limit((string) $value, 160)) . '</code>';
};
$editableColumns = array_values(array_filter($columns, static fn (array $column): bool => !empty($column['editable'])));
$systemColumns = array_values(array_filter($columns, static fn (array $column): bool => empty($column['editable'])));
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Data browser · <code><?= e($table) ?></code></span>
        <h2><?= $isEdit ? 'Edit row' : 'New row' ?></h2>
        <p><?= $isEdit ? 'Update the editable columns of this record. Primary keys, generated values and protected columns are read-only.' : 'Insert a record directly. Auto-increment, generated, binary and protected columns are filled by the database or deliberately omitted.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $tableUrl ?>"><i class="bi bi-arrow-left"></i> Back to <?= e($table) ?></a>
    </div>
</div>

<?php if ($isEdit && $rowId === null): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-exclamation-triangle"></i><span>This table has no single-column primary key, so this record cannot be updated or deleted safely.</span></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="row g-3" data-single-submit>
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <strong>Column values</strong>
                <span class="text-muted small"><?= count($editableColumns) ?> editable column(s)</span>
            </div>
            <div class="card-body">
                <?php if ($editableColumns === []): ?>
                    <p class="text-muted mb-0">This table has no columns that the data browser can edit. It may be entirely generated or protected.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($editableColumns as $column): ?>
                            <?php
                            $name = (string) $column['name'];
                            $fieldId = 'field_' . preg_replace('/[^A-Za-z0-9_]/', '_', $name);
                            $value = $valueFor($column);
                            $value = is_array($value) ? '' : $value;
                            $stringValue = $value === null ? '' : (string) $value;
                            $isPrimaryOnEdit = $isEdit && !empty($column['is_primary']);
                            $required = !empty($column['required']) && $stringValue === '';
                            $hint = [];
                            if ($column['foreign'] !== null) {
                                $hint[] = 'References ' . $column['foreign']['table'] . '.' . $column['foreign']['column'];
                            }
                            $hint[] = $column['column_type'];
                            if ($column['nullable']) {
                                $hint[] = 'nullable';
                            }
                            if ($column['default'] !== null) {
                                $hint[] = 'default: ' . (string) $column['default'];
                            }
                            if ($isPrimaryOnEdit) {
                                $hint[] = 'primary key is read-only';
                            }
                            ?>
                            <div class="<?= in_array($column['input_type'], ['textarea'], true) ? 'col-12' : 'col-md-6' ?>">
                                <label class="form-label" for="<?= e($fieldId) ?>">
                                    <?= e($column['label']) ?>
                                    <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
                                    <?php if ($column['is_primary']): ?><i class="bi bi-key-fill text-warning" title="Primary key"></i><?php endif; ?>
                                </label>

                                <?php if ($isPrimaryOnEdit): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" type="text" value="<?= e($stringValue) ?>" readonly>
                                <?php elseif (in_array($column['input_type'], ['readonly'], true)): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" type="text" value="[not editable]" readonly>
                                <?php elseif ($column['input_type'] === 'boolean'): ?>
                                    <select class="form-select" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>>
                                        <?php if ($column['nullable']): ?><option value="" <?= selected($stringValue, '') ?>>— NULL —</option><?php endif; ?>
                                        <option value="1" <?= selected($stringValue, '1') ?>>Yes / 1</option>
                                        <option value="0" <?= selected($stringValue, '0') ?>>No / 0</option>
                                    </select>
                                <?php elseif ($column['input_type'] === 'enum'): ?>
                                    <select class="form-select" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>>
                                        <?php if ($column['nullable'] || !$required): ?><option value="">— <?= $column['nullable'] ? 'NULL' : 'Use default' ?> —</option><?php endif; ?>
                                        <?php foreach ($column['enum_values'] as $enumValue): ?>
                                            <option value="<?= e($enumValue) ?>" <?= selected($stringValue, $enumValue) ?>><?= e($enumValue) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($column['input_type'] === 'foreign'): ?>
                                    <select class="form-select" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>>
                                        <option value="">— <?= $column['nullable'] ? 'NULL' : 'None' ?> —</option>
                                        <?php foreach (($foreignOptions[$name] ?? []) as $option): ?>
                                            <option value="<?= e($option['value']) ?>" <?= selected($stringValue, $option['value']) ?>><?= e($option['label'] . ' (#' . $option['value'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($column['input_type'] === 'textarea'): ?>
                                    <textarea class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" rows="4" <?= $column['max_length'] !== null ? 'maxlength="' . (int) $column['max_length'] . '"' : '' ?> <?= $required ? 'required' : '' ?>><?= e($stringValue) ?></textarea>
                                <?php elseif ($column['input_type'] === 'date'): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" type="date" value="<?= e($stringValue) ?>" <?= $required ? 'required' : '' ?>>
                                <?php elseif ($column['input_type'] === 'datetime'): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" type="text" value="<?= e($stringValue) ?>" placeholder="YYYY-MM-DD HH:MM:SS" <?= $required ? 'required' : '' ?>>
                                <?php elseif ($column['input_type'] === 'time'): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" type="time" step="1" value="<?= e($stringValue) ?>" <?= $required ? 'required' : '' ?>>
                                <?php elseif (in_array($column['input_type'], ['number', 'decimal', 'year'], true)): ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" type="number" step="<?= $column['input_type'] === 'decimal' ? 'any' : '1' ?>" value="<?= e($stringValue) ?>" <?= $required ? 'required' : '' ?>>
                                <?php else: ?>
                                    <input class="form-control" id="<?= e($fieldId) ?>" name="fields[<?= e($name) ?>]" type="text" value="<?= e($stringValue) ?>" <?= $column['max_length'] !== null ? 'maxlength="' . (int) $column['max_length'] . '"' : '' ?> <?= $required ? 'required' : '' ?>>
                                <?php endif; ?>

                                <div class="form-text"><?= e(implode(' · ', $hint)) ?><?= $column['comment'] !== '' ? e(' · ' . $column['comment']) : '' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($systemColumns !== []): ?>
            <div class="card mt-3">
                <div class="card-header"><strong>System-managed columns</strong><span class="text-muted small">Read-only</span></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Column</th><th>Type</th><th>Value</th><th>Reason</th></tr></thead>
                            <tbody>
                            <?php foreach ($systemColumns as $column): ?>
                                <?php $value = $isEdit ? ($row[$column['name']] ?? null) : null; ?>
                                <tr>
                                    <td><code><?= e($column['name']) ?></code></td>
                                    <td class="text-tiny text-muted"><?= e($column['column_type']) ?></td>
                                    <td class="text-tiny"><?= $isEdit ? $displayStatic($column, $value) : '<span class="text-muted">Set on insert</span>' ?></td>
                                    <td class="text-tiny text-muted">
                                        <?= $column['is_sensitive'] ? 'Protected' : ($column['is_binary'] ? 'Binary' : ($column['is_generated'] ? 'Generated' : ($column['is_auto_increment'] ? 'Auto-increment' : 'Read-only'))) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><strong><?= $isEdit ? 'Save row' : 'Insert row' ?></strong><?php if ($rowId !== null): ?><span class="badge badge-soft">#<?= e((string) $rowId) ?></span><?php endif; ?></div>
            <div class="card-body">
                <p class="text-muted small">Database constraints, foreign keys and column types are still enforced. A failed write leaves the record unchanged.</p>
                <button class="btn btn-primary w-100" type="submit" <?= $editableColumns === [] ? 'disabled' : '' ?>><i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Insert row' ?></button>
                <a class="btn btn-ghost w-100 mt-2" href="<?= $tableUrl ?>">Cancel</a>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body small text-muted">
                <div><strong>Table:</strong> <code><?= e($table) ?></code></div>
                <div><strong>Primary key:</strong> <?= $primaryKey !== null ? '<code>' . e($primaryKey) . '</code>' : 'none' ?></div>
                <div><strong>Columns:</strong> <?= count($columns) ?></div>
                <div><strong>Mode:</strong> <?= $isEdit ? 'Update existing row' : 'Insert new row' ?></div>
            </div>
        </div>

        <?php if ($isEdit && $rowId !== null): ?>
            <div class="card mt-3 developer-danger">
                <div class="card-header"><strong class="text-danger">Delete row</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= $tableUrl . '/' . rawurlencode((string) $rowId) . '/delete' ?>" data-confirm="Delete row #<?= e((string) $rowId) ?> from <?= e($table) ?>? This cannot be undone.">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash"></i> Delete this row</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</form>
