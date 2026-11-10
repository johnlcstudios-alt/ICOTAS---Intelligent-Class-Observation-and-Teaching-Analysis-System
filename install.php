<?php
/**
 * ICOTAS browser installer.
 *
 * Creates config/local.php, imports database/schema.sql, creates the first
 * Dean account, seeds the default survey templates and writes a lock file.
 * Delete this file after a successful installation.
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);

$configPath = __DIR__ . '/config/local.php';
$lockPath = __DIR__ . '/storage/installed.lock';
$schemaPath = __DIR__ . '/database/schema.sql';

if (session_status() === PHP_SESSION_NONE) {
    session_name('icotas_installer');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post_value(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function valid_database_name(string $name): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{1,64}$/', $name);
}

function strong_password(string $password): bool
{
    if (strlen($password) < 12) {
        return false;
    }
    $classes = 0;
    $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
    $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
    $classes += preg_match('/[0-9]/', $password) ? 1 : 0;
    $classes += preg_match('/[^A-Za-z0-9]/', $password) ? 1 : 0;
    return $classes >= 3;
}

/**
 * Split a MySQL dump into statements without breaking quoted semicolons.
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= $char;
            }
            continue;
        }
        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($char === '#') {
                $inLineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle && $i > 0 && $sql[$i - 1] === '\\') {
                $buffer .= $char;
                continue;
            }
            $inSingle = !$inSingle;
            $buffer .= $char;
            continue;
        }
        if ($char === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble && $i > 0 && $sql[$i - 1] === '\\') {
                $buffer .= $char;
                continue;
            }
            $inDouble = !$inDouble;
            $buffer .= $char;
            continue;
        }
        if ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

$locked = is_file($lockPath);
$errors = [];
$warnings = [];
$installed = false;
$form = [
    'db_host' => post_value('db_host', '127.0.0.1'),
    'db_port' => post_value('db_port', '3306'),
    'db_name' => post_value('db_name', 'icotas'),
    'db_user' => post_value('db_user', 'root'),
    'db_pass' => post_value('db_pass', ''),
    'site_url' => post_value('site_url', ''),
    'first_name' => post_value('first_name', ''),
    'last_name' => post_value('last_name', ''),
    'username' => post_value('username', ''),
    'email' => post_value('email', ''),
    'institution_name' => post_value('institution_name', 'ACLC College Tacloban City'),
    'system_name' => post_value('system_name', 'ICOTAS'),
];

$requirements = [
    'PHP 8.0 or newer' => ['ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'detail' => 'Detected PHP ' . PHP_VERSION . '. PHP 8.4 is the target runtime.'],
    'PDO MySQL extension' => ['ok' => extension_loaded('pdo') && extension_loaded('pdo_mysql'), 'detail' => 'Required for all database access.'],
    'OpenSSL extension' => ['ok' => extension_loaded('openssl'), 'detail' => 'Required to encrypt re-displayable tokens.'],
    'Mbstring extension' => ['ok' => extension_loaded('mbstring'), 'detail' => 'Required for text handling and CSV import.'],
    'JSON extension' => ['ok' => extension_loaded('json'), 'detail' => 'Required for questionnaire definitions and analytics.'],
    'Session support' => ['ok' => function_exists('session_start'), 'detail' => 'Required for authentication and CSRF protection.'],
    'Schema file readable' => ['ok' => is_readable($schemaPath), 'detail' => 'database/schema.sql'],
    'config/ writable' => ['ok' => is_dir(__DIR__ . '/config') && is_writable(__DIR__ . '/config'), 'detail' => 'The installer writes config/local.php.'],
];

foreach (['storage', 'storage/logs', 'storage/cache', 'storage/reports'] as $directory) {
    $path = __DIR__ . '/' . $directory;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    $requirements[$directory . '/ writable'] = [
        'ok' => is_dir($path) && is_writable($path),
        'detail' => 'Runtime logs and caches are stored here.',
    ];
}
$requirements['Installer lock writable'] = [
    'ok' => is_dir(__DIR__ . '/storage') && is_writable(__DIR__ . '/storage'),
    'detail' => 'Writes storage/installed.lock after setup.',
];

$requirementsOk = true;
foreach ($requirements as $requirement) {
    if (!$requirement['ok']) {
        $requirementsOk = false;
    }
}

if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($_SESSION['installer_csrf']) || !hash_equals((string) $_SESSION['installer_csrf'], $token)) {
        $errors[] = 'Your installer session expired. Refresh the page and submit the form again.';
    }

    $host = $form['db_host'];
    $port = (int) $form['db_port'];
    $database = $form['db_name'];
    $dbUser = $form['db_user'];
    $dbPass = isset($_POST['db_pass']) && is_string($_POST['db_pass']) ? $_POST['db_pass'] : '';
    $firstName = $form['first_name'];
    $lastName = $form['last_name'];
    $username = strtolower($form['username']);
    $email = strtolower($form['email']);
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if ($host === '') {
        $errors[] = 'Database host is required.';
    }
    if ($port < 1 || $port > 65535) {
        $errors[] = 'Database port must be between 1 and 65535.';
    }
    if (!valid_database_name($database)) {
        $errors[] = 'Database name may contain only letters, numbers and underscores.';
    }
    if ($dbUser === '') {
        $errors[] = 'Database username is required.';
    }
    if ($firstName === '' || $lastName === '') {
        $errors[] = 'Administrator first and last name are required.';
    }
    if (!preg_match('/^[a-z0-9._-]{4,60}$/', $username)) {
        $errors[] = 'Administrator username must be 4–60 characters using letters, numbers, dots, underscores or hyphens.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid administrator email address is required.';
    }
    if (!strong_password($password)) {
        $errors[] = 'Administrator password must be at least 12 characters and use three of four character classes (uppercase, lowercase, number, symbol).';
    }
    if ($password !== $confirm) {
        $errors[] = 'The administrator password confirmation does not match.';
    }
    if ($form['site_url'] !== '') {
        $validUrl = filter_var($form['site_url'], FILTER_VALIDATE_URL);
        $scheme = strtolower((string) parse_url($form['site_url'], PHP_URL_SCHEME));
        if (!$validUrl || !in_array($scheme, ['http', 'https'], true)) {
            $errors[] = 'Application URL must be a complete http:// or https:// URL, or left blank for automatic detection.';
        }
    }
    if (!$requirementsOk) {
        $errors[] = 'Resolve the failed server requirements before installing.';
    }

    $pdo = null;
    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            try {
                $pdo->exec('USE `' . $database . '`');
            } catch (PDOException $useError) {
                $code = (int) ($useError->errorInfo[1] ?? 0);
                if ($code === 1049 && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo->exec('USE `' . $database . '`');
                } else {
                    throw new RuntimeException(
                        'The database "' . $database . '" could not be opened. Create it in your hosting control panel first, or check the database name. Server said: ' . $useError->getMessage()
                    );
                }
            }
            $pdo->exec('SET NAMES utf8mb4');
        } catch (Throwable $connectionError) {
            $errors[] = 'Database connection failed: ' . $connectionError->getMessage();
            $pdo = null;
        }
    }

    if (empty($errors) && $pdo instanceof PDO) {
        try {
            $schema = file_get_contents($schemaPath);
            if ($schema === false) {
                throw new RuntimeException('The schema file could not be read.');
            }
            $statements = split_sql($schema);
            if (empty($statements)) {
                throw new RuntimeException('The schema file did not contain executable statements.');
            }
            foreach ($statements as $index => $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $statementError) {
                    throw new RuntimeException('Schema statement ' . ($index + 1) . ' failed: ' . $statementError->getMessage());
                }
            }

            $deanRoleId = $pdo->query('SELECT id FROM roles WHERE slug = "dean" LIMIT 1')->fetchColumn();
            if (!$deanRoleId) {
                throw new RuntimeException('The Dean role was not created by the schema. Import database/schema.sql manually and retry.');
            }

            $existingDean = (int) $pdo->query('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "dean"')->fetchColumn();
            if ($existingDean === 0) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username) = ? OR LOWER(email) = ?');
                $check->execute([$username, $email]);
                if ((int) $check->fetchColumn() > 0) {
                    throw new RuntimeException('That username or email is already used by another account. Choose different administrator credentials.');
                }
                $insert = $pdo->prepare(
                    'INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status, is_demo, password_changed_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, "active", 0, NOW(), NOW(), NOW())'
                );
                $insert->execute([
                    (int) $deanRoleId,
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $firstName,
                    $lastName,
                ]);
            } else {
                $warnings[] = 'An existing Dean account was found, so the installer did not create or replace an administrator. Sign in with the existing account.';
            }

            $settings = [
                'system_name' => $form['system_name'] !== '' ? $form['system_name'] : 'ICOTAS',
                'institution_name' => $form['institution_name'] !== '' ? $form['institution_name'] : 'ACLC College Tacloban City',
            ];
            $settingStatement = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, "general") ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($settings as $key => $value) {
                $settingStatement->execute([$key, $value]);
            }

            $localConfig = [
                'app' => [
                    'key' => bin2hex(random_bytes(32)),
                    'debug' => false,
                    'base_url' => $form['site_url'] !== '' ? rtrim($form['site_url'], '/') : '',
                    'environment' => 'production',
                ],
                'database' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $dbUser,
                    'password' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
            ];
            $configContent = "<?php\n/**\n * Local secrets generated by install.php. Never commit this file.\n */\nreturn " . var_export($localConfig, true) . ";\n";
            $configDirectory = dirname($configPath);
            if (!is_dir($configDirectory) && !@mkdir($configDirectory, 0775, true) && !is_dir($configDirectory)) {
                throw new RuntimeException('The config directory could not be created.');
            }
            $tempConfig = $configPath . '.tmp';
            if (@file_put_contents($tempConfig, $configContent, LOCK_EX) === false) {
                throw new RuntimeException('config/local.php could not be written. Check directory permissions.');
            }
            @chmod($tempConfig, 0640);
            if (!@rename($tempConfig, $configPath)) {
                @unlink($tempConfig);
                throw new RuntimeException('config/local.php could not be finalized. Check directory permissions.');
            }

            require __DIR__ . '/app/bootstrap.php';
            try {
                $seeded = (new App\Services\DefaultDataService())->seed();
                if (!empty($seeded)) {
                    $warnings[] = 'Default definitions created: ' . implode(', ', $seeded) . '.';
                }
            } catch (Throwable $seedError) {
                $warnings[] = 'Database installed, but default survey/UAT definitions could not be seeded automatically. Create definitions in the interface, or run php tools/seed_defaults.php from a shell if available. ' . $seedError->getMessage();
            }

            $lock = [
                'installed_at' => date('c'),
                'php_version' => PHP_VERSION,
                'application' => 'ICOTAS',
                'version' => '2.0.0',
            ];
            if (@file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
                $warnings[] = 'Installation completed, but storage/installed.lock could not be written. Delete install.php immediately.';
            }
            $installed = true;
            $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
        } catch (Throwable $installError) {
            $errors[] = $installError->getMessage();
        }
    }
}

