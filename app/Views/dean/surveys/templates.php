<?php
/**
 * @var array $templates
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Instrument management</span>
        <h2>Survey templates</h2>
        <p>Start from a curated instrument. Cloning a template creates a new draft survey that you can adapt freely.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/surveys') ?>"><i class="bi bi-arrow-left"></i> Back to surveys</a>
        <a class="btn btn-primary" href="<?= url('dean/surveys/create') ?>"><i class="bi bi-plus-lg"></i> Blank survey</a>
    </div>
</div>

<?php if (empty($templates)): ?>
    <div class="card"><div class="empty-state"><i class="bi bi-collection"></i><h3>No templates available</h3><p class="text-muted">Templates are installed with the application. Create a blank survey instead.</p></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($templates as $template): ?>
            <div class="col-lg-6 col-xxl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <span class="badge badge-soft mb-2 align-self-start"><i class="bi bi-collection"></i> Template</span>
                        <h3 class="h5"><?= e($template['name']) ?></h3>
                        <p class="text-muted small mb-3"><?= e(str_limit($template['description'] ?? $template['survey_description'] ?? '', 160) ?: 'No description.') ?></p>
                        <div class="mt-auto text-muted small">
                            <i class="bi bi-list-ol"></i> <?= (int) $template['question_count'] ?> question(s) in the published definition
                        </div>
                    </div>
                    <div class="card-footer">
                        <form method="post" action="<?= url('dean/surveys/templates/' . (int) $template['id'] . '/clone') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-copy"></i> Use this template</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
