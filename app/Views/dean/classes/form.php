<?php
/**
 * Create or edit a class offering.
 *
 * @var array|null $class
 * @var array $departments
 * @var array $faculty
 * @var array $periods
 * @var bool $hasHistory
 */
$isEdit = $class !== null;
$hasHistory = $hasHistory ?? false;
$action = $isEdit ? '/dean/classes/' . (int) $class['class_id'] : '/dean/classes';
?>
<div class="grid grid--sidebar">
    <section class="card">
        <div class="card__head">
            <div>
                <h2><?= $isEdit ? 'Class offering details' : 'New class offering' ?></h2>
                <p><?= $isEdit ? 'Period, faculty, subject code and section are protected once observation history exists.' : 'A class offering links one subject and section to one faculty member for a period.' ?></p>
            </div>
            <?php if ($isEdit): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/observations?class_id=' . (int) $class['class_id'])) ?>">Observations</a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if ($hasHistory): ?>
                <div class="alert alert--warning" role="status">
                    <span class="alert__icon"><?= icon('alert', 18) ?></span>
                    <div class="alert__body"><strong>Observation history exists.</strong> Faculty, period, subject code and section changes are rejected. Create a new offering for a new assignment.</div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(url($action)) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field">
                        <label for="subject_code">Subject code <span class="required">*</span></label>
                        <input class="input<?= has_error('subject_code') ? ' is-invalid' : '' ?>" type="text" id="subject_code" name="subject_code"
                               value="<?= e(old('subject_code', $class['subject_code'] ?? '')) ?>" maxlength="30" required autofocus>
                        <?php if (has_error('subject_code')): ?><span class="field-error"><?= e(error('subject_code')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="subject_name">Subject name <span class="required">*</span></label>
                        <input class="input<?= has_error('subject_name') ? ' is-invalid' : '' ?>" type="text" id="subject_name" name="subject_name"
                               value="<?= e(old('subject_name', $class['subject_name'] ?? '')) ?>" maxlength="150" required>
                        <?php if (has_error('subject_name')): ?><span class="field-error"><?= e(error('subject_name')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="section_name">Section <span class="required">*</span></label>
                        <input class="input<?= has_error('section_name') ? ' is-invalid' : '' ?>" type="text" id="section_name" name="section_name"
                               value="<?= e(old('section_name', $class['section_name'] ?? '')) ?>" maxlength="60" required>
                        <?php if (has_error('section_name')): ?><span class="field-error"><?= e(error('section_name')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="department_id">Department <span class="required">*</span></label>
                        <?php $selectedDepartment = (string) old('department_id', $class['department_id'] ?? ''); ?>
                        <select class="select<?= has_error('department_id') ? ' is-invalid' : '' ?>" id="department_id" name="department_id" required>
                            <option value="">Select a department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['department_id'] ?>"<?= $selectedDepartment === (string) $department['department_id'] ? ' selected' : '' ?>>
                                    <?= e($department['department_name']) ?> (<?= e($department['department_code']) ?>)<?= ($department['status'] ?? '') !== 'Active' ? ' — archived' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_error('department_id')): ?><span class="field-error"><?= e(error('department_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="faculty_id">Faculty member <span class="required">*</span></label>
                        <?php $selectedFaculty = (string) old('faculty_id', $class['faculty_id'] ?? ''); ?>
                        <select class="select<?= has_error('faculty_id') ? ' is-invalid' : '' ?>" id="faculty_id" name="faculty_id" required>
                            <option value="">Select faculty</option>
                            <?php foreach ($faculty as $member): ?>
                                <option value="<?= (int) $member['user_id'] ?>"<?= $selectedFaculty === (string) $member['user_id'] ? ' selected' : '' ?>>
                                    <?= e($member['full_name']) ?><?= !empty($member['employee_no']) ? ' · ' . e($member['employee_no']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_error('faculty_id')): ?><span class="field-error"><?= e(error('faculty_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="period_id">Academic period <span class="required">*</span></label>
                        <?php $selectedPeriod = (string) old('period_id', $class['period_id'] ?? ''); ?>
                        <select class="select<?= has_error('period_id') ? ' is-invalid' : '' ?>" id="period_id" name="period_id" required>
                            <option value="">Select period</option>
                            <?php foreach ($periods as $period): ?>
                                <option value="<?= (int) $period['period_id'] ?>"<?= $selectedPeriod === (string) $period['period_id'] ? ' selected' : '' ?>>
                                    <?= e($period['academic_year']) ?> · <?= e(semester_abbr((string) $period['semester'])) ?> (<?= e($period['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_error('period_id')): ?><span class="field-error"><?= e(error('period_id')) ?></span><?php endif; ?>
                        <span class="help">The class inherits the period's academic year and semester.</span>
                    </div>
                    <?php if ($isEdit): ?>
                        <div class="field">
                            <label for="status">Status</label>
                            <select class="select" id="status" name="status">
                                <option value="Active"<?= ($class['status'] ?? 'Active') === 'Active' ? ' selected' : '' ?>>Active</option>
                                <option value="Archived"<?= ($class['status'] ?? '') === 'Archived' ? ' selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> <?= $isEdit ? 'Save changes' : 'Create offering' ?></button>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/classes')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside class="stack">
        <section class="card">
            <div class="card__body">
                <h3>Class policy</h3>
                <ul class="small muted" style="padding-left:18px;line-height:1.7">
                    <li>One subject, section and period combination may exist only once.</li>
                    <li>Only active classes can receive new observations.</li>
                    <li>Archiving preserves every historical observation and class record.</li>
                    <li>Observations must match the class faculty assignment.</li>
                </ul>
            </div>
        </section>
        <?php if ($isEdit && !empty($class['faculty_name'])): ?>
            <section class="card">
                <div class="card__body">
                    <h3>Current assignment</h3>
                    <ul class="meta-list">
                        <li><span>Faculty</span><strong><?= e($class['faculty_name']) ?></strong></li>
                        <li><span>Department</span><strong><?= e($class['department_name']) ?></strong></li>
                        <li><span>Period</span><strong><?= e($class['academic_year']) ?> · <?= e(semester_abbr((string) $class['semester'])) ?></strong></li>
                        <li><span>Status</span><strong><?= e($class['status']) ?></strong></li>
                    </ul>
                </div>
            </section>
        <?php endif; ?>
    </aside>
</div>
