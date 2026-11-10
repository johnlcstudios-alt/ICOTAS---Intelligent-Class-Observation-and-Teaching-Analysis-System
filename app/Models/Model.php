<?php

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM ' . $this->table . ' WHERE ' . $this->primaryKey . ' = ? LIMIT 1', [$id]);
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        return Database::fetchAll('SELECT * FROM ' . $this->table . ' ORDER BY ' . $orderBy);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . $where, $params, 0);
    }

    protected function db(): PDO
    {
        return Database::connection();
    }
}
