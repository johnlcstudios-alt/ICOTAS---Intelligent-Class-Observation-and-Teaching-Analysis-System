<?php

namespace App\Models;

use App\Core\Database;

final class Survey extends Model
{
    protected string $table = 'surveys';

    public function listing(bool $demo, bool $templates = false): array
    {
        return Database::fetchAll(
            'SELECT s.*, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM survey_versions v WHERE v.survey_id = s.id) AS version_count,
                    (SELECT v.id FROM survey_versions v WHERE v.survey_id = s.id AND v.status = "published" ORDER BY v.version_number DESC LIMIT 1) AS published_version_id,
                    (SELECT v.id FROM survey_versions v WHERE v.survey_id = s.id AND v.status = "draft" ORDER BY v.version_number DESC LIMIT 1) AS draft_version_id,
                    (SELECT v.version_number FROM survey_versions v WHERE v.survey_id = s.id AND v.status = "published" ORDER BY v.version_number DESC LIMIT 1) AS published_version_no,
                    (SELECT COUNT(*) FROM survey_questions q
                       JOIN survey_versions v ON v.id = q.survey_version_id
                       WHERE v.survey_id = s.id AND v.id = (
                         SELECT MAX(v2.id) FROM survey_versions v2 WHERE v2.survey_id = s.id
                       )) AS question_count
             FROM surveys s
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.is_demo = ? AND s.is_template = ?
             ORDER BY s.updated_at DESC',
            [$demo ? 1 : 0, $templates ? 1 : 0]
        );
    }

    public function templates(): array
    {
        return Database::fetchAll(
            'SELECT t.*, s.description AS survey_description,
                    (SELECT COUNT(*) FROM survey_questions q
                      JOIN survey_versions v ON v.id = q.survey_version_id
                      WHERE v.survey_id = t.survey_id AND v.status = "published") AS question_count
             FROM survey_templates t JOIN surveys s ON s.id = t.survey_id
             WHERE t.is_active = 1 ORDER BY t.name'
        );
    }

    public function findDetailed(int $id): ?array
    {
        return Database::fetch(
            'SELECT s.*, u.first_name, u.last_name FROM surveys s
             LEFT JOIN users u ON u.id = s.created_by WHERE s.id = ? LIMIT 1',
            [$id]
        );
    }

    public function versions(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT v.*, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_version_id = v.id) AS question_count,
                    (SELECT COUNT(*) FROM evaluation_assignments a WHERE a.survey_version_id = v.id) AS assignment_count
             FROM survey_versions v LEFT JOIN users u ON u.id = v.created_by
             WHERE v.survey_id = ? ORDER BY v.version_number DESC',
            [$surveyId]
        );
    }

    public function publishedVersion(int $surveyId): ?array
    {
        return Database::fetch(
            'SELECT * FROM survey_versions WHERE survey_id = ? AND status = "published" ORDER BY version_number DESC LIMIT 1',
            [$surveyId]
        );
    }

    public function draftVersion(int $surveyId): ?array
    {
        return Database::fetch(
            'SELECT * FROM survey_versions WHERE survey_id = ? AND status = "draft" ORDER BY version_number DESC LIMIT 1',
            [$surveyId]
        );
    }

    public function latestVersion(int $surveyId): ?array
    {
        return Database::fetch(
            'SELECT * FROM survey_versions WHERE survey_id = ? ORDER BY version_number DESC LIMIT 1',
            [$surveyId]
        );
    }

    public function version(int $versionId): ?array
    {
        return Database::fetch(
            'SELECT v.*, s.title AS survey_title, s.description AS survey_description, s.category AS survey_category, s.is_demo
             FROM survey_versions v JOIN surveys s ON s.id = v.survey_id
             WHERE v.id = ? LIMIT 1',
            [$versionId]
        );
    }

    public function questions(int $versionId): array
    {
        $questions = Database::fetchAll(
            'SELECT * FROM survey_questions WHERE survey_version_id = ? ORDER BY order_index ASC, id ASC',
            [$versionId]
        );
        foreach ($questions as &$question) {
            $question['options'] = Database::fetchAll(
                'SELECT * FROM question_options WHERE question_id = ? ORDER BY order_index ASC, id ASC',
                [$question['id']]
            );
        }
        return $questions;
    }

    public function versionWithQuestions(int $versionId): ?array
    {
        $version = $this->version($versionId);
        if (!$version) {
            return null;
        }
        $version['questions'] = $this->questions($versionId);
        return $version;
    }

    public function nextVersionNumber(int $surveyId): int
    {
        return (int) Database::scalar('SELECT COALESCE(MAX(version_number), 0) + 1 FROM survey_versions WHERE survey_id = ?', [$surveyId], 1);
    }

    public function createSurvey(string $title, ?string $description, string $category, ?int $userId, bool $isDemo = false, bool $isTemplate = false): array
    {
        $surveyId = Database::insert(
            'INSERT INTO surveys (title, description, category, status, is_template, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, ?, "draft", ?, ?, ?, NOW(), NOW())',
            [$title, $description, $category, $isTemplate ? 1 : 0, $isDemo ? 1 : 0, $userId]
        );
        $versionId = Database::insert(
            'INSERT INTO survey_versions (survey_id, version_number, status, created_by, created_at, updated_at)
             VALUES (?, 1, "draft", ?, NOW(), NOW())',
            [$surveyId, $userId]
        );
        return [$surveyId, $versionId];
    }

    public function copyQuestions(int $fromVersionId, int $toVersionId): void
    {
        $questions = $this->questions($fromVersionId);
        foreach ($questions as $index => $question) {
            $qid = Database::insert(
                'INSERT INTO survey_questions
                 (survey_version_id, question_text, description, question_type, category, order_index, is_required, min_value, max_value, min_label, max_label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$toVersionId, $question['question_text'], $question['description'], $question['question_type'],
                 $question['category'], $index + 1, $question['is_required'], $question['min_value'],
                 $question['max_value'], $question['min_label'], $question['max_label']]
            );
            foreach ($question['options'] as $optionIndex => $option) {
                Database::execute(
                    'INSERT INTO question_options (question_id, option_label, option_value, order_index) VALUES (?, ?, ?, ?)',
                    [$qid, $option['option_label'], $option['option_value'], $optionIndex + 1]
                );
            }
        }
    }

    public function createDraftVersion(int $surveyId, ?int $userId): int
    {
        $source = $this->publishedVersion($surveyId) ?? $this->latestVersion($surveyId);
        $number = $this->nextVersionNumber($surveyId);
        $versionId = Database::insert(
            'INSERT INTO survey_versions (survey_id, version_number, status, created_by, created_at, updated_at)
             VALUES (?, ?, "draft", ?, NOW(), NOW())',
            [$surveyId, $number, $userId]
        );
        if ($source) {
            $this->copyQuestions((int) $source['id'], $versionId);
        }
        return $versionId;
    }

    public function cloneSurvey(int $surveyId, ?int $userId, bool $isDemo = false): ?int
    {
        $source = $this->findDetailed($surveyId);
        if (!$source) {
            return null;
        }
        [$newId, $versionId] = $this->createSurvey(
            $source['title'] . ' (Copy)',
            $source['description'],
            $source['category'],
            $userId,
            $isDemo,
            false
        );
        $sourceVersion = $this->publishedVersion($surveyId) ?? $this->latestVersion($surveyId);
        if ($sourceVersion) {
            $this->copyQuestions((int) $sourceVersion['id'], $versionId);
        }
        return $newId;
    }

    public function countQuestions(int $versionId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM survey_questions WHERE survey_version_id = ?', [$versionId], 0);
    }
}
