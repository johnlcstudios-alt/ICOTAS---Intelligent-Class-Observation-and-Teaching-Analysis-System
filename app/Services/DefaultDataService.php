<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Survey;

/**
 * Seeds the institutional survey template library and the default ISO/IEC 25010
 * UAT form. These are definitions, not responses, and contain no synthetic data.
 */
final class DefaultDataService
{
    public function seed(): array
    {
        $created = [];
        foreach ($this->templates() as $definition) {
            $exists = Database::scalar('SELECT id FROM surveys WHERE title = ? AND is_template = 1 LIMIT 1', [$definition['title']]);
            if ($exists) {
                continue;
            }
            $this->createTemplate($definition);
            $created[] = $definition['title'];
        }

        $uatExists = Database::scalar('SELECT id FROM uat_surveys WHERE title = ? LIMIT 1', ['ISO/IEC 25010 User Acceptance Evaluation']);
        if (!$uatExists) {
            $surveyId = Database::insert(
                'INSERT INTO uat_surveys (title, description, status, is_demo, created_by, created_at, updated_at)
                 VALUES (?, ?, "draft", 0, NULL, NOW(), NOW())',
                ['ISO/IEC 25010 User Acceptance Evaluation', 'Standard six-characteristic UAT form. Configure questions and assign respondents before opening the survey.']
            );
            (new UatService())->seedStandardQuestions((int) $surveyId);
            $created[] = 'ISO/IEC 25010 UAT form';
        }

        return $created;
    }

    private function createTemplate(array $definition): void
    {
        $surveyId = Database::insert(
            'INSERT INTO surveys (title, description, category, status, is_template, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, ?, "active", 1, 0, NULL, NOW(), NOW())',
            [$definition['title'], $definition['description'], $definition['category']]
        );
        $versionId = Database::insert(
            'INSERT INTO survey_versions
             (survey_id, version_number, status, instructions, rating_labels, created_by, published_at, created_at, updated_at)
             VALUES (?, 1, "published", ?, ?, NULL, NOW(), NOW(), NOW())',
            [
                $surveyId,
                $definition['instructions'] ?? 'Rate each statement using the five-point agreement scale. Your response is anonymous.',
                json_encode((new SurveyService())->defaultRatingLabels(), JSON_UNESCAPED_UNICODE),
            ]
        );
        $index = 1;
        foreach ($definition['questions'] as $question) {
            $questionId = Database::insert(
                'INSERT INTO survey_questions
                 (survey_version_id, question_text, description, question_type, category, order_index, is_required, min_value, max_value, min_label, max_label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())',
                [
                    $versionId,
                    $question['text'],
                    $question['description'] ?? null,
                    $question['type'] ?? 'agreement',
                    $question['category'],
                    $index++,
                    $question['min'] ?? ($question['type'] ?? 'agreement' === 'numeric' ? 1 : 1),
                    $question['max'] ?? 5,
                    'Strongly Disagree',
                    'Strongly Agree',
                ]
            );
            foreach (($question['options'] ?? []) as $optionIndex => $option) {
                Database::execute(
                    'INSERT INTO question_options (question_id, option_label, option_value, order_index) VALUES (?, ?, ?, ?)',
                    [$questionId, $option, $this->slug($option), $optionIndex + 1]
                );
            }
        }
        Database::execute(
            'INSERT INTO survey_templates (name, description, category, survey_id, is_active, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())',
            [$definition['title'], $definition['description'], $definition['category'], $surveyId]
        );
    }

