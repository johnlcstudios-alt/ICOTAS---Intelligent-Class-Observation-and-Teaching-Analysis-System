<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Setting;
use App\Services\DemoService;

final class DemoController extends BaseController
{
    public function index(): void
    {
        $scope = $this->demo() ? 1 : 0;
        $counts = [
            'departments' => (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE is_demo = ?', [$scope], 0),
            'programs' => (int) Database::scalar('SELECT COUNT(*) FROM programs WHERE is_demo = ?', [$scope], 0),
            'courses' => (int) Database::scalar('SELECT COUNT(*) FROM courses WHERE is_demo = ?', [$scope], 0),
            'sections' => (int) Database::scalar('SELECT COUNT(*) FROM sections WHERE is_demo = ?', [$scope], 0),
            'instructors' => (int) Database::scalar('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "instructor" AND u.is_demo = ?', [$scope], 0),
            'students' => (int) Database::scalar('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "student" AND u.is_demo = ?', [$scope], 0),
            'sessions' => (int) Database::scalar('SELECT COUNT(*) FROM evaluation_sessions WHERE is_demo = ?', [$scope], 0),
            'responses' => (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_sessions es ON es.id = r.evaluation_session_id WHERE es.is_demo = ?',
                [$scope],
                0
            ),
            'uat_respondents' => (int) Database::scalar(
                'SELECT COUNT(*) FROM uat_responses r JOIN uat_surveys s ON s.id = r.uat_survey_id WHERE s.is_demo = ?',
                [$scope],
                0
            ),
        ];

        $this->view('dean/demo/index', [
            'pageTitle' => 'Demonstration Data',
            'counts' => $counts,
            'demoMode' => $this->demo(),
            'hasDemoAccounts' => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE username = 'demo.dean'", [], 0) > 0,
        ]);
    }

    public function generate(): void
    {
        $this->requirePost();
        try {
            $stats = (new DemoService())->seed();
        } catch (\Throwable $e) {
            $this->backWithError('Demo data could not be generated: ' . $e->getMessage(), 'dean/demo');
        }
        Setting::clearCache();
        $this->audit('demo.generate', 'demo', null, 'Generated synthetic demonstration data');
        $this->success(
            'Synthetic demonstration data generated: ' . (int) ($stats['responses'] ?? 0) . ' responses across '
            . (int) ($stats['sessions'] ?? 0) . ' sessions. Demo mode is now ON. Password for demo accounts: Demo@12345.',
            'dean/demo'
        );
    }

    public function reset(): void
    {
        $this->requirePost();
        try {
            (new DemoService())->reset();
            Setting::set('demo_mode', '0', 'demo', null);
            Setting::clearCache();
        } catch (\Throwable $e) {
            $this->backWithError('Demo data could not be reset: ' . $e->getMessage(), 'dean/demo');
        }
        $this->audit('demo.reset', 'demo', null, 'Removed synthetic demonstration data');
        $this->success('All flagged demonstration data was removed. Live records were not touched. Demo mode is OFF.', 'dean/demo');
    }
}
