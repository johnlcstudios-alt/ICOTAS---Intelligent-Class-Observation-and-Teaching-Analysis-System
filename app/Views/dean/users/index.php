<?php
/** Dean users index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Users</h2><p class="small">Accounts across the institution.</p></div>
            <div class="card__body card__body--flush">
                <?php if (empty($users)): ?>
                    <div class="table-empty"><strong>No users yet.</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th class="num">Account</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Last activity</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="row-title"><?= e($user['full_name']) ?></div>
                                            <div class="row-sub small"><?= e($user['email']) ?></div>
                                        </td>
                                        <td class="num"><?= e($user['employee_id'] ?? '—') ?></td>
                                        <td><?= e($user['role']) ?></td>
                                        <td><?= e($user['department_name'] ?? '—') ?></td>
                                        <td class="nowrap muted small"><?= e($user['last_active'] ? fmt_datetime($user['last_active']) : '—') ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/users/' . (int) $user['id'])) ?>"><?= icon('eye', 16) ?></a>
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/users/' . (int) $user['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?></a>
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
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/users?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/users?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Quick actions</h2></div>
            <div class="card__body">
                <a class="btn btn--primary btn--block" href="<?= e(url('/dean/users/create')) ?>"><?= icon('plus', 16) ?> Add user</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Summary</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Total users</span><strong><?= (int) $pagination['total'] ?></strong></li>
                    <li><span>Deans</span><strong><?= (int) $stats['dean'] ?></strong></li>
                    <li><span>Faculty</span><strong><?= (int) $stats['faculty'] ?></strong></li>
                    <li><span>On leave</span><strong><?= (int) $stats['on_leave'] ?></strong></li>
                </ul>
            </div>
        </section>
    </div>
</div>
