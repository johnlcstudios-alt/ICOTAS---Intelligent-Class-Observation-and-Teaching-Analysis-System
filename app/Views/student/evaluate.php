<?php
/**
 * Anonymous course evaluation form.
 *
 * @var array $session
 * @var array $state
 * @var array|null $version
 * @var array $ratingLabels
 * @var string $token
 */
$available = ($state['state'] ?? '') === 'available';
$questions = $available ? ($version['questions'] ?? []) : [];
$surveyTitle = $version['survey_title'] ?? $session['survey_title'] ?? 'Course Evaluation';
$instructions = trim((string) ($version['instructions'] ?? $session['instructions'] ?? ''));
$category = $version['survey_category'] ?? $session['survey_category'] ?? '';
$instructor = trim((string) ($session['first_name'] ?? '') . ' ' . (string) ($session['last_name'] ?? ''));

$stateIcons = [
    'not_started' => 'bi-clock',
    'expired' => 'bi-hourglass-bottom',
    'closed' => 'bi-lock',
    'not_eligible' => 'bi-person-x',
    'already_submitted' => 'bi-check2-circle',
    'unavailable' => 'bi-slash-circle',
];
$stateIcon = $stateIcons[$state['state'] ?? ''] ?? 'bi-info-circle';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Anonymous course evaluation</span>
        <h2><?= e($surveyTitle) ?></h2>
        <p class="mb-1">
            <?= e($session['course_code']) ?> · <?= e($session['course_title'] ?? '') ?>
            <?php if (!empty($session['section_code'])): ?>· Section <?= e($session['section_code']) ?><?php endif; ?>
        </p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge badge-soft"><i class="bi bi-person-badge"></i> <?= e($instructor !== '' ? $instructor : 'Instructor') ?></span>
            <?php if ($category !== ''): ?><span class="badge badge-soft"><?= e($category) ?></span><?php endif; ?>
            <span class="badge badge-soft"><i class="bi bi-calendar3"></i> <?= e($session['academic_year_name'] ?? '') ?> · <?= e($session['semester_name'] ?? '') ?></span>
            <?= status_badge($session['status']) ?>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('student/dashboard') ?>"><i class="bi bi-house"></i> Portal</a>
    </div>
</div>

<?php if (!$available): ?>
    <div class="card">
        <div class="card-body py-5 text-center">
            <div class="empty-state">
                <i class="bi <?= e($stateIcon) ?>"></i>
                <h3 class="h5 mt-2 mb-2">Evaluation not available</h3>
                <p class="text-muted mb-4"><?= e($state['message'] ?? 'This evaluation cannot be opened from your account.') ?></p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a class="btn btn-primary" href="<?= url('student/dashboard') ?>"><i class="bi bi-house"></i> Back to portal</a>
                    <a class="btn btn-outline-secondary" href="<?= url('student/history') ?>"><i class="bi bi-clock-history"></i> My history</a>
                    <a class="btn btn-outline-secondary" href="<?= url('student/scan') ?>"><i class="bi bi-qr-code-scan"></i> Scan another QR</a>
                </div>
            </div>
        </div>
    </div>
