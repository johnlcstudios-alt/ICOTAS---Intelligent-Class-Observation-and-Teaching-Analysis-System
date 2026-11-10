<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Survey;

/**
 * Survey builder persistence, versioning and template cloning.
 * Published versions are immutable: edits produce a new draft version.
 */
final class SurveyService
{
    public const QUESTION_TYPES = [
        'likert5' => 'Five-point Likert scale',
        'agreement' => 'Agreement scale',
        'numeric' => 'Numeric rating',
        'single_choice' => 'Single choice',
        'multiple_choice' => 'Multiple choice',
        'yes_no' => 'Yes / No',
        'short_answer' => 'Short answer',
        'paragraph' => 'Paragraph',
    ];

    public const THEMES = [
        'Teaching Clarity',
        'Communication',
        'Classroom Engagement',
        'Lesson Pacing',
        'Teaching Methodology',
        'Classroom Interaction',
        'Learning Materials',
        'Assessment Methods',
        'Instructor Responsiveness',
        'Subject Comprehension',
        'Overall Satisfaction',
        'General',
    ];

    private Survey $surveys;

    public function __construct()
    {
        $this->surveys = new Survey();
    }

    public function defaultRatingLabels(): array
    {
        return [
            1 => 'Strongly Disagree',
            2 => 'Disagree',
            3 => 'Neutral',
            4 => 'Agree',
            5 => 'Strongly Agree',
        ];
    }

    public function createSurvey(string $title, ?string $description, string $category, int $userId, bool $demo = false): array
    {
        $title = \App\Core\Security::cleanText($title, 180, false);
        $category = \App\Core\Security::cleanText($category, 80, false) ?: 'Teaching Effectiveness';
        return $this->surveys->createSurvey($title, $description ? \App\Core\Security::cleanText($description, 2000) : null, $category, $userId, $demo, false);
    }

    public function saveBuilder(int $surveyId, int $versionId, array $payload, int $userId): array
    {
        $survey = $this->surveys->findDetailed($surveyId);
        $version = $this->surveys->version($versionId);
        if (!$survey || !$version || (int) $version['survey_id'] !== $surveyId) {
            return [false, 'Survey version not found.'];
        }
        if ($version['status'] !== 'draft') {
            return [false, 'Published survey versions cannot be edited. Create a new draft version first.'];
        }

        $title = \App\Core\Security::cleanText((string) ($payload['title'] ?? ''), 180, false);
        if ($title === '') {
            return [false, 'Survey title is required.'];
        }
        $description = \App\Core\Security::cleanText((string) ($payload['description'] ?? ''), 3000);
        $instructions = \App\Core\Security::cleanText((string) ($payload['instructions'] ?? ''), 3000);
        $category = \App\Core\Security::cleanText((string) ($payload['category'] ?? 'Teaching Effectiveness'), 80, false);
        $ratingLabels = $this->normalizeRatingLabels($payload['rating_labels'] ?? []);
        $questions = $payload['questions'] ?? [];
        if (!is_array($questions) || count($questions) < 1) {
            return [false, 'Add at least one question before saving.'];
        }
        if (count($questions) > 80) {
            return [false, 'A survey may contain at most 80 questions.'];
        }

        $normalizedQuestions = [];
        foreach (array_values($questions) as $index => $question) {
            $type = (string) ($question['type'] ?? '');
            if (!isset(self::QUESTION_TYPES[$type])) {
                return [false, 'Question ' . ($index + 1) . ' has an unsupported type.'];
            }
            $text = \App\Core\Security::cleanText((string) ($question['text'] ?? ''), 500, false);
            if ($text === '') {
                return [false, 'Question ' . ($index + 1) . ' needs question text.'];
            }
            $categoryName = \App\Core\Security::cleanText((string) ($question['category'] ?? 'General'), 80, false);
            if ($categoryName === '') {
                $categoryName = 'General';
            }
            $min = $type === 'numeric' ? (float) ($question['min'] ?? 1) : ($type === 'likert5' || $type === 'agreement' ? 1.0 : null);
            $max = $type === 'numeric' ? (float) ($question['max'] ?? 5) : ($type === 'likert5' || $type === 'agreement' ? 5.0 : null);
            if ($type === 'numeric' && ($min < 1 || $max > 10 || $max <= $min)) {
                return [false, 'Numeric rating on question ' . ($index + 1) . ' must use a range between 1 and 10.'];
            }

            $options = [];
            if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                foreach ((array) ($question['options'] ?? []) as $option) {
                    $label = \App\Core\Security::cleanText((string) (is_array($option) ? ($option['label'] ?? '') : $option), 180, false);
                    if ($label === '') {
                        continue;
                    }
                    $value = \App\Core\Security::cleanText((string) (is_array($option) ? ($option['value'] ?? '') : ''), 180, false);
                    $options[] = ['label' => $label, 'value' => $value !== '' ? $value : $this->slug($label)];
                }
                if (count($options) < 2) {
                    return [false, 'Choice question ' . ($index + 1) . ' requires at least two options.'];
                }
            }

            $normalizedQuestions[] = [
                'text' => $text,
                'description' => \App\Core\Security::cleanText((string) ($question['description'] ?? ''), 500, false),
                'type' => $type,
                'category' => $categoryName,
                'required' => !empty($question['required']) ? 1 : 0,
                'min' => $min,
                'max' => $max,
                'min_label' => \App\Core\Security::cleanText((string) ($question['min_label'] ?? ''), 80, false),
                'max_label' => \App\Core\Security::cleanText((string) ($question['max_label'] ?? ''), 80, false),
                'options' => $options,
            ];
        }

