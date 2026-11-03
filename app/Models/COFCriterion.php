<?php

namespace App\Models;

/**
 * The configurable 20-item ACLC Classroom Observation Form.
 * 13 Teaching Competence + 7 Personality criteria, each rated 1–5.
 */
final class COFCriterion extends BaseModel
{
    protected static string $table = 'cof_criteria';
    protected static string $primaryKey = 'criterion_id';

    public const TEACHING = 'Teaching Competence';
    public const PERSONALITY = 'Personality';
    public const TEACHING_COUNT = 13;
    public const PERSONALITY_COUNT = 7;
    public const TOTAL_COUNT = 20;

    public static function active(): array
    {
        return static::fetchAll(
            'SELECT * FROM cof_criteria WHERE is_active = 1 ORDER BY display_order ASC, criterion_number ASC'
        );
    }

    public static function allOrdered(): array
    {
        return static::fetchAll(
            'SELECT * FROM cof_criteria ORDER BY display_order ASC, criterion_number ASC'
        );
    }

    public static function byCategory(?array $criteria = null): array
    {
        $criteria = $criteria ?? self::active();
        return [
            self::TEACHING   => array_values(array_filter($criteria, static fn (array $c): bool => $c['category'] === self::TEACHING)),
            self::PERSONALITY => array_values(array_filter($criteria, static fn (array $c): bool => $c['category'] === self::PERSONALITY)),
        ];
    }

    public static function activeForCategory(string $category): array
    {
        return static::fetchAll(
            'SELECT * FROM cof_criteria WHERE is_active = 1 AND category = ? ORDER BY display_order ASC, criterion_number ASC',
            [$category]
        );
    }

    public static function structureIsValid(): bool
    {
        $rows = self::active();
        $teaching = 0;
        $personality = 0;
        $numbers = [];
        foreach ($rows as $row) {
            $numbers[] = (int) $row['criterion_number'];
            if ($row['category'] === self::TEACHING) {
                $teaching++;
            } elseif ($row['category'] === self::PERSONALITY) {
                $personality++;
            }
        }
        sort($numbers);
        return $teaching === self::TEACHING_COUNT
            && $personality === self::PERSONALITY_COUNT
            && $numbers === range(1, 20);
    }

    public static function structureReport(): array
    {
        $rows = self::active();
        $teaching = count(array_filter($rows, static fn (array $r): bool => $r['category'] === self::TEACHING));
        $personality = count(array_filter($rows, static fn (array $r): bool => $r['category'] === self::PERSONALITY));
        return [
            'teaching'    => $teaching,
            'personality' => $personality,
            'total'       => count($rows),
            'valid'       => self::structureIsValid(),
        ];
    }

    public static function updateCriterion(int $id, array $data): bool
    {
        $allowed = [];
        foreach (['criterion_text', 'category', 'display_order', 'is_active', 'source_note'] as $field) {
            if (array_key_exists($field, $data)) {
                $allowed[$field] = $data[$field];
            }
        }
        return $allowed !== [] && static::updateById($id, $allowed);
    }

    public static function countsForValidation(): array
    {
        $rows = static::fetchAll(
            "SELECT category, COUNT(*) AS total FROM cof_criteria WHERE is_active = 1 GROUP BY category"
        );
        $out = [self::TEACHING => 0, self::PERSONALITY => 0];
        foreach ($rows as $row) {
            $out[$row['category']] = (int) $row['total'];
        }
        return $out;
    }
}
