<?php
/**
 * ICOTAS route table.
 *
 * All state-changing routes are POST; the router validates CSRF tokens
 * automatically. Role middleware is applied to every workspace route, and
 * controllers repeat the authorization checks server-side.
 */

use App\Controllers\AcademicPeriodController;
use App\Controllers\AccountController;
use App\Controllers\AnalyticsController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\ClassController;
use App\Controllers\ClassRecordController;
use App\Controllers\COFController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\FacultyController;
use App\Controllers\NotificationController;
use App\Controllers\ObservationController;
use App\Controllers\PredictionController;
use App\Controllers\QRController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;
use App\Controllers\UserController;

/** @var App\Core\Router $router */

// ---------------------------------------------------------------------
// Public / authentication
// ---------------------------------------------------------------------
$router->get('/', [DashboardController::class, 'root']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/session', [AuthController::class, 'session']);

$router->get('/setup', [SetupController::class, 'index']);
$router->post('/setup', [SetupController::class, 'store']);

// ---------------------------------------------------------------------
// Account (both roles)
// ---------------------------------------------------------------------
$router->get('/account', [AccountController::class, 'profile'], ['auth']);
$router->post('/account', [AccountController::class, 'updateProfile'], ['auth']);
$router->get('/account/password', [AccountController::class, 'passwordForm'], ['auth']);
$router->post('/account/password', [AccountController::class, 'updatePassword'], ['auth']);

// ---------------------------------------------------------------------
// Notifications (both roles)
// ---------------------------------------------------------------------
$router->get('/notifications', [NotificationController::class, 'index'], ['auth']);
$router->get('/notifications/unread', [NotificationController::class, 'unread'], ['auth']);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], ['auth']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], ['auth']);

// ---------------------------------------------------------------------
// Dashboards
// ---------------------------------------------------------------------
$router->get('/dean/dashboard', [DashboardController::class, 'dean'], ['dean']);
$router->get('/faculty/dashboard', [DashboardController::class, 'faculty'], ['faculty']);

