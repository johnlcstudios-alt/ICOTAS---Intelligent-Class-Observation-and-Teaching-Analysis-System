<?php
/**
 * @var string $developerKey
 * @var array $stats
 * @var array $roleCounts
 * @var array $recentUsers
 * @var array $recentAudit
 * @var int $auditToday
 * @var int $activeSessions
 * @var bool $demoMode
 * @var string $appVersion
 * @var string $phpVersion
 * @var array $health
 */
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int) floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
};
$healthOk = count(array_filter($health, static fn (array $check): bool => !empty($check['ok'])));
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Developer console</span>
        <h2>System command center</h2>
        <p>Inspect and repair every part of the installation: accounts, raw records, settings, schema-level queries and demonstration data. The console sits outside user RBAC and is reachable only with the developer key in the URL.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= $console('data') ?>"><i class="bi bi-table"></i> Data browser</a>
        <a class="btn btn-outline-primary" href="<?= $console('sql') ?>"><i class="bi bi-code-square"></i> SQL console</a>
        <a class="btn btn-primary" href="<?= $console('users/create') ?>"><i class="bi bi-person-plus"></i> New account</a>
    </div>
</div>

<div class="alert alert-danger app-alert">
    <i class="bi bi-shield-exclamation"></i>
    <span><strong>Treat this URL like a root password.</strong> Anyone holding the developer key can create accounts, edit any row, change settings and run SQL. There is no login prompt, navigation link or recovery flow. Rotate <code>developer_key</code> in <code>config/local.php</code> immediately if this URL is exposed.</span>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-table"></i></span>
        <div class="stat-label">Database tables</div>
        <div class="stat-value"><?= (int) ($stats['tables'] ?? 0) ?></div>
        <div class="stat-note"><?= e((string) ($stats['name'] ?? 'database')) ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></span>
        <div class="stat-label">Rows (estimated)</div>
        <div class="stat-value"><?= number_format((int) ($stats['rows_estimate'] ?? 0)) ?></div>
        <div class="stat-note">InnoDB estimates</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-hdd-stack"></i></span>
        <div class="stat-label">Storage</div>
        <div class="stat-value"><?= e($formatBytes((int) ($stats['size_bytes'] ?? 0))) ?></div>
        <div class="stat-note">Data + index length</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-person-check"></i></span>
        <div class="stat-label">Active sessions</div>
        <div class="stat-value"><?= (int) $activeSessions ?></div>
        <div class="stat-note">Last 30 minutes</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-shield-check"></i></span>
        <div class="stat-label">Audit events today</div>
        <div class="stat-value"><?= (int) $auditToday ?></div>
        <div class="stat-note"><?= $healthOk ?> of <?= count($health) ?> health checks pass</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header">
                <strong>Recent accounts</strong>
                <a class="small" href="<?= $console('users') ?>">View all users</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentUsers)): ?>
                    <div class="empty-state"><i class="bi bi-people"></i>No accounts exist yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Account</th><th>Role</th><th>Status</th><th class="text-end">Created</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentUsers as $row): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-sm"><?= e(initials($row)) ?></span>
                                            <span class="min-w-0">
                                                <strong class="d-block text-truncate"><?= e(full_name($row)) ?></strong>
                                                <span class="text-tiny text-muted d-block text-truncate">@<?= e($row['username']) ?> · <?= e($row['email']) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft"><?= e($row['role_name'] ?? $row['role']) ?></span>
                                        <?= display_demo_badge($row['is_demo'] ?? 0) ?>
                                    </td>
                                    <td><?= status_badge((string) $row['status']) ?></td>
                                    <td class="text-end text-tiny text-muted"><?= e(format_datetime($row['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>Accounts by role</strong>
                <span class="text-muted small">Live and demonstration records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Role</th><th class="text-end">Total</th><th class="text-end">Live</th><th class="text-end">Demo</th><th class="text-end">Manage</th></tr></thead>
                        <tbody>
                        <?php foreach ($roleCounts as $row): ?>
                            <tr>
                                <td><strong><?= e($row['name']) ?></strong><div class="text-tiny text-muted"><?= e($row['slug']) ?></div></td>
                                <td class="text-end"><?= (int) $row['total'] ?></td>
                                <td class="text-end"><?= (int) $row['live_count'] ?></td>
                                <td class="text-end"><?= (int) $row['demo_count'] ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= $console('users?role=' . rawurlencode((string) $row['slug'])) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header">
                <strong>Environment</strong>
                <span class="badge <?= $demoMode ? 'badge-demo' : 'badge-success' ?>"><?= $demoMode ? 'Demo mode ON' : 'Live mode' ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Application</dt>
                    <dd class="col-7">ICOTAS <?= e($appVersion) ?></dd>
                    <dt class="col-5 text-muted">PHP</dt>
                    <dd class="col-7"><code><?= e($phpVersion) ?></code></dd>
                    <dt class="col-5 text-muted">Database</dt>
                    <dd class="col-7"><?= e((string) ($stats['name'] ?? '—')) ?></dd>
                    <dt class="col-5 text-muted">MySQL / MariaDB</dt>
                    <dd class="col-7 mb-0"><?= e((string) ($stats['version'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <strong>Health checks</strong>
                <span class="text-muted small"><?= $healthOk ?>/<?= count($health) ?> passing</span>
            </div>
            <div class="card-body">
                <ul class="list-clean small">
                    <?php foreach ($health as $check): ?>
                        <li>
                            <span>
                                <i class="bi <?= !empty($check['ok']) ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?>"></i>
                                <?= e($check['label']) ?>
                                <span class="d-block text-tiny text-muted"><?= e($check['detail'] ?? '') ?></span>
                            </span>
                            <span class="badge <?= !empty($check['ok']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($check['ok']) ? 'Pass' : 'Check' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>Latest audit events</strong>
                <a class="small" href="<?= $console('audit') ?>">Full log</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentAudit)): ?>
                    <div class="empty-state py-4"><i class="bi bi-shield-check"></i>No events recorded.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentAudit as $event): ?>
                            <div class="list-group-item py-2">
                                <div class="d-flex justify-content-between gap-2">
                                    <code class="text-tiny"><?= e($event['action']) ?></code>
                                    <span class="text-tiny text-muted text-nowrap"><?= e(time_ago($event['created_at'])) ?></span>
                                </div>
                                <div class="text-tiny text-muted text-truncate"><?= e($event['description'] ?: ($event['entity_type'] ?: 'No description')) ?></div>
                                <div class="text-tiny text-muted">@<?= e($event['username'] ?? 'system') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
