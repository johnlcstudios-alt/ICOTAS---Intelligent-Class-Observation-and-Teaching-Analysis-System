<?php
/** Dean audit log index. */
$labels = rating_labels();
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Audit log</h2><p class="small">Institution security and admin events, newest first.</p></div>
            <div class="card__body card__body--flush">
                <?php if ($rows === []): ?>
                    <div class="table-empty"><strong>No audit events yet</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Performed by</th>
                                    <th class="num">IP</th>
                                    <th>Via</th>
                                    <th class="num">Role</th>
                                    <th>Details</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="row-title"><?= e($row['event']) ?></span>
                                            <span class="row-sub">ID <?= (int) $row['id'] ?> · <?= e($row['action_name'] ?? '') ?></span>
                                        </td>
                                        <td><?= e($row['performed_by_name']) ?></td>
                                        <td class="mono nowrap"><?= e($row['ip'] ?: '—') ?></td>
                                        <td class="nowrap"><?= $row['context'] ? e($row['context']) : '—' ?></td>
                                        <td class="num"><?= e($row['role'] ?: '—') ?></td>
                                        <td><span class="small muted"><?= e(str_excerpt($row['details'], 160)) ?></span></td>
                                        <td class="text-right"><a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/audit/' . (int) $row['id'])) ?>"><?= icon('eye', 16) ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($pagination['total'] > 0): ?>
            <div class="stack stack--flush">
                <div class="pagination-wrap">
                    <span class="muted small"><?= (int) $pagination['offset'] + 1 ?>–<?= min((int) $pagination['offset'] + (int) $pagination['per_page'], (int) $pagination['total']) ?> of <?= (int) $pagination['total'] ?></span>
                    <?php if ((int) $pagination['page'] > 1): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/audit?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/audit?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Log summary</h2></div>
            <div class="card__body">
                <div class="stat-grid" style="grid-template-columns:1fr 1fr 1fr">
                    <div class="stat stat--flat">
                        <div class="stat__label">Total events</div>
                        <div class="stat__value"><?= (int) $pagination['total'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">This page</div>
                        <div class="stat__value"><?= (int) count($rows) ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Pages</div>
                        <div class="stat__value"><?= (int) $pagination['last_page'] ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>About the audit log</h2></div>
            <div class="card__body">
                <p class="small">The audit log records login attempts, account changes, configuration updates, COF management and other security-relevant events across the system.</p>
                <p class="small">IP addresses are recorded where available and are used only for review within this institution.</p>
            </div>
        </section>
    </div>
</div>
