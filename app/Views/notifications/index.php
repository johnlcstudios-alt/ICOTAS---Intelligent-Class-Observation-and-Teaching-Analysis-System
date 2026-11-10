<?php
/**
 * @var array $pagination
 */
use App\Core\View;

$icons = [
    'session' => 'bi-qr-code',
    'evaluation' => 'bi-clipboard-check',
    'observation' => 'bi-eye',
    'prediction' => 'bi-activity',
    'recommendation' => 'bi-lightbulb',
    'report' => 'bi-file-earmark-text',
    'system' => 'bi-gear',
];
$rows = $pagination['rows'] ?? [];
$unread = 0;
foreach ($rows as $row) {
    if (empty($row['is_read'])) {
        $unread++;
    }
}
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account</span>
        <h2>Notifications</h2>
        <p>System messages about evaluations, observations, reports and account activity.</p>
    </div>
    <div class="page-actions">
        <?php if (!empty($rows)): ?>
            <form method="post" action="<?= url('notifications/read-all') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-check2-all"></i> Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Inbox</strong>
        <span class="text-muted small"><?= (int) ($pagination['total'] ?? 0) ?> message(s)<?= $unread > 0 ? ' · ' . $unread . ' unread on this page' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-bell-slash"></i>No notifications yet. New evaluation and observation activity will appear here.</div>
        <?php else: ?>
            <ul class="list-clean">
                <?php foreach ($rows as $row): ?>
                    <li class="d-block <?= empty($row['is_read']) ? 'notification-unread' : '' ?>">
                        <div class="d-flex gap-3">
                            <span class="stat-icon flex-shrink-0"><i class="bi <?= e($icons[$row['type']] ?? 'bi-bell') ?>"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <strong><?= e($row['title']) ?></strong>
                                    <span class="text-tiny text-muted"><?= e(time_ago($row['created_at'])) ?> · <?= e(format_datetime($row['created_at'])) ?></span>
                                </div>
                                <p class="mb-1 small"><?= e($row['message']) ?></p>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <?php if (!empty($row['link'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url($row['link'])) ?>">Open</a>
                                    <?php endif; ?>
                                    <?php if (empty($row['is_read'])): ?>
                                        <form method="post" action="<?= url('notifications/' . (int) $row['id'] . '/read') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-ghost" type="submit"><i class="bi bi-check2"></i> Mark read</button>
                                        </form>
                                        <span class="badge badge-info">New</span>
                                    <?php else: ?>
                                        <span class="text-tiny text-muted">Read</span>
                                    <?php endif; ?>
                                    <span class="badge badge-soft"><?= e(ucfirst($row['type'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php View::partial('partials.pagination', ['pagination' => $pagination]); ?>
