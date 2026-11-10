<?php

/**
 * Development-only HTTP smoke test runner.
 * Not part of the deployed application; see documentation/testing.md.
 *
 * Usage:
 *   php tools/smoke.php --base=http://127.0.0.1:8090 --login=demo.dean --password=Demo@12345 \
 *       /dean/dashboard /dean/users ...
 *
 * Without paths, a built-in route list is used for the selected account.
 */

declare(strict_types=1);

$base = 'http://127.0.0.1:8090';
$login = 'demo.dean';
$password = 'Demo@12345';
$paths = [];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    } elseif (str_starts_with($arg, '--login=')) {
        $login = substr($arg, 8);
    } elseif (str_starts_with($arg, '--password=')) {
        $password = substr($arg, 11);
    } else {
        $paths[] = '/' . ltrim($arg, '/');
    }
}

$presets = [
    'demo.dean' => [
        '/dean/dashboard', '/dean/users', '/dean/users/create', '/dean/users/import',
        '/dean/academic/departments', '/dean/academic/programs', '/dean/academic/academic-years',
        '/dean/academic/semesters', '/dean/academic/courses', '/dean/academic/sections',
        '/dean/academic/schedules', '/dean/academic/enrollments', '/dean/academic/teaching-assignments',
        '/dean/surveys', '/dean/surveys/create', '/dean/surveys/templates',
        '/dean/assignments', '/dean/assignments/create',
        '/dean/sessions', '/dean/sessions/create',
        '/dean/analytics', '/dean/ai-insights', '/dean/predictions',
        '/dean/recommendations', '/dean/reports', '/dean/observations',
        '/dean/observations/create', '/dean/uat', '/dean/uat/create',
        '/notifications', '/dean/audit-logs', '/dean/settings', '/dean/demo',
    ],
    'demo.instructor.01' => [
        '/instructor/dashboard', '/instructor/evaluations', '/instructor/analytics',
        '/instructor/predictions', '/instructor/recommendations',
        '/instructor/observations', '/instructor/reports', '/notifications',
    ],
    'demo.student.001' => [
        '/student/dashboard', '/student/scan', '/student/history', '/notifications',
    ],
];
if ($paths === [] && isset($presets[$login])) {
    $paths = $presets[$login];
}
if ($paths === []) {
    fwrite(STDERR, "No paths given and no preset for '$login'.\n");
    exit(2);
}

$jar = tempnam(sys_get_temp_dir(), 'icotas-cookies-');
$errors = 0;

function request(string $url, array $options = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [$status, '', $err, []];
    }
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headers, $m);
    return [$status, $body, '', $m[1] ?? []];
}

function cookieHeader(array $cookies): string
{
    return implode('; ', array_map(static fn($c) => trim($c), $cookies));
}

// 1. Prime cookies + CSRF token.
[$status, $body, $err, $cookies] = request($base . '/login', [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
if ($status !== 200) {
    fwrite(STDERR, "Cannot reach $base/login (status $status) $err\n");
    exit(2);
}
if (!preg_match('/name="_token" value="([^"]+)"/', $body, $m)) {
    fwrite(STDERR, "CSRF token not found on login page.\n");
    exit(2);
}
$token = $m[1];

// 2. Sign in.
[$status, $body, $err, $setCookies] = request($base . '/login', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token' => $token, 'login' => $login, 'password' => $password,
    ]),
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_COOKIEFILE => $jar,
]);
$flash = '';
if (preg_match('/data-flash="([^"]*)"/', $body, $fm)) {
    $flash = html_entity_decode($fm[1], ENT_QUOTES);
}
if ($status !== 302) {
    fwrite(STDERR, "Login for $login failed (status $status).\n");
    $text = trim(strip_tags($body));
    fwrite(STDERR, substr($text, 0, 800) . "\n");
    exit(2);
}
printf("login   %-20s %d -> %s\n", $login, $status, $flash !== '' ? $flash : 'redirected');

// 3. Crawl.
foreach ($paths as $path) {
    [$status, $body, $err, $_] = request($base . $path, [CURLOPT_COOKIEFILE => $jar]);
    $flags = [];
    if ($err !== '') {
        $flags[] = 'CURL:' . $err;
    }
    if ($status === 0) {
        $flags[] = 'NORESPONSE';
    }
    if (preg_match('/Fatal error|Uncaught (?:Error|Exception)|Warning:|Deprecated:/i', $body)) {
        $flags[] = 'PHPERROR';
    }
    if (preg_match('/System Error|Something went wrong/i', $body)) {
        $flags[] = 'ERRORPAGE';
    }
    if ($status >= 500) {
        $flags[] = 'HTTP5XX';
    } elseif ($status >= 400) {
        $flags[] = 'HTTP' . $status;
    }
    $title = preg_match('/<title>(.*?)<\/title>/si', $body, $tm) ? trim(html_entity_decode($tm[1], ENT_QUOTES)) : '';
    if ($flags !== []) {
        $errors++;
    }
    printf(
        "%-7d %-14s %7d  %-42s %s\n",
        $status,
        $flags === [] ? 'ok' : implode(',', $flags),
        strlen($body),
        $path,
        $title
    );

    // Extract the first PHP error line to aid diagnosis.
    if (in_array('PHPERROR', $flags, true) && preg_match('/(?:Fatal error|Uncaught (?:Error|Exception)|Warning|Deprecated):.{0,300}/i', strip_tags($body), $em)) {
        echo '        ' . preg_replace('/\s+/', ' ', $em[0]) . "\n";
    }
}

@unlink($jar);
echo $errors === 0 ? "\nAll routes passed.\n" : "\n$errors route(s) reported problems.\n";
exit($errors === 0 ? 0 : 1);
