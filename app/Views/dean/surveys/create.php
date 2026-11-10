<?php
/**
 * @var array $categories
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Instrument management</span>
        <h2>New survey</h2>
        <p>Create the survey shell. Questions are added in the builder, then published when the questionnaire is final.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/surveys') ?>"><i class="bi bi-arrow-left"></i> Back to surveys</a>
    </div>
</div>

<form method="post" action="<?= url('dean/surveys') ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><strong>Survey details</strong><span class="text-muted small">Fields marked * are required</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="surveyTitle">Survey title <span class="text-danger">*</span></label>
                        <input class="form-control" id="surveyTitle" name="title" value="<?= e(old('title')) ?>" maxlength="180" required placeholder="e.g. Student Evaluation of Teaching Effectiveness">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="surveyCategory">Category</label>
                        <select class="form-select" id="surveyCategory" name="category">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category) ?>" <?= selected(old('category', 'Teaching Effectiveness'), $category) ?>><?= e($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="surveyDescription">Description</label>
                        <textarea class="form-control" id="surveyDescription" name="description" rows="3" maxlength="2000" placeholder="Purpose of this instrument, as shown to respondents."><?= e(old('description')) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a class="btn btn-ghost" href="<?= url('dean/surveys') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-right"></i> Create and open builder</button>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><strong>How versioning works</strong></div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-0">
                    <li class="mb-2">A new survey starts as <strong>version 1 draft</strong>.</li>
                    <li class="mb-2">Add or reorder questions, then save the draft.</li>
                    <li class="mb-2">Publish the version when it is final. Published versions are locked.</li>
                    <li>To change a published instrument, create a <strong>new version</strong>. Previous assignments and responses keep their original version.</li>
                </ol>
            </div>
        </div>
        <?php if ($demo): ?>
            <div class="alert alert-warning app-alert mt-3 mb-0"><i class="bi bi-database"></i><span>The survey will be created inside the demo dataset.</span></div>
        <?php endif; ?>
    </div>
</form>
