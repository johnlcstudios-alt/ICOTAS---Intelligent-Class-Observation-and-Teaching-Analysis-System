<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Setting;
use App\Models\Analytics;
use App\Models\Evaluation;
use App\Models\UAT;

/**
 * Institutional report assembly and low-dependency CSV / PDF output.
 */
final class ReportService
{
    public const TYPES = [
        'faculty_summary' => 'Faculty Evaluation Summary',
        'individual_instructor' => 'Individual Instructor Report',
        'department' => 'Department Evaluation Report',
        'course' => 'Course Evaluation Report',
        'section' => 'Section Evaluation Report',
        'participation' => 'Student Participation Report',
        'question_statistics' => 'Question-Level Statistics',
        'sentiment' => 'Sentiment Analysis Report',
        'predictive' => 'Predictive Trend Report',
        'semester_comparison' => 'Academic Semester Comparison',
        'executive' => 'Institutional Executive Summary',
        'uat' => 'ISO/IEC 25010 UAT Report',
    ];

    /** Report types an instructor may generate; institution-wide reports are withheld. */
    private const INSTRUCTOR_TYPES = [
        'individual_instructor',
        'faculty_summary',
        'participation',
        'sentiment',
        'predictive',
        'semester_comparison',
        'course',
        'section',
    ];

    private AnalyticsService $service;
    private Analytics $analytics;
    private Evaluation $evaluations;

    public function __construct()
    {
        $this->service = new AnalyticsService();
        $this->analytics = new Analytics();
        $this->evaluations = new Evaluation();
    }

    public static function typesFor(bool $instructor): array
    {
        return $instructor
            ? array_intersect_key(self::TYPES, array_flip(self::INSTRUCTOR_TYPES))
            : self::TYPES;
    }

    public static function allowedFor(string $type, bool $instructor): bool
    {
        return isset(self::TYPES[$type])
            && (!$instructor || in_array($type, self::INSTRUCTOR_TYPES, true));
    }

    public function build(string $type, array $filters, bool $demo, ?array $viewer = null): array
    {
        if (!isset(self::TYPES[$type])) {
            return ['error' => 'Unknown report type.'];
        }

        $generatedAt = date('Y-m-d H:i:s');
        $period = $this->periodLabel($filters);
        $report = [
            'type' => $type,
            'title' => self::TYPES[$type],
            'institution' => Setting::institution(),
            'period' => $period,
            'generated_at' => $generatedAt,
            'filters' => $filters,
            'demo' => $demo,
            'data_note' => $demo
                ? 'SYNTHETIC DEMONSTRATION DATA — this report is not institutional research evidence.'
                : 'Generated from ICOTAS live database records. Numeric ratings are normalized to a five-point scale.',
            'summary' => [],
            'sections' => [],
            'charts' => [],
            'narrative' => [],
        ];

        switch ($type) {
            case 'faculty_summary':
            case 'individual_instructor':
                return $this->instructorReport($report, $type, $filters, $demo, $viewer);
            case 'department':
                return $this->departmentReport($report, $filters, $demo);
            case 'course':
                return $this->groupReport($report, 'course', $filters, $demo);
            case 'section':
                return $this->groupReport($report, 'section', $filters, $demo);
            case 'participation':
                return $this->participationReport($report, $filters, $demo, $viewer);
            case 'question_statistics':
                return $this->questionReport($report, $filters, $demo);
            case 'sentiment':
                return $this->sentimentReport($report, $filters, $demo);
            case 'predictive':
                return $this->predictiveReport($report, $filters, $demo);
            case 'semester_comparison':
                return $this->semesterReport($report, $filters, $demo);
            case 'executive':
                return $this->executiveReport($report, $filters, $demo);
            case 'uat':
                return $this->uatReport($report, $filters, $demo);
        }

        return ['error' => 'Report type is unavailable.'];
    }

