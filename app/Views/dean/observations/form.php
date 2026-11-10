<?php
/**
 * @var array|null $observation
 * @var array $instructors
 * @var array $courses
 * @var array $sections
 * @var array $years
 * @var array $semesters
 * @var array $assignments
 * @var bool $demo
 */
$isEdit = !empty($observation);
$current = static function (string $key, $default = '') use ($observation) {
    if ($observation && array_key_exists($key, $observation) && $observation[$key] !== null) {
        return $observation[$key];
    }
    return old($key, $default);
};
$returnTo = $isEdit ? 'dean/observations/' . (int) $observation['id'] . '/edit' : 'dean/observations/create';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Teaching quality</span>
        <h2><?= $isEdit ? 'Edit class observation' : 'New class observation' ?></h2>
        <p>Record observed practice, strengths and improvement areas. This record is separate from student evaluation data.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/observations') ?>"><i class="bi bi-arrow-left"></i> All observations</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span>This observation will be stored in the demonstration data scope.</span></div>
<?php endif; ?>

<form method="post" action="<?= url($isEdit ? 'dean/observations/' . (int) $observation['id'] : 'dean/observations') ?>" data-single-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="_return" value="<?= e($returnTo) ?>">

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header"><strong>Class and context</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="obsInstructor">Instructor <span class="text-danger">*</span></label>
                            <select class="form-select" id="obsInstructor" name="instructor_id" required>
                                <option value="">Select instructor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= (int) $instructor['id'] ?>" <?= selected($current('instructor_id'), $instructor['id']) ?>><?= e(full_name($instructor)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsDate">Observation date <span class="text-danger">*</span></label>
                            <input class="form-control" id="obsDate" name="observation_date" type="date" required value="<?= e($current('observation_date', date('Y-m-d'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsCourse">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="obsCourse" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int) $course['id'] ?>" <?= selected($current('course_id'), $course['id']) ?>><?= e($course['code'] . ' — ' . $course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsSection">Section <span class="text-danger">*</span></label>
                            <select class="form-select" id="obsSection" name="section_id" required>
                                <option value="">Select section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= (int) $section['id'] ?>" <?= selected($current('section_id'), $section['id']) ?>><?= e($section['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsYear">Academic year <span class="text-danger">*</span></label>
                            <select class="form-select" id="obsYear" name="academic_year_id" required>
                                <option value="">Select academic year</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= (int) $year['id'] ?>" <?= selected($current('academic_year_id'), $year['id']) ?>><?= e($year['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsSemester">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" id="obsSemester" name="semester_id" required>
                                <option value="">Select semester</option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?= (int) $semester['id'] ?>" <?= selected($current('semester_id'), $semester['id']) ?> data-year="<?= (int) $semester['academic_year_id'] ?>"><?= e($semester['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">The semester must belong to the selected academic year.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="obsAssignment">Related evaluation assignment</label>
                            <select class="form-select" id="obsAssignment" name="assignment_id">
                                <option value="">None</option>
                                <?php foreach ($assignments as $assignment): ?>
                                    <option value="<?= (int) $assignment['id'] ?>" <?= selected($current('assignment_id'), $assignment['id']) ?>><?= e($assignment['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="obsType">Observation type</label>
                            <select class="form-select" id="obsType" name="observation_type">
                                <?php foreach (['classroom_observation' => 'Classroom observation', 'peer_observation' => 'Peer observation', 'supervisory_observation' => 'Supervisory observation', 'walkthrough' => 'Walkthrough'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected($current('observation_type', 'classroom_observation'), $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="obsStatus">Status</label>
                            <select class="form-select" id="obsStatus" name="status">
                                <?php foreach (['draft' => 'Draft', 'completed' => 'Completed', 'shared' => 'Shared with instructor'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected($current('status', 'draft'), $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="obsRating">Overall rating</label>
                            <input class="form-control" id="obsRating" name="overall_rating" type="number" min="1" max="5" step="0.01" value="<?= e($current('overall_rating')) ?>" placeholder="1.00 – 5.00">
                            <div class="form-text">Optional. Observation evidence is primarily qualitative.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Observation notes</strong><span class="text-muted small">Visible to the observed instructor when shared</span></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="obsStrengths">Strengths observed</label>
                        <textarea class="form-control" id="obsStrengths" name="strengths" rows="4" maxlength="5000" placeholder="Effective practices, engagement strategies and evidence observed."><?= e($current('strengths')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="obsImprovements">Areas for improvement</label>
                        <textarea class="form-control" id="obsImprovements" name="areas_for_improvement" rows="4" maxlength="5000" placeholder="Specific, observable areas that could be strengthened."><?= e($current('areas_for_improvement')) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="obsRecommendations">Recommendations</label>
                        <textarea class="form-control" id="obsRecommendations" name="recommendations" rows="4" maxlength="5000" placeholder="Agreed next steps, support or follow-up observation."><?= e($current('recommendations')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header"><strong>Guidance</strong></div>
                <div class="card-body">
                    <ul class="list-clean small">
                        <li class="d-block mb-2"><i class="bi bi-eye text-primary"></i> Record observable practice, not personality judgments.</li>
                        <li class="d-block mb-2"><i class="bi bi-chat-left-text text-primary"></i> Keep notes specific enough to support a professional conversation.</li>
                        <li class="d-block mb-2"><i class="bi bi-shield-check text-primary"></i> Observation records are attributed to the observer and instructor.</li>
                        <li class="d-block"><i class="bi bi-bar-chart text-primary"></i> Do not combine observation ratings with anonymous student evaluation averages.</li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> <?= $isEdit ? 'Save changes' : 'Record observation' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= url('dean/observations') ?>">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
