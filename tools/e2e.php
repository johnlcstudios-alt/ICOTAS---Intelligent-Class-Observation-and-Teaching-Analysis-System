<?php

/**
 * Development-only end-to-end HTTP test.
 *
 * Requires a second PHP development server bound to the isolated test database:
 *   $env:ICOTAS_DB_DATABASE='icotas_test'
 *   C:\xampp\php\php.exe -S 127.0.0.1:8095 tools/dev-router.php
 * Then:
 *   php tests/prepare.php
 *   C:\xampp\php\php.exe tools/e2e.php
 *
 * It exercises real routes, CSRF protection, flash messages, QR lifecycle,
 * academic CRUD, settings, notifications, report downloads and demo reset.
 * Never point this at the institutional database.
 */

declare(strict_types=1);

$base = 'http://127.0.0.1:8095';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

// Two guards keep this harness away from anything but a local development
// server: the target must be loopback on the reserved 8090-8099 development
// range, and the bootstrapped application must point at the test database
// (checked again below). Change the port with --base= if 8095 is taken.
$host = (string) (parse_url($base, PHP_URL_HOST) ?: '');
$port = (int) (parse_url($base, PHP_URL_PORT) ?: 80);
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || $port < 8090 || $port > 8099) {
    fwrite(STDERR, "Refusing to run: expected a loopback development server on port 8090-8099 (got $base).\n");
    exit(2);
}

$developerKey = 'e2e-developer-key-7f3c9a';
putenv('ICOTAS_DEVELOPER_KEY=' . $developerKey);
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

/**
 * Clear a rate-limit bucket for every identifier.
 *
 * The harness runs in a different process from the web server, so the
 * IP-hash-scoped RateLimiter::clear() cannot be relied on here: the CLI
 * request has no client address. Deleting the bucket rows directly keeps the
 * scenarios independent of how many requests earlier scenarios made.
 */
function clearRateLimit(string $bucket): void
{
    Database::execute('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
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
            // Never let a machine-level proxy intercept loopback traffic.
            CURLOPT_NOPROXY => '*',
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
$guest = new Http($base);

echo "Student self-registration gate (demo mode)\n";
$blocked = $guest->get('/signup');
check($blocked['status'] === 302, 'signup redirects while demo mode is active', (string) $blocked['status']);
check(str_contains($blocked['location'], '/login'), 'signup gate redirects to the sign-in page', $blocked['location']);
$gatePage = $guest->get('/login');
check(str_contains($gatePage['body'], 'demonstration mode is active'), 'demo-mode signup block explains why registration is closed');

echo "\nAuthentication and flash messages\n";
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
    'allow_student_signup' => (string) Database::scalar('SELECT setting_value FROM system_settings WHERE setting_key = "allow_student_signup"', [], '1'),
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

echo "\nStudent self-registration (live mode)\n";
$signupClient = new Http($base);
$page = $signupClient->get('/signup');
check($page['status'] === 200, 'signup page opens in live mode', (string) $page['status']);
check(str_contains($page['body'], 'Create your student account'), 'signup page renders the registration form');

$suffix = (string) random_int(100000, 999999);
$username = 'e2e.signup.' . $suffix;
$email = $username . '@example.test';
$studentNumber = 'SIGNUP-' . $suffix;
$password = 'E2eStudent123';
$newStudent = [
    'first_name' => 'End-to-End',
    'middle_name' => '',
    'last_name' => 'Registrant',
    'suffix' => '',
    'username' => $username,
    'email' => $email,
    'student_number' => $studentNumber,
    'password' => $password,
    'password_confirmation' => $password,
    'accept_terms' => '1',
];

$missing = $signupClient->post('/signup', ['_token' => $signupClient->csrf('/signup')], true);
check(str_contains($missing['body'], 'First name is required'), 'missing required fields are rejected');

$mismatch = $signupClient->post('/signup', array_merge($newStudent, [
    '_token' => $signupClient->csrf('/signup'),
    'password_confirmation' => 'DifferentPass123',
]), true);
check(str_contains($mismatch['body'], 'confirmation does not match'), 'password confirmation mismatch is rejected');

$created = $signupClient->post('/signup', array_merge($newStudent, [
    '_token' => $signupClient->csrf('/signup'),
]), true);
check($created['status'] === 200, 'valid registration follows to the sign-in page', (string) $created['status']);
check(str_contains($created['body'], 'Account created'), 'registration success flash is shown');

$row = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$username]
);
check($row !== null, 'self-registered account exists');
$userId = $row ? (int) $row['id'] : 0;
if ($row) {
    check($row['role'] === 'student', 'self-registered account receives the student role');
    check((int) $row['is_demo'] === 0, 'self-registered account is a live record');
    check(strtolower((string) $row['status']) === 'active', 'self-registered account is active');
    check(password_verify($password, $row['password_hash']), 'self-registered password hash verifies');
}
$audit = Database::fetch(
    'SELECT * FROM audit_logs WHERE action = "user.signup" AND entity_id = ? ORDER BY id DESC LIMIT 1',
    [$userId]
);
check($audit !== null, 'registration writes an audit record');

