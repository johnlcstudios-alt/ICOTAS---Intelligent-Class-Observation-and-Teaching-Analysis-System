<?php
/**
 * @var array $observations
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">My teaching</span>
        <h2>Class observations</h2>
        <p>Feedback recorded by an observer for your classes. Observation notes are separate from anonymous student evaluation results.</p>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration workspace.</strong> These observation records are synthetic.</span></div>
<?php endif; ?>

<?php if (empty($observations)): ?>
    <div class="card"><div class="empty-state"><i class="bi bi-eye"></i>No class observation has been shared with you in this data scope.</div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($observations as $observation): ?>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <strong><?= e(format_date($observation['observation_date'])) ?></strong>
                            <span class="text-muted small d-block"><?= e($observation['course_code'] . ' · ' . $observation['section_code']) ?> · <?= e($observation['academic_year_name'] . ' ' . $observation['semester_name']) ?></span>
                        </div>
                        <div class="ms-auto text-end">
                            <?= status_badge($observation['status']) ?>
                            <div class="text-tiny text-muted mt-1">Rating: <?= e(format_rating($observation['overall_rating'])) ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($observation['strengths'])): ?>
                            <div class="mb-3">
                                <div class="text-uppercase text-tiny text-muted mb-1">Strengths observed</div>
                                <p class="small mb-0"><?= nl2br(e($observation['strengths'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($observation['areas_for_improvement'])): ?>
                            <div class="mb-3">
                                <div class="text-uppercase text-tiny text-muted mb-1">Areas for improvement</div>
                                <p class="small mb-0"><?= nl2br(e($observation['areas_for_improvement'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($observation['recommendations'])): ?>
                            <div class="mb-0">
                                <div class="text-uppercase text-tiny text-muted mb-1">Recommendations</div>
                                <p class="small mb-0"><?= nl2br(e($observation['recommendations'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-tiny text-muted">
                        Observer: <?= e(trim($observation['observer_first'] . ' ' . $observation['observer_last'])) ?>
                        · <?= e(ucwords(str_replace('_', ' ', $observation['observation_type']))) ?>
                        <?= display_demo_badge($observation['is_demo'] ?? 0) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
