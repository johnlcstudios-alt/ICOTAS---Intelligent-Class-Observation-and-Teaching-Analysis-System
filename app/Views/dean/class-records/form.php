<?php
/** Dean class record form (create/edit). */
$labels = rating_labels();

$isEdit = !empty($record);
$title = $isEdit ? 'Edit class record' : 'New class record';
$action = $isEdit
    ? url('/dean/class-records/' . (int) $record['id'])
    : url('/dean/class-records');
$method = $isEdit ? 'put' : 'post';
$record = $record ?: [];

$usedTotal = (int) ($record['total_students'] ?? 0);
$usedPassed = (int) ($record['passed_count'] ?? 0);
$usedFailed = (int) ($record['failed_count'] ?? 0);
$computedUnfinalizedTotal = $usedPassed + $usedFailed;
$showRequireCompleteWarning = !empty($settings['class_record_require_complete']) && $isEdit && (int) $record['record_status'] === 0 && (int) $usedTotal > 0 && (int) $computedUnfinalizedTotal > 0 && (int) $computedUnfinalizedTotal !== (int) $usedTotal;

$yearOpts = $yearOpts ?? [];
$semesterOpts = $semesterOpts ?? [];
$y = [];
foreach ($yearOpts as $oy) { $y = $y ?: []; }
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <h2><?= e($title) ?></h2>
                <p class="small"><?= e($isEdit ? 'Update the class record below.' : 'Add a new class record for tracking grades by section and period.') ?></p>
            </div>
            <div class="card__body">
                <form method="post" action="<?= e($action) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="_method" value="<?= e($method) ?>">
                    <?php endif; ?>

                    <div class="section">
                        <span class="eyebrow">Section details</span>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="subject_code">Subject code <span class="required">*</span></label>
                                <input class="input<?= has_error('subject_code') ? ' is-invalid' : '' ?>" type="text" id="subject_code" name="subject_code" maxlength="30" value="<?= e(old('subject_code', $record['subject_code'] ?? '')) ?>" required>
                                <?php if (has_error('subject_code')): ?><span class="field-error"><?= e(error('subject_code')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="section_name">Section <span class="required">*</span></label>
                                <input class="input<?= has_error('section_name') ? ' is-invalid' : '' ?>" type="text" id="section_name" name="section_name" maxlength="100" value="<?= e(old('section_name', $record['section_name'] ?? '')) ?>" required>
                                <?php if (has_error('section_name')): ?><span class="field-error"><?= e(error('section_name')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="academic_year">Academic year <span class="required">*</span></label>
                                <input class="input<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="academic_year" name="academic_year" maxlength="20" value="<?= e(old('academic_year', $record['academic_year'] ?? '')) ?>" required>
                                <?php if (has_error('academic_year')): ?><span class="field-error"><?= e(error('academic_year')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select id="semester" name="semester" class="select" required>
                                    <option value="">— Select semester —</option>
                                    <option value="1" <?= (int) ($record['semester'] ?? 0) === 1 ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2" <?= (int) ($record['semester'] ?? 0) === 2 ? 'selected' : '' ?>>2nd Semester</option>
                                    <option value="3" <?= (int) ($record['semester'] ?? 0) === 3 ? 'selected' : '' ?>>Summer</option>
                                </select>
                                <?php if (has_error('semester')): ?><span class="field-error"><?= e(error('semester')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="school_year">School year</label>
                                <input class="input<?= has_error('school_year') ? ' is-invalid' : '' ?>" type="text" id="school_year" name="school_year" maxlength="20" value="<?= e(old('school_year', $record['school_year'] ?? ($yearOpts ?: ''))) ?>">
                                <?php if (has_error('school_year')): ?><span class="field-error"><?= e(error('school_year')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Faculty and department</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="faculty_id">Faculty member <span class="required">*</span></label>
                                <select id="faculty_id" name="faculty_id" class="select" required>
                                    <option value="">— Select faculty —</option>
                                    <?php foreach ($facultyOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" <?= (int) ($record['faculty_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('faculty_id')): ?><span class="field-error"><?= e(error('faculty_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="department_id">Department <span class="required">*</span></label>
                                <select id="department_id" name="department_id" class="select" required>
                                    <option value="">— Select department —</option>
                                    <?php foreach ($departmentOptions as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= (int) ($record['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('department_id')): ?><span class="field-error"><?= e(error('department_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="room">Room / venue</label>
                                <input class="input<?= has_error('room') ? ' is-invalid' : '' ?>" type="text" id="room" name="room" maxlength="20" value="<?= e(old('room', $record['room'] ?? '')) ?>">
                                <?php if (has_error('room')): ?><span class="field-error"><?= e(error('room')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="schedule">Schedule</label>
                                <input class="input<?= has_error('schedule') ? ' is-invalid' : '' ?>" type="text" id="schedule" name="schedule" maxlength="100" value="<?= e(old('schedule', $record['schedule'] ?? '')) ?>">
                                <?php if (has_error('schedule')): ?><span class="field-error"><?= e(error('schedule')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Grades</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="total_students">Total students <span class="required">*</span></label>
                                <input class="input<?= has_error('total_students') ? ' is-invalid' : '' ?>" type="number" id="total_students" name="total_students" min="0" max="9999" value="<?= e(old('total_students', $usedTotal)) ?>" required>
                                <?php if (has_error('total_students')): ?><span class="field-error"><?= e(error('total_students')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="passed_count">Passed <span class="required">*</span></label>
                                <input class="input<?= has_error('passed_count') ? ' is-invalid' : '' ?>" type="number" id="passed_count" name="passed_count" min="0" max="9999" value="<?= e(old('passed_count', $usedPassed)) ?>" required>
                                <?php if (has_error('passed_count')): ?><span class="field-error"><?= e(error('passed_count')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="failed_count">Failed <span class="required">*</span></label>
                                <input class="input<?= has_error('failed_count') ? ' is-invalid' : '' ?>" type="number" id="failed_count" name="failed_count" min="0" max="9999" value="<?= e(old('failed_count', $usedFailed)) ?>" required>
                                <?php if (has_error('failed_count')): ?><span class="field-error"><?= e(error('failed_count')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="average_grade">Average grade (0–100)</label>
                                <input class="input<?= has_error('average_grade') ? ' is-invalid' : '' ?>" type="number" id="average_grade" name="average_grade" step="0.01" min="<?= (double) ($settings['grading_min'] ?? 0) ?>" max="<?= (double) ($settings['grading_max'] ?? 100) ?>" value="<?= e(old('average_grade', $record['average_grade'] ?? '')) ?>">
                                <?php if (has_error('average_grade')): ?><span class="field-error"><?= e(error('average_grade')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="record_status">Status</label>
                                <select id="record_status" name="record_status" class="select">
                                    <option value="0" <?= ($record['record_status'] ?? 0) === 0 ? 'selected' : '' ?>>— Select —</option>
                                    <option value="1" <?= (int) ($record['record_status'] ?? 0) === 1 ? 'selected' : '' ?>>Unfinalized</option>
                                    <option value="2" <?= (int) ($record['record_status'] ?? 0) === 2 ? 'selected' : '' ?>>Finalized</option>
                                    <option value="3" <?= (int) ($record['record_status'] ?? 0) === 3 ? 'selected' : '' ?>>Expired</option>
                                    <option value="4" <?= (int) ($record['record_status'] ?? 0) === 4 ? 'selected' : '' ?>>Void</option>
                                </select>
                                <?php if (has_error('record_status')): ?><span class="field-error"><?= e(error('record_status')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Notes</span>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="notes">Notes</label>
                                <textarea class="textarea<?= has_error('notes') ? ' is-invalid' : '' ?>" id="notes" name="notes" maxlength="1000" rows="3"><?= e(old('notes', $record['notes'] ?? '')) ?></textarea>
                                <?php if (has_error('notes')): ?><span class="field-error"><?= e(error('notes')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($showRequireCompleteWarning): ?>
                        <div class="alert alert--warning">
                            <span class="alert__icon"><?= icon('alert', 18) ?></span>
                            <div class="alert__body">
                                <strong>Passed + Failed ≠ Total.</strong>
                                The setting <?= e($settings['class_record_require_complete'] ? 'ON' : 'OFF') ?> requires that passed and failed together equal the total number of students for a finalized record. Adjust the counts, or set the record to Unfinalized.</div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <?php if ($isEdit): ?>
                            <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Update record</button>
                            <a class="btn btn--ghost" href="<?= e(url('/dean/class-records')) ?>">Cancel</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Create record</button>
                            <a class="btn btn--ghost" href="<?= e(url('/dean/class-records')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Loss and void guidance</h2></div>
            <div class="card__body">
                <p class="small">Use <strong>Expired</strong> when a record is no longer in force (e.g. term ended without finalisation).</p>
                <p class="small">Use <strong>Void</strong> when a record must be removed from active totals (e.g. roster error, double entry).</p>
                <p class="small">Grade ranges follow the institution's grading rules. Average grade may be omitted at creation and set later.</p>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Counts preview</h2></div>
            <div class="card__body">
                <?php
                    $previewTotal = max(0, (int) old('total_students', $usedTotal));
                    $previewPassed = max(0, (int) old('passed_count', $usedPassed));
                    $previewFailed = max(0, (int) old('failed_count', $usedFailed));
                    $previewComputed = $previewPassed + $previewFailed;
                    $previewMismatch = $previewTotal > 0 && $previewComputed !== $previewTotal;
                ?>
                <ul class="meta-list">
                    <li><span>Total students</span><strong><?= (int) $previewTotal ?></strong></li>
                    <li><span>Passed</span><strong><?= (int) $previewPassed ?></strong></li>
                    <li><span>Failed</span><strong><?= (int) $previewFailed ?></strong></li>
                    <li><span>Passed + Failed</span><strong><?= (int) $previewComputed ?></strong></li>
                    <?php if ($previewMismatch): ?>
                        <li><span class="row-sub" style="color:#c2410c">Mismatch — does not match total</span></li>
                    <?php elseif ($previewTotal > 0): ?>
                        <li><span class="row-sub muted">Passed + Failed matches total</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Help</h2></div>
            <div class="card__body">
                <p class="small"><strong>Subject code</strong> examples: CS101, MATH201, ENG301.</p>
                <p class="small"><strong>Section name</strong> examples: 01, A, SEC_OP.</p>
                <p class="small"><strong>School year</strong> is separate from academic year and may be used for cohort naming.</p>
            </div>
        </section>
    </div>
</div>