$duplicateClient = new Http($base);
$duplicate = $duplicateClient->post('/signup', array_merge($newStudent, [
    '_token' => $duplicateClient->csrf('/signup'),
    'email' => 'other.' . $email,
    'student_number' => 'OTHER-' . $suffix,
]), true);
check(str_contains($duplicate['body'], 'already registered'), 'duplicate username is rejected');

$duplicateNumber = $duplicateClient->post('/signup', array_merge($newStudent, [
    '_token' => $duplicateClient->csrf('/signup'),
    'username' => 'other.' . $username,
    'email' => 'other.' . $email,
]), true);
check(str_contains($duplicateNumber['body'], 'student number is already registered'), 'duplicate student number is rejected');

$honeypotClient = new Http($base);
$honeypot = $honeypotClient->post('/signup', array_merge($newStudent, [
    '_token' => $honeypotClient->csrf('/signup'),
    'username' => 'e2e.honeypot.' . $suffix,
    'email' => 'e2e.honeypot.' . $suffix . '@example.test',
    'student_number' => 'HONEY-' . $suffix,
    'website' => 'https://spam.example',
]));
check($honeypot['status'] === 302 && str_contains($honeypot['location'], '/login'), 'honeypot submissions are silently discarded');
check((int) Database::scalar('SELECT COUNT(*) FROM users WHERE username = ?', ['e2e.honeypot.' . $suffix], 0) === 0, 'honeypot creates no account');

$newStudentSession = new Http($base);
$newLogin = $newStudentSession->login($username, $password);
check($newLogin['status'] === 200, 'self-registered student can sign in', (string) $newLogin['status']);
check(str_contains($newLogin['body'], 'My Evaluation Portal'), 'self-registered student reaches the student portal');

$csrfRejected = (new Http($base))->post('/signup', [
    'first_name' => 'No',
    'last_name' => 'Token',
    'username' => 'e2e.nocsrf.' . $suffix,
    'email' => 'e2e.nocsrf.' . $suffix . '@example.test',
    'student_number' => 'NOCSRF-' . $suffix,
    'password' => $password,
    'password_confirmation' => $password,
    'accept_terms' => '1',
]);
check($csrfRejected['status'] === 419, 'registration requires a valid CSRF token', (string) $csrfRejected['status']);

Database::execute('UPDATE system_settings SET setting_value = "0" WHERE setting_key = "allow_student_signup"');
$closedClient = new Http($base);
$closed = $closedClient->get('/signup');
check($closed['status'] === 302, 'disabled signup redirects to sign-in', (string) $closed['status']);
$closedPage = $closedClient->get('/login');
check(str_contains($closedPage['body'], 'currently closed'), 'disabled signup explains that registration is closed');
Database::execute('UPDATE system_settings SET setting_value = "1" WHERE setting_key = "allow_student_signup"');

if ($userId > 0) {
    Database::execute('DELETE FROM audit_logs WHERE action = "user.signup" AND entity_id = ?', [$userId]);
    Database::execute('DELETE FROM users WHERE id = ?', [$userId]);
}
Database::execute('DELETE FROM rate_limits WHERE bucket = "auth.signup"');

echo "\nDeveloper console access and pages\n";
$developerPath = '/developer/' . rawurlencode($developerKey);
$developer = new Http($base);
$hidden = $developer->get('/developer/not-the-configured-key');
check($hidden['status'] === 404, 'developer console hides itself for a wrong key', (string) $hidden['status']);
$console = $developer->get($developerPath);
check($console['status'] === 200, 'developer console opens with the configured key', (string) $console['status']);
check(str_contains($console['body'], 'System command center'), 'developer dashboard renders');
check(str_contains($console['body'], 'Data browser') && str_contains($console['body'], 'SQL console'), 'developer dashboard exposes the management tools');
$guestLogin = (new Http($base))->get('/login');
check(!str_contains($guestLogin['body'], '/developer/'), 'developer console is not linked from the sign-in page');

foreach ([
    '/users' => 'User accounts',
    '/data' => 'Data browser',
    '/settings' => 'System settings',
    '/audit' => 'Audit log',
    '/sql' => 'SQL console',
    '/demo' => 'Demo data',
] as $suffix => $heading) {
    $page = $developer->get($developerPath . $suffix);
    check($page['status'] === 200, 'developer page ' . $suffix . ' opens', (string) $page['status']);
    check(str_contains($page['body'], $heading), 'developer page ' . $suffix . ' renders its heading');
}

