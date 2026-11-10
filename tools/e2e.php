<?php

/**
 * Development-only end-to-end HTTP test.
 *
 * Requires a second PHP development server bound to the isolated test database:
 *   $env:ICOTAS_DB_DATABASE='icotas_test'
 *   C:\xampp\php\php.exe -S 127.0.0.1:8091 tools/dev-router.php
 * Then:
 *   php tests/prepare.php
 *   C:\xampp\php\php.exe tools/e2e.php
 *
 * It exercises real routes, CSRF protection, flash messages, QR lifecycle,
 * academic CRUD, settings, notifications, report downloads and demo reset.
 * Never point this at the institutional database.
 */

declare(strict_types=1);

$base = 'http://127.0.0.1:8091';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

$port = (int) (parse_url($base, PHP_URL_PORT) ?: 80);
if ($port !== 8091) {
    fwrite(STDERR, "Refusing to run: use the isolated test server on port 8091 (got $base).\n");
    exit(2);
}

putenv('ICOTAS_DB_DATABASE=icotas_test');
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Security;
use App\Services\QRService;

if (config('database.database') !== 'icotas_test') {
    fwrite(STDERR, "Refusing to run: database scope must be icotas_test.\n");
    exit(2);
}

$failures = 0;
$checks = 0;

function check(bool $condition, string $label, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  PASS  $label\n";
        return;
    }
    $failures++;
    echo "  FAIL  $label" . ($detail !== '' ? "  [$detail]" : '') . "\n";
}

final class Http
{
    private string $base;
    private string $jar;

    public function __construct(string $base)
    {
        $this->base = $base;
        $this->jar = tempnam(sys_get_temp_dir(), 'icotas-e2e-');
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $data, bool $follow = false): array
    {
        return $this->request('POST', $path, $data, $follow);
    }

