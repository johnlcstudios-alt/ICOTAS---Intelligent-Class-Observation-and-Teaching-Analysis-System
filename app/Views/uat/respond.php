<?php
/**
 * Public single-use UAT response form.
 *
 * @var array|null $respondent
 * @var array $questions
 * @var string $error
 * @var bool|null $already
 * @var string $token
 */
$already = $already ?? false;
$isInvited = is_array($respondent);
$labels = [
    1 => 'Strongly Disagree',
    2 => 'Disagree',
    3 => 'Neutral',
    4 => 'Agree',
    5 => 'Strongly Agree',
];

$grouped = [];
if ($isInvited && !empty($questions)) {
    foreach ($questions as $question) {
        $grouped[(string) $question['characteristic']][] = $question;
    }
}
?>
<div class="page-head">
    <div>
        <span class="eyebrow">User Acceptance Testing</span>
        <h2><?= $isInvited ? e($respondent['survey_title']) : 'UAT Invitation' ?></h2>
        <?php if ($isInvited): ?>
            <p class="mb-1"><?= e($respondent['survey_description'] ?: 'Please rate each statement based on your experience with the system.') ?></p>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge badge-soft"><i class="bi bi-person"></i> <?= e($respondent['name']) ?></span>
                <span class="badge badge-soft"><?= e($respondent['respondent_type']) ?></span>
                <?= status_badge($respondent['survey_status']) ?>
                <?= !empty($respondent['is_demo']) ? '<span class="badge badge-warning">Demonstration</span>' : '' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isInvited): ?>
    <div class="card">
        <div class="card-body py-5">
            <div class="empty-state">
                <i class="bi bi-link-45deg"></i>
                <h3 class="h5 mt-2 mb-2">Invitation unavailable</h3>
                <p class="text-muted mb-0"><?= e($error ?: 'This UAT invitation link is invalid or has expired.') ?></p>
            </div>
        </div>
    </div>
<?php elseif ($already): ?>
    <div class="card">
        <div class="card-body py-5">
            <div class="empty-state">
                <i class="bi bi-check2-circle text-success"></i>
                <h3 class="h5 mt-2 mb-2">Response already submitted</h3>
                <p class="text-muted mb-0">This single-use invitation has already been used. Thank you for participating in the user acceptance testing.</p>
            </div>
        </div>
    </div>
<?php elseif ($error !== ''): ?>
    <div class="card">
        <div class="card-body py-5">
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <h3 class="h5 mt-2 mb-2">Survey not accepting responses</h3>
                <p class="text-muted mb-0"><?= e($error) ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (!empty($respondent['is_demo'])): ?>
        <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration UAT.</strong> This invitation exists for interface testing; submitted ratings are demonstration data, not acceptance evidence.</span></div>
    <?php endif; ?>

    <div class="alert alert-info app-alert">
        <i class="bi bi-info-circle"></i>
        <span><strong>This form is not anonymous.</strong> Your invitation and respondent name are recorded with the UAT results so the research team can document acceptance testing. It is kept separate from anonymous course evaluations.</span>
    </div>

    <form method="post" action="<?= url('uat/respond/' . urlencode($token)) ?>" data-single-submit>
        <?= csrf_field() ?>

        <?php if (empty($questions)): ?>
            <div class="card">
                <div class="card-body py-5">
                    <div class="empty-state">
                        <i class="bi bi-list-check"></i>
                        <h3 class="h5 mt-2 mb-2">No statements yet</h3>
                        <p class="text-muted mb-0">This UAT survey has no statements. Please contact the person who invited you.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Rating scale</strong>
                    <span class="text-muted small ms-auto"><?= count($questions) ?> statement<?= count($questions) === 1 ? '' : 's' ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 text-tiny">
                        <?php foreach ($labels as $score => $text): ?>
                            <span class="badge badge-soft"><?= (int) $score ?> · <?= e($text) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="data-note mb-0 mt-2">All statements require a rating from 1 to 5 before the response can be submitted.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><strong>Statements</strong></div>
                <div class="card-body">
                    <?php $number = 0; ?>
                    <?php foreach ($grouped as $characteristic => $items): ?>
                        <div class="uat-group">
                            <h3 class="uat-group-title"><?= e($characteristic) ?></h3>
                            <?php foreach ($items as $question): ?>
                                <?php $number++; $qid = (int) $question['id']; ?>
                                <fieldset class="eval-question">
                                    <legend class="question-label">
                                        <span class="question-number"><?= $number ?></span>
                                        <span><?= e($question['question_text']) ?> <span class="text-danger" aria-hidden="true">*</span></span>
                                    </legend>
                                    <div class="likert" role="radiogroup" aria-label="<?= e($question['question_text']) ?>">
                                        <?php for ($value = 1; $value <= 5; $value++): ?>
                                            <?php $id = 'uat' . $qid . '_' . $value; ?>
                                            <input type="radio" id="<?= $id ?>" name="ratings[<?= $qid ?>]" value="<?= $value ?>" required>
                                            <label for="<?= $id ?>">
                                                <span class="score"><?= $value ?></span>
                                                <span><?= e($labels[$value]) ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </fieldset>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="data-note mb-0"><i class="bi bi-lock"></i> Single-use invitation · Submitted responses cannot be edited</span>
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-send-check"></i> Submit UAT response
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>