$maskedRows = $developer->get($developerPath . '/data/users');
check($maskedRows['status'] === 200, 'developer data browser opens the users table', (string) $maskedRows['status']);
check(str_contains($maskedRows['body'], 'protected'), 'sensitive columns are masked in the data browser');
check(!str_contains($maskedRows['body'], '$2y$'), 'password hashes are never rendered by the data browser');

echo "\nDeveloper console creates a live Dean directly\n";
$suffix2 = (string) random_int(100000, 999999);
$deanUsername = 'e2e.dean.' . $suffix2;
$deanEmail = $deanUsername . '@example.test';
$deanPassword = 'E2eDean123456';
$token = $developer->csrf($developerPath . '/users/create');
$deanCreated = $developer->post($developerPath . '/users', [
    '_token' => $token,
    'first_name' => 'End-to-End',
    'middle_name' => '',
    'last_name' => 'Dean',
    'suffix' => '',
    'username' => $deanUsername,
    'email' => $deanEmail,
    'employee_number' => 'DEAN-' . $suffix2,
    'student_number' => '',
    'phone' => '',
    'role' => 'dean',
    'status' => 'active',
    'password_mode' => 'generate',
], true);
check($deanCreated['status'] === 200, 'developer console account creation returns to the account', (string) $deanCreated['status']);
check(str_contains($deanCreated['body'], 'Temporary password'), 'developer console shows the generated credential once');
$deanTemporary = '';
if (preg_match('/id="generatedPassword"[^>]*value="([^"]+)"/', $deanCreated['body'], $m)) {
    $deanTemporary = html_entity_decode($m[1], ENT_QUOTES);
}
check($deanTemporary !== '', 'generated Dean password can be parsed');
$deanRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$deanUsername]
);
$newDeanId = $deanRow ? (int) $deanRow['id'] : 0;
check($deanRow !== null, 'developer console creates the Dean row');
if ($deanRow) {
    check($deanRow['role'] === 'dean', 'the developer-created account receives the Dean role');
    check((int) $deanRow['is_demo'] === 0, 'the developer-created Dean is a live record');
    check((int) $deanRow['must_change_password'] === 1, 'a generated Dean password requires a change');
    check(password_verify($deanTemporary, $deanRow['password_hash']), 'the generated Dean password hash verifies');
}
$devAudit = Database::fetch(
    'SELECT * FROM audit_logs WHERE action = "developer.user_create" AND entity_id = ? ORDER BY id DESC LIMIT 1',
    [$newDeanId]
);
check($devAudit !== null, 'developer account creation is audited');

$deanSession = new Http($base);
$deanLogin = $deanSession->login($deanUsername, $deanTemporary);
check(str_contains($deanLogin['url'], '/account/password'), 'the new Dean must replace the temporary password');
$token = $deanSession->csrf('/account/password');
$deanChanged = $deanSession->post('/account/password', [
    '_token' => $token,
    'current_password' => $deanTemporary,
    'password' => $deanPassword,
    'password_confirmation' => $deanPassword,
], true);
check(str_contains($deanChanged['body'], 'Password updated'), 'the new Dean chooses a permanent password');
check(str_contains($deanChanged['url'], '/dean/dashboard'), 'the new Dean reaches the administrative dashboard');

$token = $developer->csrf($developerPath . '/users/' . $newDeanId . '/edit');
$deanUpdated = $developer->post($developerPath . '/users/' . $newDeanId, [
    '_token' => $token,
    'first_name' => 'End-to-End',
    'middle_name' => '',
    'last_name' => 'Dean Repaired',
    'suffix' => '',
    'username' => $deanUsername,
    'email' => $deanEmail,
    'employee_number' => 'DEAN-' . $suffix2,
    'student_number' => '',
    'phone' => '',
    'department_id' => '',
    'program_id' => '',
    'section_id' => '',
    'year_level' => '',
    'role' => 'dean',
    'status' => 'active',
], true);
check(str_contains($deanUpdated['body'], 'Account updated'), 'developer console updates an account');
check(
    (string) Database::scalar('SELECT last_name FROM users WHERE id = ?', [$newDeanId], '') === 'Dean Repaired',
    'developer account update persisted'
);

$devInstructorUsername = 'e2e.dev.instructor.' . $suffix2;
$token = $developer->csrf($developerPath . '/users/create');
$devInstructorCreated = $developer->post($developerPath . '/users', [
    '_token' => $token,
    'first_name' => 'Developer',
    'middle_name' => '',
    'last_name' => 'Managed',
    'suffix' => '',
    'username' => $devInstructorUsername,
    'email' => $devInstructorUsername . '@example.test',
    'employee_number' => 'DEV-EMP-' . $suffix2,
    'student_number' => '',
    'phone' => '',
    'role' => 'instructor',
    'status' => 'active',
    'password_mode' => 'generate',
], true);
$devInstructorRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$devInstructorUsername]
);
$devInstructorId = $devInstructorRow ? (int) $devInstructorRow['id'] : 0;
check($devInstructorRow !== null, 'developer console creates a disposable instructor');
check(str_contains($devInstructorCreated['body'], 'Temporary password'), 'the disposable instructor receives a generated password');

