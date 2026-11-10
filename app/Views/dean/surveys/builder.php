<?php
/**
 * @var array $survey
 * @var array $versions
 * @var array $version
 * @var array $questions
 * @var array $ratingLabels
 * @var array $questionTypes
 * @var array $themes
 * @var array $categories
 * @var bool $demo
 */
$editable = ($version['status'] ?? '') === 'draft';
$versionId = (int) $version['id'];
$builderState = [
    'editable' => $editable,
    'version_id' => $versionId,
    'types' => $questionTypes,
    'themes' => $themes,
    'questions' => array_map(static function (array $question): array {
        return [
            'text' => $question['question_text'] ?? '',
            'description' => $question['description'] ?? '',
            'type' => $question['question_type'] ?? 'likert5',
            'category' => $question['category'] ?? 'General',
            'required' => !empty($question['is_required']),
            'min' => $question['min_value'] !== null ? (float) $question['min_value'] : null,
            'max' => $question['max_value'] !== null ? (float) $question['max_value'] : null,
            'min_label' => $question['min_label'] ?? '',
            'max_label' => $question['max_label'] ?? '',
            'options' => array_map(static fn (array $option): array => [
                'label' => $option['option_label'] ?? '',
                'value' => $option['option_value'] ?? '',
            ], $question['options'] ?? []),
        ];
    }, $questions),
];
$stateJson = json_encode($builderState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$pageScripts = array_merge((array) ($pageScripts ?? []), ['survey-builder.js']);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Instrument builder</span>
        <h2><?= e($survey['title']) ?></h2>
        <p class="mb-1"><?= e($survey['category'] ?: 'General') ?> &middot; version <?= (int) $version['version_number'] ?> &middot; <?= status_badge((string) $version['status']) ?></p>
        <p class="text-muted small mb-0"><?= $editable ? 'Draft versions can be edited. Publish when the questionnaire is final.' : 'This version is locked. Create a new draft version to make changes.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/surveys') ?>"><i class="bi bi-arrow-left"></i> Surveys</a>
        <?php if (!empty($versions) && count($versions) > 1): ?>
            <form method="get" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/builder') ?>" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="version_id" aria-label="Select survey version">
                    <?php foreach ($versions as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= selected($item['id'], $versionId) ?>>
                            v<?= (int) $item['version_number'] ?> — <?= e(ucfirst((string) $item['status'])) ?> (<?= (int) $item['question_count'] ?> q)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit">Load</button>
            </form>
        <?php endif; ?>
        <?php if ($editable): ?>
            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/publish') ?>" data-confirm="Publish this version? Published questionnaires become immutable.">
                <?= csrf_field() ?>
                <input type="hidden" name="version_id" value="<?= $versionId ?>">
                <button class="btn btn-success" type="submit"><i class="bi bi-send-check"></i> Publish</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/new-version') ?>" data-confirm="Create a new draft version from the latest published definition?">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-plus-square"></i> New draft version</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration scope.</strong> This instrument is part of the synthetic demo dataset.</span></div>
<?php endif; ?>

<?php if ($editable): ?>
    <div class="alert alert-info app-alert"><i class="bi bi-layers"></i><span><strong>Draft v<?= (int) $version['version_number'] ?>.</strong> Saving replaces the draft's question set. Published versions and existing responses are never modified.</span></div>
<?php else: ?>
    <div class="alert alert-secondary app-alert"><i class="bi bi-lock"></i><span><strong>Read-only version.</strong> v<?= (int) $version['version_number'] ?> is <?= e($version['status']) ?> and cannot be edited. Use <em>New draft version</em> to continue.</span></div>
<?php endif; ?>

<form id="builderForm" method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/builder') ?>" data-no-double-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="version_id" value="<?= $versionId ?>">
    <input type="hidden" name="payload" id="builderPayload" value="">

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><strong>Questionnaire settings</strong><span class="text-muted small">Shown to respondents</span></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="builderTitle">Survey title <span class="text-danger">*</span></label>
                            <input class="form-control" id="builderTitle" value="<?= e($survey['title']) ?>" maxlength="180" <?= $editable ? '' : 'disabled' ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="builderCategory">Category</label>
                            <select class="form-select" id="builderCategory" <?= $editable ? '' : 'disabled' ?>>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category) ?>" <?= selected($survey['category'] ?? '', $category) ?>><?= e($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="builderDescription">Description</label>
                            <textarea class="form-control" id="builderDescription" rows="2" maxlength="2000" <?= $editable ? '' : 'disabled' ?>><?= e($survey['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="builderInstructions">Respondent instructions</label>
                            <textarea class="form-control" id="builderInstructions" rows="2" maxlength="3000" placeholder="Optional guidance shown at the top of the evaluation form." <?= $editable ? '' : 'disabled' ?>><?= e($version['instructions'] ?? '') ?></textarea>
                        </div>
                        <?php if (!$editable): ?>
                            <div class="col-12"><div class="form-text">Settings are disabled because this version is read-only.</div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <strong>Questions</strong>
                    <span class="text-muted small"><span id="builderQuestionCount"><?= count($questions) ?></span> question(s)</span>
                    <?php if ($editable): ?>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" type="button" id="builderAddQuestion"><i class="bi bi-plus-lg"></i> Add question</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div id="questionList" class="question-list"></div>
                    <div id="builderEmpty" class="empty-state <?= empty($questions) ? '' : 'd-none' ?>">
                        <i class="bi bi-ui-checks"></i>
                        <h3>No questions yet</h3>
                        <p class="text-muted mb-0"><?= $editable ? 'Add your first question to begin building the instrument.' : 'This version has no questions.' ?></p>
                    </div>
                    <noscript><div class="alert alert-warning mb-0">JavaScript is required for the drag-and-drop builder. The questionnaire remains viewable in read-only form below.</div></noscript>
                </div>
            </div>

            <?php if ($editable): ?>
                <div class="card mt-3">
                    <div class="card-header"><strong>Likert response labels</strong></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="col-md-4 col-lg">
                                    <label class="form-label small" for="ratingLabel<?= $i ?>"><?= $i ?> point<?= $i === 1 ? '' : 's' ?></label>
                                    <input class="form-control form-control-sm" id="ratingLabel<?= $i ?>" data-rating-label="<?= $i ?>" value="<?= e($ratingLabels[$i] ?? '') ?>" maxlength="60">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="form-text">Used by five-point Likert and agreement questions in this version.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-xl-4">
            <div class="card position-sticky" style="top: 1rem;">
                <div class="card-header"><strong>Save</strong></div>
                <div class="card-body">
                    <?php if ($editable): ?>
                        <p class="small text-muted">Saving stores the draft question set. Publish only when the instrument is final.</p>
                        <button class="btn btn-primary w-100" type="submit" id="builderSaveButton"><i class="bi bi-check2"></i> Save draft</button>
                        <div class="form-text mt-2">At least one question is required.</div>
                    <?php else: ?>
                        <p class="small text-muted mb-3">This version is read-only. Existing responses and assignments depend on it.</p>
                        <form method="post" action="<?= url('dean/surveys/' . (int) $survey['id'] . '/duplicate') ?>" class="mb-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-files"></i> Duplicate survey</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body border-top small text-muted">
                    <div class="d-flex justify-content-between"><span>Version</span><strong>v<?= (int) $version['version_number'] ?></strong></div>
                    <div class="d-flex justify-content-between"><span>Status</span><span><?= status_badge((string) $version['status']) ?></span></div>
                    <div class="d-flex justify-content-between"><span>Questions</span><strong><?= count($questions) ?></strong></div>
                    <div class="d-flex justify-content-between"><span>Published</span><strong><?= e(format_date($version['published_at'] ?? null)) ?></strong></div>
                    <?php if (!empty($version['first_name'])): ?>
                        <div class="d-flex justify-content-between"><span>Author</span><strong><?= e($version['first_name'] . ' ' . $version['last_name']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong>Question types</strong></div>
                <div class="card-body small text-muted">
                    <ul class="mb-0 ps-3">
                        <li><strong>Likert / agreement</strong> — scored 1 to 5, included in rating analytics.</li>
                        <li><strong>Numeric rating</strong> — custom 1–10 range, normalized to five points.</li>
                        <li><strong>Choice / yes-no</strong> — reported as distributions, not rating averages.</li>
                        <li><strong>Text</strong> — feeds sentiment and theme analysis; never shown to instructors with student identity.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="surveyBuilderData"><?= $stateJson ?></script>

<?php if ($editable): ?>
    <div class="d-xl-none mt-3">
        <button class="btn btn-primary w-100" type="submit" form="builderForm"><i class="bi bi-check2"></i> Save draft</button>
    </div>
<?php endif; ?>
