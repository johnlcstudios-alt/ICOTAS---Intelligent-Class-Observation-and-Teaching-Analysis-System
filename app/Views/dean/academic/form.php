<?php
/**
 * @var string $entity
 * @var array $definition
 * @var array|null $record
 * @var array $fields
 * @var bool $demo
 */
use App\Core\View;

$isEdit = !empty($record);
$action = $isEdit
    ? url('dean/academic/' . $entity . '/' . (int) $record['id'])
    : url('dean/academic/' . $entity);
$singular = $definition['singular'] ?? 'Record';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Academic management</span>
        <h2><?= $isEdit ? 'Edit ' . e(strtolower($singular)) : 'New ' . e(strtolower($singular)) ?></h2>
        <p><?= e($definition['title']) ?> &middot; <?= $demo ? 'demonstration scope' : 'live scope' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/academic/' . $entity) ?>"><i class="bi bi-arrow-left"></i> Back to <?= e(strtolower($definition['title'])) ?></a>
    </div>
</div>

<?php if ($isEdit && !empty($record['is_demo'])): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration record.</strong> Changes remain isolated inside the demo dataset.</span></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header"><strong><?= e($singular) ?> details</strong><span class="text-muted small">Fields marked * are required</span></div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($fields as $field): ?>
                    <?php View::partial('partials.form-field', ['field' => $field]); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a class="btn btn-ghost" href="<?= url('dean/academic/' . $entity) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Create ' . e(strtolower($singular)) ?></button>
        </div>
    </div>
</form>
