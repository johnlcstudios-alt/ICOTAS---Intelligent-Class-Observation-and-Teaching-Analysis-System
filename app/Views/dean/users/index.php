<?php
/** @var array $pagination */
/** @var array $filters */
/** @var array $roles */
/** @var array $counts */
/** @var array $departments */
/** @var bool $demo */
use App\Core\Auth;

$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account governance</span>
        <h2>User management</h2>
        <p>Create and maintain Dean, Academic Head, Instructor and Student accounts. Deactivating an account preserves its historical records.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('dean/users/import') ?>"><i class="bi bi-upload"></i> Import CSV</a>
        <a class="btn btn-primary" href="<?= url('dean/users/create') ?>"><i class="bi bi-person-plus"></i> New user</a>
    </div>
</div>

<div class="stat-grid mb-4">
    <?php foreach ($roles as $role): ?>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi <?= $role['slug'] === 'student' ? 'bi-mortarboard' : ($role['slug'] === 'instructor' ? 'bi-person-workspace' : 'bi-shield-check') ?>"></i></span>
            <div class="stat-label"><?= e($role['name']) ?></div>
            <div class="stat-value"><?= (int) ($counts[$role['slug']] ?? 0) ?></div>
            <div class="stat-note"><?= $demo ? 'Demo-scope accounts' : 'Live accounts' ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/users') ?>" class="row g-2 align-items-end">
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
            <div class="col-md-2">
                <label class="form-label" for="userDepartment">Department</label>
                <select class="form-select" id="userDepartment" name="department_id">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= selected($filters['department_id'] ?? '', $department['id']) ?>><?= e($department['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="<?= url('dean/users') ?>" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Accounts</strong>
        <span class="text-muted small"><?= $total ?> record(s) in the <?= $demo ? 'demonstration' : 'live' ?> scope</span>
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
                                        <span class="text-tiny text-muted d-block text-truncate"><?= e($row['email']) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><code><?= e($row['username']) ?></code><?= display_demo_badge($row['is_demo'] ?? 0) ?></td>
                            <td><?= e($row['role_name'] ?? $row['role']) ?></td>
                            <td class="small">
                                <?php if (!empty($row['department_code'])): ?><span class="badge badge-soft"><?= e($row['department_code']) ?></span><?php endif; ?>
                                <?php if (!empty($row['program_code'])): ?><span class="badge badge-soft"><?= e($row['program_code']) ?></span><?php endif; ?>
                                <?php if (!empty($row['section_code'])): ?><span class="badge badge-soft"><?= e($row['section_code']) ?></span><?php endif; ?>
                                <?php if (empty($row['department_code']) && empty($row['program_code']) && empty($row['section_code'])): ?>—<?php endif; ?>
                            </td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/users/' . (int) $row['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
                                    <?php if ((int) $row['id'] !== (int) Auth::id()): ?>
                                        <form method="post" action="<?= url('dean/users/' . (int) $row['id'] . '/status') ?>" class="d-inline" data-confirm="Change the status of this account?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                                <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-info">You</span>
                                    <?php endif; ?>
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
<?php App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>