$token = $developer->csrf($developerPath . '/users');
$devStatus = $developer->post($developerPath . '/users/' . $devInstructorId . '/status', ['_token' => $token], true);
check(str_contains($devStatus['body'], 'Account marked inactive'), 'developer console deactivates an account');
check((string) Database::scalar('SELECT status FROM users WHERE id = ?', [$devInstructorId], '') === 'inactive', 'developer deactivation persisted');
$token = $developer->csrf($developerPath . '/users');
$devStatus = $developer->post($developerPath . '/users/' . $devInstructorId . '/status', ['_token' => $token], true);
check(str_contains($devStatus['body'], 'Account marked active'), 'developer console reactivates an account');
check((string) Database::scalar('SELECT status FROM users WHERE id = ?', [$devInstructorId], '') === 'active', 'developer reactivation persisted');

$token = $developer->csrf($developerPath . '/users/' . $devInstructorId . '/edit');
$devPassword = $developer->post($developerPath . '/users/' . $devInstructorId . '/password', [
    '_token' => $token,
    'generate' => '1',
], true);
check(str_contains($devPassword['body'], 'Temporary password generated'), 'developer console resets an account password');
check((int) Database::scalar('SELECT must_change_password FROM users WHERE id = ?', [$devInstructorId], 0) === 1, 'the reset password requires a change');

$token = $developer->csrf($developerPath . '/users/' . $devInstructorId . '/edit');
$devDeleted = $developer->post($developerPath . '/users/' . $devInstructorId . '/delete', ['_token' => $token], true);
check(str_contains($devDeleted['body'], 'Account deleted'), 'developer console deletes an account');
check(Database::fetch('SELECT id FROM users WHERE id = ?', [$devInstructorId]) === null, 'the deleted developer account is gone');

echo "\nDeveloper data browser, settings and SQL console\n";
$dataCode = 'DEV' . $suffix2;
$token = $developer->csrf($developerPath . '/data/departments/create');
$rowCreated = $developer->post($developerPath . '/data/departments', [
    '_token' => $token,
    'fields' => [
        'code' => $dataCode,
        'name' => 'Developer Console Department',
        'status' => 'active',
        'is_demo' => '0',
    ],
], true);
check($rowCreated['status'] === 200 && str_contains($rowCreated['body'], 'Row created in departments.'), 'developer data browser creates a row');
$deptRow = Database::fetch('SELECT * FROM departments WHERE code = ?', [$dataCode]);
$deptId = $deptRow ? (int) $deptRow['id'] : 0;
check($deptRow !== null, 'the created row is stored');
$deptSearch = $developer->get($developerPath . '/data/departments?search=' . rawurlencode($dataCode));
check(str_contains($deptSearch['body'], $dataCode), 'developer data browser searches the table');
$token = $developer->csrf($developerPath . '/data/departments/' . $deptId . '/edit');
$rowUpdated = $developer->post($developerPath . '/data/departments/' . $deptId, [
    '_token' => $token,
    'fields' => [
        'name' => 'Developer Console Department Updated',
    ],
], true);
check(str_contains($rowUpdated['body'], 'Row updated in departments.'), 'developer data browser updates a row');
check((string) Database::scalar('SELECT name FROM departments WHERE id = ?', [$deptId], '') === 'Developer Console Department Updated', 'the developer row update persisted');
$token = $developer->csrf($developerPath . '/data/departments');
$rowDeleted = $developer->post($developerPath . '/data/departments/' . $deptId . '/delete', ['_token' => $token], true);
check(str_contains($rowDeleted['body'], 'Row deleted from departments.'), 'developer data browser deletes a row');
check(Database::fetch('SELECT id FROM departments WHERE id = ?', [$deptId]) === null, 'the deleted developer row is gone');

$settingKey = 'e2e_setting_' . $suffix2;
$token = $developer->csrf($developerPath . '/settings');
$settingCreated = $developer->post($developerPath . '/settings/create', [
    '_token' => $token,
    'setting_key' => $settingKey,
    'setting_group' => 'e2e',
    'setting_value' => 'alpha',
], true);
check(str_contains($settingCreated['body'], 'Setting created.'), 'developer settings manager creates a setting');
$settingRow = Database::fetch('SELECT * FROM system_settings WHERE setting_key = ?', [$settingKey]);
check($settingRow !== null, 'the created setting is stored');
if ($settingRow) {
    $token = $developer->csrf($developerPath . '/settings');
    $settingDeleted = $developer->post($developerPath . '/settings/' . (int) $settingRow['id'] . '/delete', ['_token' => $token], true);
    check(str_contains($settingDeleted['body'], 'Setting deleted.'), 'developer settings manager deletes a setting');
    check(Database::fetch('SELECT id FROM system_settings WHERE id = ?', [(int) $settingRow['id']]) === null, 'the deleted setting is gone');
}