if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['installer_csrf'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ICOTAS Installer</title>
    <style>
        :root { --maroon: #701c2b; --maroon-dark: #4d101c; --gold: #c9a227; --ink: #241a1d; --muted: #667085; --line: #e5e0d8; --ok: #146c43; --bad: #b42318; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; background: #f6f3ee; color: var(--ink); line-height: 1.5; }
        a { color: var(--maroon); }
        .shell { max-width: 1080px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .brand { display: flex; align-items: center; gap: .8rem; margin-bottom: 1.5rem; }
        .brand-mark { width: 46px; height: 46px; border-radius: 12px; background: var(--maroon); color: #fff; display: grid; place-items: center; font-weight: 800; font-family: Georgia, serif; }
        .brand strong { display: block; font-family: Georgia, serif; font-size: 1.25rem; }
        .brand small { color: var(--muted); }
        .grid { display: grid; grid-template-columns: 1.35fr .85fr; gap: 1.25rem; align-items: start; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 8px 24px rgba(50, 35, 30, .06); overflow: hidden; }
        .card-head { padding: 1rem 1.2rem; border-bottom: 1px solid var(--line); background: #fcfaf7; }
        .card-head h2 { font-family: Georgia, serif; font-size: 1.1rem; margin: 0; }
        .card-head p { margin: .25rem 0 0; color: var(--muted); font-size: .82rem; }
        .card-body { padding: 1.2rem; }
        h1 { font-family: Georgia, serif; font-size: 2rem; margin: 0 0 .4rem; }
        .lead { color: var(--muted); margin: 0 0 1.4rem; max-width: 70ch; }
        .steps { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.2rem; }
        .step { font-size: .72rem; border: 1px solid var(--line); border-radius: 999px; padding: .28rem .7rem; color: var(--muted); background: #fff; }
        .step.on { background: var(--maroon); border-color: var(--maroon); color: #fff; }
        .field { margin-bottom: .85rem; }
        label { display: block; font-size: .78rem; font-weight: 600; margin-bottom: .3rem; }
        input { width: 100%; border: 1px solid #cfc7bb; border-radius: 9px; padding: .58rem .7rem; font: inherit; background: #fff; }
        input:focus { outline: 2px solid rgba(112, 28, 43, .22); border-color: var(--maroon); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
        .row-3 { display: grid; grid-template-columns: 1.2fr .35fr 1fr; gap: .8rem; }
        .help { font-size: .72rem; color: var(--muted); margin-top: .25rem; }
        .req { display: flex; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid #f1ede6; font-size: .8rem; }
        .req:last-child { border-bottom: 0; }
        .req .mark { flex: 0 0 auto; width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-size: .68rem; font-weight: 700; color: #fff; }
        .req .mark.ok { background: var(--ok); }
        .req .mark.bad { background: var(--bad); }
        .req strong { display: block; font-weight: 600; }
        .req span { color: var(--muted); font-size: .72rem; }
        .alert { border-radius: 10px; padding: .8rem 1rem; margin-bottom: 1rem; font-size: .82rem; border: 1px solid; }
        .alert-error { background: #fff5f5; border-color: #f3c6c2; color: #7a1d15; }
        .alert-warn { background: #fffbeb; border-color: #f2dfa2; color: #6b4a06; }
        .alert-ok { background: #f0fdf4; border-color: #b7e4c7; color: #14532d; }
        .alert ul { margin: .3rem 0 0; padding-left: 1.1rem; }
        .btn { display: inline-block; border: 0; border-radius: 9px; padding: .7rem 1.1rem; background: var(--maroon); color: #fff; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { background: var(--maroon-dark); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-link { background: transparent; color: var(--maroon); padding-left: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: .8rem; align-items: center; margin-top: 1rem; }
        .fine { font-size: .72rem; color: var(--muted); }
        .section-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .09em; color: var(--muted); margin: 1.2rem 0 .6rem; font-weight: 700; }
        code { font-family: ui-monospace, Consolas, monospace; background: #f4f1eb; padding: .1rem .3rem; border-radius: 4px; font-size: .78em; }
        .manual { font-size: .78rem; color: var(--muted); }
        .manual ol { padding-left: 1.1rem; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { .row, .row-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="shell">
    <div class="brand">
        <span class="brand-mark">IC</span>
        <span><strong>ICOTAS</strong><small>Installer · ACLC College Tacloban City</small></span>
    </div>

    <?php if ($locked): ?>
        <div class="card">
            <div class="card-body">
                <h1>Installer locked</h1>
                <p class="lead">This ICOTAS installation has already been configured. The installer cannot run again while <code>storage/installed.lock</code> exists.</p>
                <div class="alert alert-ok">If you need to reinstall, back up the database and <code>config/local.php</code>, then remove <code>storage/installed.lock</code> and delete <code>config/local.php</code> deliberately.</div>
                <a class="btn" href="index.php">Open ICOTAS</a>
            </div>
        </div>
    <?php elseif ($installed): ?>
        <div class="card">
            <div class="card-body">
                <h1>Installation complete</h1>
                <p class="lead">ICOTAS is configured and the database schema has been imported.</p>
                <?php foreach ($warnings as $warning): ?>
                    <div class="alert alert-warn"><?= h($warning) ?></div>
                <?php endforeach; ?>
                <div class="alert alert-ok">
                    <strong>Next steps</strong>
                    <ul>
                        <li>Sign in with the Dean account you created.</li>
                        <li>Create the academic year, semester, departments, programs, courses and sections.</li>
                        <li>Add instructors and students, then create evaluation assignments and sessions.</li>
                        <li>Delete <code>install.php</code> from the server.</li>
                    </ul>
                </div>
                <a class="btn" href="index.php">Open ICOTAS sign-in</a>
            </div>
        </div>
    <?php else: ?>
        <h1>Set up ICOTAS</h1>
        <p class="lead">This installer writes <code>config/local.php</code>, imports <code>database/schema.sql</code>, creates the first Dean account and seeds the default survey templates. It does not create demo data.</p>
        <div class="steps">
            <span class="step on">1. Server requirements</span>
            <span class="step on">2. Database and administrator</span>
            <span class="step">3. Sign in</span>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Installation could not continue</strong>
                <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php foreach ($warnings as $warning): ?>
            <div class="alert alert-warn"><?= h($warning) ?></div>
        <?php endforeach; ?>

        <div class="grid">
            <div class="card">
                <div class="card-head">
                    <h2>Database and administrator</h2>
                    <p>Use the database credentials from your hosting control panel.</p>
                </div>
                <div class="card-body">
                    <?php if (is_file($configPath)): ?>
                        <div class="alert alert-warn">A <code>config/local.php</code> file already exists. Submitting this form replaces it with the values below. Existing database tables are preserved.</div>
                    <?php endif; ?>

                    <form method="post" action="install.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                        <div class="section-title">Database</div>
                        <div class="row-3">
                            <div class="field">
                                <label for="db_host">Host</label>
                                <input id="db_host" name="db_host" value="<?= h($form['db_host']) ?>" required>
                                <div class="help">Usually <code>localhost</code> or the host given by your provider.</div>
                            </div>
                            <div class="field">
                                <label for="db_port">Port</label>
                                <input id="db_port" name="db_port" type="number" min="1" max="65535" value="<?= h($form['db_port']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_name">Database name</label>
                                <input id="db_name" name="db_name" value="<?= h($form['db_name']) ?>" required>
                                <div class="help">On InfinityFree this looks like <code>if0_12345678_icotas</code>.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label for="db_user">Database username</label>
                                <input id="db_user" name="db_user" value="<?= h($form['db_user']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_pass">Database password</label>
                                <input id="db_pass" name="db_pass" type="password" value="<?= h($form['db_pass']) ?>">
                            </div>
                        </div>

                        <div class="section-title">Institution</div>
                        <div class="row">
                            <div class="field">
                                <label for="institution_name">Institution name</label>
                                <input id="institution_name" name="institution_name" maxlength="150" value="<?= h($form['institution_name']) ?>">
                            </div>
                            <div class="field">
                                <label for="system_name">System name</label>
                                <input id="system_name" name="system_name" maxlength="80" value="<?= h($form['system_name']) ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="site_url">Application URL <span class="help" style="display:inline;font-weight:400">(optional)</span></label>
                            <input id="site_url" name="site_url" type="url" placeholder="https://example.infinityfreeapp.com" value="<?= h($form['site_url']) ?>">
                            <div class="help">Leave blank to detect the URL from each request. Set it explicitly if the site is served under a subdirectory or proxy.</div>
                        </div>

                        <div class="section-title">First Dean account</div>
                        <div class="row">
                            <div class="field">
                                <label for="first_name">First name</label>
                                <input id="first_name" name="first_name" maxlength="80" value="<?= h($form['first_name']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="last_name">Last name</label>
                                <input id="last_name" name="last_name" maxlength="80" value="<?= h($form['last_name']) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label for="username">Username</label>
                                <input id="username" name="username" maxlength="60" value="<?= h($form['username']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" maxlength="190" value="<?= h($form['email']) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label for="password">Password</label>
                                <input id="password" name="password" type="password" minlength="12" required>
                                <div class="help">At least 12 characters with uppercase, lowercase, number and symbol (three of four classes).</div>
                            </div>
                            <div class="field">
                                <label for="password_confirm">Confirm password</label>
                                <input id="password_confirm" name="password_confirm" type="password" minlength="12" required>
                            </div>
                        </div>

                        <div class="actions">
                            <button class="btn" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>Install ICOTAS</button>
                            <span class="fine">Existing tables are preserved. Do not run against a database you cannot restore.</span>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom:1.25rem">
                    <div class="card-head">
                        <h2>Server requirements</h2>
                        <p>Checked against this PHP runtime.</p>
                    </div>
                    <div class="card-body">
                        <?php foreach ($requirements as $label => $requirement): ?>
                            <div class="req">
                                <span class="mark <?= $requirement['ok'] ? 'ok' : 'bad' ?>"><?= $requirement['ok'] ? '✓' : '!' ?></span>
                                <span><strong><?= h($label) ?></strong><span><?= h($requirement['detail']) ?></span></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2>Manual installation</h2>
                        <p>Use this if the browser installer is blocked.</p>
                    </div>
                    <div class="card-body manual">
                        <ol>
                            <li>Create the database in your hosting panel.</li>
                            <li>Import <code>database/schema.sql</code> through phpMyAdmin.</li>
                            <li>Copy <code>config/local.sample.php</code> to <code>config/local.php</code> and fill in the database credentials and a random 64-character application key.</li>
                            <li>Create a Dean account with a password hash generated by PHP <code>password_hash()</code>, then sign in and create survey and UAT definitions in the interface. If you have shell access, <code>php tools/seed_defaults.php</code> seeds the standard templates and the ISO/IEC 25010 form.</li>
                            <li>Delete or block <code>install.php</code>, then sign in.</li>
                        </ol>
                        <p style="margin-bottom:0">Never leave <code>config/local.php</code>, <code>database/schema.sql</code> or <code>storage/</code> publicly readable.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
