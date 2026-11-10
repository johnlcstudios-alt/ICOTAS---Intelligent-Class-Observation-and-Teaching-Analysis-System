<?php
/**
 * ICOTAS route table. Every protected route carries explicit authentication
 * and role middleware; controllers additionally verify record ownership.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AcademicController;
use App\Controllers\AiController;
use App\Controllers\AnalyticsController;
use App\Controllers\AssignmentController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DemoController;
use App\Controllers\EvaluationController;
use App\Controllers\HomeController;
use App\Controllers\InstructorController;
use App\Controllers\NotificationController;
use App\Controllers\ObservationController;
use App\Controllers\PredictionController;
use App\Controllers\RecommendationController;
use App\Controllers\ReportController;
use App\Controllers\SessionController;
use App\Controllers\SettingsController;
use App\Controllers\StudentController;
use App\Controllers\SurveyController;
use App\Controllers\UatController;
use App\Controllers\UserController;

/* ---------------------------------------------------------------------------
 * Public and authentication routes
 * ------------------------------------------------------------------------ */
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

/* Anonymous-by-design UAT invitations (token in URL) */
$router->get('/uat/respond/{token}', [UatController::class, 'respond']);
$router->post('/uat/respond/{token}', [UatController::class, 'submitResponse']);

/* Student evaluation access: authenticated student + per-session eligibility */
$router->get('/evaluate/{token}', [EvaluationController::class, 'show'], ['auth', 'role:student']);
$router->post('/evaluate/{token}', [EvaluationController::class, 'submit'], ['auth', 'role:student']);

/* ---------------------------------------------------------------------------
 * Student portal
 * ------------------------------------------------------------------------ */
$router->get('/student/dashboard', [StudentController::class, 'dashboard'], ['auth', 'role:student']);
$router->get('/student/scan', [StudentController::class, 'scan'], ['auth', 'role:student']);
$router->post('/student/join', [StudentController::class, 'join'], ['auth', 'role:student']);
$router->get('/student/open/{id}', [StudentController::class, 'open'], ['auth', 'role:student']);
$router->get('/student/history', [StudentController::class, 'history'], ['auth', 'role:student']);

/* ---------------------------------------------------------------------------
 * Dean / Academic Head
 * ------------------------------------------------------------------------ */
$router->get('/dean/dashboard', [DashboardController::class, 'dean'], ['auth', 'admin']);