    private function instructorReport(array $report, string $type, array $filters, bool $demo, ?array $viewer): array
    {
        if ($viewer && ($viewer['role'] ?? '') === 'instructor') {
            $filters['instructor_id'] = (int) $viewer['id'];
        }
        if (empty($filters['instructor_id'])) {
            return ['error' => 'Select an instructor for this report.'];
        }
        $instructor = Database::fetch(
            'SELECT u.*, d.name AS department_name, d.code AS department_code FROM users u
             LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? AND u.is_demo = ? LIMIT 1',
            [(int) $filters['instructor_id'], $demo ? 1 : 0]
        );
        if (!$instructor) {
            return ['error' => 'Instructor not found in the active data scope.'];
        }
        $instructorName = trim($instructor['first_name'] . ' ' . $instructor['last_name']);
        $report['title'] = $type === 'individual_instructor' ? 'Individual Instructor Report — ' . $instructorName : 'Faculty Evaluation Summary — ' . $instructorName;
        $report['subject'] = $instructorName;

        $filter = ['instructor_id' => (int) $instructor['id']] + $filters;
        $counts = $this->analytics->filteredCounts($filter, $demo);
        $rating = $this->analytics->filteredRating($filter, $demo);
        $categories = $this->analytics->categoryAverages($filter, $demo);
        $sentiment = $this->analytics->sentimentDistribution($filter, $demo);
        $themes = $this->analytics->themeCounts($filter, $demo);
        $insights = $this->service->aiInsights($filter, $demo);
        $prediction = (new PredictionService())->latest((int) $instructor['id']);
        $recommendations = (new RecommendationService())->forInstructor((int) $instructor['id']);

        $report['summary'] = [
            'Instructor' => $instructorName,
            'Department' => $instructor['department_name'] ?? '—',
            'Evaluation sessions' => (string) $counts['sessions'],
            'Anonymous responses' => (string) $counts['responses'],
            'Average rating (5-point)' => $rating['average'] !== null ? format_rating($rating['average']) : 'No ratings',
            'Participation rate' => format_percent($counts['participation_rate']),
            'Sentiment' => ucfirst($insights['summary']['dominant_sentiment']),
            'Prediction indicator' => $prediction ? ucwords(str_replace('_', ' ', $prediction['risk_level'])) : 'Not yet generated',
        ];

        $report['sections'][] = [
            'title' => 'Teaching category averages',
            'columns' => ['Category', 'Average', 'SD (population)', 'Ratings'],
            'rows' => array_map(static fn ($row) => [
                $row['category'], format_rating($row['average']), format_rating($row['std_dev']), (string) $row['response_count'],
            ], $categories),
        ];
        $report['sections'][] = [
            'title' => 'Anonymous feedback themes',
            'columns' => ['Theme', 'Polarity', 'Responses'],
            'rows' => array_map(static fn ($row) => [$row['theme'], ucfirst($row['polarity']), (string) $row['count']], $themes),
        ];
        if ($type === 'individual_instructor') {
            $report['sections'][] = [
                'title' => 'Evidence-linked recommendations',
                'columns' => ['Theme', 'Priority', 'Recommendation', 'Supporting evidence'],
                'rows' => array_map(static fn ($row) => [$row['theme'], ucfirst($row['priority']), $row['recommendation'], $row['supporting_metric']], array_slice($recommendations, 0, 8)),
            ];
        }
        $report['narrative'][] = $insights['summary']['narrative'];
        if (!empty($insights['summary']['recommendations'])) {
            $report['narrative'][] = 'Suggested actions are generated from the identified themes and category ratings. They are advisory and require professional judgment.';
        }
        $report['charts'][] = [
            'title' => 'Category averages',
            'type' => 'bar',
            'labels' => array_column($categories, 'category'),
            'values' => array_map(static fn ($row) => $row['average'] !== null ? (float) $row['average'] : 0, $categories),
            'max' => 5,
        ];
        $report['charts'][] = [
            'title' => 'Sentiment distribution',
            'type' => 'doughnut',
            'labels' => array_map(static fn ($row) => ucfirst($row['label']), $sentiment),
            'values' => array_map(static fn ($row) => (int) $row['count'], $sentiment),
            'max' => 0,
        ];
        return $report;
    }

