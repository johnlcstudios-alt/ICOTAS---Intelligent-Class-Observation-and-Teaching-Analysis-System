<?php
/**
 * @var string $developerKey
 * @var array $counts
 * @var bool $demoMode
 * @var bool $hasDemoAccounts
 */
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$labels = [
    'departments' => ['label' => 'Departments', 'icon' => 'bi-building'],
    'programs' => ['label' => 'Programs', 'icon' => 'bi-diagram-3'],
    'courses' => ['label' => 'Courses', 'icon' => 'bi-journal-bookmark'],
    'sections' => ['label' => 'Sections', 'icon' => 'bi-people-fill'],
    'users' => ['label' => 'Accounts', 'icon' => 'bi-people'],
    'surveys' => ['label' => 'Surveys', 'icon' => 'bi-ui-checks'],
    'sessions' => ['label' => 'QR sessions', 'icon' => 'bi-qr-code'],
    'responses' => ['label' => 'Evaluation responses', 'icon' => 'bi-clipboard-data'],
    'observations' => ['label' => 'Class observations', 'icon' => 'bi-eye'],
    'uat_surveys' => ['label' => 'UAT surveys', 'icon' => 'bi-patch-check'],
];
$scopeTotal = array_sum(array_map('intval', $counts));
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Demonstration workspace</span>
        <h2>Demo data</h2>
        <p>Generate, regenerate or remove the synthetic dataset used for demonstrations and interface testing. Live institutional records are never modified.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= $console('users?is_demo=1') ?>"><i class="bi bi-people"></i> Demo accounts</a>
        <a class="btn btn-outline-secondary" href="<?= $console('data') ?>"><i class="bi bi-table"></i> Data browser</a>
    </div>
</div>

<div class="alert alert-warning app-alert">
    <i class="bi bi-exclamation-triangle"></i>
    <span><strong>Synthetic records only.</strong> Demo data is fictional and flagged with <code>is_demo = 1</code>. It must never be presented as institutional findings, real student feedback or research evidence.</span>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header">
                <strong>Workspace mode</strong>
                <span class="badge <?= $demoMode ? 'badge-demo' : 'badge-success' ?>"><?= $demoMode ? 'Demo mode ON' : 'Live mode' ?></span>
            </div>
            <div class="card-body">
                <?php if ($demoMode): ?>
                    <p>Analytics, management screens, sessions and reports currently read the <strong>synthetic demonstration scope</strong>. Live records remain in the database but are hidden from the active workspace.</p>
                <?php else: ?>
                    <p>The application currently reads <strong>live institutional records</strong>. Demo records may still exist in storage but are excluded from the active workspace.</p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="<?= $console('demo/generate') ?>" data-confirm="Generate a fresh demonstration dataset? Existing demo records are replaced. Live records are not touched.">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-database-add"></i> <?= $demoMode ? 'Regenerate demo data' : 'Generate demo data' ?></button>
                    </form>
                    <?php if ($demoMode || $scopeTotal > 0): ?>
                        <form method="post" action="<?= $console('demo/reset') ?>" data-confirm="Permanently delete ALL demonstration records and switch to live mode? Live institutional records will not be touched.">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Reset demo data</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Demo accounts</strong><span class="text-muted small">Ready for role-based walkthroughs</span></div>
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
                                <div class="text-uppercase text-tiny text-muted">Instructors</div>
                                <code>demo.instructor.01</code> … <code>demo.instructor.12</code>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-uppercase text-tiny text-muted">Students</div>
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
                    <table class="table mb-0 align-middle">
                        <tbody>
                        <?php foreach ($labels as $key => $meta): ?>
                            <tr>
                                <td><i class="bi <?= e($meta['icon']) ?> text-muted"></i> <?= e($meta['label']) ?></td>
                                <td class="text-end"><strong><?= number_format((int) ($counts[$key] ?? 0)) ?></strong></td>
                            </tr>
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
                    <li class="d-block mb-2"><i class="bi bi-tag text-primary"></i> Every demo row carries an <code>is_demo</code> marker.</li>
                    <li class="d-block mb-2"><i class="bi bi-shield-check text-success"></i> Reset deletes only rows flagged as demonstration data.</li>
                    <li class="d-block mb-2"><i class="bi bi-person-x text-danger"></i> Demo identities are fictional.</li>
                    <li class="d-block mb-2"><i class="bi bi-arrow-repeat text-secondary"></i> Regeneration is deterministic for repeatable testing.</li>
                    <li class="d-block"><i class="bi bi-journal-check text-secondary"></i> Generate and reset actions are written to the audit log.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