$token = $developer->csrf($developerPath . '/sql');
$sqlRead = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'SELECT 1 AS one',
], true);
check($sqlRead['status'] === 200 && str_contains($sqlRead['body'], 'Statement executed'), 'developer SQL console runs a read');
check(str_contains($sqlRead['body'], '>one</th>'), 'the SQL result renders the returned column');

$token = $developer->csrf($developerPath . '/sql');
$sqlMultiple = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'SELECT 1; SELECT 2',
], true);
check(str_contains($sqlMultiple['body'], 'Only one statement can be executed at a time.'), 'the SQL console refuses multiple statements');

$token = $developer->csrf($developerPath . '/sql');
$sqlSet = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'SET @e2e = 1',
], true);
check(str_contains($sqlSet['body'], 'SET statements are not permitted'), 'the SQL console refuses server-level statements');

$token = $developer->csrf($developerPath . '/sql');
$sqlUnbounded = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'UPDATE system_settings SET setting_value = \'x\'',
    'confirm' => '1',
], true);
check(str_contains($sqlUnbounded['body'], 'UPDATE and DELETE must include a WHERE clause'), 'the SQL console refuses an unbounded update');

$token = $developer->csrf($developerPath . '/sql');
$sqlDrop = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'DROP TABLE e2e_missing_table',
    'confirm' => '1',
], true);
check(str_contains($sqlDrop['body'], 'Type CONFIRM to run a destructive schema statement'), 'destructive DDL needs the typed confirmation');

$sqlKey = 'e2e_sql_' . $suffix2;
$token = $developer->csrf($developerPath . '/sql');
$sqlInsert = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (\'' . $sqlKey . '\', \'alpha\', \'e2e\')',
    'confirm' => '1',
], true);
check(str_contains($sqlInsert['body'], 'Statement executed'), 'a confirmed SQL write executes');
check(Database::fetch('SELECT id FROM system_settings WHERE setting_key = ?', [$sqlKey]) !== null, 'the confirmed SQL write persisted');
$token = $developer->csrf($developerPath . '/sql');
$sqlDelete = $developer->post($developerPath . '/sql', [
    '_token' => $token,
    'sql' => 'DELETE FROM system_settings WHERE setting_key = \'' . $sqlKey . '\'',
    'confirm' => '1',
], true);
check(str_contains($sqlDelete['body'], 'Statement executed'), 'a confirmed SQL delete executes');
check(Database::fetch('SELECT id FROM system_settings WHERE setting_key = ?', [$sqlKey]) === null, 'the confirmed SQL delete persisted');

echo "\nInstructor temporary password and forced change\n";
$createPage = $deanSession->get('/dean/users/create');
check($createPage['status'] === 200, 'Dean user form opens', (string) $createPage['status']);
check(str_contains($createPage['body'], 'generated automatically'), 'instructor form explains automatic password generation');

$instructorUsername = 'e2e.instructor.' . $suffix2;
$instructorEmail = $instructorUsername . '@example.test';
$token = $deanSession->csrf('/dean/users/create');
$instructorCreated = $deanSession->post('/dean/users', [
    '_token' => $token,
    'username' => $instructorUsername,
    'email' => $instructorEmail,
    'first_name' => 'End-to-End',
    'middle_name' => '',
    'last_name' => 'Instructor',
    'suffix' => '',
    'employee_number' => 'EMP-' . $suffix2,
    'student_number' => '',
    'department_id' => '',
    'program_id' => '',
    'section_id' => '',
    'year_level' => '',
    'phone' => '',
    'role' => 'instructor',
    'status' => 'active',
], true);
check($instructorCreated['status'] === 200 && str_contains($instructorCreated['body'], 'Temporary instructor password'), 'created instructor shows a one-time password panel');
$temporaryPassword = '';
if (preg_match('/id="generatedPassword"[^>]*value="([^"]+)"/', $instructorCreated['body'], $m)) {
    $temporaryPassword = html_entity_decode($m[1], ENT_QUOTES);
}
check($temporaryPassword !== '', 'temporary instructor password can be parsed');
$instructorRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$instructorUsername]
);
$newInstructorId = $instructorRow ? (int) $instructorRow['id'] : 0;
check($instructorRow !== null, 'instructor account exists');
if ($instructorRow) {
    check($instructorRow['role'] === 'instructor', 'created account receives the instructor role');
    check((int) $instructorRow['must_change_password'] === 1, 'generated password requires a change');
    check(password_verify($temporaryPassword, $instructorRow['password_hash']), 'generated password hash verifies');
}
$editAgain = $deanSession->get('/dean/users/' . $newInstructorId . '/edit');
check(!str_contains($editAgain['body'], 'Temporary instructor password'), 'one-time password panel disappears after the redirect');

