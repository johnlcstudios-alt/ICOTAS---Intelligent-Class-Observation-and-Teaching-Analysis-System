<?php
/**
 * @var array $pagination
 * @var array $filters
 * @var array $users
 */
use App\Core\View;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Governance</span>
        <h2>Audit logs</h2>
        <p>Security-relevant actions recorded by the system. IP addresses are stored only as one-way hashes.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/settings') ?>"><i class="bi bi-gear"></i> Settings</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/audit-logs') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="auditAction">Action contains</label>
                <input class="form-control form-control-sm" id="auditAction" name="action" type="search" value="<?= e($filters['action'] ?? '') ?>" placeholder="e.g. login, report, user">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="auditUser">User</label>
                <select class="form-select form-select-sm" id="auditUser" name="user_id">
                    <option value="">All users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= selected($filters['user_id'] ?? '', $user['id']) ?>><?= e(full_name($user) . ' (' . $user['username'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="auditFrom">From</label>
                <input class="form-control form-control-sm" id="auditFrom" name="from" type="date" value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="auditTo">To</label>
                <input class="form-control form-control-sm" id="auditTo" name="to" type="date" value="<?= e($filters['to'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('dean/audit-logs') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong><?= (int) ($pagination['total'] ?? 0) ?> audit record(s)</strong><span class="text-muted small">Newest first</span></div>
    <div class="card-body p-0">
        <?php if (empty($pagination['rows'])): ?>
            <div class="empty-state"><i class="bi bi-shield-check"></i>No audit records match these filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP hint</th></tr></thead>
                    <tbody>
                    <?php foreach ($pagination['rows'] as $row): ?>
                        <tr>
                            <td class="text-nowrap text-tiny"><?= e(format_datetime($row['created_at'])) ?><div class="text-muted"><?= e(time_ago($row['created_at'])) ?></div></td>
                            <td class="text-tiny">
                                <?php if (!empty($row['username'])): ?>
                                    <?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?>
                                    <div class="text-muted">@<?= e($row['username']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">System / unknown</span>
                                <?php endif; ?>
                            </td>
                            <td><code class="text-tiny"><?= e($row['action']) ?></code></td>
                            <td class="text-tiny"><?= e($row['entity_type'] ?: '—') ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?></td>
                            <td class="text-tiny"><?= e($row['description'] ?: '—') ?></td>
                            <td class="text-tiny text-muted"><code><?= e($row['ip_hint'] ?? '—') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php View::partial('partials.pagination', ['pagination' => $pagination]); ?>

<div class="data-note mt-3">Audit logs support accountability and incident review. They are read-only in the interface; database-level access remains restricted to authorized administrators.</div>
