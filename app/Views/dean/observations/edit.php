<?php
/** Dean observation edit. */
$labels = rating_labels();

$isEdit = !empty($observation);
$title = $isEdit ? 'Edit observation' : 'New observation';
$action = $isEdit
    ? url('/dean/observations/' . (int) $observation['id'] . '/edit')
    : url('/dean/observations/edit');
$method = $isEdit ? 'put' : 'post';
$observation = $observation ?: [];

$activeBadges = $observation['active_badges'] ?? [];
$departmentOptions = $departmentOptions ?? [];
$periodOptions = $periodOptions ?? [];
$academicYearOptions = $academicYearOptions ?? [];
$faculties = $faculties ?? [];
$observers = $observers ?? [];
$selectedFacultyId = $selectedFacultyId ?? null;
$selectedPeriodId = (int) ($selectedPeriodId ?? 0);
$selectedAcademicYear = $selectedAcademicYear ?? '';
$selectedSemester = $selectedSemester ?? '';

$preFill = !empty($observation['rating_period_id']) ? $observation['rating_period_id'] : null;
$currentPeriodLabel = $preFill ? ' (currently ' . e($preFill) . ')' : '';
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2><?= e($title) ?></h2><p class="small"><?= e($isEdit ? 'Update the observation details below.' : 'Create a new observation record.') ?></p></div>
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
                                <input class="input<?= has_error('subject_code') ? ' is-invalid' : '' ?>" type="text" id="subject_code" name="subject_code" maxlength="30" value="<?= e(old('subject_code', $observation['subject_code'] ?? '')) ?>" required>
                                <?php if (has_error('subject_code')): ?><span class="field-error"><?= e(error('subject_code')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="section_name">Section <span class="required">*</span></label>
                                <input class="input<?= has_error('section_name') ? ' is-invalid' : '' ?>" type="text" id="section_name" name="section_name" maxlength="100" value="<?= e(old('section_name', $observation['section_name'] ?? '')) ?>" required>
                                <?php if (has_error('section_name')): ?><span class="field-error"><?= e(error('section_name')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="academic_year">Academic year <span class="required">*</span></label>
                                <input class="input<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="academic_year" name="academic_year" maxlength="20" value="<?= e(old('academic_year', $observation['academic_year'] ?? $selectedAcademicYear)) ?>" required>
                                <?php if (has_error('academic_year')): ?><span class="field-error"><?= e(error('academic_year')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select id="semester" name="semester" class="select" required>
                                    <option value="">— Select semester —</option>
                                    <?php foreach (['1' => '1st Semester', '2' => '2nd Semester', '3' => 'Summer'] as $v => $l): ?>
                                        <option value="<?= (string) $v ?>" <?= (string) ($observation['semester'] ?? $selectedSemester) === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('semester')): ?><span class="field-error"><?= e(error('semester')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Faculty, observer and lesson</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="faculty_id">Faculty member <span class="required">*</span></label>
                                <select id="faculty_id" name="faculty_id" class="select" required>
                                    <option value="">— Select faculty —</option>
                                    <?php foreach ($faculties as $f): ?>
                                        <option value="<?= (int) $f['user_id'] ?>" <?= (int) ($observation['faculty_id'] ?? $selectedFacultyId ?? 0) === (int) $f['user_id'] ? 'selected' : '' ?>>
                                            <?= e($f['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('faculty_id')): ?><span class="field-error"><?= e(error('faculty_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="observer_id">Observer <span class="required">*</span></label>
                                <select id="observer_id" name="observer_id" class="select" required>
                                    <option value="">— Select observer —</option>
                                    <?php foreach ($observers as $obs): ?>
                                        <option value="<?= (int) $obs['user_id'] ?>" <?= (int) ($observation['observer_id'] ?? $selectedObserverId ?? 0) === (int) $obs['user_id'] ? 'selected' : '' ?>>
                                            <?= e($obs['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('observer_id')): ?><span class="field-error"><?= e(error('observer_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="lesson_topic">Lesson topic <span class="required">*</span></label>
                                <input class="input<?= has_error('lesson_topic') ? ' is-invalid' : '' ?>" type="text" id="lesson_topic" name="lesson_topic" maxlength="200" value="<?= e(old('lesson_topic', $observation['lesson_topic'] ?? '')) ?>" required>
                                <?php if (has_error('lesson_topic')): ?><span class="field-error"><?= e(error('lesson_topic')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="room">Room / venue</label>
                                <input class="input<?= has_error('room') ? ' is-invalid' : '' ?>" type="text" id="room" name="room" maxlength="100" value="<?= e(old('room', $observation['room'] ?? '')) ?>">
                                <?php if (has_error('room')): ?><span class="field-error"><?= e(error('room')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="schedule">Schedule</label>
                                <input class="input<?= has_error('schedule') ? ' is-invalid' : '' ?>" type="text" id="schedule" name="schedule" maxlength="200" value="<?= e(old('schedule', $observation['schedule'] ?? '')) ?>">
                                <?php if (has_error('schedule')): ?><span class="field-error"><?= e(error('schedule')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="notes">Notes</label>
                                <textarea class="textarea<?= has_error('notes') ? ' is-invalid' : '' ?>" id="notes" name="notes" maxlength="2000" rows="3"><?= e(old('notes', $observation['notes'] ?? '')) ?></textarea>
                                <?php if (has_error('notes')): ?><span class="field-error"><?= e(error('notes')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Dates and periods</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="observation_date">Observation date <span class="required">*</span></label>
                                <input class="input<?= has_error('observation_date') ? ' is-invalid' : '' ?>" type="date" id="observation_date" name="observation_date" value="<?= e(old('observation_date', $observation['observation_date'] ?? '')) ?>" required>
                                <?php if (has_error('observation_date')): ?><span class="field-error"><?= e(error('observation_date')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="period_id">Period <span class="required">*</span></label>
                                <select id="period_id" name="period_id" class="select" required>
                                    <option value="">— Select period —</option>
                                    <?php foreach ($periodOptions as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($observation['period_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label_full']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('period_id')): ?><span class="field-error"><?= e(error('period_id')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Save changes</button>
                        <a class="btn btn--ghost" href="<?= e(url('/dean/observations')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($isEdit && !empty($activeBadges)): ?>
            <section class="card">
                <div class="card__head"><h2>Active badges</h2></div>
                <div class="card__body">
                    <div class="stack--flush">
                        <?php foreach ($activeBadges as $b): ?>
                            <span class="badge badge--neutral"><?= e($b) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><h2>Editing guidance</h2></div>
            <div class="card__body">
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li>Subject code and section identify the observed class.</li>
                    <li>Academic year and semester group observations by term.</li>
                    <li>The observation date records when the observation occurred.</li>
                    <li>Changes to an already-observed record are saved as a new revision only if prompted.</li>
                </ul>
            </div>
        </section>
    </div>
</div>
