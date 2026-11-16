<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Survey;
use App\Services\SurveyService;

/**
 * Survey builder persistence, ordering, validation and version immutability.
 *
 * Published definitions must never change: an institution comparing two
 * semesters has to be able to prove which instrument produced which numbers.
 */

$GLOBALS['__survey_cleanup'] = [];

/**
 * Remove a survey with every version, question, option and template pointer.
 */
function deleteSurveyFixture(int $surveyId): void
{
    $versionIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        Database::fetchAll('SELECT id FROM survey_versions WHERE survey_id = ?', [$surveyId])
    );
    foreach ($versionIds as $versionId) {
        $questionIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            Database::fetchAll('SELECT id FROM survey_questions WHERE survey_version_id = ?', [$versionId])
        );
        foreach ($questionIds as $questionId) {
            Database::execute('DELETE FROM question_options WHERE question_id = ?', [$questionId]);
        }
        Database::execute('DELETE FROM survey_questions WHERE survey_version_id = ?', [$versionId]);
    }
    Database::execute('DELETE FROM survey_templates WHERE survey_id = ?', [$surveyId]);
    Database::execute('DELETE FROM survey_versions WHERE survey_id = ?', [$surveyId]);
    Database::execute('DELETE FROM surveys WHERE id = ?', [$surveyId]);
}

/**
 * @return array{0:int,1:int} survey id and draft version id
 */
function newSurveyFixture(string $token): array
{
    $userId = (int) Database::scalar('SELECT id FROM users WHERE username = "demo.dean"', [], 0);
    assertTrue($userId > 0, 'the seeded Dean account exists');

    $service = new SurveyService();
    [$surveyId, $versionId] = $service->createSurvey('Unit survey ' . $token, 'Created by the automated survey test.', 'Teaching Effectiveness', $userId, true);
    $GLOBALS['__survey_cleanup'][] = $surveyId;
    return [$surveyId, $versionId];
}

function builderPayload(array $questions): array
{
    return [
        'title' => 'Unit survey — teaching effectiveness',
        'description' => 'Instrument used by the automated survey test.',
        'instructions' => 'Rate each statement honestly.',
        'category' => 'Teaching Effectiveness',
        'rating_labels' => [],
        'questions' => $questions,
    ];
}

function likert(string $text, bool $required = true): array
{
    return ['type' => 'likert5', 'text' => $text, 'category' => 'Teaching Clarity', 'required' => $required];
}

test('the survey builder stores every question type in the submitted order', function (): void {
    $service = new SurveyService();
    $surveys = new Survey();
    [$surveyId, $versionId] = newSurveyFixture(bin2hex(random_bytes(3)));

    $questions = [
        likert('The instructor explains lessons clearly.'),
        ['type' => 'single_choice', 'text' => 'Which resource helped most?', 'category' => 'Learning Materials', 'required' => true, 'options' => [['label' => 'Slides'], ['label' => 'Board work']]],
        ['type' => 'paragraph', 'text' => 'What should the instructor keep doing?', 'category' => 'General', 'required' => false],
        ['type' => 'yes_no', 'text' => 'Were the instructions clear?', 'category' => 'Communication', 'required' => true],
        ['type' => 'numeric', 'text' => 'Rate the pacing from 1 to 10.', 'category' => 'Lesson Pacing', 'required' => true, 'min' => 1, 'max' => 10],
    ];

    [$ok, $message] = $service->saveBuilder($surveyId, $versionId, builderPayload($questions), 1);
    assertTrue($ok, 'draft saves: ' . $message);

    $stored = $surveys->questions($versionId);
    assertSame(5, count($stored), 'every question is stored');
    $expectedTypes = ['likert5', 'single_choice', 'paragraph', 'yes_no', 'numeric'];
    foreach ($stored as $index => $question) {
        assertSame($expectedTypes[$index], (string) $question['question_type'], 'question type preserved at position ' . ($index + 1));
        assertSame($index + 1, (int) $question['order_index'], 'order index is one-based and sequential');
    }
    assertSame('The instructor explains lessons clearly.', (string) $stored[0]['question_text'], 'likert text preserved');
    assertSame(1, (int) $stored[0]['is_required'], 'required flag preserved');
    assertSame(0, (int) $stored[2]['is_required'], 'optional flag preserved');
    assertSame(2, count($stored[1]['options']), 'choice options are stored');
    assertSame('slides', (string) $stored[1]['options'][0]['option_value'], 'option values are slugged from the label');
    assertNear(10.0, (float) $stored[4]['max_value'], 0.001, 'numeric maximum preserved');

    $labels = $service->ratingLabels($surveys->version($versionId));
    assertSame('Strongly Disagree', $labels[1], 'default rating labels fall back when none are supplied');
    assertSame('Strongly Agree', $labels[5], 'the five-point agreement scale is complete');

    // Reorder: the builder must persist the submitted sequence, not the old one.
    $reordered = array_reverse($questions);
    [$okReorder, $messageReorder] = $service->saveBuilder($surveyId, $versionId, builderPayload($reordered), 1);
    assertTrue($okReorder, 'reordered draft saves: ' . $messageReorder);
    $storedAgain = $surveys->questions($versionId);
    assertSame('Rate the pacing from 1 to 10.', (string) $storedAgain[0]['question_text'], 'the first question moves to the end');
    assertSame('The instructor explains lessons clearly.', (string) $storedAgain[4]['question_text'], 'the last question moves to the front');
    assertSame(5, count($storedAgain), 'reordering neither duplicates nor drops questions');
});

