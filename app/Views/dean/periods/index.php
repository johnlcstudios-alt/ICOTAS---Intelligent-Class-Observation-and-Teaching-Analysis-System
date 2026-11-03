<?php
/** Dean academic periods index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Academic periods</h2><p class="small">Current academic periods in use.</p></div>
            <div class="card__body card__body--flush">
                <?php if (empty($periods)): ?>
                    <div class="table-empty"><strong>No academic periods yet.</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($periods as $period): ?>
                                    <tr>
                                        <td>
                                            <span class="row-title"><?= e($period['academic_year']) ?></span>
                                            <span class="row-sub"><?= e($period['semester']) ?></span>
                                        </td>
                                        <td><?= status_badge($period['status']) ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('dean/periods/' . (int) $period['period_id'])) ?>"><?= icon('eye', 16) ?></a>
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
            <div class="card__head"><h2>Current period</h2></div>
            <div class="card__body">
                <?php if ($current): ?>
                    <p class="small"><?= e($current['academic_year']) ?> · <?= e($current['semester']) ?></p>
                <?php else: ?>
                    <p class="muted">No current period is set.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
