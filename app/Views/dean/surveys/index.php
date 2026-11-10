<?php
/**
 * @var array $surveys
 * @var int $templateCount
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Instrument management</span>
        <h2>Survey builder</h2>
        <p>Design evaluation instruments with versioned, immutable published questionnaires. Assignments keep the exact version they were created with.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('dean/surveys/templates') ?>"><i class="bi bi-collection"></i> Templates <span class="badge badge-soft ms-1"><?= (int) $templateCount ?></span></a>
        <a class="btn btn-primary" href="<?= url('dean/surveys/create') ?>"><i class="bi bi-plus-lg"></i> New survey</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration scope.</strong> These instruments belong to the synthetic demo dataset.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert">
    <i class="bi bi-info-circle"></i>
    <span><strong>Versioning rule.</strong> A published version cannot be edited. Create a new draft version to change questions without altering previously submitted responses.</span>
</div>

<?php if (empty($surveys)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-ui-checks"></i>
            <h3>No surveys yet</h3>
            <p class="text-muted">Start from a template or build a questionnaire from scratch.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a class="btn btn-outline-primary" href="<?= url('dean/surveys/templates') ?>">Browse templates</a>
                <a class="btn btn-primary" href="<?= url('dean/surveys/create') ?>">Create survey</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($surveys as $survey): ?>
            <?php
            $targetVersion = (int) ($survey['draft_version_id'] ?? 0) ?: (int) ($survey['published_version_id'] ?? 0);
            $builderUrl = url('dean/surveys/' . (int) $survey['id'] . '/builder' . ($targetVersion ? '?version_id=' . $targetVersion : ''));
            ?>
            <div class="col-lg-6 col-xxl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="badge badge-soft"><?= e($survey['category'] ?: 'General') ?></span>
                            <?= status_badge((string) $survey['status']) ?>
                        </div>
                        <h3 class="h5 mb-1"><a class="stretched-link text-reset text-decoration-none" href="<?= $builderUrl ?>"><?= e($survey['title']) ?></a></h3>
                        <p class="text-muted small mb-3"><?= e(str_limit($survey['description'] ?? '', 140) ?: 'No description provided.') ?></p>
                        <div class="mt-auto">
                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                <span><i class="bi bi-list-ol"></i> <?= (int) $survey['question_count'] ?> question(s)</span>
                                <span><i class="bi bi-layers"></i> <?= (int) $survey['version_count'] ?> version(s)</span>
                                <?php if (!empty($survey['published_version_no'])): ?>
                                    <span><i class="bi bi-check2-circle"></i> v<?= (int) $survey['published_version_no'] ?> published</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($survey['first_name'])): ?>
                                <div class="text-tiny text-muted mt-2">Created by <?= e($survey['first_name'] . ' ' . $survey['last_name']) ?> · <?= e(time_ago($survey['updated_at'] ?? null)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary position-relative" style="z-index:2" href="<?= $builderUrl ?>"><i class="bi bi-pencil-square"></i> Open builder</a>
                        <?php if (!empty($survey['draft_version_id'])): ?>
                            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/publish') ?>" data-confirm="Publish this draft version? Published versions cannot be edited.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="version_id" value="<?= (int) $survey['draft_version_id'] ?>">
                                <button class="btn btn-sm btn-success position-relative" style="z-index:2" type="submit"><i class="bi bi-send-check"></i> Publish draft</button>
                            </form>
                        <?php elseif (!empty($survey['published_version_id'])): ?>
                            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/new-version') ?>" data-confirm="Create a new draft version from the published questionnaire?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary position-relative" style="z-index:2" type="submit"><i class="bi bi-plus-square"></i> New version</button>
                            </form>
                        <?php endif; ?>
                        <div class="ms-auto d-flex gap-1">
                            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/duplicate') ?>" data-confirm="Duplicate this survey as a new draft?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost position-relative" style="z-index:2" type="submit" title="Duplicate"><i class="bi bi-files"></i></button>
                            </form>
                            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/archive') ?>" data-confirm="Archive this survey? Assignments and submitted responses remain intact.">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost position-relative text-danger" style="z-index:2" type="submit" title="Archive"><i class="bi bi-archive"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