test('the survey builder refuses malformed definitions without corrupting the draft', function (): void {
    $service = new SurveyService();
    $surveys = new Survey();
    [$surveyId, $versionId] = newSurveyFixture(bin2hex(random_bytes(3)));

    [$ok] = $service->saveBuilder($surveyId, $versionId, builderPayload([likert('Baseline statement.')]), 1);
    assertTrue($ok, 'a valid baseline draft saves');

    $rejections = [
        'unsupported type' => [['type' => 'hologram', 'text' => 'Not a real question type.']],
        'missing question text' => [['type' => 'likert5', 'text' => '   ']],
        'empty questionnaire' => [],
        'choice with one option' => [['type' => 'single_choice', 'text' => 'Pick one.', 'options' => [['label' => 'Only']]]],
        'impossible numeric range' => [['type' => 'numeric', 'text' => 'Rate it.', 'min' => 9, 'max' => 2]],
    ];
    foreach ($rejections as $label => $questions) {
        [$accepted, $message] = $service->saveBuilder($surveyId, $versionId, builderPayload($questions), 1);
        assertFalse($accepted, 'refused: ' . $label);
        assertTrue(trim((string) $message) !== '', 'the refusal explains itself: ' . $label);
    }

    assertSame(1, $surveys->countQuestions($versionId), 'a refused save leaves the stored draft untouched');
    assertSame('Baseline statement.', (string) Database::fetch('SELECT question_text FROM survey_questions WHERE survey_version_id = ?', [$versionId])['question_text'], 'the previous questions survive a rejected save');
});