    public function request(string $method, string $path, ?array $data = null, bool $follow = false): array
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data ?? []);
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status' => 0, 'body' => '', 'content_type' => '', 'url' => $effective, 'error' => $error];
        }
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $location = trim($m[1]);
        }
        return [
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'url' => $effective,
            'location' => $location,
            'error' => $error,
        ];
    }

    public function csrf(string $page): string
    {
        $response = $this->get($page);
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $response['body'], $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        if (preg_match('/name="_token" value="([^"]+)"/', $response['body'], $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        return '';
    }

    public function login(string $login, string $password = 'Demo@12345'): array
    {
        $token = $this->csrf('/login');
        return $this->post('/login', [
            '_token' => $token,
            'login' => $login,
            'password' => $password,
        ], true);
    }
}

$dean = new Http($base);
$student = new Http($base);

echo "Authentication and flash messages\n";
$login = $dean->login('demo.dean');
check($login['status'] === 200, 'dean sign-in follows to a page', (string) $login['status']);
check(str_contains($login['body'], 'Welcome back'), 'success flash is rendered after redirect');

echo "\nRole-based access control\n";
$studentLogin = $student->login('demo.student.001');
check($studentLogin['status'] === 200, 'student sign-in succeeds');
$forbidden = $student->get('/dean/dashboard');
check($forbidden['status'] === 403, 'student cannot open the dean dashboard', (string) $forbidden['status']);
$forbidden = $student->get('/instructor/dashboard');
check($forbidden['status'] === 403, 'student cannot open the instructor dashboard', (string) $forbidden['status']);
$allowed = $student->get('/student/dashboard');
check($allowed['status'] === 200, 'student can open the student portal');

echo "\nAcademic records (create, update, delete)\n";
$token = $dean->csrf('/dean/academic/departments');
$code = 'E2E' . random_int(1000, 9999);
$created = $dean->post('/dean/academic/departments', [
    '_token' => $token,
    'code' => $code,
    'name' => 'End-to-End Test Department',
    'status' => 'active',
], true);
check($created['status'] === 200 && str_contains($created['body'], 'Department created'), 'department create flash');
$record = Database::fetch('SELECT * FROM departments WHERE code = ?', [$code]);
check($record !== null, 'department row exists');
check((int) ($record['is_demo'] ?? 0) === 1, 'created record inherits the active demo scope');

if ($record) {
    $token = $dean->csrf('/dean/academic/departments');
    $updated = $dean->post('/dean/academic/departments/' . $record['id'], [
        '_token' => $token,
        'code' => $code,
        'name' => 'End-to-End Department Updated',
        'status' => 'active',
    ], true);
    check($updated['status'] === 200 && str_contains($updated['body'], 'Department updated'), 'department update flash');
    $fresh = Database::fetch('SELECT name FROM departments WHERE id = ?', [$record['id']]);
    check(($fresh['name'] ?? '') === 'End-to-End Department Updated', 'department update persisted');

    $token = $dean->csrf('/dean/academic/departments');
    $deleted = $dean->post('/dean/academic/departments/' . $record['id'] . '/delete', ['_token' => $token], true);
    check($deleted['status'] === 200 && str_contains($deleted['body'], 'Department deleted'), 'department delete flash');
    check(Database::fetch('SELECT id FROM departments WHERE id = ?', [$record['id']]) === null, 'department row removed');
}

echo "\nSystem settings\n";
$token = $dean->csrf('/dean/settings');
$original = (string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "institution_name"', [], '');
$boolValues = [
    'privacy_show_comments' => (string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "privacy_show_comments"', [], '0'),
    'allow_student_self_scan' => (string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "allow_student_self_scan"', [], '0'),
];
$saved = $dean->post('/dean/settings', array_merge([
    '_token' => $token,
    'institution_name' => 'ACLC College Tacloban City (E2E)',
], array_map(static fn ($v) => $v === '1' ? '1' : '0', $boolValues)), true);
check($saved['status'] === 200 && str_contains($saved['body'], 'System settings saved'), 'settings update flash');
check((string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "institution_name"', [], '') === 'ACLC College Tacloban City (E2E)', 'settings update persisted');

$token = $dean->csrf('/dean/settings');
$dean->post('/dean/settings', array_merge([
    '_token' => $token,
    'institution_name' => $original,
], $boolValues), true);
check((string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "institution_name"', [], '') === $original, 'settings restored');

echo "\nStudent QR link, manual code and anonymous submission\n";
$pair = Database::fetch(
    'SELECT es.*, ep.student_id FROM evaluation_sessions es
     JOIN evaluation_participation ep ON ep.evaluation_session_id = es.id
     JOIN users u ON u.id = ep.student_id
     WHERE es.is_demo = 1 AND u.username = "demo.student.001" AND ep.status = "pending"
       AND es.status = "active" AND NOW() BETWEEN es.opens_at AND es.expires_at
     ORDER BY es.id LIMIT 1'
);
check($pair !== null, 'student has an open evaluation with a pending participation row');
if ($pair) {
    $session = (new \App\Models\Evaluation())->getSession((int) $pair['id']);
    $token = (new QRService())->tokenForDisplay($session);
    check($token !== null && $token !== '', 'session token decrypts for the authorized student flow');
    $before = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [(int) $session['id']], 0);

    $csrft = $student->csrf('/student/scan');
    $joined = $student->post('/student/join', [
        '_token' => $csrft,
        'session_code' => $session['session_code'],
    ]);
    check($joined['status'] === 302, 'manual session code redirects', (string) $joined['status']);
    check(str_contains($joined['location'], '/evaluate/'), 'manual code redirects to the evaluation link', $joined['location']);

    $direct = $student->get('evaluate/' . $token);
    check($direct['status'] === 200, 'direct evaluation link opens for the eligible student', (string) $direct['status']);
    check(str_contains($direct['body'], 'name="answers['), 'evaluation form renders answer fields');

    $form = (new \App\Services\EvaluationService())->formData($session);
    $answers = [];
    foreach ($form['version']['questions'] as $question) {
        $qid = (string) $question['id'];
        switch ($question['question_type']) {
            case 'likert5':
            case 'agreement':
                $answers[$qid] = '4';
                break;
            case 'numeric':
                $min = $question['min_value'] !== null ? (float) $question['min_value'] : 1.0;
                $max = $question['max_value'] !== null ? (float) $question['max_value'] : 5.0;
                $answers[$qid] = (string) round(($min + $max) / 2);
                break;
            case 'single_choice':
                $answers[$qid] = (string) ($question['options'][0]['option_value'] ?? '');
                break;
            case 'multiple_choice':
                $answers[$qid] = [(string) ($question['options'][0]['option_value'] ?? '')];
                break;
            case 'yes_no':
                $answers[$qid] = 'yes';
                break;
            default:
                $answers[$qid] = 'The instructor explains clearly, but the pace is too fast during long discussions.';
                break;
        }
    }
    $formToken = '';
    if (preg_match('/name="csrf-token" content="([^"]+)"/', $direct['body'], $m)) {
        $formToken = html_entity_decode($m[1], ENT_QUOTES);
    }
    $submitted = $student->post('evaluate/' . $token, ['_token' => $formToken, 'answers' => $answers], true);
    check($submitted['status'] === 200, 'anonymous submission follows to the student dashboard', (string) $submitted['status']);
    check(str_contains($submitted['body'], 'anonymous evaluation has been submitted'), 'submission success flash shown');
    $after = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [(int) $session['id']], 0);
    check($after === $before + 1, 'exactly one anonymous response stored');
    $participationStatus = (string) Database::scalar(
        'SELECT status FROM evaluation_participation WHERE evaluation_session_id = ? AND student_id = ?',
        [(int) $session['id'], (int) $pair['student_id']],
        ''
    );
    check($participationStatus === 'submitted', 'participation marked submitted separately');

    $duplicate = $student->get('evaluate/' . $token);
    check(str_contains($duplicate['body'], 'already submitted'), 'reopening the link shows the duplicate guard');
    $duplicateCount = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [(int) $session['id']], 0);
    check($duplicateCount === $after, 'duplicate access creates no extra response');
}

echo "\nQR lifecycle through the dean UI\n";
$session = Database::fetch(
    'SELECT * FROM evaluation_sessions WHERE is_demo = 1 AND status NOT IN ("closed","archived") ORDER BY id DESC LIMIT 1'
);
check($session !== null, 'a demo QR session is available');
$sessionId = 0;
if ($session) {
    $sessionId = (int) $session['id'];
    $oldHash = (string) $session['token_hash'];
    $token = $dean->csrf('/dean/sessions/' . $sessionId);
    $regen = $dean->post('/dean/sessions/' . $sessionId . '/regenerate', ['_token' => $token], true);
    check($regen['status'] === 200 && str_contains($regen['body'], 'QR token regenerated'), 'regenerate flash');
    $newHash = (string) Database::scalar('SELECT token_hash FROM evaluation_sessions WHERE id = ?', [$sessionId], '');
    check($newHash !== '' && $newHash !== $oldHash, 'token hash rotated');

    $token = $dean->csrf('/dean/sessions/' . $sessionId);
    $closed = $dean->post('/dean/sessions/' . $sessionId . '/close', ['_token' => $token], true);
    check($closed['status'] === 200 && str_contains($closed['body'], 'Evaluation session closed'), 'close flash');
    check((string) Database::scalar('SELECT status FROM evaluation_sessions WHERE id = ?', [$sessionId], '') === 'closed', 'session closed in database');

    $token = $dean->csrf('/dean/sessions/' . $sessionId);
    $archived = $dean->post('/dean/sessions/' . $sessionId . '/archive', ['_token' => $token], true);
    check($archived['status'] === 200 && str_contains($archived['body'], 'Session archived'), 'archive flash');
    check((string) Database::scalar('SELECT status FROM evaluation_sessions WHERE id = ?', [$sessionId], '') === 'archived', 'session archived in database');

    $token = $dean->csrf('/dean/sessions/' . $sessionId);
    $archivedRevoke = $dean->post('/dean/sessions/' . $sessionId . '/revoke', ['_token' => $token], true);
    check(str_contains($archivedRevoke['body'], 'Archived sessions cannot be revoked'), 'archived session revoke is rejected');
}

$revokeSession = Database::fetch(
    'SELECT * FROM evaluation_sessions
     WHERE is_demo = 1 AND status NOT IN ("closed","archived") AND id <> ?
     ORDER BY id LIMIT 1',
    [$sessionId]
);
check($revokeSession !== null, 'a second open demo session is available for revoke');
if ($revokeSession) {
    $revokeId = (int) $revokeSession['id'];
    $revokeToken = (new QRService())->tokenForDisplay($revokeSession);
    check($revokeToken !== null && $revokeToken !== '', 'revoke target token decrypts before revocation');
    $token = $dean->csrf('/dean/sessions/' . $revokeId);
    $revoked = $dean->post('/dean/sessions/' . $revokeId . '/revoke', ['_token' => $token], true);
    check($revoked['status'] === 200 && str_contains($revoked['body'], 'QR link revoked'), 'revoke flash');
    check((string) Database::scalar('SELECT status FROM evaluation_sessions WHERE id = ?', [$revokeId], '') === 'closed', 'revoked session is closed in database');
    $old = $student->get('evaluate/' . $revokeToken);
    check($old['status'] === 404, 'revoked evaluation link returns 404', (string) $old['status']);
    $newCipher = (string) Database::scalar('SELECT token_cipher FROM evaluation_sessions WHERE id = ?', [$revokeId], '');
    check((new QRService())->tokenForDisplay(['token_cipher' => $newCipher]) !== $revokeToken, 'revoked token cipher was rotated');
}

echo "\nNotifications\n";
$deanId = (int) Database::scalar('SELECT id FROM users WHERE username = "demo.dean"', [], 0);
$token = $dean->csrf('/notifications');
$readAll = $dean->post('/notifications/read-all', ['_token' => $token], true);
check($readAll['status'] === 200 && str_contains($readAll['body'], 'marked as read'), 'read-all flash');
check((int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$deanId], 0) === 0, 'all dean notifications marked read');

echo "\nReport exports\n";
$token = $dean->csrf('/dean/reports');
$csv = $dean->post('/dean/reports/export', ['_token' => $token, 'type' => 'executive', 'format' => 'csv']);
check($csv['status'] === 200, 'CSV export returns 200', (string) $csv['status']);
check(str_contains(strtolower($csv['content_type']), 'csv'), 'CSV export content type', $csv['content_type']);
check(strlen($csv['body']) > 20 && !str_contains($csv['body'], 'Fatal error'), 'CSV export body is populated');

$token = $dean->csrf('/dean/reports');
$pdf = $dean->post('/dean/reports/export', ['_token' => $token, 'type' => 'executive', 'format' => 'pdf']);
check($pdf['status'] === 200, 'PDF export returns 200', (string) $pdf['status']);
check(str_starts_with($pdf['body'], '%PDF'), 'PDF export starts with %PDF');
check(strlen($pdf['body']) > 1000, 'PDF export is non-trivial');

echo "\nDemo data isolation and reset\n";
$token = $dean->csrf('/dean/demo');
Database::execute("INSERT INTO departments (code, name, status, is_demo) VALUES ('LIVE-E2E', 'Live Fixture', 'active', 0)");
$reset = $dean->post('/dean/demo/reset', ['_token' => $token]);
check($reset['status'] === 302, 'demo reset redirects', (string) $reset['status']);
check((int) Database::scalar('SELECT COUNT(*) FROM users WHERE is_demo = 1', [], 0) === 0, 'demo users removed');
check((int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_sessions es ON es.id = r.evaluation_session_id WHERE es.is_demo = 1', [], 0) === 0, 'demo responses removed');
check(Database::fetch('SELECT id FROM departments WHERE code = "LIVE-E2E"') !== null, 'live records survive demo reset');
check((string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "demo_mode"', [], '') === '0', 'demo mode is switched off');
Database::execute('DELETE FROM departments WHERE code = "LIVE-E2E"');

echo "\n" . str_repeat('-', 58) . "\n";
echo "Checks: $checks  Failed: $failures\n";
exit($failures === 0 ? 0 : 1);
