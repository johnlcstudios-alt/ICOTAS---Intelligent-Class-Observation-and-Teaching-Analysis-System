<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Models\UAT;

/**
 * ISO/IEC 25010 user-acceptance testing. This module is separate from
 * instructor evaluation and never treats research reference scores as new data.
 */
final class UatService
{
    public const INTERPRETATION = [
        ['min' => 4.50, 'label' => 'Very Satisfactory'],
        ['min' => 3.50, 'label' => 'Satisfactory'],
        ['min' => 2.50, 'label' => 'Fair'],
        ['min' => 1.50, 'label' => 'Poor'],
        ['min' => 1.00, 'label' => 'Very Poor'],
    ];

    private UAT $uat;

    public function __construct()
    {
        $this->uat = new UAT();
    }

    public function createSurvey(array $data, int $userId, bool $demo): array
    {
        $title = Security::cleanText((string) ($data['title'] ?? ''), 180, false);
        if ($title === '') {
            return [false, 'UAT survey title is required.'];
        }
        $description = Security::cleanText((string) ($data['description'] ?? ''), 3000);
        $surveyId = Database::insert(
            'INSERT INTO uat_surveys (title, description, status, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, "draft", ?, ?, NOW(), NOW())',
            [$title, $description, $demo ? 1 : 0, $userId]
        );
        if (!empty($data['standard_questions'])) {
            $this->seedStandardQuestions($surveyId);
        }
        (new AuditService())->record('uat.create', 'uat_survey', $surveyId, 'Created UAT survey ' . $title);
        return [true, 'UAT survey created.', $surveyId];
    }

    public function seedStandardQuestions(int $surveyId): void
    {
        $bank = [
            'Functional Suitability' => [
                'The system provides the functions needed for class observation and teaching evaluation.',
                'The evaluation workflows produce accurate and complete results.',
                'The system modules work together as expected without missing core functions.',
            ],
            'Usability' => [
                'The interface is clear and easy to navigate for my role.',
                'Forms, labels and messages help me complete tasks without confusion.',
                'The system is usable on the devices and screen sizes I commonly use.',
            ],
            'Performance Efficiency' => [
                'Pages and reports load within an acceptable time.',
                'The system remains responsive when viewing analytics or large lists.',
                'QR evaluation access and submission complete without unreasonable delay.',
            ],
            'Reliability' => [
                'The system behaves consistently across repeated use.',
                'Saved records remain available and accurate after navigation or re-login.',
                'Errors are rare and the system recovers gracefully when they occur.',
            ],
            'Security' => [
                'Authentication and role restrictions protect unauthorized functions.',
                'Student evaluation responses are presented without exposing individual identities.',
                'The system protects data during QR access, submission and report export.',
            ],
            'Maintainability' => [
                'The system can be updated or configured without disrupting existing records.',
                'Survey and evaluation definitions can be managed clearly by administrators.',
                'Reports and analytics are understandable and traceable to source records.',
            ],
        ];
        $index = 1;
        foreach ($bank as $characteristic => $questions) {
            foreach ($questions as $text) {
                Database::execute(
                    'INSERT INTO uat_questions (uat_survey_id, characteristic, question_text, order_index, is_required)
                     VALUES (?, ?, ?, ?, 1)',
                    [$surveyId, $characteristic, $text, $index++]
                );
            }
        }
    }

    public function addQuestion(int $surveyId, string $characteristic, string $text, int $index = 1): array
    {
        if (!in_array($characteristic, UAT::CHARACTERISTICS, true)) {
            return [false, 'Choose a valid ISO/IEC 25010 characteristic.'];
        }
        $text = Security::cleanText($text, 500, false);
        if ($text === '') {
            return [false, 'Question text is required.'];
        }
        Database::execute(
            'INSERT INTO uat_questions (uat_survey_id, characteristic, question_text, order_index, is_required)
             VALUES (?, ?, ?, ?, 1)',
            [$surveyId, $characteristic, $text, max(1, $index)]
        );
        return [true, 'Question added.'];
    }

