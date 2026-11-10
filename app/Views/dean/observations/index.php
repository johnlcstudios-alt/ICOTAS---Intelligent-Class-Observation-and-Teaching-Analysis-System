<?php
/**
 * @var array $observations
 * @var array $filters
 * @var bool $demo
 */
$typeLabels = [
    'classroom_observation' => 'Classroom observation',
    'peer_observation' => 'Peer observation',
    'supervisory_observation' => 'Supervisory observation',
    'walkthrough' => 'Walkthrough',
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Teaching quality</span>
        <h2>Class observations</h2>
        <p>Record qualitative classroom observations separately from anonymous student evaluations. Observation notes are attributed and visible to the observed instructor.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('dean/observations/create') ?>"><i class="bi bi-plus-lg"></i> New observation</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration workspace.</strong> Observation records shown here are synthetic.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert"><i class="bi bi-info-circle"></i><span>Observation ratings are a separate professional-development record. They are not student evaluation responses and must not be merged into evaluation averages or presented as student findings.</span></div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/observations') ?>" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small" for="obsSearch">Search</label>
                <input class="form-control form-control-sm" id="obsSearch" name="search" type="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Course, section, instructor or strength">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="obsStatus">Status</label>
                <select class="form-select form-select-sm" id="obsStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['draft' => 'Draft', 'completed' => 'Completed', 'shared' => 'Shared'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected($filters['status'] ?? '', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('dean/observations') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Observation records</strong><span class="text-muted small">Up to 200 recent records</span></div>
    <div class="card-body p-0">
        <?php if (empty($observations)): ?>
            <div class="empty-state"><i class="bi bi-eye"></i>No class observations match this view.<div class="mt-2"><a class="btn btn-sm btn-primary" href="<?= url('dean/observations/create') ?>">Record an observation</a></div></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr><th>Date</th><th>Instructor</th><th>Class</th><th>Type</th><th class="text-center">Rating</th><th>Status</th><th>Observer</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($observations as $observation): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(format_date($observation['observation_date'])) ?></td>
                            <td>
                                <?= e(trim($observation['instructor_first'] . ' ' . $observation['instructor_last'])) ?>
                                <div class="text-tiny text-muted"><?= e($observation['academic_year_name'] . ' · ' . $observation['semester_name']) ?></div>
                            </td>
                            <td>
                                <?= e($observation['course_code']) ?>
                                <div class="text-tiny text-muted"><?= e($observation['section_code']) ?> · <?= e(str_limit($observation['course_title'], 40)) ?></div>
                            </td>
                            <td class="text-tiny"><?= e($typeLabels[$observation['observation_type']] ?? ucwords(str_replace('_', ' ', $observation['observation_type']))) ?></td>
                            <td class="text-center"><?= e(format_rating($observation['overall_rating'])) ?></td>
                            <td><?= status_badge($observation['status']) ?></td>
                            <td class="text-tiny"><?= e(trim($observation['observer_first'] . ' ' . $observation['observer_last'])) ?></td>
                            <td class="text-end text-nowrap">
                                <?= display_demo_badge($observation['is_demo'] ?? 0) ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/observations/' . (int) $observation['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="<?= url('dean/observations/' . (int) $observation['id'] . '/delete') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Delete this class observation permanently?"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php if (!empty($observation['strengths']) || !empty($observation['areas_for_improvement'])): ?>
                            <tr class="table-light">
                                <td colspan="8" class="text-tiny">
                                    <?php if (!empty($observation['strengths'])): ?><strong>Strengths:</strong> <?= e(str_limit($observation['strengths'], 180)) ?><?php endif; ?>
                                    <?php if (!empty($observation['areas_for_improvement'])): ?><span class="ms-3"><strong>Improvement areas:</strong> <?= e(str_limit($observation['areas_for_improvement'], 180)) ?></span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
