<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Thin, explicit model base. All SQL uses prepared statements.
 */
abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    protected static function pdo(): PDO
    {
        return Database::connection();
    }

    protected static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = static::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = static::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = static::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        return static::fetchOne(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ? LIMIT 1',
            [$id]
        );
    }

    public static function insert(array $data): int
    {
        $columns = [];
        $placeholders = [];
        $values = [];
        foreach ($data as $column => $value) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $column)) {
                throw new \RuntimeException('Invalid column name.');
            }
            $columns[] = '`' . $column . '`';
            $placeholders[] = '?';
            $values[] = $value;
        }
        $sql = 'INSERT INTO `' . static::$table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = static::pdo()->prepare($sql);
        $stmt->execute($values);
        return (int) static::pdo()->lastInsertId();
    }

    public static function updateById(int $id, array $data): bool
    {
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $column)) {
                throw new \RuntimeException('Invalid column name.');
            }
            $sets[] = '`' . $column . '` = ?';
            $values[] = $value;
        }
        if ($sets === []) {
            return false;
        }
        $values[] = $id;
        $sql = 'UPDATE `' . static::$table . '` SET ' . implode(', ', $sets)
            . ' WHERE `' . static::$primaryKey . '` = ?';
        $stmt = static::pdo()->prepare($sql);
        return $stmt->execute($values);
    }

    public static function countAll(): int
    {
        return (int) static::scalar('SELECT COUNT(*) FROM `' . static::$table . '`');
    }
}