    private function templates(): array
    {
        $agreement = static fn (string $text, string $category) => ['text' => $text, 'type' => 'agreement', 'category' => $category];
        return [
            [
                'title' => 'Teaching Effectiveness',
                'category' => 'Teaching Effectiveness',
                'description' => 'Broad five-point evaluation of instructional effectiveness and learning support.',
                'instructions' => 'Think about your experience in this class and rate each statement honestly. Do not include names or identifying details in written comments.',
                'questions' => [
                    $agreement('The instructor explains lesson content clearly.', 'Teaching Clarity'),
                    $agreement('The instructor presents examples that help me understand difficult concepts.', 'Teaching Clarity'),
                    $agreement('The instructor communicates instructions and expectations clearly.', 'Communication'),
                    $agreement('The instructor uses class time effectively.', 'Lesson Pacing'),
                    $agreement('The instructor uses varied and appropriate teaching strategies.', 'Teaching Methodology'),
                    $agreement('The instructor encourages students to participate and ask questions.', 'Classroom Engagement'),
                    $agreement('The learning materials support the lesson objectives.', 'Learning Materials'),
                    $agreement('Assessment tasks are fair and aligned with what was taught.', 'Assessment Methods'),
                    ['text' => 'What did this instructor do particularly well?', 'type' => 'paragraph', 'category' => 'General', 'description' => 'Your written feedback is anonymous. Please avoid names or personal information.'],
                    ['text' => 'What specific change would improve your learning experience?', 'type' => 'paragraph', 'category' => 'General', 'description' => 'Optional. Focus on teaching practice rather than individuals.'],
                ],
            ],
            [
                'title' => 'Classroom Communication',
                'category' => 'Communication',
                'description' => 'Focused evaluation of communication, explanation and accessibility of instructions.',
                'questions' => [
                    $agreement('The instructor speaks clearly and at a comfortable volume.', 'Communication'),
                    $agreement('Instructions for activities and requirements are easy to follow.', 'Communication'),
                    $agreement('The instructor checks whether students understand before moving on.', 'Teaching Clarity'),
                    $agreement('The instructor responds to questions in a helpful way.', 'Instructor Responsiveness'),
                    $agreement('The instructor uses language and examples appropriate to the class.', 'Communication'),
                    ['text' => 'Which communication practices helped you learn most?', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
            [
                'title' => 'Learning Engagement',
                'category' => 'Classroom Engagement',
                'description' => 'Evaluation of active learning, participation and student motivation.',
                'questions' => [
                    $agreement('The instructor creates opportunities for active participation.', 'Classroom Engagement'),
                    $agreement('Class activities hold my attention and support learning.', 'Classroom Engagement'),
                    $agreement('The instructor encourages discussion and collaboration.', 'Classroom Interaction'),
                    $agreement('The instructor motivates students to do their best.', 'Classroom Engagement'),
                    $agreement('The instructor makes the subject interesting and relevant.', 'Teaching Methodology'),
                    ['text' => 'Which activity helped you engage most with the lesson?', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
            [
                'title' => 'Teaching Methodology',
                'category' => 'Teaching Methodology',
                'description' => 'Evaluation of instructional strategies, structure and lesson design.',
                'questions' => [
                    $agreement('The lesson is organized in a logical sequence.', 'Teaching Methodology'),
                    $agreement('The instructor uses strategies suited to the lesson and learners.', 'Teaching Methodology'),
                    $agreement('The instructor balances explanation, demonstration and practice.', 'Teaching Methodology'),
                    $agreement('Activities are clearly connected to learning outcomes.', 'Teaching Methodology'),
                    $agreement('The instructor adjusts the approach when students need help.', 'Instructor Responsiveness'),
                    ['text' => 'What teaching method worked best for you?', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
            [
                'title' => 'Subject Comprehension',
                'category' => 'Subject Comprehension',
                'description' => 'Evaluation of clarity, pacing and support for subject mastery.',
                'questions' => [
                    $agreement('The instructor explains difficult concepts in an understandable way.', 'Teaching Clarity'),
                    $agreement('The pace allows me to follow the lesson.', 'Lesson Pacing'),
                    $agreement('The instructor provides enough practice to develop understanding.', 'Subject Comprehension'),
                    $agreement('The instructor connects new lessons to prior knowledge.', 'Subject Comprehension'),
                    $agreement('I can demonstrate what I learned from this class.', 'Subject Comprehension'),
                    ['text' => 'Which topics or concepts need more explanation?', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
            [
                'title' => 'Classroom Interaction',
                'category' => 'Classroom Interaction',
                'description' => 'Evaluation of respect, inclusion, responsiveness and classroom climate.',
                'questions' => [
                    $agreement('The instructor treats students with respect.', 'Classroom Interaction'),
                    $agreement('The instructor is approachable when students need help.', 'Instructor Responsiveness'),
                    $agreement('Students feel comfortable asking questions in this class.', 'Classroom Interaction'),
                    $agreement('The instructor listens and responds to student concerns.', 'Instructor Responsiveness'),
                    $agreement('The instructor encourages different viewpoints and inclusive participation.', 'Classroom Interaction'),
                    ['text' => 'Describe how the instructor supports student participation.', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
            [
                'title' => 'Overall Instructor Satisfaction',
                'category' => 'Overall Satisfaction',
                'description' => 'Concise overall satisfaction template suitable for end-of-term evaluation.',
                'questions' => [
                    $agreement('I am satisfied with the quality of instruction in this class.', 'Overall Satisfaction'),
                    $agreement('The instructor supports my learning effectively.', 'Overall Satisfaction'),
                    $agreement('The classroom environment helps me learn.', 'Classroom Interaction'),
                    $agreement('The instructor is responsive to the needs of students.', 'Instructor Responsiveness'),
                    $agreement('I would recommend this instructor to other students.', 'Overall Satisfaction'),
                    ['text' => 'What is your overall message for the instructor?', 'type' => 'paragraph', 'category' => 'General'],
                ],
            ],
        ];
    }

    private function slug(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        return trim($slug, '_') ?: 'option';
    }
}