    private function departmentReport(array $report, array $filters, bool $demo): array
    {
        $filters = $this->service->buildFilters($filters);
        $rows = $this->analytics->instructorComparison($filters, $demo);
        if (!empty($filters['department_id'])) {
            $report['summary']['Department'] = (string) Database::scalar('SELECT name FROM departments WHERE id = ?', [(int) $filters['department_id']], 'Selected department');
        }
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $rating = $this->analytics->filteredRating($filters, $demo);
        $report['summary'] += [
            'Evaluation sessions' => (string) $counts['sessions'],
            'Anonymous responses' => (string) $counts['responses'],
            'Department average' => $rating['average'] !== null ? format_rating($rating['average']) : 'No ratings',
            'Participation rate' => format_percent($counts['participation_rate']),
        ];
        $report['sections'][] = [
            'title' => 'Instructor comparison',
            'columns' => ['Instructor', 'Department', 'Average', 'Responses', 'Sessions'],
            'rows' => array_map(static fn ($row) => [
                trim($row['first_name'] . ' ' . $row['last_name']), $row['department_code'], format_rating($row['average']), (string) $row['response_count'], (string) $row['session_count'],
            ], $rows),
        ];
        $report['charts'][] = [
            'title' => 'Faculty averages',
            'type' => 'bar',
            'labels' => array_map(static fn ($row) => $row['last_name'], $rows),
            'values' => array_map(static fn ($row) => (float) $row['average'], $rows),
            'max' => 5,
        ];
        return $report;
    }

    private function groupReport(array $report, string $group, array $filters, bool $demo): array
    {
        $filters = $this->service->buildFilters($filters);
        $params = [];
        $where = $this->analytics->buildSessionWhere($filters, $demo, $params);
        $groupSelect = $group === 'course'
            ? 'c.id AS group_id, c.code AS group_code, c.title AS group_title, d.code AS department_code'
            : 'sec.id AS group_id, sec.code AS group_code, p.code AS program_code, d.code AS department_code';
        $groupBy = $group === 'course'
            ? 'c.id, c.code, c.title, d.code'
            : 'sec.id, sec.code, p.code, d.code';
        $rows = Database::fetchAll(
            'SELECT ' . $groupSelect . ', ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(DISTINCT r.id) AS response_count, COUNT(DISTINCT es.id) AS session_count,
                    COUNT(DISTINCT a.instructor_id) AS instructor_count
             FROM evaluation_sessions es' . $this->analytics->sessionJoins() . '
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE ' . $where . '
             GROUP BY ' . $groupBy . '
             ORDER BY average DESC, group_code',
            $params
        );
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $rating = $this->analytics->filteredRating($filters, $demo);
        $report['summary'] = [
            'Evaluation sessions' => (string) $counts['sessions'],
            'Anonymous responses' => (string) $counts['responses'],
            'Overall average' => $rating['average'] !== null ? format_rating($rating['average']) : 'No ratings',
            'Participation rate' => format_percent($counts['participation_rate']),
        ];
        $report['sections'][] = [
            'title' => $group === 'course' ? 'Course comparison' : 'Section comparison',
            'columns' => $group === 'course'
                ? ['Course', 'Description', 'Department', 'Average', 'Responses', 'Sessions']
                : ['Section', 'Program', 'Department', 'Average', 'Responses', 'Sessions'],
            'rows' => array_map(static function ($row) use ($group) {
                return $group === 'course'
                    ? [$row['group_code'], $row['group_title'], $row['department_code'], format_rating($row['average']), (string) $row['response_count'], (string) $row['session_count']]
                    : [$row['group_code'], $row['program_code'], $row['department_code'], format_rating($row['average']), (string) $row['response_count'], (string) $row['session_count']];
            }, $rows),
        ];
        $report['charts'][] = [
            'title' => 'Average by ' . $group,
            'type' => 'bar',
            'labels' => array_column($rows, 'group_code'),
            'values' => array_map(static fn ($row) => (float) $row['average'], $rows),
            'max' => 5,
        ];
        return $report;
    }

