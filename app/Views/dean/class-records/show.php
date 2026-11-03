<?php
/** Dean class record show. */
$labels = rating_labels();

$isOwner = $canEdit ?? false;
$recordStatusLabel = $record['record_status_label'] ?? '—';
$gradeRules = $gradeRules ?? ['min' => 0, 'max' => 100, 'passing' => 75];

$passedCount = (int) ($record['passed_count'] ?? 0);
$failedCount = (int) ($record['failed_count'] ?? 0);
$totalCount = (int) ($record['total_students'] ?? 0);
$computedTotal = $passedCount + $failedCount;
$avgGrade = $record['average_grade'];

$gradingMin = (double) ($settings['grading_min'] ?? 0);
$gradingMax = (double) ($settings['grading_max'] ?? 100);
$passingGrade = (double) ($settings['passing_grade'] ?? 75);

$passedPct = $totalCount > 0 ? round(100 * $passedCount / $totalCount, 1) : 0;
$failedPct = $totalCount > 0 ? round(100 * $failedCount / $totalCount, 1) : 0;
$pctsSum = $passedPct + $failedPct;
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <h2><?= e($title) ?></h2>
                <div class="card__head__meta">
                    <span class="muted small"><?= e($record['subject_code']) ?> · <?= e($record['section_name']) ?></span>
                    <span class="badge badge--neutral nowrap"><?= e($recordStatusLabel) ?></span>
                </div>
            </div>
            <div class="card__body">
                <div class="meta-list">
                    <li><span>Record</span><strong><?= (int) $record['id'] ?></strong></li>
                    <?php if (!empty($record['class_record_no'])): ?>
                        <li><span>Record number</span><strong><?= e($record['class_record_no']) ?></strong></li>
                    <?php endif; ?>
                    <li><span>Subject</span><strong><?= e($record['subject_code']) ?></strong></li>
                    <li><span>Section</span><strong><?= e($record['section_name']) ?></strong></li>
                    <li><span>Academic year</span><strong><?= e($record['academic_year']) ?></strong></li>
                    <li><span>Semester</span><strong><?= e(semester_abbr((string) $record['semester'])) ?></strong></li>
                    <?php if (!empty($record['school_year'])): ?>
                        <li><span>School year</span><strong><?= e($record['school_year']) ?></strong></li>
                    <?php endif; ?>
                    <li><span>Room</span><strong><?= e($record['room'] ?? '—') ?></strong></li>
                    <li><span>Schedule</span><strong><?= e($record['schedule'] ?? '—') ?></strong></li>
                    <li><span>Faculty</span><strong><?= e($record['faculty_name']) ?></strong></li>
                    <li><span>Department</span><strong><?= e($record['department_name']) ?></strong></li>
                    <li><span>Created</span><strong><?= e(fmt_datetime($record['created_at'])) ?></strong></li>
                    <li><span>Updated</span><strong><?= e(fmt_datetime($record['updated_at'])) ?></strong></li>
                </div>

                <?php if (!empty($record['notes'])): ?>
                    <div class="mt-3">
                        <strong>Notes</strong>
                        <div class="muted small" style="white-space:pre-wrap"><?= e($record['notes']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="stat-grid stat-grid--wide mt-3">
                    <div class="stat stat--flat">
                        <div class="stat__label">Total students</div>
                        <div class="stat__value"><?= (int) $totalCount ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Passed</div>
                        <div class="stat__value"><?= (int) $passedCount ?></div>
                        <?php if ($totalCount > 0): ?><div class="stat__meta"><?= e($passedPct) ?>% of total</div><?php endif; ?>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Failed</div>
                        <div class="stat__value"><?= (int) $failedCount ?></div>
                        <?php if ($totalCount > 0): ?><div class="stat__meta"><?= e($failedPct) ?>% of total</div><?php endif; ?>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Passed + Failed</div>
                        <div class="stat__value"><?= (int) $computedTotal ?></div>
                        <?php if ($totalCount > 0 && $computedTotal !== $totalCount): ?><div class="stat__meta stat-mismatch">Mismatch (≠ total)</div><?php else: ?><div class="stat__meta muted">Matches total</div><?php endif; ?>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Average grade</div>
                        <div class="stat__value"><?= e(score($avgGrade)) ?></div>
                        <?php if ($avgGrade !== null && $avgGrade !== ''): ?>
                            <?php if ((double) $avgGrade >= $passingGrade): ?><div class="stat__meta text-success">≥ <?= e(score($passingGrade)) ?> passing</div><?php else: ?><div class="stat__meta text-error">&lt; <?= e(score($passingGrade)) ?> passing</div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Grade range</div>
                        <div class="stat__value small"><?= e(score($gradingMin)) ?> – <?= e(score($gradingMax)) ?></div>
                        <div class="stat__meta muted">Passing ≥ <?= e(score($passingGrade)) ?></div>
                    </div>
                </div>

                <?php if ($totalCount > 0): ?>
                    <div class="pass-rate-bar mt-3">
                        <div class="pass-rate-bar__track">
                            <div class="pass-rate-bar__fill pass-rate-bar__passed" style="width:<?= (int) min(100, max(0, $passedPct + ($pctsSum > 0 ? ($passedPct / $pctsSum) * $pctsSum : $passedPct))) ?>%"></div>
                            <div class="pass-rate-bar__fill pass-rate-bar__failed" style="width:<?= (int) min(100, max(0, $failedPct + ($pctsSum > 0 ? ($failedPct / $pctsSum) * $pctsSum : $failedPct))) ?>%"></div>
                        </div>
                        <div class="pass-rate-bar__legend">
                            <span>Passed <?= e($passedPct) ?>%</span>
                            <span>Failed <?= e($failedPct) ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isOwner && $editUrl): ?>
            <section class="card">
                <div class="card__head"><h2>Actions</h2></div>
                <div class="card__body">
                    <a class="btn btn--secondary" href="<?= e($editUrl) ?>"><?= icon('pencil', 16) ?> Edit record</a>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/class-records')) ?>"><?= icon('list', 16) ?> All class records</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><h2>Grade context</h2></div>
            <div class="card__body">
                <p class="small muted">The grade range and passing threshold follow the institution's settings. This record's average grade is the mean of all graded students for the covered section and period.</p>
                <p class="small muted">Pass rate is computed as the share of passed students among the total registered, not among graded students only.</p>
            </div>
        </section>
    </div>
</div>
