<?php

namespace App\Models;

use App\Core\Database;

final class UAT extends Model
{
    protected string $table = 'uat_surveys';

    public const CHARACTERISTICS = [
        'Functional Suitability',
        'Usability',
        'Performance Efficiency',
        'Reliability',
        'Security',
        'Maintainability',
    ];

    public function surveys(bool $demo): array
    {
        return Database::fetchAll(
            'SELECT us.*, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM uat_questions q WHERE q.uat_survey_id = us.id) AS question_count,
                    (SELECT COUNT(*) FROM uat_respondents rr WHERE rr.uat_survey_id = us.id) AS respondent_count,
                    (SELECT COUNT(*) FROM uat_responses r WHERE r.uat_survey_id = us.id) AS response_count
             FROM uat_surveys us LEFT JOIN users u ON u.id = us.created_by
             WHERE us.is_demo = ? ORDER BY us.created_at DESC',
            [$demo ? 1 : 0]
        );
    }

    public function findSurvey(int $id): ?array
    {
        return Database::fetch('SELECT * FROM uat_surveys WHERE id = ? LIMIT 1', [$id]);
    }

    public function questions(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM uat_questions WHERE uat_survey_id = ? ORDER BY FIELD(characteristic, "Functional Suitability", "Usability", "Performance Efficiency", "Reliability", "Security", "Maintainability"), order_index, id',
            [$surveyId]
        );
    }

    public function respondents(int $surveyId): array
    {
        return Database::fetchAll('SELECT * FROM uat_respondents WHERE uat_survey_id = ? ORDER BY id', [$surveyId]);
    }

    public function responses(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM uat_responses WHERE uat_survey_id = ? ORDER BY submitted_at DESC',
            [$surveyId]
        );
    }

    public function resultSummary(int $surveyId): array
    {
        $overall = Database::fetch(
            'SELECT COUNT(DISTINCT ur.id) AS respondents,
                    ROUND(AVG(ua.rating), 2) AS mean,
                    ROUND(STDDEV_POP(ua.rating), 2) AS sd_pop,
                    ROUND(STDDEV_SAMP(ua.rating), 2) AS sd_sample,
                    COUNT(ua.id) AS rating_count
             FROM uat_answers ua JOIN uat_responses ur ON ur.id = ua.uat_response_id
             WHERE ur.uat_survey_id = ?',
            [$surveyId]
        );
        $characteristics = Database::fetchAll(
            'SELECT uq.characteristic, ROUND(AVG(ua.rating), 2) AS mean,
                    ROUND(STDDEV_POP(ua.rating), 2) AS sd_pop,
                    ROUND(STDDEV_SAMP(ua.rating), 2) AS sd_sample,
                    COUNT(ua.id) AS rating_count,
                    COUNT(DISTINCT ur.id) AS respondent_count
             FROM uat_answers ua
             JOIN uat_questions uq ON uq.id = ua.uat_question_id
             JOIN uat_responses ur ON ur.id = ua.uat_response_id
             WHERE ur.uat_survey_id = ?
             GROUP BY uq.characteristic
             ORDER BY FIELD(uq.characteristic, "Functional Suitability", "Usability", "Performance Efficiency", "Reliability", "Security", "Maintainability")',
            [$surveyId]
        );
        $questions = Database::fetchAll(
            'SELECT uq.id, uq.characteristic, uq.question_text, uq.order_index,
                    ROUND(AVG(ua.rating), 2) AS mean, ROUND(STDDEV_POP(ua.rating), 2) AS sd,
                    COUNT(ua.id) AS rating_count
             FROM uat_questions uq
             LEFT JOIN uat_answers ua ON ua.uat_question_id = uq.id
             LEFT JOIN uat_responses ur ON ur.id = ua.uat_response_id AND ur.uat_survey_id = uq.uat_survey_id
             WHERE uq.uat_survey_id = ?
             GROUP BY uq.id, uq.characteristic, uq.question_text, uq.order_index
             ORDER BY uq.order_index',
            [$surveyId]
        );
        return [
            'overall' => [
                'respondents' => (int) ($overall['respondents'] ?? 0),
                'mean' => $overall['mean'] !== null ? (float) $overall['mean'] : null,
                'sd_pop' => $overall['sd_pop'] !== null ? (float) $overall['sd_pop'] : null,
                'sd_sample' => $overall['sd_sample'] !== null ? (float) $overall['sd_sample'] : null,
                'rating_count' => (int) ($overall['rating_count'] ?? 0),
            ],
            'characteristics' => array_map(static function ($row) {
                $row['mean'] = $row['mean'] !== null ? (float) $row['mean'] : null;
                $row['sd_pop'] = $row['sd_pop'] !== null ? (float) $row['sd_pop'] : null;
                $row['sd_sample'] = $row['sd_sample'] !== null ? (float) $row['sd_sample'] : null;
                $row['interpretation'] = interpret_uat($row['mean']);
                return $row;
            }, $characteristics),
            'questions' => array_map(static function ($row) {
                $row['mean'] = $row['mean'] !== null ? (float) $row['mean'] : null;
                $row['sd'] = $row['sd'] !== null ? (float) $row['sd'] : null;
                return $row;
            }, $questions),
        ];
    }

    public function referenceResults(): array
    {
        return Database::fetchAll('SELECT * FROM uat_reference_results ORDER BY id');
    }
}