    private function participationReport(array $report, array $filters, bool $demo, ?array $viewer): array
    {
        if ($viewer && ($viewer['role'] ?? '') === 'instructor') {
            $filters['instructor_id'] = (int) $viewer['id'];
        }
        $filters = $this->service->buildFilters($filters);
        $sessions = $this->evaluations->sessions($demo, $filters);
        $rows = [];
        foreach ($sessions as $session) {
            $status = $this->service->effectiveSessionStatus($session);
            $eligible = (int) $session['eligible_count'];
            $submitted = (int) $session['submitted_count'];
            $rows[] = [
                $session['session_code'],
                $session['course_code'] . ' — ' . $session['section_code'],
                format_date($session['opens_at']),
                $status,
                (string) $eligible,
                (string) $submitted,
                format_percent(Analytics::participationRate($submitted, $eligible)),
            ];
        }
        $counts = $this->analytics->filteredCounts($filters, $demo);
        $report['summary'] = [
            'Sessions' => (string) count($sessions),
            'Eligible student-session records' => (string) $counts['eligible'],
            'Submitted' => (string) $counts['submitted'],
            'Participation rate' => format_percent($counts['participation_rate']),
            'Analytics responses' => (string) $counts['responses'],
        ];
        $report['sections'][] = [
            'title' => 'Participation by evaluation session',
            'columns' => ['Code', 'Class', 'Opened', 'Status', 'Eligible', 'Submitted', 'Rate'],
            'rows' => $rows,
        ];
        return $report;
    }

    private function questionReport(array $report, array $filters, bool $demo): array
    {
        $sessionId = !empty($filters['session_id']) ? (int) $filters['session_id'] : null;
        $versionId = !empty($filters['question_version_id']) ? (int) $filters['question_version_id'] : null;
        $session = $sessionId ? $this->evaluations->getSession($sessionId) : null;
        if ($session) {
            if ((int) $session['is_demo'] !== ($demo ? 1 : 0)) {
                return ['error' => 'The selected session is outside the active data scope.'];
            }
            $versionId = (int) $session['survey_version_id'];
            $report['summary']['Session'] = $session['session_code'] . ' — ' . $session['course_code'];
        }
        if (!$versionId) {
            $latest = Database::fetch(
                'SELECT es.id FROM evaluation_sessions es WHERE es.is_demo = ? AND es.status IN ("active","expired","closed") ORDER BY es.opens_at DESC LIMIT 1',
                [$demo ? 1 : 0]
            );
            if ($latest) {
                $sessionId = (int) $latest['id'];
                $session = $this->evaluations->getSession($sessionId);
                $versionId = $session ? (int) $session['survey_version_id'] : null;
            }
        }
        if (!$versionId) {
            $report['notes'][] = 'No evaluation session is available in the selected data scope.';
            return $report;
        }
        $questions = $this->analytics->questionStats($versionId, $sessionId);
        $version = Database::fetch('SELECT v.*, s.title FROM survey_versions v JOIN surveys s ON s.id = v.survey_id WHERE v.id = ?', [$versionId]);
        $report['summary']['Questionnaire'] = $version ? $version['title'] . ' (v' . $version['version_number'] . ')' : 'Version #' . $versionId;
        $report['summary']['Question count'] = (string) count($questions);
        $report['summary']['Session scope'] = $session ? $session['session_code'] : 'All comparable responses for this version';
        $report['sections'][] = [
            'title' => 'Question-level statistics',
            'columns' => ['#', 'Question', 'Category', 'N', 'Mean', 'SD', 'Agree %'],
            'rows' => array_map(static fn ($row) => [
                (string) $row['order_index'], str_limit($row['question_text'], 90), $row['category'],
                (string) $row['response_count'], format_rating($row['average']), format_rating($row['std_dev']),
                format_percent($row['agreement_percentage']),
            ], $questions),
        ];
        $report['charts'][] = [
            'title' => 'Question averages',
            'type' => 'bar',
            'labels' => array_map(static fn ($q) => 'Q' . $q['order_index'], $questions),
            'values' => array_map(static fn ($q) => $q['average'] !== null ? (float) $q['average'] : 0, $questions),
            'max' => 5,
        ];
        return $report;
    }

