<?php

namespace App\Models;

/**
 * Faculty (ratee) comments. Append-only: previous comments are never
 * overwritten, preserving the full comment history.
 */
final class RateeComment extends BaseModel
{
    protected static string $table = 'ratee_comments';
    protected static string $primaryKey = 'comment_id';

    public static function forObservation(int $observationId): array
    {
        return static::fetchAll(
            'SELECT * FROM ratee_comments WHERE observation_id = ? ORDER BY created_at ASC, comment_id ASC',
            [$observationId]
        );
    }

    public static function latestForObservation(int $observationId): ?array
    {
        return static::fetchOne(
            'SELECT * FROM ratee_comments WHERE observation_id = ? ORDER BY comment_id DESC LIMIT 1',
            [$observationId]
        );
    }

    public static function add(int $observationId, int $facultyId, string $text): int
    {
        return static::insert([
            'observation_id' => $observationId,
            'faculty_id'     => $facultyId,
            'comment_text'   => $text,
        ]);
    }

    public static function countForFaculty(int $facultyId): int
    {
        return (int) static::scalar('SELECT COUNT(*) FROM ratee_comments WHERE faculty_id = ?', [$facultyId]);
    }

    public static function recentForFaculty(int $facultyId, int $limit = 5): array
    {
        return static::fetchAll(
            'SELECT rc.*, o.lesson_topic, c.subject_code
             FROM ratee_comments rc
             JOIN observations o ON o.observation_id = rc.observation_id
             JOIN classes c ON c.class_id = o.class_id
             WHERE rc.faculty_id = ?
             ORDER BY rc.created_at DESC, rc.comment_id DESC
             LIMIT ' . $limit,
            [$facultyId]
        );
    }
}
