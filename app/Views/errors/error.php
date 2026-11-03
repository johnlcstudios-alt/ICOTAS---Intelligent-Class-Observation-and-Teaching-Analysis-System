<?php
/**
 * Branded, self-contained error page.
 * Intentionally does not depend on the database or application layout so it
 * remains usable during a database outage. No technical details are exposed.
 */
$titles = [
    400 => 'Request not understood',
    401 => 'Sign-in required',
    403 => 'Access denied',
    404 => 'Page not found',
    419 => 'Security check expired',
    429 => 'Too many requests',
    500 => 'Something went wrong',
    503 => 'Temporarily unavailable',
];
$title = $titles[(int) $errorCode] ?? 'Request could not be completed';
$message = $errorMessage ?? 'The request could not be completed. Please try again.';
$reference = $errorReference ?? null;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($errorCode) ?> — <?= e($title) ?> | ICOTAS</title>
    <style>
        :root {
            --ink: #102236;
            --muted: #5d6b7e;
            --line: #dde3ec;
            --gold: #c19a4b;
            --paper: #f4f6f9;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            background:
                radial-gradient(900px 500px at 80% -10%, rgba(193,154,75,.12), transparent 60%),
                linear-gradient(145deg, #f8fafc, var(--paper));
            color: var(--ink);
            font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
        }
        .card {
            width: min(620px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 40px 38px;
            box-shadow: 0 24px 60px rgba(16,34,54,.10);
        }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .crest {
            width: 44px; height: 44px; border-radius: 12px;
            background: var(--ink); color: #f3d9a0;
            display: grid; place-items: center;
            font-family: Georgia, serif; font-size: 19px; letter-spacing: .5px;
        }
        .brand small { display: block; color: var(--muted); letter-spacing: .14em; font-size: 10.5px; margin-top: 2px; }
        .brand strong { font-family: Georgia, serif; font-weight: 600; letter-spacing: .06em; }
        .code {
            font-family: Georgia, serif;
            font-size: 64px;
            line-height: 1;
            color: var(--gold);
            margin: 0 0 10px;
        }
        h1 { font-family: Georgia, serif; font-size: 27px; margin: 0 0 12px; font-weight: 600; }
        p { color: var(--muted); line-height: 1.65; margin: 0 0 22px; font-size: 14.5px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 42px; padding: 0 18px; border-radius: 10px;
            text-decoration: none; font-size: 13.5px; font-weight: 600;
            background: var(--ink); color: #fff;
        }
        .btn.secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .ref { margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
        code { background: #f3f5f8; padding: 2px 6px; border-radius: 5px; }
        @media (max-width: 520px) { .card { padding: 28px 22px; } .code { font-size: 50px; } }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="brand">
            <div class="crest" aria-hidden="true">IC</div>
            <div>
                <strong>ICOTAS</strong>
                <small>ACLC COLLEGE TACLOBAN</small>
            </div>
        </div>
        <div class="code"><?= e($errorCode) ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <div class="actions">
            <a class="btn" href="<?= e(url('/login')) ?>">Return to sign in</a>
            <a class="btn secondary" href="javascript:history.back()">Go back</a>
        </div>
        <?php if ($reference !== null): ?>
            <div class="ref">Reference: <code><?= e($reference) ?></code>. Provide this reference to the system administrator if the problem persists.</div>
        <?php endif; ?>
    </main>
</body>
</html>
