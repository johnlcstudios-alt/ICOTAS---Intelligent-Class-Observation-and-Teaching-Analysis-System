<?php
/**
 * Application shell for the Dean and Faculty workspaces.
 *
 * @var string $content
 * @var array|null $authUser
 * @var string $pageTitle
 * @var string $pageSubtitle
 * @var string $activeNav
 * @var string $bodyClass
 * @var array $notifications
 * @var int $unreadNotifications
 * @var string $cspNonce
 */
$role = (string) ($authUser['role'] ?? '');
$isDean = $role === 'Dean';
$displayName = (string) ($authUser['full_name'] ?? 'Signed-in user');
$workspace = $isDean ? 'Dean workspace' : 'Faculty workspace';

$nav = $isDean ? [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/dean/dashboard', 'icon' => 'dashboard'],
    ['key' => 'users', 'label' => 'Faculty', 'href' => '/dean/users', 'icon' => 'users'],
    ['key' => 'departments', 'label' => 'Departments', 'href' => '/dean/departments', 'icon' => 'building'],
    ['key' => 'periods', 'label' => 'Academic Periods', 'href' => '/dean/periods', 'icon' => 'calendar'],
    ['key' => 'classes', 'label' => 'Classes', 'href' => '/dean/classes', 'icon' => 'book'],
    ['group' => 'Observation'],
    ['key' => 'observations', 'label' => 'Observations', 'href' => '/dean/observations', 'icon' => 'clipboard'],
    ['key' => 'assignments', 'label' => 'Assignments', 'href' => '/dean/assignments', 'icon' => 'checklist'],
    ['key' => 'cof', 'label' => 'COF Configuration', 'href' => '/dean/cof', 'icon' => 'file'],
    ['group' => 'Analysis'],
    ['key' => 'class-records', 'label' => 'Class Records', 'href' => '/dean/class-records', 'icon' => 'graduation'],
    ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/dean/analytics', 'icon' => 'chart'],
    ['key' => 'predictions', 'label' => 'Predictions', 'href' => '/dean/predictions', 'icon' => 'trend'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => '/dean/reports', 'icon' => 'print'],
    ['group' => 'Administration'],
    ['key' => 'qr', 'label' => 'QR Credentials', 'href' => '/dean/qr', 'icon' => 'qr'],
    ['key' => 'audit', 'label' => 'Audit Trail', 'href' => '/dean/audit', 'icon' => 'shield'],
    ['key' => 'settings', 'label' => 'System Settings', 'href' => '/dean/settings', 'icon' => 'settings'],
] : [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/faculty/dashboard', 'icon' => 'dashboard'],
    ['group' => 'My Teaching'],
    ['key' => 'evaluations', 'label' => 'My Evaluations', 'href' => '/faculty/evaluations', 'icon' => 'clipboard'],
    ['key' => 'trends', 'label' => 'Performance Trends', 'href' => '/faculty/trends', 'icon' => 'trend'],
    ['key' => 'qr', 'label' => 'Scan QR Credential', 'href' => '/faculty/qr', 'icon' => 'qr'],
    ['group' => 'Account'],
    ['key' => 'profile', 'label' => 'Faculty Profile', 'href' => '/faculty/profile', 'icon' => 'user'],
]);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> | <?= e(setting('system_name', 'ICOTAS')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('vendor/qrcode.min.js')) ?>" defer></script>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="app-body <?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="app-shell" data-app>
    <aside class="sidebar" id="app-sidebar" aria-label="Main navigation">
        <div class="sidebar__brand">
            <a class="brand" href="<?= e(url(is_dean() ? '/dean/dashboard' : '/faculty/dashboard')) ?>">
                <span class="brand__crest" aria-hidden="true">IC</span>
                <span class="brand__text">
                    <strong><?= e(setting('system_name', 'ICOTAS')) ?></strong>
                    <small><?= e(setting('institution_name', 'ACLC College Tacloban')) ?></small>
                </span>
            </a>
            <button type="button" class="icon-btn sidebar__close" data-sidebar-close aria-label="Close navigation">
                <?= icon('x', 18) ?>
            </button>
        </div>

        <nav class="sidebar__nav" aria-label="<?= e($workspace) ?>">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['group'])): ?>
                    <div class="nav-group"><?= e($item['group']) ?></div>
                <?php else: ?>
                    <a class="nav-link<?= $activeNav === $item['key'] ? ' is-active' : '' ?>"
                       href="<?= e(url($item['href'])) ?>"
                       <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                        <?= icon($item['icon'], 18) ?>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__workspace">
                <span class="eyebrow"><?= e($workspace) ?></span>
                <span class="sidebar__role"><?= e($displayName) ?></span>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" data-sidebar-close hidden></div>

    <div class="app-main">
        <header class="topbar">
            <div class="topbar__left">
                <button type="button" class="icon-btn topbar__menu" data-sidebar-open aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">
                    <?= icon('menu', 20) ?>
                </button>
                <div class="topbar__context">
                    <span class="topbar__kicker"><?= e($workspace) ?></span>
                    <span class="topbar__date"><?= e(date('l, F j, Y')) ?></span>
                </div>
            </div>

            <div class="topbar__actions">
                <div class="dropdown" data-dropdown>
                    <button type="button" class="icon-btn notification-btn" data-dropdown-toggle aria-expanded="false" aria-label="Notifications (<?= (int) $unreadNotifications ?> unread)">
                        <?= icon('bell', 19) ?>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-dot" aria-hidden="true"><?= $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown__panel dropdown__panel--wide" data-dropdown-panel hidden>
                        <div class="dropdown__head">
                            <strong>Notifications</strong>
                            <span class="muted"><?= (int) $unreadNotifications ?> unread</span>
                        </div>
                        <?php if ($notifications === []): ?>
                            <div class="dropdown__empty">No notifications yet.</div>
                        <?php else: ?>
                            <ul class="notification-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="notification-item<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>">
                                        <form method="post" action="<?= e(url('/notifications/' . (int) $notification['notification_id'] . '/read')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="link" value="<?= e($notification['link'] ?? '') ?>">
                                            <button type="submit" class="notification-item__button">
                                                <span class="notification-item__title"><?= e($notification['title']) ?></span>
                                                <span class="notification-item__message"><?= e(str_excerpt($notification['message'], 110)) ?></span>
                                                <span class="notification-item__time"><?= e(fmt_datetime($notification['created_at'])) ?></span>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="dropdown__foot dropdown__foot--split">
                            <a href="<?= e(url('/notifications')) ?>">View all</a>
                            <?php if ($unreadNotifications > 0): ?>
                                <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button">Mark all read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dropdown" data-dropdown>
                    <button type="button" class="user-chip" data-dropdown-toggle aria-expanded="false" aria-label="Account menu for <?= e($displayName) ?>">
                        <span class="avatar" aria-hidden="true"><?= e(initials($displayName)) ?></span>
                        <span class="user-chip__text">
                            <strong><?= e($displayName) ?></strong>
                            <small><?= e($role) ?></small>
                        </span>
                        <?= icon('chevron-down', 15) ?>
                    </button>
                    <div class="dropdown__panel" data-dropdown-panel hidden>
                        <a class="dropdown__item" href="<?= e(url('/account')) ?>"><?= icon('user', 16) ?> My account</a>
                        <a class="dropdown__item" href="<?= e(url('/account/password')) ?>"><?= icon('lock', 16) ?> Change password</a>
                        <a class="dropdown__item" href="<?= e(url('/notifications')) ?>"><?= icon('bell', 16) ?> Notifications</a>
                        <div class="dropdown__divider"></div>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown__item dropdown__item--danger"><?= icon('logout', 16) ?> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="page" id="main-content" tabindex="-1">
            <?php require BASE_PATH . '/app/Views/partials/test-banner.php'; ?>
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

            <div class="page-heading">
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span><?= e(setting('system_full_name', 'Intelligent Classroom Observation and Teaching Analysis System')) ?></span>
            <span><?= e(setting('institution_name', 'ACLC College Tacloban')) ?> · <?= e(date('Y')) ?></span>
        </footer>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