    private function sentimentReport(array $report, array $filters, bool $demo): array
    {
        $filters = $this->service->buildFilters($filters);
        $insights = $this->service->aiInsights($filters, $demo);
        $report['summary'] = [
            'Anonymous responses' => (string) $insights['counts']['responses'],
            'Dominant sentiment' => ucfirst($insights['summary']['dominant_sentiment']),
            'Analyzed comments' => (string) $insights['summary']['analyzed_comments'],
            'Average rating' => $insights['rating']['average'] !== null ? format_rating($insights['rating']['average']) : 'No ratings',
            'Method' => $insights['summary']['engine'],
            'Confidence indicator' => $insights['summary']['confidence'] . '%',
        ];
        $report['sections'][] = [
            'title' => 'Sentiment distribution',
            'columns' => ['Sentiment', 'Count'],
            'rows' => array_map(static fn ($row) => [ucfirst($row['label']), (string) $row['count']], $insights['sentiment']),
        ];
        $report['sections'][] = [
            'title' => 'Theme frequency',
            'columns' => ['Theme', 'Polarity', 'Responses'],
            'rows' => array_map(static fn ($row) => [$row['theme'], ucfirst($row['polarity']), (string) $row['count']], $insights['themes']),
        ];
        $report['sections'][] = [
            'title' => 'Advisory recommendations',
            'columns' => ['Theme', 'Concern', 'Recommendation', 'Supporting evidence'],
            'rows' => array_map(static fn ($row) => [$row['theme'], $row['concern'], $row['recommendation'], $row['supporting_metric']], $insights['summary']['recommendations']),
        ];
        $report['narrative'][] = $insights['summary']['narrative'];
        $report['charts'][] = [
            'title' => 'Sentiment distribution',
            'type' => 'doughnut',
            'labels' => array_map(static fn ($row) => ucfirst($row['label']), $insights['sentiment']),
            'values' => array_map(static fn ($row) => (int) $row['count'], $insights['sentiment']),
            'max' => 0,
        ];
        return $report;
    }

    private function predictiveReport(array $report, array $filters, bool $demo): array
    {
        $predictionService = new PredictionService();
        if (!empty($filters['instructor_id'])) {
            $instructor = Database::fetch(
                'SELECT id, first_name, last_name FROM users WHERE id = ? AND is_demo = ?',
                [(int) $filters['instructor_id'], $demo ? 1 : 0]
            );
            if (!$instructor) {
                return ['error' => 'Instructor not found in the active data scope.'];
            }
            $result = $predictionService->generate((int) $instructor['id'], $demo);
            $rows = [[
                trim($instructor['first_name'] . ' ' . $instructor['last_name']),
                ucwords(str_replace('_', ' ', $result['risk_level'])),
                format_rating($result['current_average']),
                format_rating($result['forecast_average']),
                ($result['slope'] !== null ? number_format((float) $result['slope'], 3) : '—'),
                (string) $result['record_count'],
                (string) $result['response_count'],
                $result['explanation'],
            ]];
        } else {
            $latest = $predictionService->allLatest($demo);
            if (empty($latest)) {
                $predictionService->generateForAll($demo);
                $latest = $predictionService->allLatest($demo);
            }
            $rows = array_map(static fn ($row) => [
                trim($row['first_name'] . ' ' . $row['last_name']),
                ucwords(str_replace('_', ' ', $row['risk_level'])),
                format_rating($row['current_average']),
                format_rating($row['forecast_average']),
                $row['slope'] !== null ? number_format((float) $row['slope'], 3) : '—',
                (string) $row['record_count'],
                (string) $row['response_count'],
                $row['explanation'],
            ], $latest);
        }
        $report['summary'] = [
            'Method' => 'Weighted moving average + least-squares linear slope',
            'Minimum responses' => (string) Setting::get('prediction_min_responses', '5'),
            'Minimum periods' => (string) Setting::get('prediction_min_periods', '2'),
            'Status' => 'Estimates only. Not validated machine-learning forecasts and not a basis for automatic personnel decisions.',
        ];
        $report['sections'][] = [
            'title' => 'Prediction indicators',
            'columns' => ['Instructor', 'Indicator', 'Current', 'Estimate', 'Slope', 'Periods', 'Responses', 'Explanation'],
            'rows' => $rows,
        ];
        $report['narrative'][] = 'Predictions are rule-based indicators generated on request from actual historical aggregates. Insufficient data is shown as such and never replaced with a fabricated estimate.';
        return $report;
    }

