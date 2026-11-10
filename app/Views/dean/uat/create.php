<?php
/**
 * @var array $characteristics
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">ISO/IEC 25010 validation</span>
        <h2>New UAT survey</h2>
        <p>Create an invitation-only acceptance survey. The standard question bank covers six ISO/IEC 25010 characteristics.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/uat') ?>"><i class="bi bi-arrow-left"></i> UAT surveys</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span>This survey will be flagged as demonstration data while demo mode is active.</span></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><strong>Survey details</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/uat') ?>" data-single-submit>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="uatTitle">Title <span class="text-danger">*</span></label>
                        <input class="form-control" id="uatTitle" name="title" type="text" maxlength="180" required value="<?= e(old('title')) ?>" placeholder="e.g., ICOTAS User Acceptance Testing — Semester 2, 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="uatDescription">Description</label>
                        <textarea class="form-control" id="uatDescription" name="description" rows="4" maxlength="3000" placeholder="Purpose, respondent group and testing context."><?= e(old('description')) ?></textarea>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" id="uatStandard" name="standard_questions" type="checkbox" value="1" checked>
                        <label class="form-check-label" for="uatStandard">
                            Include the standard ISO/IEC 25010 question bank
                            <span class="d-block text-muted small">18 statements · 3 per characteristic. You can add or remove statements after creation.</span>
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Create UAT survey</button>
                        <a class="btn btn-outline-secondary" href="<?= url('dean/uat') ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong>Covered characteristics</strong><span class="text-muted small">ISO/IEC 25010</span></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($characteristics as $characteristic): ?>
                        <span class="badge badge-soft"><?= e($characteristic) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mb-0">Respondents rate each statement from 1 (Very Poor) to 5 (Very Satisfactory). Results are reported per characteristic and overall using means and population standard deviations.</p>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header"><strong>Next steps</strong></div>
            <div class="card-body">
                <ol class="small mb-0 ps-3">
                    <li class="mb-1">Review the statement bank.</li>
                    <li class="mb-1">Set the survey schedule and activate it.</li>
                    <li class="mb-1">Add invited respondents and share each single-use link.</li>
                    <li>Review aggregated results and generate the UAT report.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