<?php elseif (empty($questions)): ?>
    <div class="card">
        <div class="card-body py-5">
            <div class="empty-state">
                <i class="bi bi-clipboard-x"></i>
                <h3 class="h5 mt-2 mb-2">Questionnaire unavailable</h3>
                <p class="text-muted mb-4">This evaluation does not have a published questionnaire. Please inform your instructor or the academic office.</p>
                <a class="btn btn-primary" href="<?= url('student/dashboard') ?>"><i class="bi bi-house"></i> Back to portal</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info app-alert">
        <i class="bi bi-shield-lock"></i>
        <span><strong>Your response is anonymous.</strong> Your name and student ID are not stored with your answers. A separate participation record is used only to enforce one response per evaluation session.</span>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Before you begin</strong>
            <span class="badge badge-soft ms-auto">Closes <?= e(format_datetime($session['expires_at'])) ?></span>
        </div>
        <div class="card-body">
            <?php if ($instructions !== ''): ?>
                <p class="mb-3"><?= nl2br(e($instructions)) ?></p>
            <?php endif; ?>
            <ul class="small mb-0 ps-3">
                <li class="mb-1">Read each statement and choose the response that best reflects your experience in this class.</li>
                <li class="mb-1">Questions marked <span class="text-danger">*</span> are required.</li>
                <li class="mb-1">Comments are analyzed only as anonymized text. Do not include names or other identifying details.</li>
                <li>You can submit only once. Review your answers before submitting.</li>
            </ul>
        </div>
    </div>

    <form method="post" action="<?= url('evaluate/' . urlencode($token)) ?>" data-single-submit>
        <?= csrf_field() ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong>Questionnaire</strong>
                <span class="text-muted small ms-auto"><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?></span>
            </div>
            <div class="card-body">
                <?php foreach ($questions as $index => $question): ?>
                    <?php
                    $qid = (int) $question['id'];
                    $type = $question['question_type'];
                    $required = (int) $question['is_required'] === 1;
                    $label = $question['question_text'];
                    $fieldName = 'answers[' . $qid . ']';
                    ?>
                    <fieldset class="eval-question" id="question-<?= $qid ?>">
                        <legend class="question-label">
                            <span class="question-number"><?= $index + 1 ?></span>
                            <span>
                                <?= e($label) ?>
                                <?php if ($required): ?><span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span><?php endif; ?>
                            </span>
                        </legend>

                        <?php if (!empty($question['description'])): ?>
                            <p class="question-help"><?= nl2br(e($question['description'])) ?></p>
                        <?php endif; ?>

                        <?php if ($type === 'likert5' || $type === 'agreement'): ?>
                            <div class="likert" role="radiogroup" aria-label="<?= e($label) ?>">
                                <?php for ($value = 1; $value <= 5; $value++): ?>
                                    <?php $id = 'q' . $qid . '_' . $value; ?>
                                    <input type="radio" id="<?= $id ?>" name="<?= e($fieldName) ?>" value="<?= $value ?>" <?= $required ? 'required' : '' ?>>
                                    <label for="<?= $id ?>">
                                        <span class="score"><?= $value ?></span>
                                        <span><?= e($ratingLabels[$value] ?? (string) $value) ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <?php if ($question['min_label'] || $question['max_label']): ?>
                                <div class="d-flex justify-content-between text-tiny text-muted mt-1">
                                    <span><?= e($question['min_label'] ?: '') ?></span>
                                    <span><?= e($question['max_label'] ?: '') ?></span>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($type === 'numeric'): ?>
                            <?php
                            $min = $question['min_value'] !== null ? (float) $question['min_value'] : 1.0;
                            $max = $question['max_value'] !== null ? (float) $question['max_value'] : 5.0;
                            ?>
                            <div class="row g-2 align-items-center">
                                <div class="col-sm-4 col-md-3">
                                    <input class="form-control" type="number" name="<?= e($fieldName) ?>" id="q<?= $qid ?>"
                                           min="<?= e((string) $min) ?>" max="<?= e((string) $max) ?>" step="0.01"
                                           inputmode="decimal" <?= $required ? 'required' : '' ?>
                                           aria-describedby="q<?= $qid ?>_help">
                                </div>
                                <div class="col-sm-8 col-md-9">
                                    <span class="text-muted small" id="q<?= $qid ?>_help">
                                        Enter a rating from <?= e(rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.')) ?>
                                        to <?= e(rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.')) ?>.
                                        <?php if ($question['min_label']): ?><br><span class="text-tiny"><?= e($question['min_label']) ?><?php if ($question['max_label']): ?> … <?= e($question['max_label']) ?><?php endif; ?></span><?php endif; ?>
                                    </span>
                                </div>
                            </div>

                        <?php elseif ($type === 'single_choice'): ?>
                            <div class="choice-list" role="radiogroup" aria-label="<?= e($label) ?>">
                                <?php foreach ($question['options'] as $optionIndex => $option): ?>
                                    <?php $id = 'q' . $qid . '_o' . (int) $option['id']; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="<?= $id ?>" name="<?= e($fieldName) ?>"
                                               value="<?= e($option['option_value']) ?>" <?= $required ? 'required' : '' ?>>
                                        <label class="form-check-label" for="<?= $id ?>"><?= e($option['option_label']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($type === 'multiple_choice'): ?>
                            <div class="choice-list" role="group" aria-label="<?= e($label) ?>">
                                <p class="text-tiny text-muted mb-2">Select all that apply.</p>
                                <?php foreach ($question['options'] as $option): ?>
                                    <?php $id = 'q' . $qid . '_o' . (int) $option['id']; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="<?= $id ?>" name="<?= e($fieldName) ?>[]"
                                               value="<?= e($option['option_value']) ?>">
                                        <label class="form-check-label" for="<?= $id ?>"><?= e($option['option_label']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($required): ?>
                                    <p class="text-tiny text-muted mb-0">At least one selection is required. This is checked after submission.</p>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($type === 'yes_no'): ?>
                            <div class="choice-list d-flex flex-wrap gap-3" role="radiogroup" aria-label="<?= e($label) ?>">
                                <?php foreach (['yes' => 'Yes', 'no' => 'No'] as $value => $text): ?>
                                    <?php $id = 'q' . $qid . '_' . $value; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="<?= $id ?>" name="<?= e($fieldName) ?>"
                                               value="<?= $value ?>" <?= $required ? 'required' : '' ?>>
                                        <label class="form-check-label" for="<?= $id ?>"><?= e($text) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($type === 'short_answer'): ?>
                            <input class="form-control" type="text" name="<?= e($fieldName) ?>" id="q<?= $qid ?>"
                                   maxlength="500" <?= $required ? 'required' : '' ?> placeholder="Your answer">

                        <?php elseif ($type === 'paragraph'): ?>
                            <textarea class="form-control" name="<?= e($fieldName) ?>" id="q<?= $qid ?>" rows="5"
                                      maxlength="4000" <?= $required ? 'required' : '' ?> placeholder="Share your experience, suggestions, or concerns"></textarea>
                            <div class="form-text">Do not include names or other identifying information. Maximum 4,000 characters.</div>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span class="data-note mb-0"><i class="bi bi-shield-check"></i> Submitted anonymously · One response per session</span>
                <button class="btn btn-primary btn-lg" type="submit" data-confirm="Submit this evaluation? You cannot edit or submit it again.">
                    <i class="bi bi-send-check"></i> Submit evaluation
                </button>
            </div>
        </div>

        <p class="text-tiny text-muted">
            By submitting, you confirm that the responses reflect your own experience. The system records participation separately from your answers and does not store your identity with this response.
        </p>
    </form>
<?php endif; ?>
