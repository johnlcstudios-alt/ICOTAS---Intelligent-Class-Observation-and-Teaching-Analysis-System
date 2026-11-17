<?php
/** @var string $content */
/** @var string $developerKey */
/** @var string $pageTitle */
use App\Core\Request;
use App\Core\View;

$devBase = 'developer/' . rawurlencode((string) ($developerKey ?? ''));
$devPath = trim(Request::path(), '/');
$devTail = $devPath === $devBase
    ? ''
    : (str_starts_with($devPath, $devBase . '/') ? substr($devPath, strlen($devBase)) : $devPath);
$navActive = static function (string $suffix = '') use ($devTail): string {
    $suffix = trim($suffix, '/');
    if ($suffix === '') {
        return $devTail === '' ? 'active' : '';
    }
    $needle = '/' . $suffix;
    return ($devTail === $needle || str_starts_with($devTail, $needle . '/')) ? 'active' : '';
};
$console = static fn (string $path = ''): string => url($devBase . ($path !== '' ? '/' . ltrim($path, '/') : ''));
$systemName = (string) setting('system_name', 'ICOTAS');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#061b3d">
    <title><?= e($pageTitle ?? 'Developer console') ?> · <?= e($systemName) ?> Developer</title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="app-body developer-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-shell">
    <aside class="app-sidebar developer-sidebar" id="appSidebar" aria-label="Developer console navigation">
        <div class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-terminal-fill"></i></span>
            <span class="brand-text">
                <strong><?= e($systemName) ?></strong>
                <small>Developer console</small>
            </span>
        </div>

        <nav class="sidebar-nav">
            <span class="nav-label">Console</span>
            <a class="nav-link <?= $navActive() ?>" href="<?= $console() ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link <?= $navActive('users') ?>" href="<?= $console('users') ?>"><i class="bi bi-people"></i> User accounts</a>
            <a class="nav-link <?= $navActive('data') ?>" href="<?= $console('data') ?>"><i class="bi bi-table"></i> Data browser</a>

            <span class="nav-label">Maintenance</span>
            <a class="nav-link <?= $navActive('settings') ?>" href="<?= $console('settings') ?>"><i class="bi bi-sliders"></i> System settings</a>
            <a class="nav-link <?= $navActive('sql') ?>" href="<?= $console('sql') ?>"><i class="bi bi-code-square"></i> SQL console</a>
            <a class="nav-link <?= $navActive('demo') ?>" href="<?= $console('demo') ?>"><i class="bi bi-database-gear"></i> Demo data</a>

            <span class="nav-label">Oversight</span>
            <a class="nav-link <?= $navActive('audit') ?>" href="<?= $console('audit') ?>"><i class="bi bi-shield-check"></i> Audit log</a>

            <span class="nav-label">Application</span>
            <a class="nav-link" href="<?= url('') ?>"><i class="bi bi-box-arrow-up-right"></i> Open ICOTAS</a>
        </nav>

        <div class="sidebar-footer">
            <div class="developer-key-state">
                <span class="developer-key-dot" aria-hidden="true"></span>
                <span>
                    <strong>Key-authenticated</strong>
                    <small>No session · no user RBAC</small>
                </span>
            </div>
            <p class="developer-sidebar-note mb-0">This console is unlinked. Treat the URL as a password.</p>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-link sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
            <div class="topbar-title">
                <span class="eyebrow">Unlinked developer console</span>
                <h1><?= e($pageTitle ?? 'Developer dashboard') ?></h1>
            </div>
            <div class="topbar-actions">
                <span class="badge badge-dark d-none d-sm-inline-flex"><i class="bi bi-incognito"></i>&nbsp; Full system access</span>
                <a class="btn btn-ghost d-none d-md-inline-flex" href="<?= $console('users/create') ?>"><i class="bi bi-person-plus"></i> New account</a>
                <a class="btn btn-icon" href="<?= url('') ?>" aria-label="Open ICOTAS application"><i class="bi bi-house"></i></a>
            </div>
        </header>

        <main class="app-content" id="main-content">
            <?php View::partial('partials.flash'); ?>
            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span><?= e($systemName) ?> developer console · every action is audited</span>
            <span><i class="bi bi-shield-lock"></i> Rotate <code>developer_key</code> in <code>config/local.php</code> if this URL is exposed</span>
        </footer>
    </div>
</div>
<style>
.developer-sidebar { background: linear-gradient(180deg, #0b1220 0%, #050a14 100%); }
.developer-sidebar .brand-mark { background: linear-gradient(135deg, #0f766e, #115e59); }
.developer-key-state { display: flex; gap: .6rem; align-items: flex-start; color: #d7e3f5; font-size: .8rem; }
.developer-key-state strong { display: block; color: #fff; font-weight: 600; }
.developer-key-state small { display: block; color: #8fa6cb; font-size: .68rem; }
.developer-key-dot { width: 9px; height: 9px; border-radius: 50%; margin-top: .32rem; background: #34d399; box-shadow: 0 0 0 3px rgba(52,211,153,.16); flex: 0 0 auto; }
.developer-sidebar-note { color: #8fa6cb; font-size: .68rem; line-height: 1.4; margin-top: .65rem; }
.developer-body .card-header code, .developer-body .table code { color: #0b4a6f; }
.developer-mono { font-family: var(--bs-font-monospace); font-size: .78rem; }
.developer-danger { border-left: 3px solid #b42318; }
</style>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScripts)): foreach ((array) $pageScripts as $script): ?>
<script src="<?= asset('js/' . $script) ?>"></script>
<?php endforeach; endif; ?>
<?= $pageInlineScript ?? '' ?>
</body>
</html>
