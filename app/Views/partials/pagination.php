<?php
/**
 * @var array $pagination
 */
if (!isset($pagination) || (int) ($pagination['pages'] ?? 1) <= 1) {
    return;
}
$current = (int) $pagination['current'];
$pages = (int) $pagination['pages'];
$window = 2;
?>
<nav class="pagination" aria-label="Pagination">
    <span class="pagination__summary">
        Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= (int) $pagination['total'] ?>
    </span>
    <div class="pagination__links">
        <?php if ($pagination['has_prev']): ?>
            <a class="pagination__link" href="?<?= e(query_string(['page' => $current - 1])) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left', 15) ?></a>
        <?php else: ?>
            <span class="pagination__link is-disabled" aria-hidden="true"><?= icon('chevron-left', 15) ?></span>
        <?php endif; ?>

        <?php for ($p = max(1, $current - $window); $p <= min($pages, $current + $window); $p++): ?>
            <a class="pagination__link<?= $p === $current ? ' is-current' : '' ?>"
               href="?<?= e(query_string(['page' => $p])) ?>"
               <?= $p === $current ? 'aria-current="page"' : '' ?>><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($pagination['has_next']): ?>
            <a class="pagination__link" href="?<?= e(query_string(['page' => $current + 1])) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right', 15) ?></a>
        <?php else: ?>
            <span class="pagination__link is-disabled" aria-hidden="true"><?= icon('chevron-right', 15) ?></span>
        <?php endif; ?>
    </div>
</nav>
