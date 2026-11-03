<?php
/** Dean observation create. */
$labels = rating_labels();

$faculties = $faculties ?? [];
$departments = $departments ?? [];
$periods = $periods ?? [];
$academicYears = $academicYears ?? [];
$preProf = $preProf ?? null;
$academicYear = $academicYear ?? '';
$semester = $semester ?? '';
$selectedFacultyId = $selectedFacultyId ?? null;
$selectedPeriodId = (int) ($selectedPeriodId ?? 0);
$isNew = empty($observationId);

$selectedPreProfId = $selectedPreProfId ?? null;

$selectedPreProfLabel = null;
if ($selectedPreProfId && $preProf) {
    $selectedPreProfLabel = $preProf['full_name'];
}
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>New observation</h2><p class="small">Create a classroom observation record.</p></div>
            <div class="card__body">
                <form method="post" action="<?= e(url('/dean/observations/create')) ?>" novalidate id="observation-form">
                    <?= csrf_field() ?>

                    <div class="section">
                        <span class="eyebrow">Section</span>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="subject_code">Subject code <span class="required">*</span></label>
                                <input class="input<?= has_error('subject_code') ? ' is-invalid' : '' ?>" type="text" id="subject_code" name="subject_code" maxlength="30" value="<?= e(old('subject_code', '')) ?>" required>
                                <?php if (has_error('subject_code')): ?><span class="field-error"><?= e(error('subject_code')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="section_name">Section <span class="required">*</span></label>
                                <input class="input<?= has_error('section_name') ? ' is-invalid' : '' ?>" type="text" id="section_name" name="section_name" maxlength="100" value="<?= e(old('section_name', '')) ?>" required>
                                <?php if (has_error('section_name')): ?><span class="field-error"><?= e(error('section_name')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="academic_year">Academic year <span class="required">*</span></label>
                                <input class="input<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="academic_year" name="academic_year" maxlength="20" value="<?= e(old('academic_year', $academicYear)) ?>" required>
                                <?php if (has_error('academic_year')): ?><span class="field-error"><?= e(error('academic_year')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select id="semester" name="semester" class="select" required>
                                    <option value="">— Select —</option>
                                    <?php foreach (['1' => '1st Semester', '2' => '2nd Semester', '3' => 'Summer'] as $v => $l): ?>
                                        <option value="<?= (string) $v ?>" <?= (string) ($semester ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('semester')): ?><span class="field-error"><?= e(error('semester')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Faculty and observer</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="faculty_id">Faculty member <span class="required">*</span></label>
                                <select id="faculty_id" name="faculty_id" class="select" required>
                                    <option value="">— Select faculty —</option>
                                    <?php foreach ($faculties as $f): ?>
                                        <option value="<?= (int) $f['user_id'] ?>" <?= (int) ($selectedFacultyId ?? 0) === (int) $f['user_id'] ? 'selected' : '' ?>>
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
                                    <?php foreach ($observerOptions as $opt): ?>
                                        <option value="<?= (int) $opt['user_id'] ?>" <?= (int) ($selectedObserverId ?? 0) === (int) $opt['user_id'] ? 'selected' : '' ?>>
                                            <?= e($opt['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('observer_id')): ?><span class="field-error"><?= e(error('observer_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="lesson_topic">Lesson topic <span class="required">*</span></label>
                                <input class="input<?= has_error('lesson_topic') ? ' is-invalid' : '' ?>" type="text" id="lesson_topic" name="lesson_topic" maxlength="200" value="<?= e(old('lesson_topic', '')) ?>" required>
                                <?php if (has_error('lesson_topic')): ?><span class="field-error"><?= e(error('lesson_topic')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="room">Room / venue</label>
                                <input class="input<?= has_error('room') ? ' is-invalid' : '' ?>" type="text" id="room" name="room" maxlength="100" value="<?= e(old('room', '')) ?>">
                                <?php if (has_error('room')): ?><span class="field-error"><?= e(error('room')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="schedule">Schedule</label>
                                <input class="input<?= has_error('schedule') ? ' is-invalid' : '' ?>" type="text" id="schedule" name="schedule" maxlength="200" value="<?= e(old('schedule', '')) ?>">
                                <?php if (has_error('schedule')): ?><span class="field-error"><?= e(error('schedule')) ?></span><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label for="notes">Notes</label>
                                <textarea class="textarea<?= has_error('notes') ? ' is-invalid' : '' ?>" id="notes" name="notes" maxlength="2000" rows="3"><?= e(old('notes', '')) ?></textarea>
                                <?php if (has_error('notes')): ?><span class="field-error"><?= e(error('notes')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <span class="eyebrow">Period mapping</span>
                        <div class="form-grid">
                            <div class="field">
                                <label for="period_id">Period <span class="required">*</span></label>
                                <select id="period_id" name="period_id" class="select" required>
                                    <option value="">— Select period —</option>
                                    <?php foreach ($periods as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($selectedPeriodId) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label_full']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('period_id')): ?><span class="field-error"><?= e(error('period_id')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="rating_period_id">Rating period (pre-filled score range) <span class="required">*</span></label>
                                <select id="rating_period_id" name="rating_period_id" class="select" required>
                                    <option value="">— Select rating period —</option>
                                    <?php foreach ($ratingPeriods as $rp): ?>
                                        <option value="<?= (int) $rp['id'] ?>" <?= (int) ($selectedRatingPeriodId ?? 0) === (int) $rp['id'] ? 'selected' : '' ?>>
                                            <?= e($rp['label_full']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('rating_period_id')): ?><span class="field-error"><?= e(error('rating_period_id')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($selectedPreProfId && $preProf): ?>
                        <div class="alert alert--info">
                            <span class="alert__icon"><?= icon('info', 18) ?></span>
                            <div class="alert__body">
                                <strong>Pre-filled period info.</strong>
                                The selected faculty member has a pre-filled period: <?= e($selectedPreProfLabel) ?>. Use the rating period selector above to confirm.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary"><?= icon('plus', 16) ?> Create observation</button>
                        <a class="btn btn--ghost" href="<?= e(url('/dean/observations')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Section formatting</h2></div>
            <div class="card__body">
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li><strong>Subject code</strong> — short code, e.g. CS101, MATH201.</li>
                    <li><strong>Section</strong> — section label, e.g. 01, A, SEC-A.</li>
                    <li><strong>Lesson topic</strong> — the topic covered in the observed session.</li>
                    <li><strong>Schedule</strong> — day/time pattern, e.g. MWF 8:00–9:00 AM.</li>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Period mapping</h2></div>
            <div class="card__body">
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li>The <strong>Period</strong> ties the observation to the school calendar.</li>
                    <li>The <strong>Rating period</strong> pre-fills the score range for the faculty member's ratings.</li>
                </ul>
            </div>
        </section>
    </div>
</div>
