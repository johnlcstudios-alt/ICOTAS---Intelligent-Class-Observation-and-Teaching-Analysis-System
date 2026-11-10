<?php
/**
 * Report catalogue and filter form.
 *
 * @var array $types
 * @var array $options
 * @var array $instructors
 * @var array $uatSurveys
 * @var string $base
 * @var bool $demo
 */
$descriptions = [
    'faculty_summary' => 'Selected instructor: headline metrics, category averages, themes and prediction indicator.',
    'individual_instructor' => 'Selected instructor: full teaching profile with evidence-linked recommendations.',
    'department' => 'Instructor comparison within the selected department and period.',
    'course' => 'Average ratings and participation grouped by course.',
    'section' => 'Average ratings and participation grouped by section.',
    'participation' => 'Eligible, submitted and participation rate for every QR evaluation session.',
    'question_statistics' => 'Question-level means, standard deviations and agreement percentages.',
    'sentiment' => 'Rule-based sentiment distribution, recurring themes and advisory actions.',
    'predictive' => 'Rule-based trend indicators with sufficiency and transparency notes.',
    'semester_comparison' => 'Historical averages by academic year and semester.',
    'executive' => 'Institution-wide overview for academic leadership.',
    'uat' => 'ISO/IEC 25010 user acceptance testing results and interpretation.',
];
$defaultType = $base === 'instructor' ? 'individual_instructor' : 'executive';
$selectedType = (string) ($_GET['type'] ?? $defaultType);
if (!isset($types[$selectedType])) {
    $selectedType = $defaultType;
}
$instructorMode = $base === 'instructor';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Formal documentation</span>
        <h2>Reports</h2>
        <p>Generate evidence reports for institutional review, accreditation and continuous improvement. Every report states its data scope, method and limitations.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url($base . '/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> Reports generated in this workspace are not institutional findings and must not be submitted as research evidence.</span></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <strong>Build a report</strong>
                <span class="text-muted small">Filters apply to the generated tables and charts</span>
            </div>
            <div class="card-body">
                <form method="get" action="<?= url($base . '/reports/view') ?>" class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="reportType">Report type <span class="text-danger">*</span></label>
                        <select class="form-select" id="reportType" name="type" required>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= selected($selectedType, $key) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="reportTypeHelp"><?= e($descriptions[$selectedType] ?? '') ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small" for="reportYear">Academic year</label>
                        <select class="form-select" id="reportYear" name="academic_year_id">
                            <option value="">All academic years</option>
                            <?php foreach (($options['academic_years'] ?? []) as $year): ?>
                                <option value="<?= (int) $year['id'] ?>"><?= e($year['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportSemester">Semester</label>
                        <select class="form-select" id="reportSemester" name="semester_id">
                            <option value="">All semesters</option>
                            <?php foreach (($options['semesters'] ?? []) as $semester): ?>
                                <option value="<?= (int) $semester['id'] ?>"><?= e($semester['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small" for="reportDepartment">Department</label>
                        <select class="form-select" id="reportDepartment" name="department_id">
                            <option value="">All departments</option>
                            <?php foreach (($options['departments'] ?? []) as $department): ?>
                                <option value="<?= (int) $department['id'] ?>"><?= e($department['code'] . ' — ' . $department['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportProgram">Program</label>
                        <select class="form-select" id="reportProgram" name="program_id">
                            <option value="">All programs</option>
                            <?php foreach (($options['programs'] ?? []) as $program): ?>
                                <option value="<?= (int) $program['id'] ?>"><?= e($program['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small" for="reportInstructor">Instructor</label>
                        <?php if ($instructorMode): ?>
                            <input class="form-control" id="reportInstructor" type="text" value="<?= e(full_name($instructors[0] ?? null)) ?>" disabled>
                            <div class="form-text">Instructor accounts can only report on their own teaching data.</div>
                        <?php else: ?>
                            <select class="form-select" id="reportInstructor" name="instructor_id">
                                <option value="">All instructors</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= (int) $instructor['id'] ?>"><?= e(full_name($instructor)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Required for faculty summary and individual instructor reports.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportCourse">Course</label>
                        <select class="form-select" id="reportCourse" name="course_id">
                            <option value="">All courses</option>
                            <?php foreach (($options['courses'] ?? []) as $course): ?>
                                <option value="<?= (int) $course['id'] ?>"><?= e($course['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportSection">Section</label>
                        <select class="form-select" id="reportSection" name="section_id">
                            <option value="">All sections</option>
                            <?php foreach (($options['sections'] ?? []) as $section): ?>
                                <option value="<?= (int) $section['id'] ?>"><?= e($section['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportUat">UAT survey</label>
                        <select class="form-select" id="reportUat" name="uat_survey_id">
                            <option value="">Latest UAT survey in scope</option>
                            <?php foreach ($uatSurveys as $survey): ?>
                                <option value="<?= (int) $survey['id'] ?>"><?= e($survey['title']) ?> (<?= e($survey['status']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Used only by the UAT report.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportFrom">From date</label>
                        <input class="form-control" id="reportFrom" type="date" name="date_from">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="reportTo">To date</label>
                        <input class="form-control" id="reportTo" type="date" name="date_to">
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-file-earmark-bar-graph"></i> Generate report</button>
                        <a class="btn btn-outline-secondary" href="<?= url($base . '/reports') ?>"><i class="bi bi-arrow-counterclockwise"></i> Clear filters</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header"><strong>Report integrity</strong></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li class="d-block mb-2"><i class="bi bi-shield-check text-success"></i> Reports use completed evaluation sessions only.</li>
                    <li class="d-block mb-2"><i class="bi bi-person-x text-danger"></i> No report exposes student identities; comments are excerpted and filtered.</li>
                    <li class="d-block mb-2"><i class="bi bi-calculator text-primary"></i> Numeric ratings are normalized to a five-point scale before averaging.</li>
                    <li class="d-block mb-2"><i class="bi bi-exclamation-triangle text-warning"></i> Prediction sections are rule-based indicators, not validated machine-learning forecasts.</li>
                    <li class="d-block"><i class="bi bi-journal-check text-secondary"></i> Every generated report is recorded in the audit log.</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Quick reports</strong><span class="text-muted small">No filters</span></div>
            <div class="list-group list-group-flush">
                <?php if (!$instructorMode): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('dean/reports/view?type=executive') ?>"><i class="bi bi-building"></i> Institutional executive summary</a>
                    <a class="list-group-item list-group-item-action" href="<?= url('dean/reports/view?type=participation') ?>"><i class="bi bi-person-check"></i> Student participation</a>
                    <a class="list-group-item list-group-item-action" href="<?= url('dean/reports/view?type=semester_comparison') ?>"><i class="bi bi-graph-up"></i> Semester comparison</a>
                <?php else: ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('instructor/reports/view?type=individual_instructor') ?>"><i class="bi bi-person-video3"></i> My teaching report</a>
                    <a class="list-group-item list-group-item-action" href="<?= url('instructor/reports/view?type=participation') ?>"><i class="bi bi-person-check"></i> My class participation</a>
                    <a class="list-group-item list-group-item-action" href="<?= url('instructor/reports/view?type=sentiment') ?>"><i class="bi bi-chat-square-quote"></i> My feedback themes</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var descriptions = <?= json_encode($descriptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var select = document.getElementById('reportType');
    var help = document.getElementById('reportTypeHelp');
    if (!select || !help) { return; }
    select.addEventListener('change', function () {
        help.textContent = descriptions[select.value] || '';
    });
})();
</script>
