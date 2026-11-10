<?php
/**
 * @var array|null $assignment
 * @var array $versions
 * @var array $instructors
 * @var array $courses
 * @var array $sections
 * @var array $years
 * @var array $semesters
 * @var bool $demo
 */
$isEdit = !empty($assignment);
$action = $isEdit ? url('dean/assignments/' . (int) $assignment['id']) : url('dean/assignments');
$currentVersion = old('survey_version_id', $assignment['survey_version_id'] ?? '');
$toLocal = static function ($value): string {
    $ts = $value ? strtotime((string) $value) : false;
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
$opensDefault = date('Y-m-d\TH:i', time());
$closesDefault = date('Y-m-d\TH:i', time() + 7 * 86400);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Evaluation cycle</span>
        <h2><?= $isEdit ? 'Edit assignment' : 'New evaluation assignment' ?></h2>
        <p>Only published questionnaire versions can be assigned. An assignment locks the version used for every response.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/assignments') ?>"><i class="bi bi-arrow-left"></i> Back to assignments</a>
    </div>
</div>

<?php if ($isEdit && (int) ($assignment['session_count'] ?? 0) === 0): ?>
    <div class="alert alert-info app-alert"><i class="bi bi-info-circle"></i><span>The questionnaire version remains editable until the first QR session is created.</span></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="_return" value="<?= e($isEdit ? 'dean/assignments/' . (int) $assignment['id'] . '/edit' : 'dean/assignments/create') ?>">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><strong>Assignment details</strong><span class="text-muted small">Fields marked * are required</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="assignmentTitle">Title <span class="text-danger">*</span></label>
                        <input class="form-control" id="assignmentTitle" name="title" value="<?= e(old('title', $assignment['title'] ?? '')) ?>" maxlength="180" required placeholder="e.g. Midterm Student Evaluation — CS101">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assignmentType">Evaluation type</label>
                        <select class="form-select" id="assignmentType" name="evaluation_type">
                            <?php foreach (['student_feedback' => 'Student feedback', 'peer_observation' => 'Peer observation', 'self_assessment' => 'Self assessment', 'supervisor' => 'Supervisor evaluation'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= selected(old('evaluation_type', $assignment['evaluation_type'] ?? 'student_feedback'), $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="assignmentVersion">Questionnaire version <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentVersion" name="survey_version_id" required>
                            <option value="">— Select a published version —</option>
                            <?php foreach ($versions as $version): ?>
                                <option value="<?= (int) $version['id'] ?>" <?= selected($currentVersion, $version['id']) ?>>
                                    <?= e($version['title']) ?> — v<?= (int) $version['version_number'] ?> (<?= e($version['category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($versions)): ?>
                            <div class="form-text text-danger">No published questionnaire versions are available. Publish a survey first.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="assignmentInstructor">Instructor <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentInstructor" name="instructor_id" required>
                            <option value="">— Select instructor —</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= (int) $instructor['id'] ?>" <?= selected(old('instructor_id', $assignment['instructor_id'] ?? ''), $instructor['id']) ?>><?= e(full_name($instructor)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="assignmentCourse">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentCourse" name="course_id" required>
                            <option value="">— Select course —</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['id'] ?>" <?= selected(old('course_id', $assignment['course_id'] ?? ''), $course['id']) ?>><?= e($course['code'] . ' — ' . $course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assignmentSection">Section <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentSection" name="section_id" required>
                            <option value="">— Select section —</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>" <?= selected(old('section_id', $assignment['section_id'] ?? ''), $section['id']) ?>><?= e($section['code'] . ' — ' . ($section['program_code'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assignmentYear">Academic year <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentYear" name="academic_year_id" required>
                            <option value="">— Select year —</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= (int) $year['id'] ?>" <?= selected(old('academic_year_id', $assignment['academic_year_id'] ?? ''), $year['id']) ?>><?= e($year['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assignmentSemester">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignmentSemester" name="semester_id" required>
                            <option value="">— Select semester —</option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?= (int) $semester['id'] ?>" <?= selected(old('semester_id', $assignment['semester_id'] ?? ''), $semester['id']) ?>><?= e($semester['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The semester must belong to the selected academic year.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="assignmentOpens">Opens at <span class="text-danger">*</span></label>
                        <input class="form-control" type="datetime-local" id="assignmentOpens" name="opens_at" value="<?= e(old('opens_at', $toLocal($assignment['opens_at'] ?? '') ?: $opensDefault)) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="assignmentCloses">Closes at <span class="text-danger">*</span></label>
                        <input class="form-control" type="datetime-local" id="assignmentCloses" name="closes_at" value="<?= e(old('closes_at', $toLocal($assignment['closes_at'] ?? '') ?: $closesDefault)) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="assignmentInstructions">Instructions to respondents</label>
                        <textarea class="form-control" id="assignmentInstructions" name="instructions" rows="3" maxlength="3000" placeholder="Optional note shown above the questionnaire."><?= e(old('instructions', $assignment['instructions'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><strong>Publish state</strong></div>
            <div class="card-body">
                <label class="form-label" for="assignmentStatus">Status</label>
                <select class="form-select" id="assignmentStatus" name="status">
                    <?php foreach (['draft', 'scheduled', 'active', 'closed', 'archived'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected(old('status', $assignment['status'] ?? 'draft'), $status) ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Sessions additionally enforce their own opening and expiry windows.</div>
                <hr>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Create assignment' ?></button>
                <a class="btn btn-ghost w-100 mt-2" href="<?= url('dean/assignments') ?>">Cancel</a>
            </div>
        </div>
        <?php if ($demo): ?>
            <div class="alert alert-warning app-alert mt-3 mb-0"><i class="bi bi-database"></i><span>This assignment will be created inside the demo dataset.</span></div>
        <?php endif; ?>
    </div>
</form>
