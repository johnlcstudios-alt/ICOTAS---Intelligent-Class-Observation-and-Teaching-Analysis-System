<?php

namespace App\Models;

/**
 * Exceptional amendments to published observations. Original values are
 * stored as JSON so the pre-amendment state is never lost.
 */
final class ObservationAmendment extends BaseModel
{
    protected static string $table = 'observation_amendments';
    protected static string $primaryKey = 'amendment_id';

    public static function forObservation(int $observationId): array
    {
        return static::fetchAll(
            'SELECT am.*, u.full_name AS changed_by_name
             FROM observation_amendments am
             JOIN users u ON u.user_id = am.changed_by
             WHERE am.observation_id = ?
             ORDER BY am.created_at DESC, am.amendment_id DESC',
            [$observationId]
        );
    }

    public static function begin(int $observationId, int $changedBy, string $reason, array $original): int
    {
        return static::insert([
            'observation_id'          => $observationId,
            'changed_by'              => $changedBy,
            'reason'                  => $reason,
            'original_data_reference' => json_encode($original, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'amendment_status'        => 'In Progress',
        ]);
    }

    public static function complete(int $amendmentId, array $amended): void
    {
        static::updateById($amendmentId, [
            'amended_data_reference' => json_encode($amended, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'amendment_status'       => 'Completed',
            'completed_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    public static function countForObservation(int $observationId): int
    {
        return (int) static::scalar(
            'SELECT COUNT(*) FROM observation_amendments WHERE observation_id = ?',
            [$observationId]
        );
    }
}