        Database::transaction(function () use ($surveyId, $versionId, $title, $description, $instructions, $category, $ratingLabels, $normalizedQuestions, $userId) {
            Database::execute(
                'UPDATE surveys SET title = ?, description = ?, category = ?, updated_at = NOW() WHERE id = ?',
                [$title, $description, $category, $surveyId]
            );
            Database::execute(
                'UPDATE survey_versions SET instructions = ?, rating_labels = ?, updated_at = NOW() WHERE id = ?',
                [$instructions, json_encode($ratingLabels, JSON_UNESCAPED_UNICODE), $versionId]
            );
            Database::execute('DELETE FROM survey_questions WHERE survey_version_id = ?', [$versionId]);

            foreach ($normalizedQuestions as $index => $question) {
                $questionId = Database::insert(
                    'INSERT INTO survey_questions
                     (survey_version_id, question_text, description, question_type, category, order_index, is_required,
                      min_value, max_value, min_label, max_label, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$versionId, $question['text'], $question['description'] ?: null, $question['type'], $question['category'],
                     $index + 1, $question['required'], $question['min'], $question['max'],
                     $question['min_label'] ?: null, $question['max_label'] ?: null]
                );
                foreach ($question['options'] as $optionIndex => $option) {
                    Database::execute(
                        'INSERT INTO question_options (question_id, option_label, option_value, order_index) VALUES (?, ?, ?, ?)',
                        [$questionId, $option['label'], $option['value'], $optionIndex + 1]
                    );
                }
            }
        });

        return [true, 'Survey draft saved.'];
    }

    public function publish(int $surveyId, int $versionId): array
    {
        $version = $this->surveys->version($versionId);
        if (!$version || (int) $version['survey_id'] !== $surveyId) {
            return [false, 'Survey version not found.'];
        }
        if ($version['status'] !== 'draft') {
            return [false, 'Only a draft version can be published.'];
        }
        if ($this->surveys->countQuestions($versionId) < 1) {
            return [false, 'Add at least one question before publishing.'];
        }

        Database::transaction(function () use ($surveyId, $versionId) {
            Database::execute(
                'UPDATE survey_versions SET status = "archived" WHERE survey_id = ? AND status = "published"',
                [$surveyId]
            );
            Database::execute(
                'UPDATE survey_versions SET status = "published", published_at = NOW(), updated_at = NOW() WHERE id = ?',
                [$versionId]
            );
            Database::execute('UPDATE surveys SET status = "active", updated_at = NOW() WHERE id = ?', [$surveyId]);
        });
        return [true, 'Survey version published. Existing assignments keep their original version.'];
    }

    public function archive(int $surveyId): array
    {
        Database::execute('UPDATE surveys SET status = "archived", updated_at = NOW() WHERE id = ?', [$surveyId]);
        return [true, 'Survey archived. Submitted definitions remain intact.'];
    }

    public function duplicate(int $surveyId, int $userId, bool $demo = false): array
    {
        $newId = $this->surveys->cloneSurvey($surveyId, $userId, $demo);
        return $newId
            ? [true, 'Survey duplicated as a new draft.', $newId]
            : [false, 'Survey could not be duplicated.'];
    }

    public function newVersion(int $surveyId, int $userId): array
    {
        $existing = $this->surveys->draftVersion($surveyId);
        if ($existing) {
            return [true, 'A draft version already exists.', (int) $existing['id']];
        }
        $versionId = $this->surveys->createDraftVersion($surveyId, $userId);
        return [true, 'New draft version created from the latest published definition.', $versionId];
    }

    public function cloneTemplate(int $templateId, int $userId, bool $demo = false): array
    {
        $template = Database::fetch('SELECT * FROM survey_templates WHERE id = ? AND is_active = 1', [$templateId]);
        if (!$template) {
            return [false, 'Template not found.'];
        }
        $sourceSurvey = $this->surveys->findDetailed((int) $template['survey_id']);
        if (!$sourceSurvey) {
            return [false, 'Template survey is unavailable.'];
        }
        [$newId, $versionId] = $this->surveys->createSurvey(
            $sourceSurvey['title'] . ' — Copy',
            $sourceSurvey['description'],
            $sourceSurvey['category'],
            $userId,
            $demo,
            false
        );
        $sourceVersion = $this->surveys->publishedVersion((int) $template['survey_id']) ?? $this->surveys->latestVersion((int) $template['survey_id']);
        if (!$sourceVersion) {
            return [false, 'Template has no questionnaire definition.'];
        }
        $this->surveys->copyQuestions((int) $sourceVersion['id'], $versionId);
        Database::execute('UPDATE survey_versions SET instructions = ? WHERE id = ?', [$sourceVersion['instructions'] ?? null, $versionId]);
        Database::execute('UPDATE survey_versions SET rating_labels = ? WHERE id = ?', [$sourceVersion['rating_labels'] ?? null, $versionId]);
        return [true, 'Template copied to a new draft survey.', $newId];
    }

    public function normalizeRatingLabels($labels): array
    {
        $default = $this->defaultRatingLabels();
        if (!is_array($labels)) {
            return $default;
        }
        $out = [];
        for ($i = 1; $i <= 5; $i++) {
            $label = \App\Core\Security::cleanText((string) ($labels[$i] ?? ($labels[$i - 1] ?? '')), 60, false);
            $out[$i] = $label !== '' ? $label : $default[$i];
        }
        return $out;
    }

    public function ratingLabels(array $version): array
    {
        $decoded = json_decode((string) ($version['rating_labels'] ?? '[]'), true);
        return $this->normalizeRatingLabels(is_array($decoded) ? $decoded : []);
    }

    private function slug(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        return trim($slug, '_') ?: 'option';
    }
}
