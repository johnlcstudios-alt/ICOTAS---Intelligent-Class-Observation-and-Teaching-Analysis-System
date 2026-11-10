<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Setting;

/**
 * Transparent, rule-based trend forecasting. Outputs are estimates, are bound
 * to actual records, and include data-sufficiency information.
 */
final class PredictionService
{
    public function config(): array
    {
        return [
            'min_responses' => max(1, (int) Setting::get('prediction_min_responses', '5')),
            'min_periods' => max(1, (int) Setting::get('prediction_min_periods', '2')),
            'window_periods' => max(1, (int) Setting::get('prediction_window', '8')),
            'improving_slope' => (float) Setting::get('prediction_improving_slope', '0.08'),
            'declining_slope' => (float) Setting::get('prediction_declining_slope', '-0.08'),
            'negative_ratio' => (float) Setting::get('prediction_negative_ratio', '0.30'),
            'forecast_change' => (float) Setting::get('prediction_forecast_change', '0.10'),
        ];
    }

    public function periods(int $instructorId, bool $demo): array
    {
        $rows = Database::fetchAll(
            'SELECT a.academic_year_id, a.semester_id, ay.name AS academic_year, ay.start_date,
                    sem.name AS semester, sem.sequence, ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(DISTINCT r.id) AS response_count, COUNT(DISTINCT es.id) AS session_count
             FROM evaluation_sessions es
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE es.is_demo = ? AND a.instructor_id = ? AND es.status IN ("active","expired","closed")
             GROUP BY a.academic_year_id, a.semester_id, ay.id, ay.name, ay.start_date, sem.id, sem.name, sem.sequence
             ORDER BY ay.start_date ASC, sem.sequence ASC, a.academic_year_id ASC',
            [$demo ? 1 : 0, $instructorId]
        );
        foreach ($rows as &$row) {
            $row['average'] = $row['average'] !== null ? (float) $row['average'] : null;
        }
        return $rows;
    }

    public function generate(int $instructorId, bool $demo, ?int $academicYearId = null, ?int $semesterId = null): array
    {
        $config = $this->config();
        $allPeriods = array_values(array_filter($this->periods($instructorId, $demo), static fn ($p) => $p['average'] !== null));
        $periods = array_slice($allPeriods, -$config['window_periods']);
        $totalResponses = array_sum(array_column($allPeriods, 'response_count'));

        $negativeRow = Database::fetch(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN sr.sentiment_label = "negative" THEN 1 ELSE 0 END) AS negative
             FROM sentiment_results sr
             JOIN evaluation_responses r ON r.id = sr.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             WHERE es.is_demo = ? AND a.instructor_id = ?',
            [$demo ? 1 : 0, $instructorId]
        );
        $negativeRatio = (int) ($negativeRow['total'] ?? 0) > 0
            ? round((int) $negativeRow['negative'] / (int) $negativeRow['total'], 3)
            : 0.0;

        $categoryRows = Database::fetchAll(
            'SELECT q.category, ROUND(AVG(ra.numeric_value), 2) AS average, COUNT(ra.id) AS response_count
             FROM response_answers ra
             JOIN survey_questions q ON q.id = ra.question_id
             JOIN evaluation_responses r ON r.id = ra.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             WHERE es.is_demo = ? AND a.instructor_id = ? AND ra.numeric_value IS NOT NULL
             GROUP BY q.category ORDER BY average ASC',
            [$demo ? 1 : 0, $instructorId]
        );

        $method = 'Weighted moving average (recent periods) + least-squares linear slope';
        $base = [
            'instructor_id' => $instructorId,
            'academic_year_id' => $academicYearId,
            'semester_id' => $semesterId,
            'scope' => 'instructor_overall',
            'method' => $method,
            'risk_level' => 'insufficient_data',
            'current_average' => null,
            'forecast_average' => null,
            'slope' => null,
            'record_count' => count($periods),
            'response_count' => (int) $totalResponses,
            'explanation' => '',
            'supporting_metrics' => [],
            'threshold_config' => $config,
            'data_status' => 'insufficient',
        ];

        if (count($periods) < $config['min_periods'] || $totalResponses < $config['min_responses']) {
            $base['explanation'] = 'Insufficient comparable historical data. At least ' . $config['min_periods']
                . ' academic periods and ' . $config['min_responses'] . ' responses are required. Currently: '
                . count($periods) . ' period(s) and ' . (int) $totalResponses . ' response(s). No risk indicator is inferred.';
            $base['supporting_metrics'] = [
                'periods_available' => count($periods),
                'responses_available' => (int) $totalResponses,
                'period_averages' => $periods,
                'negative_ratio' => $negativeRatio,
            ];
            $this->store($base);
            return $base;
        }

        $averages = array_map(static fn ($p) => (float) $p['average'], $periods);
        $current = end($averages);
        $n = count($averages);
        $slope = $this->slope($averages);
        $forecast = $this->weightedMovingAverage($averages, min(4, $n));
        if ($forecast === null) {
            $forecast = $current;
        }

        $consecutiveDeclines = 0;
        for ($i = $n - 1; $i > 0; $i--) {
            if ($averages[$i] < $averages[$i - 1]) {
                $consecutiveDeclines++;
            } else {
                break;
            }
        }
        $change = round($forecast - $current, 2);

