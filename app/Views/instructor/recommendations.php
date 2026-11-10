<?php
/**
 * @var array $recommendations
 * @var bool $demo
 */
$statuses = ['open' => 'Open', 'acknowledged' => 'Acknowledged', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];
$priorityClass = ['high' => 'badge-danger', 'medium' => 'badge-warning', 'low' => 'badge-secondary'];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2>My teaching recommendations</h2>
        <p>Advisory next steps generated from your own evaluation themes and category averages. You decide which actions to acknowledge or complete.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('instructor/predictions') ?>"><i class="bi bi-activity"></i> Trend indicator</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> These suggestions come from demo records.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert"><i class="bi bi-lightbulb"></i><span>Recommendations are rule-based and evidence-linked. They are not an evaluation score, a disciplinary finding or a substitute for professional judgment. Supporting metrics show exactly what triggered each suggestion.</span></div>

<?php if (empty($recommendations)): ?>
    <div class="card">
        <div class="empty-state"><i class="bi bi-lightbulb"></i>
            <p class="mt-2 mb-2">No recommendations are available yet. They are generated when recurring concerns or lower category averages are detected.</p>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('instructor/predictions') ?>">Generate trend indicator</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($recommendations as $recommendation): ?>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><?= e($recommendation['theme']) ?></strong>
                        <span class="ms-auto d-flex gap-1">
                            <span class="badge <?= e($priorityClass[$recommendation['priority']] ?? 'badge-secondary') ?>"><?= e(ucfirst($recommendation['priority'])) ?></span>
                            <?= status_badge($recommendation['status']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Concern.</strong> <?= e($recommendation['concern']) ?></p>
                        <p class="mb-2"><strong>Recommendation.</strong> <?= e($recommendation['recommendation']) ?></p>
                        <p class="mb-2"><strong>Why this was suggested.</strong> <?= e($recommendation['reason']) ?></p>
                        <div class="data-note mb-3"><i class="bi bi-bar-chart"></i> <?= e($recommendation['supporting_metric']) ?></div>
                        <form method="post" action="<?= url('instructor/recommendations/' . (int) $recommendation['id'] . '/status') ?>" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <label class="form-label small mb-0" for="status_<?= (int) $recommendation['id'] ?>">Update status</label>
                            <select class="form-select form-select-sm w-auto" id="status_<?= (int) $recommendation['id'] ?>" name="status">
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected($recommendation['status'], $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                        </form>
                    </div>
                    <div class="card-footer text-tiny text-muted">Updated <?= e(format_datetime($recommendation['updated_at'])) ?> · Rule-based evidence, advisory only</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mt-4">
    <div class="card-header"><strong>How recommendations are produced</strong></div>
    <div class="card-body">
        <ul class="small mb-0 ps-3">
            <li class="mb-1">Recurring negative themes in anonymized comments trigger theme-specific teaching playbooks.</li>
            <li class="mb-1">Category averages below 3.50/5.00 also generate evidence-linked suggestions.</li>
            <li class="mb-1">Each item names the concern, supporting metric and reason; it never asserts causation.</li>
            <li>Resolved items remain visible for your records and can be reopened by choosing another status.</li>
        </ul>
    </div>
</div>