    public function deleteQuestion(int $surveyId, int $questionId): array
    {
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM uat_answers ua JOIN uat_questions uq ON uq.id = ua.uat_question_id WHERE uq.id = ? AND uq.uat_survey_id = ?',
            [$questionId, $surveyId],
            0
        );
        if ($count > 0) {
            return [false, 'This question already has responses and cannot be deleted.'];
        }
        Database::execute('DELETE FROM uat_questions WHERE id = ? AND uat_survey_id = ?', [$questionId, $surveyId]);
        return [true, 'Question removed.'];
    }

    public function setStatus(int $surveyId, string $status): array
    {
        if (!in_array($status, ['draft', 'active', 'closed'], true)) {
            return [false, 'Invalid UAT status.'];
        }
        Database::execute('UPDATE uat_surveys SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $surveyId]);
        return [true, 'UAT survey status updated.'];
    }

    public function addRespondent(int $surveyId, string $name, string $type, ?int $userId): array
    {
        $name = Security::cleanText($name, 150, false);
        $type = Security::cleanText($type, 80, false) ?: 'IT Professional';
        if ($name === '') {
            return [false, 'Respondent name is required.'];
        }
        $token = Security::randomToken(24);
        $id = Database::insert(
            'INSERT INTO uat_respondents
             (uat_survey_id, user_id, name, respondent_type, access_token_hash, access_token_cipher, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "invited", NOW())',
            [$surveyId, $userId, $name, $type, hash('sha256', $token), Security::encrypt($token)]
        );
        return [true, 'Respondent invitation created.', ['id' => $id, 'token' => $token]];
    }

    public function respondentByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT r.*, s.title AS survey_title, s.description AS survey_description, s.status AS survey_status,
                    s.opens_at, s.closes_at, s.is_demo
             FROM uat_respondents r JOIN uat_surveys s ON s.id = r.uat_survey_id
             WHERE r.access_token_hash = ? LIMIT 1',
            [hash('sha256', $token)]
        );
    }

    public function respondentLink(array $respondent): ?string
    {
        $token = Security::decrypt((string) ($respondent['access_token_cipher'] ?? ''));
        return $token ? url('uat/respond/' . $token) : null;
    }

    public function submitByToken(string $token, array $ratings): array
    {
        $respondent = $this->respondentByToken($token);
        if (!$respondent) {
            return [false, 'This UAT invitation is invalid.'];
        }
        if ($respondent['survey_status'] !== 'active') {
            return [false, 'This UAT survey is not currently accepting responses.'];
        }
        if ($respondent['status'] === 'submitted') {
            return [false, 'This UAT response has already been submitted.'];
        }
        $now = time();
        if ($respondent['opens_at'] && strtotime((string) $respondent['opens_at']) > $now) {
            return [false, 'This UAT survey has not opened yet.'];
        }
        if ($respondent['closes_at'] && strtotime((string) $respondent['closes_at']) < $now) {
            return [false, 'This UAT survey has closed.'];
        }

        $questions = $this->uat->questions((int) $respondent['uat_survey_id']);
        if (empty($questions)) {
            return [false, 'This UAT survey has no questions.'];
        }
        foreach ($questions as $question) {
            $rating = $ratings[(string) $question['id']] ?? null;
            if ($rating === null || !is_numeric($rating) || (int) $rating < 1 || (int) $rating > 5) {
                return [false, 'Please provide a rating from 1 to 5 for every statement.'];
            }
        }

        try {
            Database::transaction(function () use ($respondent, $questions, $ratings, $token) {
                $locked = Database::fetch(
                    'SELECT * FROM uat_respondents WHERE access_token_hash = ? FOR UPDATE',
                    [hash('sha256', $token)]
                );
                if (!$locked || $locked['status'] === 'submitted') {
                    throw new \RuntimeException('UAT invitation already used.');
                }
                $responseId = Database::insert(
                    'INSERT INTO uat_responses (uat_survey_id, uat_respondent_id, respondent_name, respondent_type, submitted_at, source)
                     VALUES (?, ?, ?, ?, NOW(), ?)',
                    [(int) $respondent['uat_survey_id'], (int) $respondent['id'], $respondent['name'], $respondent['respondent_type'], $respondent['is_demo'] ? 'demo' : 'live']
                );
                foreach ($questions as $question) {
                    Database::execute(
                        'INSERT INTO uat_answers (uat_response_id, uat_question_id, rating) VALUES (?, ?, ?)',
                        [$responseId, (int) $question['id'], (int) $ratings[(string) $question['id']]]
                    );
                }
                Database::execute(
                    'UPDATE uat_respondents SET status = "submitted", submitted_at = NOW() WHERE id = ?',
                    [(int) $respondent['id']]
                );
            });
        } catch (\Throwable $e) {
            return [false, 'The UAT response could not be saved. It may already have been submitted.'];
        }

        return [true, 'Thank you. Your UAT response has been recorded.'];
    }

    public function results(int $surveyId): array
    {
        return $this->uat->resultSummary($surveyId);
    }

    public function interpret(?float $mean): string
    {
        if ($mean === null) {
            return 'No data';
        }
        if ($mean >= 4.50) return 'Very Satisfactory';
        if ($mean >= 3.50) return 'Satisfactory';
        if ($mean >= 2.50) return 'Fair';
        if ($mean >= 1.50) return 'Poor';
        return 'Very Poor';
    }

    public function updateDates(int $surveyId, ?string $opensAt, ?string $closesAt): array
    {
        if ($opensAt && $closesAt && strtotime($closesAt) <= strtotime($opensAt)) {
            return [false, 'Closing time must be after opening time.'];
        }
        Database::execute(
            'UPDATE uat_surveys SET opens_at = ?, closes_at = ?, updated_at = NOW() WHERE id = ?',
            [$opensAt ?: null, $closesAt ?: null, $surveyId]
        );
        return [true, 'UAT schedule updated.'];
    }
}
