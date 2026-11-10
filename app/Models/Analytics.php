<?php

namespace App\Models;

use App\Core\Database;

/**
 * Aggregate SQL and statistical calculations. All rating metrics are normalized
 * to a five-point scale at submission time; incomparable scale definitions are
 * never mixed in the same aggregate.
 */
final class Analytics extends Model
{
    public function buildSessionWhere(array $filters, bool $demo, array &$params, string $alias = 'es'): string
    {
        $where = [$alias . '.is_demo = ?', $alias . ".status IN ('active','expired','closed')"];
        $params[] = $demo ? 1 : 0;

        $map = [
            'session_id' => $alias . '.id',
            'academic_year_id' => 'a.academic_year_id',
            'semester_id' => 'a.semester_id',
            'instructor_id' => 'a.instructor_id',
            'course_id' => 'a.course_id',
            'section_id' => 'a.section_id',
            'department_id' => 'c.department_id',
            'program_id' => 'sec.program_id',
        ];
        foreach ($map as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = $alias . '.opens_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = $alias . '.opens_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        return implode(' AND ', $where);
    }

    /**
     * Join fragment only. Callers must already provide
     * "FROM evaluation_sessions es" (or an equivalent JOIN).
     */
    public function sessionJoins(): string
    {
        return ' JOIN evaluation_assignments a ON a.id = es.assignment_id
                 JOIN users i ON i.id = a.instructor_id
                 JOIN courses c ON c.id = a.course_id
                 JOIN sections sec ON sec.id = a.section_id
                 JOIN programs p ON p.id = sec.program_id
                 JOIN departments d ON d.id = c.department_id
                 JOIN academic_years ay ON ay.id = a.academic_year_id
                 JOIN semesters sem ON sem.id = a.semester_id';
    }

    public function filteredCounts(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $sessions = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_sessions es' . $this->sessionJoins() . ' WHERE ' . $where, $params, 0);

        $params2 = [];
        $where2 = $this->buildSessionWhere($filters, $demo, $params2);
        $responses = (int) Database::scalar(
            'SELECT COUNT(DISTINCT r.id) FROM evaluation_responses r
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where2,
            $params2,
            0
        );

        $params3 = [];
        $where3 = $this->buildSessionWhere($filters, $demo, $params3);
        $eligible = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluation_eligibility_tokens t
             JOIN evaluation_sessions es ON es.id = t.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where3,
            $params3,
            0
        );

        $params4 = [];
        $where4 = $this->buildSessionWhere($filters, $demo, $params4);
        $submitted = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluation_participation ep
             JOIN evaluation_sessions es ON es.id = ep.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where4 . ' AND ep.status = "submitted"',
            $params4,
            0
        );

