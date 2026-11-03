<?php
/** Dean assignments index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Assignments</h2><p class="small">Observation assignments for Dean review.</p></div>
            <div class="card__body card__body--flush">
                <?php if (empty($rows)): ?>
                    <div class="table-empty"><strong>No assignments yet.</strong></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Faculty</th>
                                    <th>Class</th>
                                    <th class="num">Status</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= e($row['title'] ?? '—') ?></td>
                                        <td><?= e($row['faculty_name'] ?? '—') ?></td>
                                        <td><?= e($row['class_name'] ?? '—') ?></td>
                                        <td class="num"><?= status_badge($row['assignment_status']) ?></td>
                                        <td class="text-right">
                                            <a class="btn btn--ghost btn--xs" href="<?= e(url('dean/assignments/' . (int) $row['assignment_id'])) ?>"><?= icon('eye', 16) ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
