<?php
if (empty($pagination) || (int) ($pagination['total'] ?? 0) === 0) {
    return;
}
$page = (int) ($pagination['page'] ?? 1);
$pages = (int) ($pagination['pages'] ?? 1);
$total = (int) ($pagination['total'] ?? 0);
$perPage = (int) ($pagination['per_page'] ?? 20);
$from = ($page - 1) * $perPage + 1;
$to = min($total, $page * $perPage);
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <p class="text-muted small mb-0">Showing <?= $from ?>–<?= $to ?> of <?= $total ?> record(s)</p>
    <?php if ($pages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(query_url(['page' => max(1, $page - 1)])) ?>" aria-label="Previous">&laquo;</a>
                </li>
                <?php if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e(query_url(['page' => 1])) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(query_url(['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e(query_url(['page' => $pages])) ?>"><?= $pages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(query_url(['page' => min($pages, $page + 1)])) ?>" aria-label="Next">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
