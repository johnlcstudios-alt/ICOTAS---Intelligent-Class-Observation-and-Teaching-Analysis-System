<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\Request;
use App\Core\Setting;

$user = Auth::user();
$role = Auth::role();
$isAdmin = Auth::isAdmin();
$path = Request::path();
$base = $isAdmin ? 'dean' : ($role === 'instructor' ? 'instructor' : 'student');
$demo = Setting::demoMode();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#701c2b">
    <title><?= e($pageTitle ?? 'ICOTAS') ?> · <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="app-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
        <div class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="brand-text">
                <strong><?= e(setting('system_name', 'ICOTAS')) ?></strong>
                <small><?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></small>
            </span>
        </div>

        <nav class="sidebar-nav">
            <?php if ($isAdmin): ?>
                <span class="nav-label">Overview</span>
                <a class="nav-link <?= active_nav('dean/dashboard') ?>" href="<?= url('dean/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= active_nav('dean/analytics') ?>" href="<?= url('dean/analytics') ?>"><i class="bi bi-graph-up-arrow"></i> Teaching Analytics</a>
                <a class="nav-link <?= active_nav('dean/ai-insights') ?>" href="<?= url('dean/ai-insights') ?>"><i class="bi bi-stars"></i> AI-Assisted Insights</a>
                <a class="nav-link <?= active_nav('dean/predictions') ?>" href="<?= url('dean/predictions') ?>"><i class="bi bi-activity"></i> Predictive Analytics</a>
                <a class="nav-link <?= active_nav('dean/recommendations') ?>" href="<?= url('dean/recommendations') ?>"><i class="bi bi-lightbulb"></i> Recommendations</a>

                <span class="nav-label">Evaluation</span>
                <a class="nav-link <?= active_nav('dean/surveys') ?>" href="<?= url('dean/surveys') ?>"><i class="bi bi-ui-checks"></i> Survey Builder</a>
                <a class="nav-link <?= active_nav('dean/assignments') ?>" href="<?= url('dean/assignments') ?>"><i class="bi bi-clipboard-check"></i> Assignments</a>
                <a class="nav-link <?= active_nav('dean/sessions') ?>" href="<?= url('dean/sessions') ?>"><i class="bi bi-qr-code"></i> QR Sessions</a>
                <a class="nav-link <?= active_nav('dean/observations') ?>" href="<?= url('dean/observations') ?>"><i class="bi bi-eye"></i> Class Observations</a>
                <a class="nav-link <?= active_nav('dean/uat') ?>" href="<?= url('dean/uat') ?>"><i class="bi bi-patch-check"></i> UAT</a>

                <span class="nav-label">Academic Structure</span>
                <a class="nav-link <?= active_nav('dean/users') ?>" href="<?= url('dean/users') ?>"><i class="bi bi-people"></i> Users</a>
                <a class="nav-link <?= active_nav('dean/academic/departments') ?>" href="<?= url('dean/academic/departments') ?>"><i class="bi bi-building"></i> Departments</a>
                <a class="nav-link <?= active_nav('dean/academic/programs') ?>" href="<?= url('dean/academic/programs') ?>"><i class="bi bi-diagram-3"></i> Programs</a>
                <a class="nav-link <?= active_nav('dean/academic/academic-years') ?>" href="<?= url('dean/academic/academic-years') ?>"><i class="bi bi-calendar3"></i> Academic Years</a>
                <a class="nav-link <?= active_nav('dean/academic/semesters') ?>" href="<?= url('dean/academic/semesters') ?>"><i class="bi bi-calendar-range"></i> Semesters</a>
                <a class="nav-link <?= active_nav('dean/academic/courses') ?>" href="<?= url('dean/academic/courses') ?>"><i class="bi bi-journal-bookmark"></i> Courses</a>
                <a class="nav-link <?= active_nav('dean/academic/sections') ?>" href="<?= url('dean/academic/sections') ?>"><i class="bi bi-people-fill"></i> Sections</a>
                <a class="nav-link <?= active_nav('dean/academic/schedules') ?>" href="<?= url('dean/academic/schedules') ?>"><i class="bi bi-clock-history"></i> Schedules</a>
                <a class="nav-link <?= active_nav('dean/academic/teaching-assignments') ?>" href="<?= url('dean/academic/teaching-assignments') ?>"><i class="bi bi-person-workspace"></i> Teaching Loads</a>
                <a class="nav-link <?= active_nav('dean/academic/enrollments') ?>" href="<?= url('dean/academic/enrollments') ?>"><i class="bi bi-person-check"></i> Enrollments</a>

                <span class="nav-label">Governance</span>
                <a class="nav-link <?= active_nav('dean/reports') ?>" href="<?= url('dean/reports') ?>"><i class="bi bi-file-earmark-text"></i> Reports</a>
                <a class="nav-link <?= active_nav('dean/audit-logs') ?>" href="<?= url('dean/audit-logs') ?>"><i class="bi bi-shield-check"></i> Audit Logs</a>
                <a class="nav-link <?= active_nav('dean/settings') ?>" href="<?= url('dean/settings') ?>"><i class="bi bi-gear"></i> Settings</a>
                <a class="nav-link <?= active_nav('dean/demo') ?>" href="<?= url('dean/demo') ?>"><i class="bi bi-database-gear"></i> Demo Data</a>
            <?php elseif ($role === 'instructor'): ?>
                <span class="nav-label">My Teaching</span>
                <a class="nav-link <?= active_nav('instructor/dashboard') ?>" href="<?= url('instructor/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= active_nav('instructor/evaluations') ?>" href="<?= url('instructor/evaluations') ?>"><i class="bi bi-clipboard-data"></i> My Evaluations</a>
                <a class="nav-link <?= active_nav('instructor/analytics') ?>" href="<?= url('instructor/analytics') ?>"><i class="bi bi-graph-up"></i> My Analytics</a>
                <a class="nav-link <?= active_nav('instructor/predictions') ?>" href="<?= url('instructor/predictions') ?>"><i class="bi bi-activity"></i> Trend Indicator</a>
                <a class="nav-link <?= active_nav('instructor/recommendations') ?>" href="<?= url('instructor/recommendations') ?>"><i class="bi bi-lightbulb"></i> Recommendations</a>
                <a class="nav-link <?= active_nav('instructor/observations') ?>" href="<?= url('instructor/observations') ?>"><i class="bi bi-eye"></i> Observations</a>
                <a class="nav-link <?= active_nav('instructor/reports') ?>" href="<?= url('instructor/reports') ?>"><i class="bi bi-file-earmark-text"></i> My Reports</a>
            <?php else: ?>
                <span class="nav-label">Student Portal</span>
                <a class="nav-link <?= active_nav('student/dashboard') ?>" href="<?= url('student/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= active_nav('student/scan') ?>" href="<?= url('student/scan') ?>"><i class="bi bi-qr-code-scan"></i> Scan QR</a>
                <a class="nav-link <?= active_nav('student/history') ?>" href="<?= url('student/history') ?>"><i class="bi bi-clock-history"></i> My History</a>
            <?php endif; ?>
            <span class="nav-label">Account</span>
            <a class="nav-link <?= active_nav('notifications') ?>" href="<?= url('notifications') ?>"><i class="bi bi-bell"></i> Notifications</a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="avatar"><?= e(initials($user)) ?></span>
                <span class="user-meta">
                    <strong><?= e(full_name($user)) ?></strong>
                    <small><?= e($user['role_name'] ?? ucfirst($role)) ?></small>
                </span>
            </div>
            <form method="post" action="<?= url('logout') ?>" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-link sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
            <div class="topbar-title">
                <span class="eyebrow"><?= e($demo ? 'Demonstration workspace' : 'Live institutional workspace') ?></span>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="topbar-actions">
                <?php if ($demo): ?><span class="badge badge-demo">Demo data</span><?php endif; ?>
                <a class="btn btn-icon" href="<?= url('notifications') ?>" aria-label="Notifications"><i class="bi bi-bell"></i></a>
                <a class="btn btn-ghost d-none d-md-inline-flex" href="<?= url($base . '/dashboard') ?>"><i class="bi bi-house"></i> Home</a>
            </div>
        </header>

        <main class="app-content" id="main-content">
            <?php App\Core\View::partial('partials.flash'); ?>
            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span><?= e(setting('system_name', 'ICOTAS')) ?> · <?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></span>
            <span>Rule-based analytics · Human judgment required</span>
        </footer>
    </div>
</div>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/chart.umd.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScripts)): foreach ((array) $pageScripts as $script): ?>
<script src="<?= asset('js/' . $script) ?>"></script>
<?php endforeach; endif; ?>
<?= $pageInlineScript ?? '' ?>
</body>
</html>
