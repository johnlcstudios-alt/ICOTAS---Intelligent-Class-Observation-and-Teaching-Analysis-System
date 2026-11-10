<?php
/**
 * @var array $assignments
 * @var array $filters
 * @var array $instructors
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Evaluation cycle</span>
        <h2>Evaluation assignments</h2>
        <p>Bind a published questionnaire version to an instructor, course and section. QR sessions then enroll eligible students automatically.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('dean/assignments/create') ?>"><i class="bi bi-plus-lg"></i> New assignment</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration scope.</strong> Only synthetic assignments are shown.</span></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/assignments') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="assignmentSearch">Search</label>
                <input class="form-control" type="search" id="assignmentSearch" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Title, course, section, instructor">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="assignmentInstructor">Instructor</label>
                <select class="form-select" id="assignmentInstructor" name="instructor_id">
                    <option value="">All instructors</option>
                    <?php foreach ($instructors as $instructor): ?>
                        <option value="<?= (int) $instructor['id'] ?>" <?= selected($filters['instructor_id'] ?? '', $instructor['id']) ?>><?= e(full_name($instructor)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="assignmentStatus">Status</label>
                <select class="form-select" id="assignmentStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['draft', 'scheduled', 'active', 'closed', 'archived'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="<?= url('dean/assignments') ?>" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Assignments</strong><span class="text-muted small"><?= count($assignments) ?> record(s)</span></div>
    <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
            <div class="empty-state"><i class="bi bi-clipboard-check"></i><h3>No assignments found</h3><p class="text-muted">Create an assignment to start collecting evaluations.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Assignment</th><th>Instructor</th><th>Class</th><th>Questionnaire</th><th>Window</th><th class="text-center">Sessions</th><th class="text-center">Responses</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td>
                                <strong class="d-block"><?= e($assignment['title']) ?></strong>
                                <span class="text-tiny text-muted"><?= e(ucwords(str_replace('_', ' ', (string) $assignment['evaluation_type']))) ?> · <?= e($assignment['academic_year_name']) ?> <?= e($assignment['semester_name']) ?></span>
                            </td>
                            <td><?= e(full_name($assignment)) ?></td>
                            <td><span class="badge badge-soft"><?= e($assignment['course_code']) ?></span> <?= e($assignment['section_code']) ?></td>
                            <td class="small">v<?= (int) $assignment['version_number'] ?> · <?= e(str_limit($assignment['survey_title'], 40)) ?></td>
                            <td class="small"><?= e(format_date($assignment['opens_at'])) ?><br><span class="text-muted">to <?= e(format_date($assignment['closes_at'])) ?></span></td>
                            <td class="text-center"><?= (int) $assignment['session_count'] ?></td>
                            <td class="text-center"><?= (int) $assignment['response_count'] ?></td>
                            <td><?= status_badge((string) $assignment['status']) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/assignments/' . (int) $assignment['id'] . '/edit') ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= url('dean/sessions/create?assignment_id=' . (int) $assignment['id']) ?>" title="Open QR session"><i class="bi bi-qr-code"></i></a>
                                    <?php if ((int) $assignment['session_count'] === 0): ?>
                                        <form method="post" action="<?= url('dean/assignments/' . (int) $assignment['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete this assignment? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
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
