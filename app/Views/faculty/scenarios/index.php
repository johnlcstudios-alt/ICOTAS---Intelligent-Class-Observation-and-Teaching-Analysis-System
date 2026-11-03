<?php
/** Faculty scenarios index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Scenarios</h2><p class="small">Scenarios assigned to you, with current status.</p></div>
            <div class="card__body card__body--flush">
                <?php if (empty($scenarios)): ?>
                    <div class="table-empty">
                        <strong>No scenarios yet.</strong>
                        <span class="muted small">When a dean assigns a scenario, it will appear here.</span>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Scenario</th>
                                    <th class="num">Faculty</th>
                                    <th>Subject</th>
                                    <th class="num">Assigned</th>
                                    <th class="num">Due</th>
                                    <th>Status</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scenarios as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="row-title"><?= e($s['title']) ?></span>
                                            <span class="row-sub small"><?= e($s['description'] ? str_excerpt($s['description'], 80) : '') ?></span>
                                        </td>
                                        <td class="num"><?= e($s['faculty_name']) ?></td>
                                        <td class="num"><?= e($s['subject_code']) ?></td>
                                        <td class="num nowrap"><?= e(fmt_date($s['assigned_at'])) ?></td>
                                        <td class="num nowrap"><?= e($s['due_at'] ? fmt_date($s['due_at']) : '—') ?></td>
                                        <td><?= status_badge($s['status']) ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/faculty/scenarios/' . (int) $s['id'])) ?>"><?= icon('eye', 16) ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Status summary</h2></div>
            <div class="card__body">
                <div class="stat-grid stat-grid--wide">
                    <div class="stat stat--flat">
                        <div class="stat__label">Assigned</div>
                        <div class="stat__value"><?= (int) $stats['assigned'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">In review</div>
                        <div class="stat__value"><?= (int) $stats['in_review'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Approved</div>
                        <div class="stat__value"><?= (int) $stats['approved'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Returned</div>
                        <div class="stat__value"><?= (int) $stats['returned'] ?></div>
                    </div>
                    <div class="stat stat--flat">
                        <div class="stat__label">Rejected</div>
                        <div class="stat__value"><?= (int) $stats['rejected'] ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($scenarios)): ?>
            <div class="stack stack--flush">
                <div class="pagination-wrap">
                    <span class="muted small"><?= (int) $pagination['offset'] + 1 ?>–<?= min((int) $pagination['offset'] + (int) $pagination['per_page'], (int) $pagination['total']) ?> of <?= (int) $pagination['total'] ?></span>
                    <?php if ((int) $pagination['page'] > 1): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/faculty/scenarios?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/faculty/scenarios?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Next steps</h2></div>
            <div class="card__body">
                <?php if (!empty($nextDue)): ?>
                    <div class="scenario-panel scenario-panel--next">
                        <div class="scenario-panel__title"><?= icon('clock', 14) ?> Due soon</div>
                        <div class="scenario-panel__body stack stack--flush">
                            <?php foreach ($nextDue as $s): ?>
                                <div class="scenario-panel__item">
                                    <a class="scenario-panel__link" href="<?= e(url('/faculty/scenarios/' . (int) $s['id'])) ?>">
                                        <span class="scenario-panel__name"><?= e($s['title']) ?></span>
                                        <span class="scenario-panel__meta muted small">Due <?= e(fmt_date($s['due_at'])) ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pendingApproval)): ?>
                    <div class="scenario-panel scenario-panel--pending">
                        <div class="scenario-panel__title"><?= icon('approve', 14) ?> Awaiting your approval</div>
                        <div class="scenario-panel__body stack stack--flush">
                            <?php foreach ($pendingApproval as $s): ?>
                                <div class="scenario-panel__item">
                                    <a class="scenario-panel__link" href="<?= e(url('/faculty/scenarios/' . (int) $s['id'])) ?>">
                                        <span class="scenario-panel__name"><?= e($s['title']) ?></span>
                                        <span class="scenario-panel__meta muted small"><?= e($s['subject_code']) ?> · <?= e($s['faculty_name']) ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($recentlyReturned)): ?>
                    <div class="scenario-panel">
                        <div class="scenario-panel__title"><?= icon('redo', 14) ?> Recently returned for revision</div>
                        <div class="scenario-panel__body stack stack--flush">
                            <?php foreach ($recentlyReturned as $s): ?>
                                <div class="scenario-panel__item">
                                    <a class="scenario-panel__link" href="<?= e(url('/faculty/scenarios/' . (int) $s['id'])) ?>">
                                        <span class="scenario-panel__name"><?= e($s['title']) ?></span>
                                        <span class="scenario-panel__meta muted small">Returned <?= e(fmt_date($s['returned_at'])) ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