        return [
            'sessions' => $sessions,
            'responses' => $responses,
            'eligible' => $eligible,
            'submitted' => $submitted,
            'participation_rate' => $eligible > 0 ? round($submitted / $eligible * 100, 1) : 0.0,
        ];
    }

    public function filteredRating(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $row = Database::fetch(
            'SELECT COUNT(ra.id) AS rating_count, AVG(ra.numeric_value) AS average_rating,
                    MIN(ra.numeric_value) AS min_rating, MAX(ra.numeric_value) AS max_rating
             FROM response_answers ra
             JOIN evaluation_responses r ON r.id = ra.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where . ' AND ra.numeric_value IS NOT NULL',
            $params
        );
        return [
            'rating_count' => (int) ($row['rating_count'] ?? 0),
            'average' => $row['average_rating'] !== null ? round((float) $row['average_rating'], 2) : null,
            'min' => $row['min_rating'] !== null ? (float) $row['min_rating'] : null,
            'max' => $row['max_rating'] !== null ? (float) $row['max_rating'] : null,
        ];
    }

    public function categoryAverages(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        return Database::fetchAll(
            'SELECT q.category, ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(ra.id) AS response_count,
                    ROUND(STDDEV_POP(ra.numeric_value), 2) AS std_dev
             FROM response_answers ra
             JOIN survey_questions q ON q.id = ra.question_id
             JOIN evaluation_responses r ON r.id = ra.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where . ' AND ra.numeric_value IS NOT NULL
             GROUP BY q.category ORDER BY average DESC',
            $params
        );
    }

    public function sentimentDistribution(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $rows = Database::fetchAll(
            'SELECT sr.sentiment_label AS label, COUNT(*) AS count
             FROM sentiment_results sr
             JOIN evaluation_responses r ON r.id = sr.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where . '
             GROUP BY sr.sentiment_label',
            $params
        );
        $distribution = ['positive' => 0, 'neutral' => 0, 'negative' => 0, 'mixed' => 0];
        foreach ($rows as $row) {
            $distribution[$row['label']] = (int) $row['count'];
        }
        $out = [];
        foreach ($distribution as $label => $count) {
            $out[] = ['label' => $label, 'count' => $count];
        }
        return $out;
    }

    public function themeCounts(array $filters, bool $demo, string $polarity = ''): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $sql = 'SELECT ft.theme, ft.polarity, COUNT(DISTINCT ft.response_id) AS count
                FROM feedback_themes ft
                JOIN evaluation_responses r ON r.id = ft.response_id
                JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
                WHERE ' . $where;
        if ($polarity !== '') {
            $sql .= ' AND ft.polarity = ?';
            $params[] = $polarity;
        }
        $sql .= ' GROUP BY ft.theme, ft.polarity ORDER BY count DESC, ft.theme';
        return Database::fetchAll($sql, $params);
    }

    public function ratingDistribution(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $rows = Database::fetchAll(
            'SELECT ra.numeric_value AS rating, COUNT(*) AS count
             FROM response_answers ra
             JOIN evaluation_responses r ON r.id = ra.response_id
             JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
             WHERE ' . $where . ' AND ra.numeric_value IS NOT NULL
             GROUP BY ra.numeric_value ORDER BY ra.numeric_value',
            $params
        );
        return array_map(static fn ($r) => ['rating' => (float) $r['rating'], 'count' => (int) $r['count']], $rows);
    }

    public function sessionTrend(array $filters, bool $demo, int $limit = 12): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $rows = Database::fetchAll(
            'SELECT es.id, es.session_code, es.opens_at, c.code AS course_code, sec.code AS section_code,
                    i.first_name, i.last_name, ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(DISTINCT r.id) AS response_count
             FROM evaluation_sessions es' . $this->sessionJoins() . '
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE ' . $where . '
             GROUP BY es.id, es.session_code, es.opens_at, c.code, sec.code, i.first_name, i.last_name
             ORDER BY es.opens_at DESC LIMIT ' . max(1, min(50, $limit)),
            $params
        );
        return array_reverse($rows);
    }

    public function semesterTrend(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        return Database::fetchAll(
            'SELECT ay.name AS academic_year, sem.name AS semester, sem.sequence,
                    ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(DISTINCT r.id) AS response_count,
                    COUNT(DISTINCT es.id) AS session_count
             FROM evaluation_sessions es' . $this->sessionJoins() . '
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE ' . $where . '
             GROUP BY ay.id, ay.name, ay.start_date, sem.id, sem.name, sem.sequence
             ORDER BY ay.start_date ASC, sem.sequence ASC',
            $params
        );
    }

    public function instructorComparison(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        return Database::fetchAll(
            'SELECT i.id, i.first_name, i.last_name, d.code AS department_code, d.name AS department_name,
                    ROUND(AVG(ra.numeric_value), 2) AS average, COUNT(DISTINCT r.id) AS response_count,
                    COUNT(DISTINCT es.id) AS session_count
             FROM evaluation_sessions es' . $this->sessionJoins() . '
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE ' . $where . '
             GROUP BY i.id, i.first_name, i.last_name, d.id, d.code, d.name
             ORDER BY average DESC, i.last_name',
            $params
        );
    }

    public function departmentComparison(array $filters, bool $demo): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        return Database::fetchAll(
            'SELECT d.id, d.code, d.name, ROUND(AVG(ra.numeric_value), 2) AS average,
                    COUNT(DISTINCT r.id) AS response_count,
                    COUNT(DISTINCT a.instructor_id) AS instructor_count
             FROM evaluation_sessions es' . $this->sessionJoins() . '
             JOIN evaluation_responses r ON r.evaluation_session_id = es.id
             JOIN response_answers ra ON ra.response_id = r.id AND ra.numeric_value IS NOT NULL
             WHERE ' . $where . '
             GROUP BY d.id, d.code, d.name
             ORDER BY average DESC',
            $params
        );
    }

    public function questionStats(int $versionId, ?int $sessionId = null): array
    {
        $params = [$versionId];
        $sessionClause = '';
        if ($sessionId) {
            $sessionClause = ' AND r.evaluation_session_id = ?';
            $params[] = $sessionId;
        }
        $rows = Database::fetchAll(
            'SELECT q.id, q.question_text, q.category, q.question_type, q.order_index,
                    COUNT(ra.id) AS response_count,
                    ROUND(AVG(ra.numeric_value), 2) AS average,
                    ROUND(STDDEV_POP(ra.numeric_value), 2) AS std_dev,
                    SUM(CASE WHEN ra.numeric_value >= 4 THEN 1 ELSE 0 END) AS agreeable,
                    SUM(CASE WHEN ra.numeric_value <= 2 THEN 1 ELSE 0 END) AS disagreeable
             FROM survey_questions q
             LEFT JOIN response_answers ra ON ra.question_id = q.id
             LEFT JOIN evaluation_responses r ON r.id = ra.response_id' . ($sessionId ? '' : '') . '
             WHERE q.survey_version_id = ?' . $sessionClause . '
             GROUP BY q.id, q.question_text, q.category, q.question_type, q.order_index
             ORDER BY q.order_index',
            $params
        );
        return array_map(static function (array $row): array {
            $count = (int) $row['response_count'];
            $row['average'] = $row['average'] !== null ? (float) $row['average'] : null;
            $row['std_dev'] = $row['std_dev'] !== null ? (float) $row['std_dev'] : null;
            $row['agreement_percentage'] = $count > 0 ? round((int) $row['agreeable'] / $count * 100, 1) : 0.0;
            return $row;
        }, $rows);
    }

    public function comments(array $filters, bool $demo, int $limit = 50): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $sql = 'SELECT r.id, r.submitted_on, sr.sentiment_label, sr.excerpt, sr.confidence,
                       c.code AS course_code, sec.code AS section_code, es.session_code
                FROM evaluation_responses r
                JOIN sentiment_results sr ON sr.response_id = r.id
                JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
                WHERE ' . $where . " AND sr.excerpt IS NOT NULL AND sr.excerpt <> ''
                ORDER BY r.submitted_on DESC, r.id DESC LIMIT " . max(1, min(200, $limit));
        return Database::fetchAll($sql, $params);
    }

    public function responseTexts(array $filters, bool $demo, int $limit = 500): array
    {
        $params = [];
        $where = $this->buildSessionWhere($filters, $demo, $params);
        $sql = 'SELECT sr.response_id, sr.sentiment_label, sr.excerpt
                FROM sentiment_results sr
                JOIN evaluation_responses r ON r.id = sr.response_id
                JOIN evaluation_sessions es ON es.id = r.evaluation_session_id' . $this->sessionJoins() . '
                WHERE ' . $where . ' ORDER BY r.id DESC LIMIT ' . max(1, min(1000, $limit));
        return Database::fetchAll($sql, $params);
    }

    public static function mean(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null && is_numeric($v)));
        if (empty($values)) {
            return null;
        }
        return array_sum(array_map('floatval', $values)) / count($values);
    }

    /**
     * Weighted mean = Σ(f × x) / N. $frequencies maps score => count.
     */
    public static function weightedMean(array $frequencies): ?float
    {
        $total = 0.0;
        $n = 0;
        foreach ($frequencies as $score => $frequency) {
            if (!is_numeric($score) || !is_numeric($frequency) || $frequency <= 0) {
                continue;
            }
            $total += (float) $score * (float) $frequency;
            $n += (float) $frequency;
        }
        return $n > 0 ? $total / $n : null;
    }

    public static function populationSd(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null && is_numeric($v)));
        $n = count($values);
        if ($n === 0) {
            return null;
        }
        $mean = array_sum(array_map('floatval', $values)) / $n;
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += ((float) $value - $mean) ** 2;
        }
        return sqrt($sum / $n);
    }

    public static function sampleSd(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null && is_numeric($v)));
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum(array_map('floatval', $values)) / $n;
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += ((float) $value - $mean) ** 2;
        }
        return sqrt($sum / ($n - 1));
    }

    public static function percentage(int|float $part, int|float $whole, int $decimals = 1): float
    {
        return $whole > 0 ? round($part / $whole * 100, $decimals) : 0.0;
    }

    public static function participationRate(int $submitted, int $eligible): float
    {
        return $eligible > 0 ? round($submitted / $eligible * 100, 1) : 0.0;
    }

    /**
     * Normalize a numeric rating to the common five-point analysis scale.
     */
    public static function normalizeToFive(float $value, float $min = 1.0, float $max = 5.0): ?float
    {
        if ($max <= $min) {
            return null;
        }
        $value = max($min, min($max, $value));
        return round(1 + (($value - $min) / ($max - $min)) * 4, 2);
    }
}
