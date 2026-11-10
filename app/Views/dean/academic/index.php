<?php
/**
 * @var string $entity
 * @var array $definition
 * @var array $pagination
 * @var string $search
 * @var bool $demo
 */
$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
$columns = $definition['columns'] ?? [];
$singular = $definition['singular'] ?? 'Record';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Academic management</span>
        <h2><?= e($definition['title']) ?></h2>
        <p>Maintain the <?= e(strtolower($definition['title'])) ?> used by schedules, evaluation assignments and analytics. Records are <?= $demo ? 'demonstration' : 'live' ?> data.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('dean/academic/' . $entity . '/create') ?>"><i class="bi bi-plus-lg"></i> New <?= e(strtolower($singular)) ?></a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration scope.</strong> Changes here affect only synthetic demo records and never live institutional data.</span></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/academic/' . $entity) ?>" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="academicSearch">Search</label>
                <input class="form-control" type="search" id="academicSearch" name="search" value="<?= e($search) ?>" placeholder="Search <?= e(strtolower($definition['title'])) ?>">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?= url('dean/academic/' . $entity) ?>"><i class="bi bi-x-lg"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong><?= e($definition['title']) ?></strong>
        <span class="text-muted small"><?= $total ?> record(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-journal-text"></i>No <?= e(strtolower($definition['title'])) ?> found. Create the first record to get started.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?= e($column['label']) ?></th>
                        <?php endforeach; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <?php $value = $row[$column['key']] ?? ''; ?>
                                <td>
                                    <?php if (($column['type'] ?? '') === 'status'): ?>
                                        <?= status_badge((string) $value) ?>
                                    <?php elseif (($column['type'] ?? '') === 'date'): ?>
                                        <?= e(format_date($value)) ?>
                                    <?php elseif (($column['type'] ?? '') === 'current'): ?>
                                        <?php if ((int) $value === 1): ?>
                                            <span class="badge badge-success"><i class="bi bi-check2-circle"></i> Current</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    <?php elseif ($value === null || $value === ''): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?= e((string) $value) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/academic/' . $entity . '/' . (int) $row['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
                                    <form method="post" action="<?= url('dean/academic/' . $entity . '/' . (int) $row['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete this <?= e(strtolower($singular)) ?>? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>
