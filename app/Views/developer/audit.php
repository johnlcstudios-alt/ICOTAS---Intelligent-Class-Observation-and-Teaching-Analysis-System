<?php
/**
 * @var string|null $developerKey
 * @var string|null $consoleBase
 * @var array $pagination
 * @var array $filters
 * @var array $users
 */
$consoleBase = $consoleBase ?? ('developer/' . rawurlencode((string) ($developerKey ?? '')));
$console = static fn (string $path = ''): string => url($consoleBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$rows = $pagination['rows'] ?? [];
$total = (int) ($pagination['total'] ?? 0);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Oversight</span>
        <h2>Audit log</h2>
        <p>Every security-relevant action, including all developer-console reads and writes. IP addresses are stored as one-way hashes; only a short hint is shown.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= $console('audit?action=developer.') ?>"><i class="bi bi-terminal"></i> Developer events</a>
        <a class="btn btn-outline-secondary" href="<?= $console('settings') ?>"><i class="bi bi-sliders"></i> Settings</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= $console('audit') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="devAuditAction">Action contains</label>
                <input class="form-control form-control-sm" id="devAuditAction" name="action" type="search" value="<?= e($filters['action'] ?? '') ?>" placeholder="e.g. login, developer, report">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="devAuditUser">User</label>
                <select class="form-select form-select-sm" id="devAuditUser" name="user_id">
                    <option value="">All users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= selected($filters['user_id'] ?? '', $user['id']) ?>><?= e(full_name($user) . ' (' . $user['username'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="devAuditFrom">From</label>
                <input class="form-control form-control-sm" id="devAuditFrom" name="from" type="date" value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="devAuditTo">To</label>
                <input class="form-control form-control-sm" id="devAuditTo" name="to" type="date" value="<?= e($filters['to'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= $console('audit') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong><?= number_format($total) ?> audit record(s)</strong><span class="text-muted small">Newest first</span></div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-shield-check"></i>No audit records match these filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP hint</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap text-tiny">
                                <?= e(format_datetime($row['created_at'])) ?>
                                <div class="text-muted"><?= e(time_ago($row['created_at'])) ?></div>
                            </td>
                            <td class="text-tiny">
                                <?php if (!empty($row['username'])): ?>
                                    <?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?>
                                    <div class="text-muted">@<?= e($row['username']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">System / developer</span>
                                <?php endif; ?>
                            </td>
                            <td><code class="text-tiny"><?= e($row['action']) ?></code></td>
                            <td class="text-tiny"><?= e($row['entity_type'] ?: '—') ?><?= ($row['entity_id'] ?? null) !== null ? ' #' . (int) $row['entity_id'] : '' ?></td>
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

<?php \App\Core\View::partial('partials.pagination', ['pagination' => $pagination]); ?>

<div class="data-note mt-3">Audit records are read-only in the interface. Developer SQL statements are summarized in the description so schema-level changes remain attributable.</div>