$instructorSession = new Http($base);
$forcedLogin = $instructorSession->login($instructorUsername, $temporaryPassword);
check($forcedLogin['status'] === 200, 'instructor with temporary password can sign in', (string) $forcedLogin['status']);
check(str_contains($forcedLogin['body'], 'Choose a new password'), 'temporary password redirects to the password form');
check(str_contains($forcedLogin['url'], '/account/password'), 'forced-change URL is the account password page');
$blocked = $instructorSession->get('/instructor/dashboard');
check($blocked['status'] === 302, 'forced-change account cannot open the dashboard', (string) $blocked['status']);
check(str_contains($blocked['location'], '/account/password'), 'dashboard access redirects back to the password form');

$token = $instructorSession->csrf('/account/password');
$wrongCurrent = $instructorSession->post('/account/password', [
    '_token' => $token,
    'current_password' => 'WrongPass123',
    'password' => 'E2eInstructor456',
    'password_confirmation' => 'E2eInstructor456',
], true);
check(str_contains($wrongCurrent['body'], 'current password is incorrect'), 'wrong current password is rejected');

$newInstructorPassword = 'E2eInstructor456';
$token = $instructorSession->csrf('/account/password');
$changed = $instructorSession->post('/account/password', [
    '_token' => $token,
    'current_password' => $temporaryPassword,
    'password' => $newInstructorPassword,
    'password_confirmation' => $newInstructorPassword,
], true);
check($changed['status'] === 200 && str_contains($changed['body'], 'Password updated'), 'instructor changes the temporary password');
check(str_contains($changed['url'], '/instructor/dashboard'), 'password change redirects to the instructor dashboard');

$instructorAfter = Database::fetch('SELECT * FROM users WHERE id = ?', [$newInstructorId]);
check((int) ($instructorAfter['must_change_password'] ?? 1) === 0, 'forced-change flag is cleared');
check(password_verify($newInstructorPassword, (string) ($instructorAfter['password_hash'] ?? '')), 'new instructor password verifies');

$oldLogin = (new Http($base))->login($instructorUsername, $temporaryPassword);
check(str_contains($oldLogin['body'], 'Invalid credentials'), 'temporary password stops working after the change');
$newLogin = (new Http($base))->login($instructorUsername, $newInstructorPassword);
check($newLogin['status'] === 200 && str_contains($newLogin['body'], 'Welcome back'), 'instructor signs in with the chosen password');

echo "\nAdministrator accounts (Dean-governed, direct provisioning)\n";
$adminPage = $deanSession->get('/dean/administrators');
check($adminPage['status'] === 200, 'Dean opens the administrator accounts module', (string) $adminPage['status']);
check(str_contains($adminPage['body'], 'Administrator accounts'), 'module renders its heading');
check(str_contains($adminPage['body'], 'Create an administrator account'), 'module renders the creation form');
check(!str_contains($adminPage['body'], 'Dean invitation codes'), 'module no longer renders an invitation registry');
check(str_contains($adminPage['body'], 'no invitation codes'), 'module explains direct provisioning');

$headUsername = 'e2e.head.' . $suffix2;
$headPayload = [
    'first_name' => 'End-to-End',
    'middle_name' => '',
    'last_name' => 'Academic Head',
    'suffix' => '',
    'username' => $headUsername,
    'email' => $headUsername . '@example.test',
    'employee_number' => 'HEAD-' . $suffix2,
    'phone' => '',
    'department_id' => '',
    'role' => 'academic_head',
    'status' => 'active',
    'password_mode' => 'generate',
];
$token = $deanSession->csrf('/dean/administrators');
$headCreated = $deanSession->post('/dean/administrators', array_merge($headPayload, ['_token' => $token]), true);
check($headCreated['status'] === 200, 'creating an administrator returns to the module', (string) $headCreated['status']);
check(str_contains($headCreated['body'], 'New Academic Head credentials'), 'one-time administrator credentials are shown once');
$headPassword = '';
if (preg_match('/id="generatedAdminPassword"[^>]*value="([^"]+)"/', $headCreated['body'], $m)) {
    $headPassword = html_entity_decode($m[1], ENT_QUOTES);
}
check($headPassword !== '', 'generated administrator password can be parsed');
$headRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$headUsername]
);
$newHeadId = $headRow ? (int) $headRow['id'] : 0;
check($headRow !== null, 'created administrator account exists');
if ($headRow) {
    check($headRow['role'] === 'academic_head', 'created account receives the requested administrative role');
    check((int) $headRow['is_demo'] === 0, 'a directly created administrator is a live record');
    check((int) $headRow['must_change_password'] === 1, 'a generated administrator password requires a change');
    check(password_verify($headPassword, (string) $headRow['password_hash']), 'generated administrator password hash verifies');
}
$adminReload = $deanSession->get('/dean/administrators');
check(!str_contains($adminReload['body'], 'id="generatedAdminPassword"'), 'one-time administrator credentials disappear after the redirect');
$adminAudit = Database::fetch(
    'SELECT * FROM audit_logs WHERE action = "user.administrator_create" AND entity_id = ? ORDER BY id DESC LIMIT 1',
    [$newHeadId]
);
check($adminAudit !== null, 'direct administrator creation is audited');

