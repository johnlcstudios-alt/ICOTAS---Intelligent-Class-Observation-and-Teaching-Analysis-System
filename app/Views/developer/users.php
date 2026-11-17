<?php
/**
 * @var string $developerKey
 * @var array $pagination
 * @var array $filters
 * @var array $roles
 * @var array $roleCounts
 * @var bool $demoMode
 */
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
$scopeFilter = isset($filters['is_demo']) ? (string) $filters['is_demo'] : '';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Identity and access</span>
        <h2>User accounts</h2>
        <p>Create, repair, deactivate or remove any account, including Deans. Developer changes bypass the normal administrative role ceiling and are recorded in the audit log.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $console('users?is_demo=1') ?>"><i class="bi bi-database"></i> Demo accounts</a>
        <a class="btn btn-primary" href="<?= $console('users/create') ?>"><i class="bi bi-person-plus"></i> New account</a>
    </div>
</div>

<div class="stat-grid mb-4">
    <?php foreach ($roleCounts as $slug => $count): ?>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi <?= $slug === 'student' ? 'bi-mortarboard' : ($slug === 'instructor' ? 'bi-person-workspace' : 'bi-shield-check') ?>"></i></span>
            <div class="stat-label"><?= e($count['name']) ?></div>
            <div class="stat-value"><?= (int) $count['total'] ?></div>
            <div class="stat-note"><?= (int) $count['live'] ?> live · <?= (int) $count['demo'] ?> demo</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= $console('users') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="devUserSearch">Search</label>
                <input class="form-control" type="search" id="devUserSearch" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, username, email, ID number">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="devUserRole">Role</label>
                <select class="form-select" id="devUserRole" name="role">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role['slug']) ?>" <?= selected($filters['role'] ?? '', $role['slug']) ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="devUserStatus">Status</label>
                <select class="form-select" id="devUserStatus" name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= selected($filters['status'] ?? '', 'active') ?>>Active</option>
                    <option value="inactive" <?= selected($filters['status'] ?? '', 'inactive') ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="devUserScope">Data scope</label>
                <select class="form-select" id="devUserScope" name="is_demo">
                    <option value="">All records</option>
                    <option value="0" <?= selected($scopeFilter, '0') ?>>Live only</option>
                    <option value="1" <?= selected($scopeFilter, '1') ?>>Demo only</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="<?= $console('users') ?>" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Accounts</strong>
        <span class="text-muted small"><?= number_format($total) ?> record(s)<?= $demoMode ? ' · demo mode is ON' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-people"></i>No accounts match these filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Assignment</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm"><?= e(initials($row)) ?></span>
                                    <span class="min-w-0">
                                        <strong class="d-block text-truncate"><?= e(full_name($row)) ?></strong>
                                        <span class="text-tiny text-muted d-block text-truncate"><?= e($row['email']) ?> · #<?= (int) $row['id'] ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><code><?= e($row['username']) ?></code><?= display_demo_badge($row['is_demo'] ?? 0) ?></td>
                            <td><span class="badge badge-soft"><?= e($row['role_name'] ?? $row['role']) ?></span></td>
                            <td class="small">
                                <?php $assignment = array_filter([
                                    $row['department_code'] ?? '',
                                    $row['program_code'] ?? '',
                                    $row['section_code'] ?? '',
                                ]); ?>
                                <?php if ($assignment !== []): ?>
                                    <?php foreach ($assignment as $part): ?><span class="badge badge-soft"><?= e($part) ?></span><?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= status_badge((string) $row['status']) ?>
                                <?php if ((int) ($row['must_change_password'] ?? 0) === 1): ?>
                                    <span class="badge badge-warning" title="Must choose a new password at next sign-in">Temp password</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-tiny text-muted"><?= e(format_datetime($row['last_login_at'] ?? null, 'Never')) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $console('users/' . (int) $row['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
                                    <form method="post" action="<?= $console('users/' . (int) $row['id'] . '/status') ?>" class="d-inline" data-confirm="Change the status of @<?= e($row['username']) ?>? Existing sessions will be signed out.">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                            <i class="bi <?= $row['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>

<div class="data-note mt-3">Developer account changes are intentionally outside the normal Dean / Academic Head role ceiling. Every create, update, status change, password reset and deletion is written to the audit log.</div>
