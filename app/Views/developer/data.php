<?php
/**
 * @var string $developerKey
 * @var array $tables
 * @var array $stats
 */
$console = static fn (string $path = ''): string => url('developer/' . rawurlencode($developerKey) . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
};
$pageInlineScript = <<<'HTML'
<script>
(function () {
    const input = document.getElementById('tableFilter');
    const rows = document.querySelectorAll('[data-table-row]');
    if (!input) { return; }
    input.addEventListener('input', function () {
        const needle = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.hidden = needle !== '' && row.getAttribute('data-table-name').indexOf(needle) === -1;
        });
    });
})();
</script>
HTML;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Database</span>
        <h2>Data browser</h2>
        <p>Browse and edit every base table without writing SQL. Table and column names are resolved against the live schema; values always travel through prepared statements.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= $console('sql') ?>"><i class="bi bi-code-square"></i> SQL console</a>
        <a class="btn btn-outline-secondary" href="<?= $console() ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>
</div>

<div class="alert alert-warning app-alert">
    <i class="bi bi-exclamation-triangle"></i>
    <span><strong>Raw record access.</strong> The browser bypasses application validation. Database constraints still apply, but changing rows directly can break relationships, analytics or sign-in. Password, token, session and cipher columns are masked and cannot be edited here.</span>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-table"></i></span>
        <div class="stat-label">Base tables</div>
        <div class="stat-value"><?= (int) ($stats['tables'] ?? count($tables)) ?></div>
        <div class="stat-note"><?= e((string) ($stats['name'] ?? 'Active database')) ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></span>
        <div class="stat-label">Rows (estimated)</div>
        <div class="stat-value"><?= number_format((int) ($stats['rows_estimate'] ?? 0)) ?></div>
        <div class="stat-note">InnoDB table estimates</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-hdd-stack"></i></span>
        <div class="stat-label">Storage</div>
        <div class="stat-value"><?= e($formatBytes((int) ($stats['size_bytes'] ?? 0))) ?></div>
        <div class="stat-note">Data + index length</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-server"></i></span>
        <div class="stat-label">Server</div>
        <div class="stat-value fs-5"><?= e(strtok((string) ($stats['version'] ?? '—'), '-')) ?></div>
        <div class="stat-note">MySQL / MariaDB</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Tables</strong>
        <div class="d-flex align-items-center gap-2">
            <input class="form-control form-control-sm" id="tableFilter" type="search" placeholder="Filter tables…" aria-label="Filter tables" style="min-width: 200px;">
            <span class="text-muted small text-nowrap"><?= count($tables) ?> table(s)</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($tables)): ?>
            <div class="empty-state"><i class="bi bi-database-x"></i>No base tables were found in the active database.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Table</th>
                        <th>Engine</th>
                        <th class="text-end">Rows (est.)</th>
                        <th class="text-end">Data</th>
                        <th class="text-end">Index</th>
                        <th>Comment</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr data-table-row data-table-name="<?= e(strtolower($table['name'])) ?>">
                            <td><a class="fw-semibold" href="<?= $console('data/' . rawurlencode($table['name'])) ?>"><code><?= e($table['name']) ?></code></a></td>
                            <td class="small text-muted"><?= e($table['engine'] ?: '—') ?></td>
                            <td class="text-end"><?= number_format((int) $table['rows_estimate']) ?></td>
                            <td class="text-end text-tiny text-muted"><?= e($formatBytes((int) $table['data_length'])) ?></td>
                            <td class="text-end text-tiny text-muted"><?= e($formatBytes((int) $table['index_length'])) ?></td>
                            <td class="text-tiny text-muted"><?= e($table['comment'] ?: '—') ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $console('data/' . rawurlencode($table['name'])) ?>"><i class="bi bi-eye"></i> Browse</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= $console('data/' . rawurlencode($table['name']) . '/create') ?>"><i class="bi bi-plus-lg"></i> New row</a>
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

<div class="data-note mt-3">Identifier safety: the server validates every table and column against <code>information_schema</code> for the active database. The generic browser cannot reach other schemas, binary payloads, password hashes or token material.</div>