$token = $deanSession->csrf('/dean/administrators');
$duplicateAdmin = $deanSession->post('/dean/administrators', array_merge($headPayload, ['_token' => $token]), true);
check(str_contains($duplicateAdmin['body'], 'already registered'), 'a duplicate administrator account is rejected');
check((int) Database::scalar('SELECT COUNT(*) FROM users WHERE username = ?', [$headUsername], 0) === 1, 'the duplicate attempt creates no second row');

$token = $deanSession->csrf('/dean/administrators');
$missingName = $deanSession->post('/dean/administrators', array_merge($headPayload, [
    '_token' => $token,
    'username' => 'e2e.head.invalid.' . $suffix2,
    'email' => 'e2e.head.invalid.' . $suffix2 . '@example.test',
    'first_name' => '',
]), true);
check(str_contains($missingName['body'], 'required') || str_contains($missingName['body'], 'First name'), 'server-side validation refuses an incomplete administrator');
check(Database::fetch('SELECT id FROM users WHERE username = ?', ['e2e.head.invalid.' . $suffix2]) === null, 'an invalid administrator submission creates no account');

$headSession = new Http($base);
$headLogin = $headSession->login($headUsername, $headPassword);
check(str_contains($headLogin['url'], '/account/password'), 'the new administrator must change the temporary password first');
$headPasswordChosen = 'E2eHead123456';
$token = $headSession->csrf('/account/password');
$headChanged = $headSession->post('/account/password', [
    '_token' => $token,
    'current_password' => $headPassword,
    'password' => $headPasswordChosen,
    'password_confirmation' => $headPasswordChosen,
], true);
check(str_contains($headChanged['body'], 'Password updated'), 'the administrator chooses a permanent password');
check($headSession->get('/dean/dashboard')['status'] === 200, 'an Academic Head reaches the administrative dashboard');

echo "\nAcademic Head role ceiling\n";
$headAdminPage = $headSession->get('/dean/administrators');
check($headAdminPage['status'] === 200, 'an Academic Head can open the administrator module', (string) $headAdminPage['status']);
check(str_contains($headAdminPage['body'], 'Academic Head only'), 'the module scopes an Academic Head to its own role');
check(!str_contains($headAdminPage['body'], 'value="dean"'), 'the Dean role is not offered to an Academic Head');
check(!str_contains($headAdminPage['body'], $deanUsername), 'Dean accounts are hidden from the module');

$headManagedUsername = 'e2e.head.created.' . $suffix2;
$token = $headSession->csrf('/dean/administrators');
$headManaged = $headSession->post('/dean/administrators', array_merge($headPayload, [
    '_token' => $token,
    'username' => $headManagedUsername,
    'email' => $headManagedUsername . '@example.test',
    'employee_number' => 'HEAD-CREATED-' . $suffix2,
]), true);
check(str_contains($headManaged['body'], 'New Academic Head credentials'), 'an Academic Head can create an Academic Head directly');
$headManagedRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$headManagedUsername]
);
$headManagedId = $headManagedRow ? (int) $headManagedRow['id'] : 0;
check($headManagedRow !== null && $headManagedRow['role'] === 'academic_head', 'the Academic Head creation holds the Academic Head role');
$headManageHead = $headSession->get('/dean/users/' . $headManagedId . '/edit');
check($headManageHead['status'] === 200, 'an Academic Head can manage another Academic Head account', (string) $headManageHead['status']);

$token = $headSession->csrf('/dean/administrators');
$escalationPost = $headSession->post('/dean/administrators', array_merge($headPayload, [
    '_token' => $token,
    'username' => 'e2e.head.escalate.' . $suffix2,
    'email' => 'e2e.head.escalate.' . $suffix2 . '@example.test',
    'employee_number' => 'HEAD-ESCALATE-' . $suffix2,
    'role' => 'dean',
]));
check($escalationPost['status'] === 403, 'an Academic Head cannot create a Dean through the administrator module', (string) $escalationPost['status']);
check(Database::fetch('SELECT id FROM users WHERE username = ?', ['e2e.head.escalate.' . $suffix2]) === null, 'the blocked Dean escalation creates no account');