test('published survey versions are immutable and new versions copy the definition', function (): void {
    $service = new SurveyService();
    $surveys = new Survey();
    [$surveyId, $versionId] = newSurveyFixture(bin2hex(random_bytes(3)));

    [$ok] = $service->saveBuilder($surveyId, $versionId, builderPayload([
        likert('Published statement one.'),
        likert('Published statement two.'),
    ]), 1);
    assertTrue($ok, 'draft saves before publication');

    [$published, $publishMessage] = $service->publish($surveyId, $versionId);
    assertTrue($published, 'draft publishes: ' . $publishMessage);
    assertSame('published', (string) $surveys->version($versionId)['status'], 'the version is published');
    assertSame('active', (string) $surveys->findDetailed($surveyId)['status'], 'the survey becomes active');

    [$editPublished, $editMessage] = $service->saveBuilder($surveyId, $versionId, builderPayload([likert('Attempted overwrite.')]), 1);
    assertFalse($editPublished, 'a published version cannot be edited');
    assertContains('cannot be edited', $editMessage, 'the refusal names the immutability rule');
    assertSame(2, $surveys->countQuestions($versionId), 'published questions are unchanged after a refused edit');

    [$republish, $republishMessage] = $service->publish($surveyId, $versionId);
    assertFalse($republish, 'a published version cannot be published twice');
    assertContains('draft', $republishMessage, 'the republish refusal explains the required state');

    [$createdVersion, , $newVersionId] = $service->newVersion($surveyId, 1);
    assertTrue($createdVersion, 'a new draft version can be created');
    assertSame(2, (int) $surveys->version((int) $newVersionId)['version_number'], 'the new version number increments');
    assertSame(2, $surveys->countQuestions((int) $newVersionId), 'the new draft copies the published questions');
    assertSame('published', (string) $surveys->version($versionId)['status'], 'the published version stays published');

    [$editDraft, $editDraftMessage] = $service->saveBuilder($surveyId, (int) $newVersionId, builderPayload([
        likert('Revised statement one.'),
        likert('Revised statement two.'),
        likert('New statement three.'),
    ]), 1);
    assertTrue($editDraft, 'the new draft is editable: ' . $editDraftMessage);
    assertSame(3, $surveys->countQuestions((int) $newVersionId), 'the draft carries the revised definition');
    assertSame(2, $surveys->countQuestions($versionId), 'editing the draft leaves the published version intact');
    assertSame('Published statement one.', (string) Database::fetch('SELECT question_text FROM survey_questions WHERE survey_version_id = ? ORDER BY order_index LIMIT 1', [$versionId])['question_text'], 'published wording is byte-for-byte unchanged');

    [$publishedCopy, , $copyId] = $service->duplicate($surveyId, 1);
    assertTrue($publishedCopy, 'a survey can be duplicated');
    assertTrue((int) $copyId > 0 && (int) $copyId !== $surveyId, 'the duplicate is a new survey');
    $copyVersion = $surveys->latestVersion((int) $copyId);
    assertTrue($copyVersion !== null, 'the duplicate has a version');
    assertSame(2, $surveys->countQuestions((int) $copyVersion['id']), 'the duplicate copies the published definition, not the draft');
});

test('institutional templates can be cloned into a new editable draft', function (): void {
    // The seven institutional templates are provisioned by DefaultDataService
    // during installation, not by demo seeding, so this test builds its own
    // template fixture and proves the clone path end to end.
    $service = new SurveyService();
    $surveys = new Survey();
    [$templateSurveyId, $templateVersionId] = newSurveyFixture(bin2hex(random_bytes(3)));

    [$saved] = $service->saveBuilder($templateSurveyId, $templateVersionId, builderPayload([
        likert('Template statement one.'),
        likert('Template statement two.'),
    ]), 1);
    assertTrue($saved, 'the template fixture definition saves');
    [$published] = $service->publish($templateSurveyId, $templateVersionId);
    assertTrue($published, 'the template fixture publishes');
    Database::execute('UPDATE surveys SET is_template = 1 WHERE id = ?', [$templateSurveyId]);
    $templateId = Database::insert(
        'INSERT INTO survey_templates (name, description, category, survey_id, is_active, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())',
        ['Unit test template', 'Fixture template for the automated survey test.', 'Teaching Effectiveness', $templateSurveyId]
    );

    $available = $surveys->templates();
    $ids = array_map(static fn (array $row): int => (int) $row['id'], $available);
    assertTrue(in_array($templateId, $ids, true), 'the template fixture is listed as available');

    [$ok, $message, $newSurveyId] = $service->cloneTemplate($templateId, 1, true);
    assertTrue($ok, 'the template clones: ' . $message);
    $GLOBALS['__survey_cleanup'][] = (int) $newSurveyId;

    $version = $surveys->latestVersion((int) $newSurveyId);
    assertTrue($version !== null, 'the cloned survey has a draft version');
    assertSame('draft', (string) $version['status'], 'the clone starts as a draft');
    assertSame(2, $surveys->countQuestions((int) $version['id']), 'the cloned survey carries the template questions');

    $labels = $service->ratingLabels($version);
    assertSame('Strongly Agree', $labels[5], 'the clone inherits the rating labels');

    [$missingOk, $missingMessage] = $service->cloneTemplate($templateId + 100000, 1, true);
    assertFalse($missingOk, 'an unknown template is refused');
    assertContains('not found', $missingMessage, 'the refusal explains the missing template');
});

test('survey fixtures are removed', function (): void {
    foreach (array_reverse($GLOBALS['__survey_cleanup']) as $surveyId) {
        deleteSurveyFixture((int) $surveyId);
        assertTrue(Database::fetch('SELECT id FROM surveys WHERE id = ?', [$surveyId]) === null, 'fixture survey removed');
    }
    $GLOBALS['__survey_cleanup'] = [];
});
