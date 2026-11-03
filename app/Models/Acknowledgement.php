<?php

namespace App\Models;

/**
 * Faculty acknowledgement of a published evaluation (receipt, not agreement).
 */
final class Acknowledgement extends BaseModel
{
    protected static string $table = 'faculty_acknowledgements';
    protected static string $primaryKey = 'acknowledgement_id';

    public static function forObservation(int $observationId, int $facultyId): ?array
    {
        return static::fetchOne(
            'SELECT * FROM faculty_acknowledgements WHERE observation_id = ? AND faculty_id = ? LIMIT 1',
            [$observationId, $facultyId]
        );
    }

    public static function acknowledge(int $observationId, int $facultyId, ?string $remarks = null): int
    {
        $existing = self::forObservation($observationId, $facultyId);
        if ($existing !== null) {
            static::updateById((int) $existing['acknowledgement_id'], [
                'acknowledgement_remarks' => $remarks,
                'acknowledged_at'         => date('Y-m-d H:i:s'),
            ]);
            return (int) $existing['acknowledgement_id'];
        }

        return static::insert([
            'observation_id'         => $observationId,
            'faculty_id'             => $facultyId,
            'acknowledgement_status' => 'Acknowledged',
            'acknowledgement_remarks'=> $remarks,
            'acknowledged_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public static function countForFaculty(int $facultyId): int
    {
        return (int) static::scalar(
            'SELECT COUNT(*) FROM faculty_acknowledgements WHERE faculty_id = ?',
            [$facultyId]
        );
    }

    public static function countForObservation(int $observationId): int
    {
        return (int) static::scalar(
            'SELECT COUNT(*) FROM faculty_acknowledgements WHERE observation_id = ?',
            [$observationId]
        );
    }

    public static function acknowledgedObservationIds(int $facultyId): array
    {
        $rows = static::fetchAll(
            'SELECT observation_id FROM faculty_acknowledgements WHERE faculty_id = ?',
            [$facultyId]
        );
        return array_map(static fn (array $row): int => (int) $row['observation_id'], $rows);
    }
}
