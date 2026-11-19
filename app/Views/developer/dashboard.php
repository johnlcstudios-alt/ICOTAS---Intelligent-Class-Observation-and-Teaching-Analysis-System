<?php
/**
 * @var array $stats
 * @var array $roleCounts
 * @var array $recentUsers
 * @var array $recentAudit
 * @var int $auditToday
 * @var int $activeSessions
 * @var bool $demoMode
 * @var string $appVersion
 * @var string $phpVersion
 */
$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int) floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
};
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Developer Console</span>
        <h2>System command center</h2>
        <p>Full system access: manage users, raw data, settings, audit logs, SQL, and demonstration data. Every action is audited.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('developer/data') ?>"><i class="bi bi-table"></i> Data browser</a>
        <a class="btn btn-outline-primary" href="<?= url('developer/sql') ?>"><i class="bi bi-code-square"></i> SQL console</a>
        <a class="btn btn-primary" href="<?= url('developer/users/create') ?>"><i class="bi bi-person-plus"></i> New account</a>
    </div>
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
        <div class="stat-note">System activity</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header"><strong>Users by role</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                        <tr><th>Role</th><th class="text-end">Total</th><th class="text-end">Live</th><th class="text-end">Demo</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($roleCounts as $row): ?>
                            <tr>
                                <td><?= e((string) $row['name']) ?> <span class="badge badge-soft"><?= e((string) $row['slug']) ?></span></td>
                                <td class="text-end"><?= (int) $row['total'] ?></td>
                                <td class="text-end"><?= (int) $row['live_count'] ?></td>
                                <td class="text-end"><?= (int) $row['demo_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Recent accounts</strong></div>
            <div class="card-body p-0">
                <?php if (empty($recentUsers)): ?>
                    <div class="empty-state"><i class="bi bi-person"></i>No accounts yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                            <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentUsers as $row): ?>
                                <tr>
                                    <td><?= e(full_name($row)) ?></td>
                                    <td><code><?= e((string) $row['username']) ?></code></td>
                                    <td><span class="badge badge-soft"><?= e((string) $row['role']) ?></span></td>
                                    <td><?= status_badge((string) $row['status']) ?></td>
                                    <td class="small"><?= e(format_datetime($row['created_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="card-footer text-end"><a href="<?= url('developer/users') ?>"><i class="bi bi-people"></i> View all users</a></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header"><strong>Quick actions</strong></div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/users/create') ?>">
                        <i class="bi bi-person-plus"></i> Create account
                    </a>
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/users') ?>">
                        <i class="bi bi-people"></i> Browse all users
                    </a>
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/data') ?>">
                        <i class="bi bi-table"></i> Browse data tables
                    </a>
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/sql') ?>">
                        <i class="bi bi-code-square"></i> Run SQL query
                    </a>
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/settings') ?>">
                        <i class="bi bi-gear"></i> System settings
                    </a>
                    <a class="btn btn-outline-primary w-100" href="<?= url('developer/audit') ?>">
                        <i class="bi bi-shield-check"></i> Audit log
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>System information</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-6"><strong>App version</strong></div>
                    <div class="col-6 text-end"><?= e($appVersion) ?></div>
                    <div class="col-6"><strong>PHP version</strong></div>
                    <div class="col-6 text-end"><?= e($phpVersion) ?></div>
                    <div class="col-6"><strong>Demo mode</strong></div>
                    <div class="col-6 text-end">
                        <span class="badge <?= $demoMode ? 'badge-warning' : 'badge-success' ?>">
                            <?= $demoMode ? 'On' : 'Off' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($recentAudit)): ?>
    <div class="card mt-4">
        <div class="card-header"><strong>Recent audit events</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentAudit as $row): ?>
                        <tr>
                            <td class="small"><?= e(format_datetime($row['created_at'] ?? null)) ?></td>
                            <td class="small"><?= e((string) ($row['username'] ?? 'system')) ?></td>
                            <td class="small text-truncate" style="max-width: 200px"><?= e((string) $row['action']) ?></td>
                            <td class="small text-truncate" style="max-width: 120px"><?= e((string) ($row['entity_type'] ?? '-')) . ' #' . e((string) ($row['entity_id'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end"><a href="<?= url('developer/audit') ?>"><i class="bi bi-shield-check"></i> View full audit log</a></div>
        </div>
    </div>
<?php endif; ?>
