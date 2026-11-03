<?php
/** Dean departments index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Departments</h2><p class="small">Academic departments in the institution.</p></div>
            <div class="card__body card__body--flush">
                <?php if (empty($departments)): ?>
                    <div class="table-empty"><strong>No departments yet.</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="num">Code</th>
                                    <th>Faculty</th>
                                    <th>Periods</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td>
                                            <div class="row-title"><?= e($d['name']) ?></div>
                                            <div class="row-sub small"><?= e($d['head'] ?? '') ?></div>
                                        </td>
                                        <td class="num"><?= e($d['code'] ?? '—') ?></td>
                                        <td class="num"><?= (int) ($d['faculty_count'] ?? 0) ?></td>
                                        <td class="num"><?= (int) ($d['period_count'] ?? 0) ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/departments/' . (int) $d['id'])) ?>"><?= icon('eye', 16) ?></a>
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/departments/' . (int) $d['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?></a>
                                        </td>
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
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/departments?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/departments?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Quick actions</h2></div>
            <div class="card__body">
                <a class="btn btn--primary btn--block" href="<?= e(url('/dean/departments/create')) ?>"><?= icon('plus', 16) ?> Add department</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Summary</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Departments</span><strong><?= (int) $pagination['total'] ?></strong></li>
                    <li><span>Total faculty</span><strong><?= (int) $stats['faculty_count'] ?></strong></li>
                    <li><span>Total periods</span><strong><?= (int) $stats['period_count'] ?></strong></li>
                </ul>
            </div>
        </section>
    </div>
</div>