// ---------------------------------------------------------------------
// Dean — faculty accounts
// ---------------------------------------------------------------------
$router->get('/dean/users', [UserController::class, 'index'], ['dean']);
$router->get('/dean/users/create', [UserController::class, 'create'], ['dean']);
$router->post('/dean/users', [UserController::class, 'store'], ['dean']);
$router->get('/dean/users/{id}/edit', [UserController::class, 'edit'], ['dean']);
$router->post('/dean/users/{id}/status', [UserController::class, 'toggleStatus'], ['dean']);
$router->post('/dean/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['dean']);
$router->post('/dean/users/{id}', [UserController::class, 'update'], ['dean']);
$router->get('/dean/users/{id}', [UserController::class, 'show'], ['dean']);

// ---------------------------------------------------------------------
// Dean — departments
// ---------------------------------------------------------------------
$router->get('/dean/departments', [DepartmentController::class, 'index'], ['dean']);
$router->post('/dean/departments', [DepartmentController::class, 'store'], ['dean']);
$router->post('/dean/departments/{id}', [DepartmentController::class, 'update'], ['dean']);

// ---------------------------------------------------------------------
// Dean — academic periods
// ---------------------------------------------------------------------
$router->get('/dean/periods', [AcademicPeriodController::class, 'index'], ['dean']);
$router->post('/dean/periods', [AcademicPeriodController::class, 'store'], ['dean']);
$router->post('/dean/periods/{id}/current', [AcademicPeriodController::class, 'setCurrent'], ['dean']);
$router->post('/dean/periods/{id}/close', [AcademicPeriodController::class, 'close'], ['dean']);
$router->post('/dean/periods/{id}', [AcademicPeriodController::class, 'update'], ['dean']);

// ---------------------------------------------------------------------
// Dean — classes
// ---------------------------------------------------------------------
$router->get('/dean/classes', [ClassController::class, 'index'], ['dean']);
$router->get('/dean/classes/create', [ClassController::class, 'create'], ['dean']);
$router->post('/dean/classes', [ClassController::class, 'store'], ['dean']);
$router->get('/dean/classes/{id}/edit', [ClassController::class, 'edit'], ['dean']);
$router->post('/dean/classes/{id}/archive', [ClassController::class, 'archive'], ['dean']);
$router->post('/dean/classes/{id}', [ClassController::class, 'update'], ['dean']);

// ---------------------------------------------------------------------
// Dean — observations and assignments
// ---------------------------------------------------------------------
$router->get('/dean/observations', [ObservationController::class, 'index'], ['dean']);
$router->get('/dean/observations/create', [ObservationController::class, 'create'], ['dean']);
$router->post('/dean/observations', [ObservationController::class, 'store'], ['dean']);
$router->get('/dean/observations/{id}/edit', [ObservationController::class, 'edit'], ['dean']);
$router->get('/dean/observations/{id}/amend', [ObservationController::class, 'amendForm'], ['dean']);
$router->post('/dean/observations/{id}/draft', [ObservationController::class, 'saveDraft'], ['dean']);
$router->post('/dean/observations/{id}/submit', [ObservationController::class, 'submit'], ['dean']);
$router->post('/dean/observations/{id}/publish', [ObservationController::class, 'publish'], ['dean']);
$router->post('/dean/observations/{id}/return', [ObservationController::class, 'returnForCorrection'], ['dean']);
$router->post('/dean/observations/{id}/archive', [ObservationController::class, 'archive'], ['dean']);
$router->post('/dean/observations/{id}/discard', [ObservationController::class, 'discard'], ['dean']);
$router->post('/dean/observations/{id}/amend', [ObservationController::class, 'amend'], ['dean']);
$router->get('/dean/observations/{id}', [ObservationController::class, 'show'], ['dean']);

$router->get('/dean/assignments', [ObservationController::class, 'assignments'], ['dean']);
$router->post('/dean/assignments', [ObservationController::class, 'storeAssignment'], ['dean']);
$router->post('/dean/assignments/{id}/cancel', [ObservationController::class, 'cancelAssignment'], ['dean']);

// ---------------------------------------------------------------------
// Dean — COF configuration
// ---------------------------------------------------------------------
$router->get('/dean/cof', [COFController::class, 'index'], ['dean']);
$router->post('/dean/cof/criteria/{id}', [COFController::class, 'updateCriterion'], ['dean']);
$router->post('/dean/cof/verify', [COFController::class, 'verify'], ['dean']);

// ---------------------------------------------------------------------
// Dean — class records
// ---------------------------------------------------------------------
$router->get('/dean/class-records', [ClassRecordController::class, 'index'], ['dean']);
$router->get('/dean/class-records/create', [ClassRecordController::class, 'create'], ['dean']);
$router->get('/dean/class-records/export', [ClassRecordController::class, 'export'], ['dean']);
$router->post('/dean/class-records', [ClassRecordController::class, 'store'], ['dean']);
$router->get('/dean/class-records/{id}/edit', [ClassRecordController::class, 'edit'], ['dean']);
$router->post('/dean/class-records/{id}/delete', [ClassRecordController::class, 'destroy'], ['dean']);
$router->post('/dean/class-records/{id}', [ClassRecordController::class, 'update'], ['dean']);
$router->get('/dean/class-records/{id}', [ClassRecordController::class, 'show'], ['dean']);

// ---------------------------------------------------------------------
// Dean — analytics, predictions, reports, settings, audit
// ---------------------------------------------------------------------
$router->get('/dean/analytics', [AnalyticsController::class, 'index'], ['dean']);
$router->get('/dean/analytics/export', [AnalyticsController::class, 'export'], ['dean']);

$router->get('/dean/predictions', [PredictionController::class, 'index'], ['dean']);
$router->post('/dean/predictions/generate', [PredictionController::class, 'generate'], ['dean']);

$router->get('/dean/reports', [ReportController::class, 'index'], ['dean']);
$router->get('/dean/reports/observations/export', [ReportController::class, 'exportObservations'], ['dean']);
$router->get('/dean/reports/observations', [ReportController::class, 'observations'], ['dean']);
$router->get('/dean/reports/observations/{id}', [ReportController::class, 'observation'], ['dean']);
$router->get('/dean/reports/faculty/{id}', [ReportController::class, 'facultyHistory'], ['dean']);
$router->get('/dean/reports/class-records', [ClassRecordController::class, 'printReport'], ['dean']);

$router->get('/dean/settings', [SettingsController::class, 'index'], ['dean']);
$router->post('/dean/settings', [SettingsController::class, 'update'], ['dean']);

$router->get('/dean/audit', [AuditLogController::class, 'index'], ['dean']);
$router->get('/dean/audit/export', [AuditLogController::class, 'export'], ['dean']);

// ---------------------------------------------------------------------
// Dean — QR credentials
// ---------------------------------------------------------------------
$router->get('/dean/qr', [QRController::class, 'index'], ['dean']);
$router->post('/dean/qr/generate', [QRController::class, 'generate'], ['dean']);
$router->get('/dean/qr/preview', [QRController::class, 'preview'], ['dean']);
$router->post('/dean/qr/revoke', [QRController::class, 'revoke'], ['dean']);

// QR landing and scanner endpoint: authentication/ownership are enforced
// inside the controller so JSON clients receive proper status codes.
$router->get('/qr/access', [QRController::class, 'access']);
$router->post('/qr/verify', [QRController::class, 'verify']);
$router->get('/faculty/qr', [QRController::class, 'scanner'], ['faculty']);

// ---------------------------------------------------------------------
// Faculty workspace
// ---------------------------------------------------------------------
$router->get('/faculty/evaluations', [FacultyController::class, 'evaluations'], ['faculty']);
$router->get('/faculty/evaluations/{id}/report', [FacultyController::class, 'report'], ['faculty']);
$router->post('/faculty/evaluations/{id}/comments', [FacultyController::class, 'addComment'], ['faculty']);
$router->post('/faculty/evaluations/{id}/acknowledge', [FacultyController::class, 'acknowledge'], ['faculty']);
$router->get('/faculty/evaluations/{id}', [FacultyController::class, 'show'], ['faculty']);
$router->get('/faculty/trends', [FacultyController::class, 'trends'], ['faculty']);
$router->get('/faculty/profile', [FacultyController::class, 'profile'], ['faculty']);
