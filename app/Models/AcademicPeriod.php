<?php

namespace App\Models;

use App\Core\Database;

final class AcademicPeriod extends BaseModel
{
    protected static string $table = 'academic_periods';
    protected static string $primaryKey = 'period_id';

    public static function allOrdered(): array
    {
        return static::fetchAll(
            'SELECT * FROM academic_periods ORDER BY period_start DESC, period_id DESC'
        );
    }

    public static function withCounts(): array
    {
        return static::fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM classes c WHERE c.period_id = p.period_id) AS class_count,
                    (SELECT COUNT(*) FROM observations o WHERE o.period_id = p.period_id) AS observation_count
             FROM academic_periods p
             ORDER BY p.period_start DESC, p.period_id DESC"
        );
    }

    public static function current(): ?array
    {
        return static::fetchOne("SELECT * FROM academic_periods WHERE status = 'Current' LIMIT 1");
    }

    public static function options(): array
    {
        return static::fetchAll(
            'SELECT period_id, academic_year, semester, status, period_start, period_end
             FROM academic_periods
             ORDER BY period_start DESC, period_id DESC'
        );
    }

    public static function hasObservations(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM observations WHERE period_id = ?', [$id]) > 0;
    }

    public static function whereYearSemester(string $year, string $semester): ?array
    {
        return static::fetchOne(
            'SELECT * FROM academic_periods WHERE academic_year = ? AND semester = ? LIMIT 1',
            [$year, $semester]
        );
    }

    public static function hasClassRecords(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM class_records WHERE period_id = ?', [$id]) > 0;
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function setCurrent(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            $stmt = static::pdo()->prepare("UPDATE academic_periods SET status = 'Closed' WHERE status = 'Current' AND period_id <> ?");
            $stmt->execute([$id]);
            static::updateById($id, ['status' => 'Current']);
        });
    }

    public static function label(array $period): string
    {
        return $period['academic_year'] . ' · ' . semester_abbr((string) $period['semester']);
    }
}