$secondDeanUsername = 'e2e.dean.created.' . $suffix2;
$token = $deanSession->csrf('/dean/administrators');
$secondDean = $deanSession->post('/dean/administrators', [
    '_token' => $token,
    'first_name' => 'Second',
    'middle_name' => '',
    'last_name' => 'Dean',
    'suffix' => '',
    'username' => $secondDeanUsername,
    'email' => $secondDeanUsername . '@example.test',
    'employee_number' => 'DEAN-SECOND-' . $suffix2,
    'phone' => '',
    'department_id' => '',
    'role' => 'dean',
    'status' => 'active',
    'password_mode' => 'generate',
], true);
check(str_contains($secondDean['body'], 'New Dean credentials'), 'a Dean can create another Dean directly');
$secondDeanRow = Database::fetch(
    'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?',
    [$secondDeanUsername]
);
$secondDeanId = $secondDeanRow ? (int) $secondDeanRow['id'] : 0;
check($secondDeanRow !== null && $secondDeanRow['role'] === 'dean', 'the second direct Dean holds the Dean role');

$headAdminReload = $headSession->get('/dean/administrators');
check(!str_contains($headAdminReload['body'], $deanUsername) && !str_contains($headAdminReload['body'], $secondDeanUsername), 'all Dean accounts are hidden from an Academic Head module');
$headUserForm = $headSession->get('/dean/users/create');
check($headUserForm['status'] === 200, 'an Academic Head can still open the user form', (string) $headUserForm['status']);
check(!str_contains($headUserForm['body'], 'value="dean"'), 'the Dean role is not offered to an Academic Head');
$token = $headSession->csrf('/dean/users/create');
$forgedDean = $headSession->post('/dean/users', [
    '_token' => $token,
    'first_name' => 'Forged',
    'middle_name' => '',
    'last_name' => 'Dean',
    'suffix' => '',
    'username' => 'e2e.forged.dean.' . $suffix2,
    'email' => 'e2e.forged.dean.' . $suffix2 . '@example.test',
    'employee_number' => 'FORGED-' . $suffix2,
    'student_number' => '',
    'department_id' => '',
    'program_id' => '',
    'section_id' => '',
    'year_level' => '',
    'phone' => '',
    'role' => 'dean',
    'status' => 'active',
    'password' => 'Forged123456',
    'password_confirmation' => 'Forged123456',
]);
check($forgedDean['status'] === 403, 'a forged Dean role in the user form is refused', (string) $forgedDean['status']);
check(Database::fetch('SELECT id FROM users WHERE username = ?', ['e2e.forged.dean.' . $suffix2]) === null, 'the forged Dean role creates no account');
$headDirectory = $headSession->get('/dean/users');
check(!str_contains($headDirectory['body'], $deanUsername) && !str_contains($headDirectory['body'], $secondDeanUsername), 'Dean accounts are hidden from an Academic Head directory');
$headEditDean = $headSession->get('/dean/users/' . $newDeanId . '/edit');
check($headEditDean['status'] === 403, 'an Academic Head cannot edit a Dean account', (string) $headEditDean['status']);
$headStatusDean = $headSession->post('/dean/users/' . $newDeanId . '/status', ['_token' => $headSession->csrf('/dean/users')]);
check($headStatusDean['status'] === 403, 'an Academic Head cannot deactivate a Dean account', (string) $headStatusDean['status']);

$testUserIds = array_values(array_filter([
    $newDeanId,
    $newInstructorId,
    $newHeadId,
    $headManagedId,
    $secondDeanId,
    $devInstructorId,
]));
if ($testUserIds !== []) {
    $userPlaceholders = implode(',', array_fill(0, count($testUserIds), '?'));
    Database::execute(
        'DELETE FROM audit_logs WHERE user_id IN (' . $userPlaceholders . ') OR (entity_type = "user" AND entity_id IN (' . $userPlaceholders . '))',
        array_merge($testUserIds, $testUserIds)
    );
    Database::execute('DELETE FROM users WHERE id IN (' . $userPlaceholders . ')', $testUserIds);
}
Database::execute('DELETE FROM audit_logs WHERE action LIKE "developer.%" AND created_at >= (NOW() - INTERVAL 1 HOUR)');
$testLogins = array_values(array_unique(array_filter([
    $deanUsername,
    $instructorUsername,
    $headUsername,
    $headManagedUsername,
    $secondDeanUsername,
    $devInstructorUsername,
])));
if ($testLogins !== []) {
    $loginPlaceholders = implode(',', array_fill(0, count($testLogins), '?'));
    Database::execute('DELETE FROM login_attempts WHERE username IN (' . $loginPlaceholders . ')', $testLogins);
}
Database::execute('DELETE FROM system_settings WHERE setting_key IN (?, ?)', [$settingKey, $sqlKey]);
Database::execute('DELETE FROM departments WHERE code = ?', [$dataCode]);
Database::execute('DELETE FROM rate_limits WHERE bucket IN ("administrator.create", "developer.user_write", "developer.sql")');


echo "\n" . str_repeat('-', 58) . "\n";
echo "Checks: $checks  Failed: $failures\n";
exit($failures === 0 ? 0 : 1);
