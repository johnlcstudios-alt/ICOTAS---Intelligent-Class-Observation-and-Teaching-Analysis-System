<?php
/** @var array $tables */
/** @var array $stats */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Data browser</span>
        <h2>Database tables</h2>
        <p>Inspect and edit any table in the ICOTAS database. Use with care — changes are immediate and audited.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('developer/sql') ?>"><i class="bi bi-code-square"></i> SQL console</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($tables)): ?>
            <div class="empty-state"><i class="bi bi-table"></i>No tables found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr><th>Table</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><code><?= e((string) $table) ?></code></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('developer/data/' . rawurlencode((string) $table)) ?>"><i class="bi bi-table"></i> Browse</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><strong>Database statistics</strong></div>
    <div class="card-body small">
        <div class="row g-2">
            <div class="col-6"><strong>Tables</strong></div>
            <div class="col-6 text-end"><?= (int) ($stats['tables'] ?? 0) ?></div>
            <div class="col-6"><strong>Rows (estimate)</strong></div>
            <div class="col-6 text-end"><?= number_format((int) ($stats['rows_estimate'] ?? 0)) ?></div>
            <div class="col-6"><strong>Storage</strong></div>
            <div class="col-6 text-end"><?= isset($stats['size_bytes']) ? number_format((int) $stats['size_bytes']) . ' bytes' : '—' ?></div>
        </div>
    </div>
</div>
