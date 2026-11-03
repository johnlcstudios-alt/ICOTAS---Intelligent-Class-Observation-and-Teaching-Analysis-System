<?php

namespace App\Models;

/**
 * Individual COF ratings. One row per criterion per observation.
 */
final class ObservationRating extends BaseModel
{
    protected static string $table = 'observation_ratings';
    protected static string $primaryKey = 'rating_id';

    public static function forObservation(int $observationId): array
    {
        return static::fetchAll(
            'SELECT * FROM observation_ratings WHERE observation_id = ? ORDER BY criterion_number ASC',
            [$observationId]
        );
    }

    /**
     * Ratings keyed by criterion number, joined with criterion text for display.
     */
    public static function detailedForObservation(int $observationId): array
    {
        return static::fetchAll(
            'SELECT r.*, c.criterion_text, c.display_order
             FROM observation_ratings r
             LEFT JOIN cof_criteria c ON c.criterion_number = r.criterion_number
             WHERE r.observation_id = ?
             ORDER BY r.criterion_number ASC',
            [$observationId]
        );
    }

    public static function scoreMap(int $observationId): array
    {
        $rows = static::forObservation($observationId);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['criterion_number']] = (int) $row['score'];
        }
        return $map;
    }

    public static function replaceForObservation(int $observationId, array $ratings, string $teaching, string $personality): void
    {
        $pdo = static::pdo();
        $delete = $pdo->prepare('DELETE FROM observation_ratings WHERE observation_id = ?');
        $delete->execute([$observationId]);

        $insert = $pdo->prepare(
            'INSERT INTO observation_ratings (observation_id, criterion_number, category, score)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($ratings as $number => $score) {
            $category = (int) $number <= 13 ? $teaching : $personality;
            $insert->execute([$observationId, (int) $number, $category, (int) $score]);
        }
    }

    public static function deleteForObservation(int $observationId): void
    {
        $stmt = static::pdo()->prepare('DELETE FROM observation_ratings WHERE observation_id = ?');
        $stmt->execute([$observationId]);
    }

    public static function categoryDistribution(int $facultyId = 0): array
    {
        $sql = 'SELECT r.category, r.score, COUNT(*) AS total
                FROM observation_ratings r
                JOIN observations o ON o.observation_id = r.observation_id
                WHERE o.status = \'Published\'';
        $params = [];
        if ($facultyId > 0) {
            $sql .= ' AND o.faculty_id = ?';
            $params[] = $facultyId;
        }
        $sql .= ' GROUP BY r.category, r.score ORDER BY r.category, r.score';
        return static::fetchAll($sql, $params);
    }
}