        $risk = 'stable';
        $reasons = [];
        if ($slope <= $config['declining_slope'] || $change <= -$config['forecast_change']) {
            $risk = 'needs_attention';
            $reasons[] = 'The recent trend is declining (slope ' . number_format($slope, 3) . ', forecast change ' . number_format($change, 2) . ').';
        }
        if ($consecutiveDeclines >= 2) {
            $risk = 'needs_attention';
            $reasons[] = $consecutiveDeclines . ' consecutive period declines were detected.';
        }
        if ($negativeRatio >= $config['negative_ratio']) {
            $risk = 'needs_attention';
            $reasons[] = 'Negative written feedback represents ' . round($negativeRatio * 100, 1) . '% of analyzed comments (threshold ' . round($config['negative_ratio'] * 100, 1) . '%).';
        }
        if ($risk !== 'needs_attention' && ($slope >= $config['improving_slope'] || $change >= $config['forecast_change'])) {
            $risk = 'improving';
            $reasons[] = 'The recent trend is improving (slope ' . number_format($slope, 3) . ', forecast change ' . number_format($change, 2) . ').';
        }
        if (empty($reasons)) {
            $reasons[] = 'No consecutive decline or negative-feedback threshold was triggered. The indicator is stable.';
        }

        $base['risk_level'] = $risk;
        $base['current_average'] = round($current, 2);
        $base['forecast_average'] = round($forecast, 2);
        $base['slope'] = round($slope, 4);
        $base['record_count'] = $n;
        $base['data_status'] = 'sufficient';
        $base['explanation'] = implode(' ', $reasons) . ' The estimate uses ' . $n . ' comparable period average(s) and '
            . (int) $totalResponses . ' response(s). This is a rule-based estimate, not a validated machine-learning prediction.';
        $base['supporting_metrics'] = [
            'period_averages' => $periods,
            'current_average' => round($current, 2),
            'weighted_forecast' => round($forecast, 2),
            'slope_per_period' => round($slope, 4),
            'consecutive_declines' => $consecutiveDeclines,
            'negative_ratio' => $negativeRatio,
            'negative_comments' => (int) ($negativeRow['negative'] ?? 0),
            'analyzed_comments' => (int) ($negativeRow['total'] ?? 0),
            'lowest_categories' => array_slice($categoryRows, 0, 4),
            'periods_used' => $n,
        ];
        $this->store($base);
        return $base;
    }

    public function latest(int $instructorId): ?array
    {
        $row = Database::fetch(
            'SELECT * FROM prediction_results WHERE instructor_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1',
            [$instructorId]
        );
        if (!$row) {
            return null;
        }
        $row['supporting_metrics'] = json_decode((string) ($row['supporting_metrics'] ?? '{}'), true) ?: [];
        $row['threshold_config'] = json_decode((string) ($row['threshold_config'] ?? '{}'), true) ?: [];
        return $row;
    }

    public function generateForAll(bool $demo): array
    {
        $instructors = Database::fetchAll(
            'SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = "instructor" AND u.is_demo = ? AND u.status = "active" ORDER BY u.last_name',
            [$demo ? 1 : 0]
        );
        $results = [];
        foreach ($instructors as $instructor) {
            $data = $this->generate((int) $instructor['id'], $demo);
            $data['instructor_name'] = $instructor['first_name'] . ' ' . $instructor['last_name'];
            $results[] = $data;
        }
        return $results;
    }

    public function allLatest(bool $demo): array
    {
        return Database::fetchAll(
            'SELECT pr.*, u.first_name, u.last_name,
                    (SELECT MAX(generated_at) FROM prediction_results p2 WHERE p2.instructor_id = pr.instructor_id) AS latest_at
             FROM prediction_results pr
             JOIN users u ON u.id = pr.instructor_id
             WHERE pr.id = (
                 SELECT p3.id FROM prediction_results p3
                 WHERE p3.instructor_id = pr.instructor_id
                 ORDER BY p3.generated_at DESC, p3.id DESC LIMIT 1
             ) AND u.is_demo = ?
             ORDER BY FIELD(pr.risk_level, "needs_attention", "improving", "stable", "insufficient_data"), pr.current_average DESC',
            [$demo ? 1 : 0]
        );
    }

    public function slope(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $xMean = ($n - 1) / 2;
        $yMean = array_sum($values) / $n;
        $num = 0.0;
        $den = 0.0;
        foreach ($values as $i => $y) {
            $num += ($i - $xMean) * ($y - $yMean);
            $den += ($i - $xMean) ** 2;
        }
        return $den > 0 ? $num / $den : 0.0;
    }

    public function weightedMovingAverage(array $values, int $window = 4): ?float
    {
        if (empty($values)) {
            return null;
        }
        $window = max(1, min($window, count($values)));
        $slice = array_slice($values, -$window);
        $weighted = 0.0;
        $weights = 0.0;
        foreach (array_values($slice) as $index => $value) {
            $weight = $index + 1;
            $weighted += $value * $weight;
            $weights += $weight;
        }
        return $weights > 0 ? $weighted / $weights : null;
    }

    private function store(array $data): void
    {
        Database::insert(
            'INSERT INTO prediction_results
             (instructor_id, academic_year_id, semester_id, scope, method, risk_level, current_average, forecast_average,
              slope, record_count, response_count, explanation, supporting_metrics, threshold_config, data_status, generated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['instructor_id'], $data['academic_year_id'], $data['semester_id'], $data['scope'], $data['method'],
                $data['risk_level'], $data['current_average'], $data['forecast_average'], $data['slope'],
                $data['record_count'], $data['response_count'], $data['explanation'],
                json_encode($data['supporting_metrics'], JSON_UNESCAPED_UNICODE),
                json_encode($data['threshold_config'], JSON_UNESCAPED_UNICODE), $data['data_status'],
            ]
        );
    }
}
