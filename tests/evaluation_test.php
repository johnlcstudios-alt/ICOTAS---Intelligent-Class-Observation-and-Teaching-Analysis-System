<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use App\Services\QRService;

/**
 * Builds a valid answer payload from the published questionnaire definition.
 */
$answerBuilder = static function (array $questions): array {
    $answers = [];
    foreach ($questions as $question) {
        $id = (string) $question['id'];
        switch ($question['question_type']) {
            case 'likert5':
            case 'agreement':
                $answers[$id] = '4';
                break;
            case 'numeric':
                $min = $question['min_value'] !== null ? (float) $question['min_value'] : 1.0;
                $max = $question['max_value'] !== null ? (float) $question['max_value'] : 5.0;
                $answers[$id] = (string) round(($min + $max) / 2);
                break;
            case 'single_choice':
                $answers[$id] = (string) ($question['options'][0]['option_value'] ?? '');
                break;
            case 'multiple_choice':
                $answers[$id] = [(string) ($question['options'][0]['option_value'] ?? '')];
                break;
            case 'yes_no':
                $answers[$id] = 'yes';
                break;
            case 'short_answer':
            case 'paragraph':
                $answers[$id] = 'The instructor explains clearly, but the pace is too fast during long discussions.';
                break;
        }
    }
    return $answers;
};

test('response tables contain no direct student identifier', function (): void {
    foreach (['evaluation_responses', 'response_answers'] as $table) {
        $columns = Database::fetchAll(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        $names = array_map(static fn ($row) => strtolower((string) $row['COLUMN_NAME']), $columns);
        assertFalse(in_array('student_id', $names, true), "$table must not contain student_id");
        assertFalse(in_array('user_id', $names, true), "$table must not contain user_id");
    }
});

test('anonymous submission writes one response and separates participation', function () use ($answerBuilder): void {
    $assignment = Database::fetch(
        'SELECT * FROM evaluation_assignments WHERE is_demo = 1 AND status = "active" ORDER BY id DESC LIMIT 1'
    );
    assertTrue($assignment !== null, 'expected a demo assignment');

    $students = (new Evaluation())->eligibleStudents($assignment);
    assertTrue(count($students) > 0, 'expected eligible demo students');
    $student = $students[0];

    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $qr = new QRService();
    $created = $qr->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    assertTrue($created[0], $created[1]);
    $sessionId = (int) $created[2]['id'];
    $session = (new Evaluation())->getSession($sessionId);

    $service = new EvaluationService();
    $form = $service->formData($session);
    assertTrue($form['version'] !== null, 'published questionnaire must load');
    $answers = $answerBuilder($form['version']['questions']);
    assertTrue(count($answers) > 0);

    $before = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [$sessionId], 0);
    $result = $service->submit($session, (int) $student['id'], $answers);
    assertTrue($result[0], $result[1]);
    assertSame('submitted', $result[2]);
    assertTrue(isset($result[4]) && (int) $result[4] > 0);

    $after = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [$sessionId], 0);
    assertSame($before + 1, $after);

    $response = Database::fetch(
        'SELECT * FROM evaluation_responses WHERE evaluation_session_id = ? ORDER BY id DESC LIMIT 1',
        [$sessionId]
    );
    assertSame((int) $form['version']['id'], (int) $response['survey_version_id']);
    assertSame('00', substr((string) $response['submitted_at'], 14, 2), 'submission time must be hour-bucketed');
    assertSame('00', substr((string) $response['submitted_at'], 17, 2), 'submission time must not carry minutes');

    $participation = (new Evaluation())->participation($sessionId, (int) $student['id']);
    assertSame('submitted', $participation['status']);
    $usedTokens = (int) Database::scalar(
        'SELECT COUNT(*) FROM evaluation_eligibility_tokens
         WHERE evaluation_session_id = ? AND student_id = ? AND used_at IS NOT NULL',
        [$sessionId, (int) $student['id']],
        0
    );
    assertSame(1, $usedTokens, 'eligibility token must be redeemed exactly once');
});

test('a second submission from the same account is rejected', function () use ($answerBuilder): void {
    $session = Database::fetch(
        'SELECT es.id FROM evaluation_sessions es
         JOIN evaluation_participation ep ON ep.evaluation_session_id = es.id
         WHERE es.is_demo = 1 AND ep.status = "submitted"
         ORDER BY es.id DESC LIMIT 1'
    );
    assertTrue($session !== null, 'expected a session with a submitted participation row');

    $model = new Evaluation();
    $row = $model->getSession((int) $session['id']);
    $participation = Database::fetch(
        'SELECT * FROM evaluation_participation WHERE evaluation_session_id = ? AND status = "submitted" LIMIT 1',
        [(int) $session['id']]
    );
    $before = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [(int) $session['id']], 0);

    $service = new EvaluationService();
    $form = $service->formData($row);
    $result = $service->submit($row, (int) $participation['student_id'], $answerBuilder($form['version']['questions']));

    assertFalse($result[0]);
    assertSame('already_submitted', $result[2]);
    $after = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [(int) $session['id']], 0);
    assertSame($before, $after, 'duplicate submission must not create a second response');
});

test('written feedback is analyzed without linking it to a student', function () use ($answerBuilder): void {
    $assignment = Database::fetch(
        'SELECT * FROM evaluation_assignments WHERE is_demo = 1 AND status = "active" ORDER BY id LIMIT 1'
    );
    $students = (new Evaluation())->eligibleStudents($assignment);
    $student = $students[1] ?? $students[0];

    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $created = (new QRService())->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    assertTrue($created[0], $created[1]);
    $sessionId = (int) $created[2]['id'];
    $session = (new Evaluation())->getSession($sessionId);

    $service = new EvaluationService();
    $form = $service->formData($session);
    $result = $service->submit($session, (int) $student['id'], $answerBuilder($form['version']['questions']));
    assertTrue($result[0], $result[1]);

    $responseId = (int) $result[4];
    $sentiment = Database::fetch('SELECT * FROM sentiment_results WHERE response_id = ?', [$responseId]);
    assertTrue($sentiment !== null, 'expected stored sentiment analysis');
    assertSame('mixed', $sentiment['sentiment_label']);

    $link = Database::fetch(
        'SELECT COUNT(*) AS c FROM evaluation_responses r
         WHERE r.id = ? AND EXISTS (SELECT 1 FROM evaluation_participation ep WHERE ep.evaluation_session_id = r.evaluation_session_id)',
        [$responseId]
    );
    assertSame(1, (int) $link['c'], 'response and participation remain separable records');
});
