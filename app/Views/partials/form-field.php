<?php
/**
 * Generic field renderer for definition-driven academic forms.
 * Expects $field = [name,label,type,value,required,options,...].
 */
$name = $field['name'] ?? '';
$label = $field['label'] ?? ucfirst($name);
$type = $field['type'] ?? 'text';
$value = $field['value'] ?? '';
$required = !empty($field['required']);
$id = 'field_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
$col = (int) ($field['col'] ?? 12);
$attributes = [];
if ($required) {
    $attributes[] = 'required';
}
if (!empty($field['maxlength']) || !empty($field['max'])) {
    if ($type === 'text' || $type === 'textarea') {
        $attributes[] = 'maxlength="' . (int) $field['max'] . '"';
    }
}
if (isset($field['min']) && $type === 'number') {
    $attributes[] = 'min="' . e($field['min']) . '"';
}
if (isset($field['max']) && $type === 'number') {
    $attributes[] = 'max="' . e($field['max']) . '"';
}
if (!empty($field['step'])) {
    $attributes[] = 'step="' . e($field['step']) . '"';
}
if (!empty($field['placeholder'])) {
    $attributes[] = 'placeholder="' . e($field['placeholder']) . '"';
}
?>
<div class="col-md-<?= $col ?>">
    <?php if ($type === 'checkbox'): ?>
        <div class="form-check form-switch mt-md-4 pt-md-2">
            <input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1" <?= checked($value) ?>>
            <label class="form-check-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        </div>
    <?php else: ?>
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?></label>
        <?php if ($type === 'select'): ?>
            <select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                <?php if (!empty($field['empty'])): ?>
                    <option value=""><?= e($field['empty']) ?></option>
                <?php elseif (!$required): ?>
                    <option value="">— Select —</option>
                <?php endif; ?>
                <?php foreach (($field['options'] ?? []) as $option): ?>
                    <option value="<?= e($option['value']) ?>" <?= selected($value, $option['value']) ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($field['rows'] ?? 3) ?>" <?= implode(' ', $attributes) ?>><?= e($value) ?></textarea>
        <?php else: ?>
            <input class="form-control" type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($type === 'checkbox' ? '' : $value) ?>" <?= implode(' ', $attributes) ?>>
        <?php endif; ?>
        <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
    <?php endif; ?>
</div>
