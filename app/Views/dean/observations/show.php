<?php
/** Dean observation show. */
$labels = rating_labels();

$canEdit = $canEdit ?? false;
$editUrl = $canEdit
    ? url('/dean/observations/' . (int) ($observation['id'] ?? 0) . '/edit')
    : null;

$statusLabel = $observation['status'] ?? '—';
$grouping = $observation['grouped'] ?? [];
$groupingLabels = [
    'Teaching Competence' => 'Teaching Competence',
    'Personality' => 'Personality',
];

$teachingCount = count($grouping['Teaching Competence'] ?? []);
$personalityCount = count($grouping['Personality'] ?? []);
$overallCount = $teachingCount + $personalityCount;
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head">
                <h2><?= e($title) ?></h2>
                <div class="card__head__meta">
                    <span class="muted small"><?= e($observation['subject_code']) ?> · <?= e($observation['section_name']) ?></span>
                    <span class="badge badge--neutral nowrap"><?= e($statusLabel) ?></span>
                </div>
            </div>
            <div class="card__body">
                <div class="card-body__column">
                    <div class="meta-list">
                        <li><span>Observation ID</span><strong><?= (int) ($observation['id'] ?? 0) ?></strong></li>
                        <li><span>Subject</span><strong><?= e($observation['subject_code'] ?? '') ?></strong></li>
                        <li><span>Section</span><strong><?= e($observation['section_name'] ?? '') ?></strong></li>
                        <li><span>Academic year</span><strong><?= e($observation['academic_year'] ?? '') ?></strong></li>
                        <li><span>Semester</span><strong><?= e(semester_abbr((string) ($observation['semester'] ?? ''))) ?></strong></li>
                        <li><span>Observation date</span><strong><?= e(fmt_date($observation['observation_date'] ?? '')) ?></strong></li>
                        <li><span>Lesson topic</span><strong><?= e($observation['lesson_topic'] ?? '') ?></strong></li>
                        <li><span>Room</span><strong><?= e($observation['room'] ?? '—') ?></strong></li>
                        <li><span>Schedule</span><strong><?= e($observation['schedule'] ?? '—') ?></strong></li>
                        <li><span>Faculty</span><strong><?= e($observation['faculty_name'] ?? '') ?></strong></li>
                        <li><span>Department</span><strong><?= e($observation['department_name'] ?? '—') ?></strong></li>
                        <li><span>Observer</span><strong><?= e($observation['observer_name'] ?? '') ?></strong></li>
                        <li><span>Notes</span><strong><?= e($observation['notes'] ?? '—') ?></strong></li>
                        <li><span>Created</span><strong><?= e(fmt_datetime($observation['created_at'] ?? '')) ?></strong></li>
                        <li><span>Updated</span><strong><?= e(fmt_datetime($observation['updated_at'] ?? '')) ?></strong></li>
                    </div>

                    <?php if (!empty($observation['notes'])): ?>
                        <div class="mt-3">
                            <strong>Notes</strong>
                            <div class="muted small" style="white-space:pre-wrap"><?= e($observation['notes']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="card-body__section-title mt-3">Section</div>
                    <div class="card-body__column">
                        <div class="meta-list">
                            <li><span>Subject</span><strong><?= e($observation['subject_code'] ?? '') ?></strong></li>
                            <li><span>Section</span><strong><?= e($observation['section_name'] ?? '') ?></strong></li>
                            <li><span>Academic year</span><strong><?= e($observation['academic_year'] ?? '') ?></strong></li>
                            <li><span>Semester</span><strong><?= e(semester_abbr((string) ($observation['semester'] ?? ''))) ?></strong></li>
                            <li><span>Study mode</span><strong><?= e($observation['study_mode'] ?? '') ?></strong></li>
                            <li><span>Room</span><strong><?= e($observation['room'] ?? '—') ?></strong></li>
                            <li><span>Schedule</span><strong><?= e($observation['schedule'] ?? '—') ?></strong></li>
                            <li><span>Faculty</span><strong><?= e($observation['faculty_name'] ?? '') ?></strong></li>
                            <li><span>Department</span><strong><?= e($observation['department_name'] ?? '—') ?></strong></li>
                            <li><span>Created</span><strong><?= e(fmt_datetime($observation['created_at'] ?? '')) ?></strong></li>
                            <li><span>Updated</span><strong><?= e(fmt_datetime($observation['updated_at'] ?? '')) ?></strong></li>
                        </div>
                    </div>
                </div>

                <div class="card-body__column card-body__column--scores">
                    <div class="meta-list">
                        <li><span>Observation</span><strong><?= (int) ($observation['id'] ?? 0) ?></strong></li>
                        <li><span>Faculty</span><strong><?= e($observation['faculty_name'] ?? '') ?></strong></li>
                        <li><span>Department</span><strong><?= e($observation['department_name'] ?? '—') ?></strong></li>
                        <li><span>Observer</span><strong><?= e($observation['observer_name'] ?? '') ?></strong></li>
                        <li><span>Subject</span><strong><?= e($observation['subject_code'] ?? '') ?></strong></li>
                        <li><span>Section</span><strong><?= e($observation['section_name'] ?? '') ?></strong></li>
                        <li><span>Academic year</span><strong><?= e($observation['academic_year'] ?? '') ?></strong></li>
                        <li><span>Semester</span><strong><?= e(semester_abbr((string) ($observation['semester'] ?? ''))) ?></strong></li>
                        <li><span>Observation date</span><strong><?= e(fmt_date($observation['observation_date'] ?? '')) ?></strong></li>
                        <li><span>Lesson topic</span><strong><?= e($observation['lesson_topic'] ?? '') ?></strong></li>
                        <li><span>Room</span><strong><?= e($observation['room'] ?? '—') ?></strong></li>
                        <li><span>Schedule</span><strong><?= e($observation['schedule'] ?? '—') ?></strong></li>
                        <li><span>Notes</span><strong><?= e($observation['notes'] ?? '—') ?></strong></li>
                        <li><span>Created</span><strong><?= e(fmt_datetime($observation['created_at'] ?? '')) ?></strong></li>
                        <li><span>Updated</span><strong><?= e(fmt_datetime($observation['updated_at'] ?? '')) ?></strong></li>
                    </div>

                    <?php if (!empty($observation['notes'])): ?>
                        <div class="mt-3">
                            <strong>Notes</strong>
                            <div class="muted small" style="white-space:pre-wrap"><?= e($observation['notes']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($canEdit && $editUrl): ?>
            <section class="card">
                <div class="card__head"><h2>Actions</h2></div>
                <div class="card__body">
                    <a class="btn btn--secondary" href="<?= e($editUrl) ?>"><?= icon('pencil', 16) ?> Edit observation</a>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/observations')) ?>"><?= icon('list', 16) ?> All observations</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><h2>Observation summary</h2></div>
            <div class="card__body">
                <?php if (empty($grouping)): ?>
                    <p class="muted">This observation has no detail row yet.</p>
                <?php else: ?>
                    <ul class="meta-list">
                        <li><span>Teaching Competence</span><strong><?= $teachingCount ?> criteria</strong></li>
                        <li><span>Personality</span><strong><?= $personalityCount ?> criteria</strong></li>
                        <li><span>Overall criteria</span><strong><?= $overallCount ?> criteria</strong></li>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
