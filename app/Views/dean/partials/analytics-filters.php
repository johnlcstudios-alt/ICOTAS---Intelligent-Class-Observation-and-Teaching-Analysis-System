<?php
/**
 * Shared analytics filter bar.
 * @var array $filters
 * @var array $filter_options
 * @var string $action
 */
$action = $action ?? 'dean/analytics';
$years = $filter_options['academic_years'] ?? [];
$semesters = $filter_options['semesters'] ?? [];
$departments = $filter_options['departments'] ?? [];
$programs = $filter_options['programs'] ?? [];
$instructors = $filter_options['instructors'] ?? [];
$courses = $filter_options['courses'] ?? [];
$sections = $filter_options['sections'] ?? [];
$hasFilters = !empty(array_filter($filters, static fn ($value) => $value !== '' && $value !== null));
?>
<div class="card mb-4">
    <div class="card-header">
        <strong>Filter analysis</strong>
        <span class="text-muted small">Applied to every chart and table on this page</span>
        <?php if ($hasFilters): ?><span class="badge badge-info ms-auto">Filtered</span><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url($action) ?>" class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterYear">Academic year</label>
                <select class="form-select form-select-sm" id="filterYear" name="academic_year_id">
                    <option value="">All years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= (int) $year['id'] ?>" <?= selected($filters['academic_year_id'] ?? '', $year['id']) ?>><?= e($year['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterSemester">Semester</label>
                <select class="form-select form-select-sm" id="filterSemester" name="semester_id">
                    <option value="">All semesters</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= (int) $semester['id'] ?>" <?= selected($filters['semester_id'] ?? '', $semester['id']) ?>><?= e($semester['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterDepartment">Department</label>
                <select class="form-select form-select-sm" id="filterDepartment" name="department_id">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= selected($filters['department_id'] ?? '', $department['id']) ?>><?= e($department['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterProgram">Program</label>
                <select class="form-select form-select-sm" id="filterProgram" name="program_id">
                    <option value="">All programs</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) $program['id'] ?>" <?= selected($filters['program_id'] ?? '', $program['id']) ?>><?= e($program['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterInstructor">Instructor</label>
                <select class="form-select form-select-sm" id="filterInstructor" name="instructor_id">
                    <option value="">All instructors</option>
                    <?php foreach ($instructors as $instructor): ?>
                        <option value="<?= (int) $instructor['id'] ?>" <?= selected($filters['instructor_id'] ?? '', $instructor['id']) ?>><?= e(full_name($instructor)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterCourse">Course</label>
                <select class="form-select form-select-sm" id="filterCourse" name="course_id">
                    <option value="">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>" <?= selected($filters['course_id'] ?? '', $course['id']) ?>><?= e($course['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterSection">Section</label>
                <select class="form-select form-select-sm" id="filterSection" name="section_id">
                    <option value="">All sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?= (int) $section['id'] ?>" <?= selected($filters['section_id'] ?? '', $section['id']) ?>><?= e($section['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterFrom">From</label>
                <input class="form-control form-control-sm" type="date" id="filterFrom" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="filterTo">To</label>
                <input class="form-control form-control-sm" type="date" id="filterTo" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-sm-12 col-lg-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply filters</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url($action) ?>"><i class="bi bi-x-lg"></i> Reset</a>
            </div>
        </form>
    </div>
</div>
