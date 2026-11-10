<?php
/**
 * @var array $counts
 * @var bool $demoMode
 * @var bool $hasDemoAccounts
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Demonstration workspace</span>
        <h2>Demo data</h2>
        <p>Generate isolated, fictional records for testing and demonstrations. Demo records never mix with live institutional data.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/settings') ?>"><i class="bi bi-gear"></i> Settings</a>
    </div>
</div>

<div class="alert alert-warning app-alert"><i class="bi bi-exclamation-triangle"></i><span>
    <strong>Synthetic data notice.</strong> Demonstration records are fictional. Reports and analytics built from them must not be presented as institutional findings or research evidence.
</span></div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header">
                <strong>Current mode</strong>
                <span class="ms-auto"><?= $demoMode ? '<span class="badge badge-demo">Demo mode ON</span>' : '<span class="badge badge-success">Live mode</span>' ?></span>
            </div>
            <div class="card-body">
                <?php if ($demoMode): ?>
                    <p>Analytics, sessions, surveys and management screens are currently scoped to <strong>synthetic demonstration records</strong>. Live records remain in the database but are hidden from the active workspace.</p>
                <?php else: ?>
                    <p>The system is currently showing <strong>live institutional records</strong>. Demo records may still exist in the database but are excluded from the active workspace.</p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="<?= url('dean/demo/generate') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit" data-confirm="Generate a fresh demonstration dataset? Existing demo records will be replaced. Live records are not touched."><i class="bi bi-database-add"></i> <?= $demoMode ? 'Regenerate demo data' : 'Generate demo data' ?></button>
                    </form>
                    <?php if ($demoMode || array_sum($counts) > 0): ?>
                        <form method="post" action="<?= url('dean/demo/reset') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger" type="submit" data-confirm="Permanently delete ALL demonstration records and switch to live mode? Live institutional records will not be touched."><i class="bi bi-trash"></i> Reset demo data</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Demo accounts</strong><span class="text-muted small">Password for all demo accounts</span></div>
            <div class="card-body">
                <?php if ($hasDemoAccounts): ?>
                    <div class="row g-3 small">
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-uppercase text-tiny text-muted">Dean / Admin</div>
                                <code>demo.dean</code>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-uppercase text-tiny text-muted">Instructor</div>
                                <code>demo.instructor.01</code> … <code>demo.instructor.12</code>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-uppercase text-tiny text-muted">Student</div>
                                <code>demo.student.01</code> …
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0">Shared password: <code>Demo@12345</code></p>
                <?php else: ?>
                    <p class="mb-0 text-muted">No demo accounts are present. Generate demo data to create the demonstration accounts.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><strong>Records in the active scope</strong><span class="text-muted small"><?= $demoMode ? 'Demo' : 'Live' ?></span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <tbody>
                        <?php
                        $labels = [
                            'departments' => 'Departments',
                            'programs' => 'Programs',
                            'courses' => 'Courses',
                            'sections' => 'Sections',
                            'instructors' => 'Instructors',
                            'students' => 'Students',
                            'sessions' => 'QR evaluation sessions',
                            'responses' => 'Anonymous evaluation responses',
                            'uat_respondents' => 'UAT responses',
                        ];
                        foreach ($labels as $key => $label): ?>
                            <tr><td><?= e($label) ?></td><td class="text-end"><strong><?= (int) ($counts[$key] ?? 0) ?></strong></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Safety guarantees</strong></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li class="d-block mb-2"><i class="bi bi-tag text-primary"></i> Demo records carry an <code>is_demo</code> marker and are queried separately from live records.</li>
                    <li class="d-block mb-2"><i class="bi bi-shield-check text-success"></i> Reset deletes only records flagged as demonstration data.</li>
                    <li class="d-block mb-2"><i class="bi bi-person-x text-danger"></i> Demo students are fictional; no real student identity is used.</li>
                    <li class="d-block mb-2"><i class="bi bi-file-earmark-excel text-warning"></i> Demo analytics must be labeled as synthetic in any export.</li>
                    <li class="d-block"><i class="bi bi-arrow-repeat text-secondary"></i> Regeneration is deterministic for repeatable interface testing.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
