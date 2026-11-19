<?php
/**
 * @var string|null $developerKey
 * @var string|null $consoleBase
 * @var string $table
 * @var array $columns
 * @var array $pagination
 * @var string $search
 * @var string|null $primaryKey
 * @var bool $compositePrimaryKey
 */
$consoleBase = $consoleBase ?? ('developer/' . rawurlencode((string) ($developerKey ?? '')));
$console = static fn (string $path = ''): string => url($consoleBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$tableUrl = $console('data/' . rawurlencode($table));
$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
$hasSingleKey = $primaryKey !== null && !$compositePrimaryKey;
$renderCell = static function (array $column, $value): string {
    if (!empty($column['is_sensitive'])) {
        return '<span class="text-muted"><i class="bi bi-lock"></i> protected</span>';
    }
    if (!empty($column['is_binary'])) {
        return '<span class="badge badge-soft">&lt;binary&gt;</span>';
    }
    if ($value === null) {
        return '<span class="text-muted">NULL</span>';
    }
    if (($column['input_type'] ?? '') === 'boolean') {
        return (string) $value === '1'
            ? '<span class="badge badge-success">Yes</span>'
            : '<span class="badge badge-secondary">No</span>';
    }
    if (($column['input_type'] ?? '') === 'enum') {
        return '<span class="badge badge-soft">' . e($value) . '</span>';
    }
    $text = (string) $value;
    if (mb_strlen($text) > 90) {
        $text = mb_substr($text, 0, 89) . '…';
    }
    return '<span class="text-tiny">' . e($text) . '</span>';
};
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Data browser</span>
        <h2><code><?= e($table) ?></code></h2>
        <p><?= number_format($total) ?> row(s). Search covers the first eight text columns. Sensitive values are masked and cannot be edited through this screen.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $console('data') ?>"><i class="bi bi-arrow-left"></i> All tables</a>
        <a class="btn btn-primary" href="<?= $tableUrl . '/create' ?>"><i class="bi bi-plus-lg"></i> New row</a>
    </div>
</div>

<?php if ($compositePrimaryKey): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-key"></i><span><strong>Composite primary key.</strong> Rows can be created, but editing and deleting are disabled because the generic browser cannot safely address a single record.</span></div>
<?php elseif ($primaryKey === null): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-key"></i><span><strong>No single-column primary key.</strong> Rows can be created, but editing and deleting are disabled for safety.</span></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= $tableUrl ?>" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="rowSearch">Search this table</label>
                <input class="form-control" type="search" id="rowSearch" name="search" value="<?= e($search) ?>" placeholder="Contains text…">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary" href="<?= $tableUrl ?>" aria-label="Reset search"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Rows</strong>
        <span class="text-muted small">
            Primary key: <?= $primaryKey !== null ? '<code>' . e($primaryKey) . '</code>' : 'none' ?>
            <?= $compositePrimaryKey ? ' (composite)' : '' ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-inbox"></i>No rows match this view.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle developer-mono">
                    <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th class="text-nowrap">
                                <?= e($column['name']) ?>
                                <?php if ($column['is_primary']): ?><i class="bi bi-key-fill text-warning" title="Primary key"></i><?php endif; ?>
                                <span class="d-block text-tiny text-muted fw-normal"><?= e($column['data_type']) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $rowId = $hasSingleKey ? ($row[$primaryKey] ?? null) : null; ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <td class="text-nowrap align-top"><?= $renderCell($column, $row[$column['name']] ?? null) ?></td>
                            <?php endforeach; ?>
                            <td class="text-end align-top">
                                <?php if ($rowId !== null): ?>
                                    <div class="table-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= $tableUrl . '/' . rawurlencode((string) $rowId) . '/edit' ?>"><i class="bi bi-pencil"></i> Edit</a>
                                        <form method="post" action="<?= $tableUrl . '/' . rawurlencode((string) $rowId) . '/delete' ?>" class="d-inline" data-confirm="Delete row #<?= e((string) $rowId) ?> from <?= e($table) ?>? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>

<div class="data-note mt-3">Values marked <span class="badge badge-soft">&lt;binary&gt;</span> or <i class="bi bi-lock"></i> protected are never rendered in plaintext. Writes reject arrays, invalid numbers, malformed dates and values outside enum or column-length limits.</div>
