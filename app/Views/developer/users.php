<?php
/** @var array $pagination */
/** @var array $filters */
/** @var array $roles */
/** @var array $roleCounts */
/** @var bool $demo */
/** @var string|null $consoleBase */
$consoleBase = $consoleBase ?? ('developer/' . rawurlencode((string) ($developerKey ?? '')));
$console = static fn (string $path = ''): string => url($consoleBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">User management</span>
        <h2>All accounts</h2>
        <p>Create, edit, activate, deactivate, and delete any account in the system. Every change is audited.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= $console('users/create') ?>"><i class="bi bi-person-plus"></i> New account</a>
    </div>
</div>

<div class="stat-grid mb-4">
    <?php foreach ($roles as $role): ?>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi <?= $role['slug'] === 'student' ? 'bi-mortarboard' : ($role['slug'] === 'instructor' ? 'bi-person-workspace' : ($role['slug'] === 'developer' ? 'bi-pc-display' : 'bi-shield-check')) ?>"></i></span>
            <div class="stat-label"><?= e($role['name']) ?></div>
            <div class="stat-value"><?= (int) ($roleCounts[$role['slug']]['total'] ?? 0) ?></div>
            <div class="stat-note"><?= $demo ? 'Demo scope' : 'Live scope' ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= $console('users') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="userSearch">Search</label>
                <input class="form-control" type="search" id="userSearch" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, username, email, ID number">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="userRole">Role</label>
                <select class="form-select" id="userRole" name="role">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role['slug']) ?>" <?= selected($filters['role'] ?? '', $role['slug']) ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="userStatus">Status</label>
                <select class="form-select" id="userStatus" name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= selected($filters['status'] ?? '', 'active') ?>>Active</option>
                    <option value="inactive" <?= selected($filters['status'] ?? '', 'inactive') ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="<?= $console('users') ?>"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($rows)): ?>
    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
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
                                        <span class="text-tiny text-muted d-block text-truncate"><?= e($row['email'] ?? '') ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><code><?= e((string) $row['username']) ?></code></td>
                            <td class="text-truncate" style="max-width: 180px"><?= e((string) ($row['email'] ?? '-')) ?></td>
                            <td><span class="badge badge-soft"><?= e((string) ($row['role'] ?? $row['role_name'] ?? '')) ?></span><?= display_demo_badge($row['is_demo'] ?? 0) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $console('users/' . (int) $row['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
                                    <?php if ((int) $row['id'] !== (int) auth_id()): ?>
                                        <form method="post" action="<?= $console('users/' . (int) $row['id'] . '/status') ?>" class="d-inline" data-confirm="Change the status of this account?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                                <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>
    </div>
<?php else: ?>
    <div class="empty-state"><i class="bi bi-people"></i>No accounts found.</div>
<?php endif; ?>