    private function semesterReport(array $report, array $filters, bool $demo): array
    {
        $filters = $this->service->buildFilters($filters);
        $trend = $this->analytics->semesterTrend($filters, $demo);
        $report['summary'] = [
            'Comparable periods' => (string) count($trend),
            'Responses included' => (string) array_sum(array_column($trend, 'response_count')),
            'Method' => 'Arithmetic mean of normalized numeric ratings, grouped by academic year and semester.',
        ];
        $report['sections'][] = [
            'title' => 'Semester comparison',
            'columns' => ['Academic year', 'Semester', 'Average', 'Responses', 'Sessions'],
            'rows' => array_map(static fn ($row) => [
                $row['academic_year'], $row['semester'], format_rating($row['average']), (string) $row['response_count'], (string) $row['session_count'],
            ], $trend),
        ];
        $report['charts'][] = [
            'title' => 'Historical average rating',
            'type' => 'line',
            'labels' => array_map(static fn ($row) => $row['academic_year'] . ' ' . $row['semester'], $trend),
            'values' => array_map(static fn ($row) => (float) $row['average'], $trend),
            'max' => 5,
        ];
        return $report;
    }

    private function executiveReport(array $report, array $filters, bool $demo): array
    {
        $dashboard = $this->service->deanDashboard($demo);
        $insights = $this->service->aiInsights([], $demo);
        $report['summary'] = [
            'Instructors' => (string) $dashboard['counts']['instructors'],
            'Students' => (string) $dashboard['counts']['students'],
            'Departments' => (string) $dashboard['counts']['departments'],
            'Courses' => (string) $dashboard['counts']['courses'],
            'Sections' => (string) $dashboard['counts']['sections'],
            'Completed sessions' => (string) $dashboard['counts']['completed_sessions'],
            'Anonymous responses' => (string) $dashboard['counts']['responses'],
            'Institutional average' => $dashboard['average'] !== null ? format_rating($dashboard['average']) : 'No ratings',
            'Participation rate' => format_percent($dashboard['participation']),
            'Dominant sentiment' => ucfirst($insights['summary']['dominant_sentiment']),
        ];
        $report['sections'][] = [
            'title' => 'Department comparison',
            'columns' => ['Department', 'Code', 'Average', 'Responses', 'Instructors'],
            'rows' => array_map(static fn ($row) => [
                $row['name'], $row['code'], format_rating($row['average']), (string) $row['response_count'], (string) $row['instructor_count'],
            ], $dashboard['department_comparison']),
        ];
        $report['sections'][] = [
            'title' => 'Instructors requiring attention',
            'columns' => ['Instructor', 'Department', 'Average', 'Responses'],
            'rows' => array_map(static fn ($row) => [
                trim($row['first_name'] . ' ' . $row['last_name']), $row['department_code'], format_rating($row['average']), (string) $row['response_count'],
            ], $dashboard['attention']),
        ];
        $report['sections'][] = [
            'title' => 'Recurring themes',
            'columns' => ['Theme', 'Polarity', 'Responses'],
            'rows' => array_map(static fn ($row) => [$row['theme'], ucfirst($row['polarity']), (string) $row['count']], array_slice($dashboard['themes'], 0, 15)),
        ];
        $report['narrative'][] = $insights['summary']['narrative'];
        $report['charts'][] = [
            'title' => 'Department averages',
            'type' => 'bar',
            'labels' => array_map(static fn ($row) => $row['code'], $dashboard['department_comparison']),
            'values' => array_map(static fn ($row) => (float) $row['average'], $dashboard['department_comparison']),
            'max' => 5,
        ];
        return $report;
    }

