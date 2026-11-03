<?php

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/**
 * PDO connection factory and transaction helper.
 * All queries use prepared statements; emulation is disabled.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('ICOTAS database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /**
     * Run a callback inside a transaction, rolling back on any exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function schemaPath(): string
    {
        return BASE_PATH . '/database/schema.sql';
    }

    public static function tableExists(string $table): bool
    {
        try {
            $stmt = self::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
