<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Setting;
use App\Models\Academic;
use App\Models\Analytics;
use App\Models\Evaluation;

final class AnalyticsService
{
    private Analytics $analytics;
    private Academic $academic;
    private Evaluation $evaluations;

    public function __construct()
    {
        $this->analytics = new Analytics();
        $this->academic = new Academic();
        $this->evaluations = new Evaluation();
    }

    public function deanDashboard(bool $demo): array
    {
        $scope = $demo ? 1 : 0;
        $instructors = (int) Database::scalar(
            'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "instructor" AND u.is_demo = ? AND u.status = "active"',
            [$scope],
            0
        );
        $students = (int) Database::scalar(
            'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "student" AND u.is_demo = ? AND u.status = "active"',
            [$scope],
            0
        );
        $departments = (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE is_demo = ? AND status = "active"', [$scope], 0);
        $programs = (int) Database::scalar('SELECT COUNT(*) FROM programs WHERE is_demo = ? AND status = "active"', [$scope], 0);
        $courses = (int) Database::scalar('SELECT COUNT(*) FROM courses WHERE is_demo = ? AND status = "active"', [$scope], 0);
        $sections = (int) Database::scalar('SELECT COUNT(*) FROM sections WHERE is_demo = ? AND status = "active"', [$scope], 0);
        $activeSessions = (int) Database::scalar(
            "SELECT COUNT(*) FROM evaluation_sessions WHERE is_demo = ? AND status = 'active' AND NOW() BETWEEN opens_at AND expires_at",
            [$scope],
            0
        );
        $completedSessions = (int) Database::scalar(
            "SELECT COUNT(*) FROM evaluation_sessions WHERE is_demo = ? AND status IN ('expired','closed')",
            [$scope],
            0
        );
        $totalResponses = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_sessions es ON es.id = r.evaluation_session_id WHERE es.is_demo = ?',
            [$scope],
            0
        );
        $pending = (int) Database::scalar(
            "SELECT COUNT(*) FROM evaluation_participation ep
             JOIN evaluation_sessions es ON es.id = ep.evaluation_session_id
             WHERE es.is_demo = ? AND es.status = 'active' AND NOW() BETWEEN es.opens_at AND es.expires_at AND ep.status = 'pending'",
            [$scope],
            0
        );

        $base = $this->analytics->filteredCounts([], $demo);
        $rating = $this->analytics->filteredRating([], $demo);
        $sentiment = $this->analytics->sentimentDistribution([], $demo);
        $categories = $this->analytics->categoryAverages([], $demo);
        $semesterTrend = $this->analytics->semesterTrend([], $demo);
        $sessionTrend = $this->analytics->sessionTrend([], $demo, 8);
        $comparison = $this->analytics->instructorComparison([], $demo);
        $departmentComparison = $this->analytics->departmentComparison([], $demo);
        $ratingDistribution = $this->analytics->ratingDistribution([], $demo);
        $themes = $this->analytics->themeCounts([], $demo);

        $threshold = max(3, Setting::privacyThreshold());
        $attention = [];
        foreach ($comparison as $row) {
            if ((int) $row['response_count'] >= $threshold && $row['average'] !== null && (float) $row['average'] < 3.60) {
                $attention[] = $row;
            }
        }
        $attention = array_slice($attention, 0, 5);

        $recommendationsOpen = (int) Database::scalar(
            "SELECT COUNT(*) FROM instructor_recommendations ir JOIN users u ON u.id = ir.instructor_id
             WHERE u.is_demo = ? AND ir.status IN ('open','acknowledged','in_progress')",
            [$scope],
            0
        );
        $observations = (int) Database::scalar('SELECT COUNT(*) FROM class_observations WHERE is_demo = ?', [$scope], 0);
        $surveys = (int) Database::scalar('SELECT COUNT(*) FROM surveys WHERE is_demo = ? AND is_template = 0 AND status <> "archived"', [$scope], 0);
        $assignments = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_assignments WHERE is_demo = ?', [$scope], 0);

        $recentActivities = Database::fetchAll(
            'SELECT a.*, u.first_name, u.last_name, u.username FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8'
        );

        return [
            'counts' => [
                'instructors' => $instructors,
                'students' => $students,
                'departments' => $departments,
                'programs' => $programs,
                'courses' => $courses,
                'sections' => $sections,
                'active_sessions' => $activeSessions,
                'completed_sessions' => $completedSessions,
                'responses' => $totalResponses,
                'pending' => $pending,
                'surveys' => $surveys,
                'assignments' => $assignments,
                'observations' => $observations,
                'open_recommendations' => $recommendationsOpen,
            ],
            'average' => $rating['average'],
            'rating_count' => $rating['rating_count'],
            'participation' => $base['participation_rate'],
            'eligible' => $base['eligible'],
            'submitted' => $base['submitted'],
            'analytics_sessions' => $base['sessions'],
            'analytics_responses' => $base['responses'],
            'sentiment' => $sentiment,
            'categories' => $categories,
            'semester_trend' => $semesterTrend,
            'session_trend' => $sessionTrend,
            'instructor_comparison' => $comparison,
            'department_comparison' => $departmentComparison,
            'rating_distribution' => $ratingDistribution,
            'themes' => $themes,
            'attention' => $attention,
            'recent_activities' => $recentActivities,
        ];
    }