    private function uatReport(array $report, array $filters, bool $demo): array
    {
        $surveyId = !empty($filters['uat_survey_id']) ? (int) $filters['uat_survey_id'] : null;
        if (!$surveyId) {
            $surveyId = (int) Database::scalar('SELECT id FROM uat_surveys WHERE is_demo = ? ORDER BY created_at DESC LIMIT 1', [$demo ? 1 : 0], 0);
        }
        $uat = new UAT();
        $survey = $surveyId ? $uat->findSurvey($surveyId) : null;
        if (!$survey) {
            return ['error' => 'No UAT survey is available. Create and collect a UAT survey first.'];
        }
        if ((int) $survey['is_demo'] !== ($demo ? 1 : 0)) {
            return ['error' => 'The UAT survey is outside the active data scope.'];
        }
        $results = $this->service;
        $summary = $uat->resultSummary($surveyId);
        $report['title'] = 'ISO/IEC 25010 UAT Report — ' . $survey['title'];
        $report['summary'] = [
            'UAT survey' => $survey['title'],
            'Status' => ucfirst($survey['status']),
            'Submitted respondents' => (string) $summary['overall']['respondents'],
            'Composite mean' => $summary['overall']['mean'] !== null ? format_rating($summary['overall']['mean']) : 'No responses',
            'Composite interpretation' => interpret_uat($summary['overall']['mean']),
            'SD (population)' => format_rating($summary['overall']['sd_pop']),
            'Scale' => '5-point Likert: 4.50–5.00 Very Satisfactory; 3.50–4.49 Satisfactory; 2.50–3.49 Fair; 1.50–2.49 Poor; 1.00–1.49 Very Poor',
        ];
        $report['sections'][] = [
            'title' => 'ISO/IEC 25010 characteristics',
            'columns' => ['Characteristic', 'Mean', 'SD (population)', 'Responses', 'Interpretation'],
            'rows' => array_map(static fn ($row) => [
                $row['characteristic'], format_rating($row['mean']), format_rating($row['sd_pop']), (string) $row['rating_count'], $row['interpretation'],
            ], $summary['characteristics']),
        ];
        $report['sections'][] = [
            'title' => 'Question-level UAT results',
            'columns' => ['Characteristic', 'Statement', 'N', 'Mean', 'SD'],
            'rows' => array_map(static fn ($row) => [
                $row['characteristic'], str_limit($row['question_text'], 100), (string) $row['rating_count'], format_rating($row['mean']), format_rating($row['sd']),
            ], $summary['questions']),
        ];
        $references = $uat->referenceResults();
        if (!empty($references)) {
            $report['sections'][] = [
                'title' => 'Previously reported research reference results (not new UAT data)',
                'columns' => ['Characteristic', 'Mean', 'SD', 'Interpretation', 'Source'],
                'rows' => array_map(static fn ($row) => [
                    $row['characteristic'], format_rating($row['mean_score']), format_rating($row['sd_score']), $row['interpretation'], $row['source_label'],
                ], $references),
            ];
        }
        $report['charts'][] = [
            'title' => 'UAT characteristic means',
            'type' => 'bar',
            'labels' => array_map(static fn ($row) => $row['characteristic'], $summary['characteristics']),
            'values' => array_map(static fn ($row) => (float) $row['mean'], $summary['characteristics']),
            'max' => 5,
        ];
        $report['narrative'][] = 'This UAT report uses actual responses recorded in this implementation. It is separate from previously reported manuscript findings and does not claim ISO certification.';
        return $report;
    }

    public function toCsv(array $report): string
    {
        $filename = 'ICOTAS_' . $report['type'] . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $safe = static function ($value): string {
            $value = (string) $value;
            if (preg_match('/^[=+\-@]/', $value)) {
                return "'" . $value;
            }
            return $value;
        };
        fputcsv($out, [$report['institution']]);
        fputcsv($out, ['ICOTAS — ' . $report['title']]);
        fputcsv($out, ['Period', $report['period']]);
        fputcsv($out, ['Generated', $report['generated_at']]);
        fputcsv($out, ['Data note', $report['data_note']]);
        fputcsv($out, []);
        fputcsv($out, ['Summary']);
        foreach ($report['summary'] as $label => $value) {
            fputcsv($out, [$safe($label), $safe($value)]);
        }
        foreach ($report['sections'] as $section) {
            fputcsv($out, []);
            fputcsv($out, [$section['title']]);
            fputcsv($out, array_map($safe, $section['columns']));
            foreach ($section['rows'] as $row) {
                fputcsv($out, array_map($safe, $row));
            }
        }
        fclose($out);
        return $filename;
    }