$router->get('/dean/users', [UserController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/users/create', [UserController::class, 'create'], ['auth', 'admin']);
$router->get('/dean/users/import', [UserController::class, 'importForm'], ['auth', 'admin']);
$router->get('/dean/users/import-template', [UserController::class, 'importTemplate'], ['auth', 'admin']);
$router->post('/dean/users/import', [UserController::class, 'import'], ['auth', 'admin']);
$router->post('/dean/users', [UserController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'admin']);
$router->post('/dean/users/{id}', [UserController::class, 'update'], ['auth', 'admin']);
$router->post('/dean/users/{id}/status', [UserController::class, 'toggleStatus'], ['auth', 'admin']);
$router->post('/dean/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['auth', 'admin']);

/* Academic structure: generic entity controller */
$router->get('/dean/academic/{entity}', [AcademicController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/academic/{entity}/create', [AcademicController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/academic/{entity}', [AcademicController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/academic/{entity}/{id}/edit', [AcademicController::class, 'edit'], ['auth', 'admin']);
$router->post('/dean/academic/{entity}/{id}', [AcademicController::class, 'update'], ['auth', 'admin']);
$router->post('/dean/academic/{entity}/{id}/delete', [AcademicController::class, 'destroy'], ['auth', 'admin']);

/* Survey builder */
$router->get('/dean/surveys', [SurveyController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/surveys/create', [SurveyController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/surveys', [SurveyController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/surveys/templates', [SurveyController::class, 'templates'], ['auth', 'admin']);
$router->post('/dean/surveys/templates/{id}/clone', [SurveyController::class, 'cloneTemplate'], ['auth', 'admin']);
$router->get('/dean/surveys/{id}/builder', [SurveyController::class, 'builder'], ['auth', 'admin']);
$router->post('/dean/surveys/{id}/builder', [SurveyController::class, 'saveBuilder'], ['auth', 'admin']);
$router->post('/dean/surveys/{id}/publish', [SurveyController::class, 'publish'], ['auth', 'admin']);
$router->post('/dean/surveys/{id}/new-version', [SurveyController::class, 'newVersion'], ['auth', 'admin']);
$router->post('/dean/surveys/{id}/duplicate', [SurveyController::class, 'duplicate'], ['auth', 'admin']);
$router->post('/dean/surveys/{id}/archive', [SurveyController::class, 'archive'], ['auth', 'admin']);

/* Evaluation assignments and QR sessions */
$router->get('/dean/assignments', [AssignmentController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/assignments/create', [AssignmentController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/assignments', [AssignmentController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/assignments/{id}/edit', [AssignmentController::class, 'edit'], ['auth', 'admin']);
$router->post('/dean/assignments/{id}', [AssignmentController::class, 'update'], ['auth', 'admin']);
$router->post('/dean/assignments/{id}/delete', [AssignmentController::class, 'destroy'], ['auth', 'admin']);

$router->get('/dean/sessions', [SessionController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/sessions/create', [SessionController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/sessions', [SessionController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/sessions/{id}', [SessionController::class, 'show'], ['auth', 'admin']);
$router->get('/dean/sessions/{id}/poster', [SessionController::class, 'poster'], ['auth', 'admin']);
$router->post('/dean/sessions/{id}/regenerate', [SessionController::class, 'regenerate'], ['auth', 'admin']);
$router->post('/dean/sessions/{id}/revoke', [SessionController::class, 'revoke'], ['auth', 'admin']);
$router->post('/dean/sessions/{id}/close', [SessionController::class, 'close'], ['auth', 'admin']);
$router->post('/dean/sessions/{id}/archive', [SessionController::class, 'archive'], ['auth', 'admin']);

/* Analytics, AI and decision support */
$router->get('/dean/analytics', [AnalyticsController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/ai-insights', [AiController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/predictions', [PredictionController::class, 'index'], ['auth', 'admin']);
$router->post('/dean/predictions/generate', [PredictionController::class, 'generate'], ['auth', 'admin']);
$router->get('/dean/recommendations', [RecommendationController::class, 'index'], ['auth', 'admin']);
$router->post('/dean/recommendations/{id}/status', [RecommendationController::class, 'status'], ['auth', 'admin']);

/* Reports */
$router->get('/dean/reports', [ReportController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/reports/view', [ReportController::class, 'show'], ['auth', 'admin']);
$router->post('/dean/reports/export', [ReportController::class, 'export'], ['auth', 'admin']);

/* UAT management */
$router->get('/dean/uat', [UatController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/uat/create', [UatController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/uat', [UatController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/uat/{id}', [UatController::class, 'show'], ['auth', 'admin']);
$router->post('/dean/uat/{id}/questions', [UatController::class, 'addQuestion'], ['auth', 'admin']);
$router->post('/dean/uat/{id}/questions/{questionId}/delete', [UatController::class, 'deleteQuestion'], ['auth', 'admin']);
$router->post('/dean/uat/{id}/status', [UatController::class, 'status'], ['auth', 'admin']);
$router->post('/dean/uat/{id}/dates', [UatController::class, 'dates'], ['auth', 'admin']);
$router->post('/dean/uat/{id}/respondents', [UatController::class, 'addRespondent'], ['auth', 'admin']);

/* Class observations */
$router->get('/dean/observations', [ObservationController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/observations/create', [ObservationController::class, 'create'], ['auth', 'admin']);
$router->post('/dean/observations', [ObservationController::class, 'store'], ['auth', 'admin']);
$router->get('/dean/observations/{id}/edit', [ObservationController::class, 'edit'], ['auth', 'admin']);
$router->post('/dean/observations/{id}', [ObservationController::class, 'update'], ['auth', 'admin']);
$router->post('/dean/observations/{id}/delete', [ObservationController::class, 'destroy'], ['auth', 'admin']);

/* Governance and configuration */
$router->get('/dean/audit-logs', [AuditLogController::class, 'index'], ['auth', 'admin']);
$router->get('/dean/settings', [SettingsController::class, 'index'], ['auth', 'admin']);
$router->post('/dean/settings', [SettingsController::class, 'update'], ['auth', 'admin']);

$router->get('/dean/demo', [DemoController::class, 'index'], ['auth', 'admin']);
$router->post('/dean/demo/generate', [DemoController::class, 'generate'], ['auth', 'admin']);
$router->post('/dean/demo/reset', [DemoController::class, 'reset'], ['auth', 'admin']);

/* ---------------------------------------------------------------------------
 * Instructor portal (private dashboard: own data only)
 * ------------------------------------------------------------------------ */
$router->get('/instructor/dashboard', [DashboardController::class, 'instructor'], ['auth', 'role:instructor']);
$router->get('/instructor/evaluations', [InstructorController::class, 'evaluations'], ['auth', 'role:instructor']);
$router->get('/instructor/evaluations/{id}', [InstructorController::class, 'evaluation'], ['auth', 'role:instructor']);
$router->get('/instructor/analytics', [InstructorController::class, 'analytics'], ['auth', 'role:instructor']);
$router->get('/instructor/predictions', [InstructorController::class, 'predictions'], ['auth', 'role:instructor']);
$router->post('/instructor/predictions/generate', [InstructorController::class, 'generatePrediction'], ['auth', 'role:instructor']);
$router->get('/instructor/recommendations', [InstructorController::class, 'recommendations'], ['auth', 'role:instructor']);
$router->post('/instructor/recommendations/{id}/status', [InstructorController::class, 'recommendationStatus'], ['auth', 'role:instructor']);
$router->get('/instructor/observations', [ObservationController::class, 'instructorIndex'], ['auth', 'role:instructor']);
$router->get('/instructor/reports', [ReportController::class, 'index'], ['auth', 'role:instructor']);
$router->get('/instructor/reports/view', [ReportController::class, 'show'], ['auth', 'role:instructor']);
$router->post('/instructor/reports/export', [ReportController::class, 'export'], ['auth', 'role:instructor']);

/* ---------------------------------------------------------------------------
 * Shared notifications
 * ------------------------------------------------------------------------ */
$router->get('/notifications', [NotificationController::class, 'index'], ['auth']);
$router->post('/notifications/read-all', [NotificationController::class, 'readAll'], ['auth']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'read'], ['auth']);
