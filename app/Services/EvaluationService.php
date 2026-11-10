<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Models\Evaluation;
use App\Models\Survey;

/**
 * Eligible-student access checks and atomic anonymous submission.
 */
final class EvaluationService
{
    private Evaluation $evaluations;
    private Survey $surveys;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->surveys = new Survey();
    }

    public function accessState(array $session, int $studentId): array
    {
        $status = $this->effectiveStatus($session);
        if ($status === 'scheduled') {
            return ['state' => 'not_started', 'message' => 'This evaluation is not yet open. It opens ' . format_datetime($session['opens_at']) . '.'];
        }
        if ($status === 'expired') {
            return ['state' => 'expired', 'message' => 'This evaluation session has expired.'];
        }
        if ($status === 'closed' || $status === 'archived') {
            return ['state' => 'closed', 'message' => 'This evaluation session is closed.'];
        }
        if ($status !== 'active') {
            return ['state' => 'unavailable', 'message' => 'This evaluation is not available.'];
        }

        $assignment = [
            'course_id' => $session['course_id'],
            'section_id' => $session['section_id'],
            'academic_year_id' => $session['academic_year_id'],
            'semester_id' => $session['semester_id'],
        ];
        if (!$this->evaluations->isStudentEligible($assignment, $studentId)) {
            return ['state' => 'not_eligible', 'message' => 'Your account is not on the eligible roster for this class and evaluation period.'];
        }

        $participation = $this->evaluations->participation((int) $session['id'], $studentId);
        if ($participation && $participation['status'] === 'submitted') {
            return ['state' => 'already_submitted', 'message' => 'You have already submitted this evaluation. Only one response is allowed.'];
        }
        return ['state' => 'available', 'message' => 'Evaluation available.'];
    }

    public function effectiveStatus(array $session): string
    {
        if (in_array($session['status'], ['closed', 'archived'], true)) {
            return $session['status'];
        }
        $now = time();
        if (strtotime((string) $session['expires_at']) < $now) {
            return 'expired';
        }
        if (strtotime((string) $session['opens_at']) > $now) {
            return 'scheduled';
        }
        return 'active';
    }

    public function formData(array $session): array
    {
        $version = $this->surveys->versionWithQuestions((int) $session['survey_version_id']);
        $labels = $version ? (new SurveyService())->ratingLabels($version) : (new SurveyService())->defaultRatingLabels();
        return ['version' => $version, 'rating_labels' => $labels];
    }

    /**
     * Submit one anonymous response. Participation and the response are stored
     * separately; no student_id is written to evaluation_responses or
     * response_answers. The participation row is locked and updated exactly once.
     */
    public function submit(array $session, int $studentId, array $rawAnswers): array
    {
        $state = $this->accessState($session, $studentId);
        if ($state['state'] !== 'available') {
            return [false, $state['message'], $state['state']];
        }

        $version = $this->surveys->versionWithQuestions((int) $session['survey_version_id']);
        if (!$version) {
            return [false, 'The questionnaire definition is unavailable.', 'unavailable'];
        }
        if ($version['status'] !== 'published') {
            return [false, 'The questionnaire version is not published.', 'unavailable'];
        }

        $answers = [];
        $errors = [];
        foreach ($version['questions'] as $question) {
            $qid = (string) $question['id'];
            $raw = $rawAnswers[$qid] ?? null;
            $type = $question['question_type'];
            $required = (int) $question['is_required'] === 1;

            if (in_array($type, ['likert5', 'agreement', 'numeric'], true)) {
                if ($raw === null || $raw === '') {
                    if ($required) {
                        $errors[] = 'Please answer: ' . str_limit($question['question_text'], 80);
                    }
                    continue;
                }
                if (!is_numeric($raw)) {
                    $errors[] = 'Invalid numeric rating for: ' . str_limit($question['question_text'], 80);
                    continue;
                }
                $value = (float) $raw;
                $min = $question['min_value'] !== null ? (float) $question['min_value'] : 1.0;
                $max = $question['max_value'] !== null ? (float) $question['max_value'] : 5.0;
                if ($value < $min || $value > $max) {
                    $errors[] = 'Rating out of range for: ' . str_limit($question['question_text'], 80);
                    continue;
                }
                $labels = (new SurveyService())->ratingLabels($version);
                if ($type === 'likert5' || $type === 'agreement') {
                    $label = $labels[(int) $value] ?? (string) $value;
                    $normalized = $value;
                } else {
                    $label = (string) $value;
                    $normalized = \App\Models\Analytics::normalizeToFive($value, $min, $max);
                }
                $answers[] = ['question_id' => $question['id'], 'value' => $label, 'numeric' => $normalized];
                continue;
            }

            if ($type === 'single_choice') {
                if ($raw === null || $raw === '') {
                    if ($required) {
                        $errors[] = 'Please choose an option for: ' . str_limit($question['question_text'], 80);
                    }
                    continue;
                }
                $option = $this->findOption($question, (string) $raw);
                if (!$option) {
                    $errors[] = 'Invalid option for: ' . str_limit($question['question_text'], 80);
                    continue;
                }
                $answers[] = ['question_id' => $question['id'], 'value' => $option['option_label'], 'numeric' => null];
                continue;
            }

            if ($type === 'multiple_choice') {
                $selected = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []);
                if (empty($selected)) {
                    if ($required) {
                        $errors[] = 'Please choose at least one option for: ' . str_limit($question['question_text'], 80);
                    }
                    continue;
                }
                $labels = [];
                foreach ($selected as $selection) {
                    $option = $this->findOption($question, (string) $selection);
                    if (!$option) {
                        $errors[] = 'Invalid option for: ' . str_limit($question['question_text'], 80);
                        continue 2;
                    }
                    $labels[] = $option['option_label'];
                }
                $answers[] = ['question_id' => $question['id'], 'value' => json_encode(array_values(array_unique($labels)), JSON_UNESCAPED_UNICODE), 'numeric' => null];
                continue;
            }

            if ($type === 'yes_no') {
                if ($raw === null || $raw === '') {
                    if ($required) {
                        $errors[] = 'Please answer yes or no for: ' . str_limit($question['question_text'], 80);
                    }
                    continue;
                }
                $value = strtolower((string) $raw);
                if (!in_array($value, ['yes', 'no'], true)) {
                    $errors[] = 'Invalid answer for: ' . str_limit($question['question_text'], 80);
                    continue;
                }
                $answers[] = ['question_id' => $question['id'], 'value' => $value, 'numeric' => null];
                continue;
            }

            if ($type === 'short_answer' || $type === 'paragraph') {
                $text = Security::cleanText(is_array($raw) ? '' : (string) $raw, $type === 'short_answer' ? 500 : 4000);
                if ($text === '') {
                    if ($required) {
                        $errors[] = 'Please provide a response for: ' . str_limit($question['question_text'], 80);
                    }
                    continue;
                }
                $answers[] = ['question_id' => $question['id'], 'value' => $text, 'numeric' => null, 'text' => true];
            }
        }

        if (!empty($errors)) {
            return [false, $errors[0], 'validation', $errors];
        }
        if (empty($answers)) {
            return [false, 'The evaluation contains no answers.', 'validation'];
        }

        // Use a separate participation lock so simultaneous taps cannot create two responses.
        try {
            $result = Database::transaction(function () use ($session, $studentId, $answers, $version) {
                Database::execute(
                    'INSERT IGNORE INTO evaluation_participation (evaluation_session_id, student_id, status, created_at)
                     VALUES (?, ?, "pending", NOW())',
                    [(int) $session['id'], $studentId]
                );
                $participation = Database::fetch(
                    'SELECT * FROM evaluation_participation WHERE evaluation_session_id = ? AND student_id = ? FOR UPDATE',
                    [(int) $session['id'], $studentId]
                );
                if (!$participation || $participation['status'] === 'submitted') {
                    return [false, 'This evaluation was already submitted from your account.', 'duplicate'];
                }

                $now = new \DateTimeImmutable('now');
                $bucket = $now->setTime((int) $now->format('H'), 0, 0)->format('Y-m-d H:i:s');
                $responseId = Database::insert(
                    'INSERT INTO evaluation_responses
                     (evaluation_session_id, survey_version_id, submitted_at, submitted_on, response_status, sentiment_status, source, created_at)
                     VALUES (?, ?, ?, ?, "submitted", "pending", "live", NOW())',
                    [(int) $session['id'], (int) $version['id'], $bucket, $now->format('Y-m-d')]
                );

                foreach ($answers as $answer) {
                    Database::execute(
                        'INSERT INTO response_answers (response_id, question_id, answer_value, numeric_value, created_at)
                         VALUES (?, ?, ?, ?, NOW())',
                        [$responseId, $answer['question_id'], $answer['value'], $answer['numeric']]
                    );
                }

                $updated = Database::execute(
                    'UPDATE evaluation_participation SET status = "submitted", submitted_at = NOW()
                     WHERE id = ? AND status = "pending"',
                    [$participation['id']]
                );
                if ($updated !== 1) {
                    throw new \RuntimeException('Participation was already finalized.');
                }
                Database::execute(
                    'UPDATE evaluation_eligibility_tokens SET used_at = NOW()
                     WHERE evaluation_session_id = ? AND student_id = ? AND used_at IS NULL',
                    [(int) $session['id'], $studentId]
                );

                return [$responseId, $answers];
            });
        } catch (\Throwable $e) {
            error_log('Evaluation submission failed: ' . $e->getMessage());
            return [false, 'The response could not be saved. Refresh and try again.', 'error'];
        }

        if (!$result[0]) {
            return [false, $result[1], $result[2]];
        }

        $responseId = (int) $result[0];
        $textParts = [];
        foreach ($answers as $answer) {
            if (!empty($answer['text'])) {
                $textParts[] = $answer['value'];
            }
        }
        try {
            (new AIEngine())->analyzeAndStore($responseId, implode("\n", $textParts));
        } catch (\Throwable $e) {
            error_log('AI analysis failed for response ' . $responseId . ': ' . $e->getMessage());
        }

        (new AuditService())->record('evaluation.submit', 'evaluation_session', (int) $session['id'], 'Anonymous evaluation response submitted');
        (new NotificationService())->submissionReceived((int) $session['id'], $session, (bool) $session['is_demo']);

        return [true, 'Your anonymous evaluation has been submitted.', 'submitted', [], $responseId];
    }

    private function findOption(array $question, string $value): ?array
    {
        foreach ($question['options'] as $option) {
            if ((string) $option['option_value'] === $value || (string) $option['option_label'] === $value) {
                return $option;
            }
        }
        return null;
    }
}