    public function toPdf(array $report): string
    {
        if (!class_exists('FPDF')) {
            require_once BASE_PATH . '/app/Libraries/fpdf.php';
        }
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetMargins(14, 16, 14);
        $pdf->SetTitle($this->latin($report['title']));
        $pdf->SetAuthor('ICOTAS — ' . $this->latin($report['institution']));
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 7, $this->latin('ICOTAS'), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, $this->latin($report['institution']), 0, 1, 'L');
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 5, $this->latin($report['title']), 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(0, 5, $this->latin('Period: ' . $report['period'] . '   |   Generated: ' . $report['generated_at']), 0, 1);
        $pdf->SetTextColor(140, 30, 30);
        $pdf->MultiCell(0, 4, $this->latin($report['data_note']), 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Summary', 0, 1);
        $pdf->SetFont('Helvetica', '', 8);
        foreach ($report['summary'] as $label => $value) {
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell(55, 5, $this->latin(str_limit((string) $label, 45)), 0, 0);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell(0, 5, $this->latin(str_limit((string) $value, 180)), 0, 'L');
        }

        foreach ($report['sections'] as $section) {
            if (empty($section['rows'])) {
                continue;
            }
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(0, 5, $this->latin($section['title']), 0, 1);
            $columns = $section['columns'];
            $count = max(1, count($columns));
            $width = (210 - 28) / $count;
            $pdf->SetFont('Helvetica', 'B', 7);
            foreach ($columns as $column) {
                $pdf->Cell($width, 5, $this->latin(str_limit((string) $column, 24)), 1, 0, 'L');
            }
            $pdf->Ln();
            $pdf->SetFont('Helvetica', '', 7);
            foreach ($section['rows'] as $row) {
                foreach (array_values($row) as $index => $cell) {
                    if ($index >= $count) {
                        break;
                    }
                    $pdf->Cell($width, 4.5, $this->latin(str_limit((string) $cell, 42)), 1, 0, 'L');
                }
                $pdf->Ln();
                if ($pdf->GetY() > 272) {
                    $pdf->AddPage();
                    $pdf->SetFont('Helvetica', '', 7);
                }
            }
        }
        if (!empty($report['narrative'])) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Findings and recommendations', 0, 1);
            $pdf->SetFont('Helvetica', '', 8);
            foreach ($report['narrative'] as $paragraph) {
                $pdf->MultiCell(0, 4.5, $this->latin($paragraph), 0, 'L');
                $pdf->Ln(1);
            }
        }
        $pdf->SetY(-12);
        $pdf->SetFont('Helvetica', 'I', 7);
        $pdf->Cell(0, 5, $this->latin('ICOTAS · Rule-based analytics · Page ' . $pdf->PageNo()), 0, 0, 'C');

        $filename = 'ICOTAS_' . $report['type'] . '_' . date('Ymd_His') . '.pdf';
        $pdf->Output('D', $filename);
        return $filename;
    }

    public function recordHistory(array $report, int $userId, string $format): void
    {
        Database::insert(
            'INSERT INTO report_history (requested_by, report_type, title, filters, format, generated_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, $report['type'], $report['title'], json_encode($report['filters'], JSON_UNESCAPED_UNICODE), $format]
        );
        (new AuditService())->record('report.generate', 'report', null, 'Generated ' . $report['title'] . ' (' . strtoupper($format) . ')', $userId);
    }

    private function periodLabel(array $filters): string
    {
        $year = !empty($filters['academic_year_id'])
            ? Database::scalar('SELECT name FROM academic_years WHERE id = ?', [(int) $filters['academic_year_id']])
            : null;
        $semester = !empty($filters['semester_id'])
            ? Database::scalar('SELECT name FROM semesters WHERE id = ?', [(int) $filters['semester_id']])
            : null;
        if ($year && $semester) {
            return $year . ' — ' . $semester;
        }
        return $year ?: ($semester ?: 'All recorded academic periods');
    }

    private function latin(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}
