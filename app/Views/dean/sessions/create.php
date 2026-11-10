<?php
/**
 * @var array $assignments
 * @var int $preselect
 * @var bool $demo
 */
$opensDefault = date('Y-m-d\TH:i', time());
$expiresDefault = date('Y-m-d\TH:i', time() + 86400);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Field data collection</span>
        <h2>Open a QR session</h2>
        <p>Choose an assignment and a collection window. Eligible enrolled students are added automatically and can submit once.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/sessions') ?>"><i class="bi bi-arrow-left"></i> Back to sessions</a>
    </div>
</div>

<?php if (empty($assignments)): ?>
    <div class="card"><div class="empty-state"><i class="bi bi-clipboard-x"></i><h3>No assignments available</h3><p class="text-muted">Create an evaluation assignment before opening a QR session.</p><a class="btn btn-primary" href="<?= url('dean/assignments/create') ?>">Create assignment</a></div></div>
<?php else: ?>
    <form method="post" action="<?= url('dean/sessions') ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><strong>Session details</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="sessionAssignment">Evaluation assignment <span class="text-danger">*</span></label>
                        <select class="form-select" id="sessionAssignment" name="assignment_id" required>
                            <option value="">— Select assignment —</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?= (int) $assignment['id'] ?>" <?= selected($preselect ?: old('assignment_id'), $assignment['id']) ?>>
                                    <?= e($assignment['title']) ?> — <?= e($assignment['course_code']) ?> / <?= e($assignment['section_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="sessionOpens">Opens at <span class="text-danger">*</span></label>
                            <input class="form-control" type="datetime-local" id="sessionOpens" name="opens_at" value="<?= e(old('opens_at', $opensDefault)) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sessionExpires">Expires at <span class="text-danger">*</span></label>
                            <input class="form-control" type="datetime-local" id="sessionExpires" name="expires_at" value="<?= e(old('expires_at', $expiresDefault)) ?>" required>
                            <div class="form-text">Maximum window is 30 days.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-ghost" href="<?= url('dean/sessions') ?>">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-qr-code"></i> Create session</button>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><strong>Anonymity by design</strong></div>
                <div class="card-body small text-muted">
                    <ul class="ps-3 mb-0">
                        <li class="mb-2">Eligibility is checked before the form opens; the response itself stores no student identifier.</li>
                        <li class="mb-2">Participation status is stored separately from answers and used only to prevent duplicate submissions.</li>
                        <li class="mb-2">The QR token is stored as a hash; plaintext is recoverable only for authorized re-display.</li>
                        <li>Regenerating the token invalidates previously printed QR codes.</li>
                    </ul>
                </div>
            </div>
            <?php if ($demo): ?>
                <div class="alert alert-warning app-alert mt-3 mb-0"><i class="bi bi-database"></i><span>The session will be created inside the demo dataset.</span></div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