    public function instructorDashboard(int $instructorId, bool $demo): array
    {
        $filters = ['instructor_id' => $instructorId];
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $rating = $this->analytics->filteredRating($filters, $demo);
        $categories = $this->analytics->categoryAverages($filters, $demo);
        $sentiment = $this->analytics->sentimentDistribution($filters, $demo);
        $themes = $this->analytics->themeCounts($filters, $demo);
        $trend = $this->analytics->sessionTrend($filters, $demo, 10);
        $semesterTrend = $this->analytics->semesterTrend($filters, $demo);
        $distribution = $this->analytics->ratingDistribution($filters, $demo);

        $this->refreshOwnSessionStatuses();
        $sessions = $this->evaluations->sessionsForInstructor($instructorId, $demo);
        $now = time();
        $completed = 0;
        $upcoming = 0;
        $open = 0;
        foreach ($sessions as &$session) {
            $status = $this->effectiveSessionStatus($session);
            $session['effective_status'] = $status;
            if ($status === 'active') {
                $open++;
            } elseif ($status === 'scheduled') {
                $upcoming++;
            } elseif (in_array($status, ['expired', 'closed'], true)) {
                $completed++;
            }
        }
        unset($session);

        $assignments = $this->academic->instructorAssignments($demo, $instructorId);
        $recommendations = Database::fetchAll(
            "SELECT * FROM instructor_recommendations WHERE instructor_id = ? AND status IN ('open','acknowledged','in_progress') ORDER BY FIELD(priority,'high','medium','low'), created_at DESC LIMIT 6",
            [$instructorId]
        );
        $prediction = Database::fetch(
            'SELECT * FROM prediction_results WHERE instructor_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1',
            [$instructorId]
        );

        return [
            'assignments' => $assignments,
            'sessions' => $sessions,
            'completed_sessions' => $completed,
            'upcoming_sessions' => $upcoming,
            'open_sessions' => $open,
            'average' => $rating['average'],
            'rating_count' => $rating['rating_count'],
            'responses' => $counts['responses'],
            'participation' => $counts['participation_rate'],
            'eligible' => $counts['eligible'],
            'submitted' => $counts['submitted'],
            'categories' => $categories,
            'sentiment' => $sentiment,
            'themes' => $themes,
            'trend' => $trend,
            'semester_trend' => $semesterTrend,
            'rating_distribution' => $distribution,
            'recommendations' => $recommendations,
            'prediction' => $prediction,
        ];
    }

    public function studentDashboard(int $studentId, bool $demo): array
    {
        $available = $this->evaluations->availableSessionsForStudent($studentId, $demo);
        $history = $this->evaluations->studentHistory($studentId, $demo);
        $completed = 0;
        $pending = 0;
        foreach ($history as $row) {
            if ($row['status'] === 'submitted') {
                $completed++;
            } else {
                $pending++;
            }
        }
        return [
            'available' => $available,
            'history' => $history,
            'available_count' => count($available),
            'completed' => $completed,
            'pending' => $pending,
        ];
    }

