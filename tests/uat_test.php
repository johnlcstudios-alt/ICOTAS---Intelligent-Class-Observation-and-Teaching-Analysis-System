<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\UatService;

test('UAT interpretation follows the approved threshold table exactly', function (): void {
    $service = new UatService();

    assertSame('Very Satisfactory', $service->interpret(5.00));
    assertSame('Very Satisfactory', $service->interpret(4.50));
    assertSame('Satisfactory', $service->interpret(4.49));
    assertSame('Satisfactory', $service->interpret(4.48), 'the reported 4.48 must remain Satisfactory');
    assertSame('Satisfactory', $service->interpret(3.50));
    assertSame('Fair', $service->interpret(3.49));
    assertSame('Fair', $service->interpret(2.50));
    assertSame('Poor', $service->interpret(2.49));
    assertSame('Poor', $service->interpret(1.50));
    assertSame('Very Poor', $service->interpret(1.49));
    assertSame('Very Poor', $service->interpret(1.00));
    assertSame('No data', $service->interpret(null));
});

test('UAT survey creation, invitation and submission are transaction-safe', function (): void {
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new UatService();

    $created = $service->createSurvey([
        'title' => 'Automated UAT Fixture ' . date('His'),
        'description' => 'Synthetic test survey; removed with the test database.',
        'standard_questions' => true,
    ], (int) $dean['id'], true);
    assertTrue($created[0], $created[1]);
    $surveyId = (int) $created[2];

    $questions = (int) Database::scalar('SELECT COUNT(*) FROM uat_questions WHERE uat_survey_id = ?', [$surveyId], 0);
    assertSame(18, $questions, 'the standard ISO/IEC 25010 bank has 18 statements');

    assertTrue($service->setStatus($surveyId, 'active')[0]);
    $invited = $service->addRespondent($surveyId, 'Synthetic UAT Respondent', 'IT Professional', null);
    assertTrue($invited[0], $invited[1]);
    $token = $invited[2]['token'];

    $ratings = [];
    foreach (Database::fetchAll('SELECT id FROM uat_questions WHERE uat_survey_id = ?', [$surveyId]) as $question) {
        $ratings[(string) $question['id']] = '5';
    }

    $submitted = $service->submitByToken($token, $ratings);
    assertTrue($submitted[0], $submitted[1]);

    $status = Database::scalar('SELECT status FROM uat_respondents WHERE uat_survey_id = ? LIMIT 1', [$surveyId]);
    assertSame('submitted', $status);
    $responseCount = (int) Database::scalar('SELECT COUNT(*) FROM uat_responses WHERE uat_survey_id = ?', [$surveyId], 0);
    assertSame(1, $responseCount);

    $again = $service->submitByToken($token, $ratings);
    assertFalse($again[0]);
    assertContains('already been submitted', $again[1]);
    $responseCountAfter = (int) Database::scalar('SELECT COUNT(*) FROM uat_responses WHERE uat_survey_id = ?', [$surveyId], 0);
    assertSame(1, $responseCountAfter, 'duplicate UAT submission must not create another response');

    $results = $service->results($surveyId);
    assertNear(5.0, (float) $results['overall']['mean'], 0.001, 'all-5 ratings produce a 5.00 mean');
    assertSame('Very Satisfactory', $service->interpret((float) $results['overall']['mean']));
    assertSame(1, (int) $results['overall']['respondents']);
});

test('UAT rejects malformed ratings before writing anything', function (): void {
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new UatService();
    $created = $service->createSurvey([
        'title' => 'Invalid Rating Fixture ' . date('His'),
        'standard_questions' => true,
    ], (int) $dean['id'], true);
    $surveyId = (int) $created[2];
    $service->setStatus($surveyId, 'active');
    $invited = $service->addRespondent($surveyId, 'Invalid Rating Respondent', 'Faculty', null);
    $token = $invited[2]['token'];

    $ratings = [];
    foreach (Database::fetchAll('SELECT id FROM uat_questions WHERE uat_survey_id = ?', [$surveyId]) as $index => $question) {
        $ratings[(string) $question['id']] = $index === 0 ? '6' : '4';
    }
    $result = $service->submitByToken($token, $ratings);
    assertFalse($result[0]);
    assertContains('1 to 5', $result[1]);
    assertSame(0, (int) Database::scalar('SELECT COUNT(*) FROM uat_responses WHERE uat_survey_id = ?', [$surveyId], 0));
    assertSame('invited', (string) Database::scalar('SELECT status FROM uat_respondents WHERE uat_survey_id = ?', [$surveyId]));
});
