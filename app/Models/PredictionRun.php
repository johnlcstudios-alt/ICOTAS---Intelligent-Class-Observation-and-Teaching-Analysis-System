<?php

namespace App\Models;

/**
 * Persisted Simple Linear Regression runs.
 */
final class PredictionRun extends BaseModel
{
    protected static string $table = 'prediction_runs';
    protected static string $primaryKey = 'prediction_id';

    public static function latestForFaculty(int $facultyId): ?array
    {
        return static::fetchOne(
            'SELECT * FROM prediction_runs WHERE faculty_id = ? ORDER BY generated_at DESC, prediction_id DESC LIMIT 1',
            [$facultyId]
        );
    }

    public static function historyForFaculty(int $facultyId, int $limit = 10): array
    {
        return static::fetchAll(
            'SELECT * FROM prediction_runs WHERE faculty_id = ? ORDER BY generated_at DESC, prediction_id DESC LIMIT ' . max(1, $limit),
            [$facultyId]
        );
    }

    public static function saveRun(array $data): int
    {
        return static::insert($data);
    }

    public static function latestPerFaculty(): array
    {
        return static::fetchAll(
            "SELECT pr.*, u.full_name AS faculty_name, d.department_name
             FROM prediction_runs pr
             JOIN users u ON u.user_id = pr.faculty_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             JOIN (
                 SELECT faculty_id, MAX(prediction_id) AS latest_id
                 FROM prediction_runs
                 GROUP BY faculty_id
             ) latest ON latest.latest_id = pr.prediction_id
             ORDER BY u.full_name ASC"
        );
    }
}
