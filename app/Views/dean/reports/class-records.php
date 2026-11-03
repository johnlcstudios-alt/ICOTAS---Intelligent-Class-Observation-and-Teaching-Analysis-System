<?php
/** Class record printable report. */

$stats = $stats ?? [];
?>
<div class="report-body">
    <div class="report-body__header">
        <div>
            <span class="eyebrow">Class record report</span>
            <h2><?= e($pageTitle) ?></h2>
            <div class="muted small">Generated <?= e(date('F j, Y g:i A')) ?></div>
        </div>
        <div class="report-body__meta">
            <ul class="meta-list">
                <?php if (!empty($stats['record_count'])): ?>
                    <li><span>Total records</span><strong><?= (int) $stats['record_count'] ?></strong></li>
                    <li><span>Total students tallied</span><strong><?= (int) $stats['total_students'] ?></strong></li>
                    <li><span>Average grade (mean of records)</span><strong><?= e(score($stats['average_grade'])) ?></strong></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <?php if (!empty($filters)): ?>
        <div class="report-body__filters">
            <span class="muted small"><strong>Filters:</strong></span>
            <ul class="meta-list muted small" style="margin-top:4px">
                <?php foreach (['faculty_id', 'department_id', 'class_id', 'period_id', 'academic_year', 'semester', 'record_status', 'search'] as $key): ?>
                    <?php if (!empty($filters[$key])): ?>
                        <li><?= e(str_replace('_', ' ', $key)) ?>: <?= e($filters[$key]) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="report-body__table">
        <?php if (empty($rows)): ?>
            <div class="report-body__empty"><strong>No class records on file</strong></div>
        <?php else: ?>
            <div class="report-body__row report-body__row--head">
                <span class="report-body__cell">Subject</span>
                <span class="report-body__cell">Section</span>
                <span class="report-body__cell">Academic year</span>
                <span class="report-body__cell">Semester</span>
                <span class="report-body__cell">Faculty</span>
                <span class="report-body__cell">Department</span>
                <span class="report-body__cell text-right">Total</span>
                <span class="report-body__cell text-right">Passed</span>
                <span class="report-body__cell text-right">Failed</span>
                <span class="report-body__cell text-right">Avg grade</span>
                <span class="report-body__cell">Status</span>
            </div>
            <?php foreach ($rows as $row): ?>
                <div class="report-body__row">
                    <span class="report-body__cell"><?= e($row['subject_code']) ?></span>
                    <span class="report-body__cell"><?= e($row['section_name']) ?></span>
                    <span class="report-body__cell"><?= e($row['academic_year']) ?></span>
                    <span class="report-body__cell"><?= e(semester_abbr((string) $row['semester'])) ?></span>
                    <span class="report-body__cell"><?= e($row['faculty_name']) ?></span>
                    <span class="report-body__cell"><?= e($row['department_name']) ?></span>
                    <span class="report-body__cell text-right"><?= (int) $row['total_students'] ?></span>
                    <span class="report-body__cell text-right"><?= (int) $row['passed_count'] ?></span>
                    <span class="report-body__cell text-right"><?= (int) $row['failed_count'] ?></span>
                    <span class="report-body__cell text-right"><?= e(score($row['average_grade'])) ?></span>
                    <span class="report-body__cell"><?= status_badge($row['record_status']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