    public function analyticsPage(array $filters, bool $demo): array
    {
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $rating = $this->analytics->filteredRating($filters, $demo);
        return [
            'filters' => $filters,
            'counts' => $counts,
            'rating' => $rating,
            'categories' => $this->analytics->categoryAverages($filters, $demo),
            'sentiment' => $this->analytics->sentimentDistribution($filters, $demo),
            'themes' => $this->analytics->themeCounts($filters, $demo),
            'distribution' => $this->analytics->ratingDistribution($filters, $demo),
            'session_trend' => $this->analytics->sessionTrend($filters, $demo, 16),
            'semester_trend' => $this->analytics->semesterTrend($filters, $demo),
            'instructors' => $this->analytics->instructorComparison($filters, $demo),
            'departments' => $this->analytics->departmentComparison($filters, $demo),
            'comments' => $this->analytics->comments($filters, $demo, 20),
            'filter_options' => $this->filterOptions($demo),
        ];
    }

    public function aiInsights(array $filters, bool $demo): array
    {
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $rating = $this->analytics->filteredRating($filters, $demo);
        $sentiment = $this->analytics->sentimentDistribution($filters, $demo);
        $themes = $this->analytics->themeCounts($filters, $demo);
        $categories = $this->analytics->categoryAverages($filters, $demo);
        $engine = new AIEngine();
        $summary = $engine->summarize($sentiment, $themes, $categories, (int) $counts['responses']);

        $threshold = Setting::privacyThreshold();
        $comments = [];
        if ((int) $counts['responses'] >= $threshold && (string) Setting::get('privacy_show_comments', '1') === '1') {
            $comments = $this->analytics->comments($filters, $demo, 12);
        }

        $comparison = null;
        $trend = $this->analytics->semesterTrend($filters, $demo);
        if (count($trend) >= 2) {
            $last = (float) $trend[count($trend) - 1]['average'];
            $previous = (float) $trend[count($trend) - 2]['average'];
            $comparison = round($last - $previous, 2);
            $summary['narrative'] = $engine->summarize($sentiment, $themes, $categories, (int) $counts['responses'], $comparison)['narrative'];
            $summary['comparison'] = $comparison;
        }

        return [
            'counts' => $counts,
            'rating' => $rating,
            'sentiment' => $sentiment,
            'themes' => $themes,
            'categories' => $categories,
            'summary' => $summary,
            'comments' => $comments,
            'privacy_threshold' => $threshold,
            'filter_options' => $this->filterOptions($demo),
        ];
    }

    public function filterOptions(bool $demo): array
    {
        return [
            'departments' => $this->academic->departmentOptions($demo),
            'programs' => $this->academic->programOptions($demo),
            'courses' => $this->academic->courseOptions($demo),
            'sections' => $this->academic->sectionOptions($demo),
            'instructors' => Database::fetchAll(
                'SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = "instructor" AND u.is_demo = ? AND u.status = "active" ORDER BY u.last_name',
                [$demo ? 1 : 0]
            ),
            'academic_years' => $this->academic->academicYearOptions(),
            'semesters' => $this->academic->semesterOptions(),
        ];
    }

    public function buildFilters(array $input): array
    {
        $keys = ['academic_year_id', 'semester_id', 'department_id', 'program_id', 'instructor_id', 'course_id', 'section_id', 'session_id', 'date_from', 'date_to'];
        $filters = [];
        foreach ($keys as $key) {
            if (!empty($input[$key])) {
                $filters[$key] = $input[$key];
            }
        }
        return $filters;
    }

    public function effectiveSessionStatus(array $session): string
    {
        if (in_array($session['status'], ['closed', 'archived'], true)) {
            return $session['status'];
        }
        $now = time();
        if (strtotime((string) $session['expires_at']) < $now) {
            return 'expired';
        }
        if (strtotime((string) $session['opens_at']) > $now) {
            return 'scheduled';
        }
        return 'active';
    }

    public function refreshOwnSessionStatuses(): void
    {
        Database::execute("UPDATE evaluation_sessions SET status = 'expired' WHERE status IN ('scheduled','active') AND expires_at < NOW()");
        Database::execute("UPDATE evaluation_sessions SET status = 'active' WHERE status = 'scheduled' AND opens_at <= NOW() AND expires_at >= NOW()");
    }

    /**
     * Category-level averages for a single instructor and optional filters.
     */
    public function instructorCategories(int $instructorId, bool $demo, array $extra = []): array
    {
        return $this->analytics->categoryAverages(array_merge(['instructor_id' => $instructorId], $extra), $demo);
    }

    public function questionStats(int $versionId, ?int $sessionId = null): array
    {
        return $this->analytics->questionStats($versionId, $sessionId);
    }
}
