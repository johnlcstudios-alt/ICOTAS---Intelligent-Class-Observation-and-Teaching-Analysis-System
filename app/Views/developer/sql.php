<?php
/**
 * @var string|null $developerKey
 * @var string|null $consoleBase
 * @var array|null $result
 * @var string $sqlText
 * @var array $tables
 */
$consoleBase = $consoleBase ?? ('developer/' . rawurlencode((string) ($developerKey ?? '')));
$console = static fn (string $path = ''): string => url($consoleBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$cells = static function ($value): string {
    if ($value === null) {
        return '<span class="text-muted">NULL</span>';
    }
    return '<span class="text-tiny">' . e(str_limit((string) $value, 160)) . '</span>';
};
$pageInlineScript = <<<'HTML'
<script>
(function () {
    const area = document.getElementById('sqlStatement');
    const form = document.getElementById('sqlForm');
    if (!area) { return; }
    document.querySelectorAll('[data-sql]').forEach(function (button) {
        button.addEventListener('click', function () {
            area.value = button.getAttribute('data-sql');
            area.focus();
        });
    });
    if (form) {
        area.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    }
})();
</script>
HTML;
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Maintenance</span>
        <h2>SQL console</h2>
        <p>Run exactly one statement against the active application database. Reads are immediate; writes and schema changes require explicit confirmation.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= $console('data') ?>"><i class="bi bi-table"></i> Data browser</a>
        <a class="btn btn-outline-secondary" href="<?= $console('audit?action=developer.sql') ?>"><i class="bi bi-shield-check"></i> SQL audit trail</a>
    </div>
</div>

<div class="alert alert-danger app-alert">
    <i class="bi bi-shield-exclamation"></i>
    <span><strong>No undo.</strong> A statement can alter or remove institutional data, break foreign keys or change the schema. There is no transaction wrapper around the console. Take a database backup before structural changes.</span>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header"><strong>Statement</strong><span class="text-muted small">Ctrl/Cmd + Enter to run</span></div>
            <div class="card-body">
                <form id="sqlForm" method="post" action="<?= $console('sql') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="sqlStatement">SQL</label>
                        <textarea class="form-control font-monospace" id="sqlStatement" name="sql" rows="12" spellcheck="false" required placeholder="SELECT * FROM users ORDER BY id DESC LIMIT 25;"><?= e($sqlText) ?></textarea>
                        <div class="form-text">One statement only. A single trailing semicolon is allowed. Comments before the statement are ignored for classification.</div>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <div class="form-check">
                                <input class="form-check-input" id="sqlConfirm" name="confirm" type="checkbox" value="1">
                                <label class="form-check-label" for="sqlConfirm">I understand this is a write or DDL statement</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sqlConfirmText">Type <code>CONFIRM</code> for DROP / TRUNCATE / ALTER / RENAME</label>
                            <input class="form-control form-control-sm font-monospace" id="sqlConfirmText" name="confirm_text" type="text" autocomplete="off" placeholder="CONFIRM">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-play-fill"></i> Run statement</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($result !== null): ?>
            <div class="card">
                <div class="card-header">
                    <strong>Result</strong>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (!empty($result['statement'])): ?><span class="badge badge-soft"><?= e($result['statement']) ?></span><?php endif; ?>
                        <?php if (array_key_exists('read', $result)): ?><span class="badge <?= $result['read'] ? 'badge-info' : 'badge-warning' ?>"><?= $result['read'] ? 'Read' : 'Write / DDL' ?></span><?php endif; ?>
                        <?php if (isset($result['duration_ms'])): ?><span class="badge badge-secondary"><?= e((string) $result['duration_ms']) ?> ms</span><?php endif; ?>
                    </div>
                </div>

                <?php if (empty($result['ok'])): ?>
                    <div class="card-body">
                        <div class="alert alert-danger app-alert mb-3">
                            <i class="bi bi-x-octagon"></i>
                            <span><strong>Statement rejected or failed.</strong> <?= e($result['error'] ?? 'Unknown error') ?></span>
                        </div>
                        <?php if (!empty($result['sql'])): ?>
                            <pre class="bg-light border rounded p-2 mb-0 small text-wrap"><?= e($result['sql']) ?></pre>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3 mb-3 small">
                            <?php if (($result['affected'] ?? 0) > 0): ?><span><strong><?= (int) $result['affected'] ?></strong> row(s) affected</span><?php endif; ?>
                            <?php if (($result['last_insert_id'] ?? 0) > 0): ?><span>Last insert ID <strong><?= (int) $result['last_insert_id'] ?></strong></span><?php endif; ?>
                            <?php if (!empty($result['truncated'])): ?><span class="badge badge-warning">Result limited to 500 rows</span><?php endif; ?>
                            <?php if (empty($result['columns'])): ?><span class="text-muted">No result set was returned.</span><?php endif; ?>
                        </div>

                        <?php if (!empty($result['columns'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 align-middle developer-mono">
                                    <thead><tr><?php foreach ($result['columns'] as $column): ?><th class="text-nowrap"><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                                    <tbody>
                                    <?php foreach (($result['rows'] ?? []) as $row): ?>
                                        <tr><?php foreach ($result['columns'] as $column): ?><td class="align-top"><?= $cells($row[$column] ?? null) ?></td><?php endforeach; ?></tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (empty($result['rows'])): ?><p class="text-muted small mb-0 mt-2">The statement succeeded but returned no rows.</p><?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($result['sql'])): ?>
                            <details class="mt-3">
                                <summary class="small text-muted">Executed statement</summary>
                                <pre class="bg-light border rounded p-2 mb-0 mt-2 small text-wrap"><?= e($result['sql']) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header"><strong>Guardrails</strong><span class="badge badge-success">Server-enforced</span></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li class="d-block mb-2"><i class="bi bi-1-circle text-primary"></i> Exactly one statement; interior semicolons are rejected.</li>
                    <li class="d-block mb-2"><i class="bi bi-slash-circle text-danger"></i> Server-level statements are blocked: <code>USE</code>, <code>SET</code>, <code>GRANT</code>, <code>REVOKE</code>, <code>LOAD</code>, <code>CALL</code>, transaction control and user management.</li>
                    <li class="d-block mb-2"><i class="bi bi-file-lock text-danger"></i> File output is blocked: <code>INTO OUTFILE</code>, <code>INTO DUMPFILE</code>, <code>LOAD_FILE()</code> and <code>LOAD DATA</code>.</li>
                    <li class="d-block mb-2"><i class="bi bi-database-lock text-danger"></i> Other schemas are blocked: <code>mysql.*</code>, <code>performance_schema.*</code> and <code>sys.*</code>.</li>
                    <li class="d-block mb-2"><i class="bi bi-filter-circle text-warning"></i> <code>UPDATE</code> and <code>DELETE</code> must include a <code>WHERE</code> clause.</li>
                    <li class="d-block mb-2"><i class="bi bi-check2-square text-primary"></i> Writes require the confirmation checkbox.</li>
                    <li class="d-block"><i class="bi bi-key text-danger"></i> <code>DROP</code>, <code>TRUNCATE</code>, <code>ALTER</code> and <code>RENAME</code> require typing <code>CONFIRM</code>.</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Quick reads</strong><span class="text-muted small">Loads into the editor</span></div>
            <div class="card-body">
                <?php if (empty($tables)): ?>
                    <p class="text-muted small mb-0">No tables are available.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($tables as $table): ?>
                            <?php $statement = 'SELECT * FROM `' . $table['name'] . '` ORDER BY 1 DESC LIMIT 50;'; ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-sql="<?= e($statement) ?>"><code><?= e($table['name']) ?></code></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="data-note mt-3">Every execution is audited with the statement keyword and a whitespace-normalized summary. Results are capped at 500 rows and 200 characters per cell in the interface.</div>
