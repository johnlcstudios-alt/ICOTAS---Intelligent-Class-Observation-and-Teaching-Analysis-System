<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Schema-driven data access for the unlinked developer console.
 *
 * Table and column identifiers are never taken from request text directly.
 * Every identifier is resolved against information_schema for the active
 * database and then re-emitted with backticks. Values always travel through
 * prepared statements.
 *
 * The service also owns the SQL-console guardrails: one statement, no
 * server-level or file-side-effect statements, and explicit confirmation for
 * writes and destructive DDL.
 */
final class DeveloperDataService
{
    /** Columns whose values must not be exposed or edited in the generic browser. */
    private const SENSITIVE_COLUMNS = [
        'password_hash',
        'session_hash',
        'token_hash',
        'token_cipher',
        'access_token_hash',
        'access_token_cipher',
        'code_hash',
        'ip_hash',
    ];

    private const READ_KEYWORDS = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];

    private const DESTRUCTIVE_KEYWORDS = ['DROP', 'TRUNCATE', 'RENAME', 'ALTER'];

    private const BLOCKED_KEYWORDS = [
        'USE', 'SET', 'GRANT', 'REVOKE', 'FLUSH', 'KILL', 'SHUTDOWN',
        'INSTALL', 'UNINSTALL', 'LOAD', 'RESET', 'PURGE', 'CHANGE',
        'START', 'STOP', 'PREPARE', 'EXECUTE', 'DEALLOCATE', 'CALL',
        'DO', 'HANDLER', 'XA', 'SAVEPOINT', 'RELEASE', 'ROLLBACK',
        'COMMIT', 'BEGIN', 'LOCK', 'UNLOCK',
    ];

    private const OMIT = '__icotas_omit__';

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $columnCache = [];

    /**
     * Base tables in the active database with lightweight storage metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public function tables(): array
    {
        $rows = Database::fetchAll(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_estimate,
                    DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length, TABLE_COMMENT AS comment
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"
             ORDER BY TABLE_NAME ASC'
        );

        return array_map(static function (array $row): array {
            return [
                'name' => (string) $row['name'],
                'engine' => (string) ($row['engine'] ?? ''),
                'rows_estimate' => (int) ($row['rows_estimate'] ?? 0),
                'data_length' => (int) ($row['data_length'] ?? 0),
                'index_length' => (int) ($row['index_length'] ?? 0),
                'comment' => (string) ($row['comment'] ?? ''),
            ];
        }, $rows);
    }

    public function tableExists(string $table): bool
    {
        foreach ($this->tables() as $row) {
            if ($row['name'] === $table) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalized column metadata for a whitelisted table.
     *
     * @return array<int,array<string,mixed>>
     */
    public function columns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        $rows = Database::fetchAll(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_KEY AS column_key,
                    EXTRA AS extra, CHARACTER_MAXIMUM_LENGTH AS max_length,
                    NUMERIC_PRECISION AS numeric_precision, NUMERIC_SCALE AS numeric_scale,
                    COLUMN_COMMENT AS comment
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION ASC',
            [$table]
        );

        $foreignRows = Database::fetchAll(
            'SELECT COLUMN_NAME AS column_name, REFERENCED_TABLE_NAME AS ref_table, REFERENCED_COLUMN_NAME AS ref_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        );
        $foreign = [];
        foreach ($foreignRows as $row) {
            $foreign[(string) $row['column_name']] = [
                'table' => (string) $row['ref_table'],
                'column' => (string) $row['ref_column'],
            ];
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $dataType = strtolower((string) $row['data_type']);
            $columnType = strtolower((string) $row['column_type']);
            $extra = strtolower((string) ($row['extra'] ?? ''));
            $isPrimary = (string) $row['column_key'] === 'PRI';
            $isAuto = str_contains($extra, 'auto_increment');
            $isGenerated = str_contains($extra, 'generated') || str_contains($extra, 'virtual') || str_contains($extra, 'stored');
            $isBinary = in_array($dataType, [
                'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob',
                'bit', 'geometry', 'point', 'linestring', 'polygon', 'multipoint',
                'multilinestring', 'multipolygon', 'geometrycollection',
            ], true);
            $isSensitive = in_array($name, self::SENSITIVE_COLUMNS, true);
            $enumValues = [];
            if ($dataType === 'enum' && preg_match('/^enum\((.*)\)$/i', $columnType, $m)) {
                preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $matches);
                $enumValues = array_map(static fn (string $value): string => str_replace("\\'", "'", $value), $matches[1] ?? []);
            }

            $inputType = 'text';
            if ($isBinary || $isSensitive || $isGenerated) {
                $inputType = 'readonly';
            } elseif ($enumValues !== []) {
                $inputType = 'enum';
            } elseif ($dataType === 'tinyint' && preg_match('/^tinyint\(1\)/', $columnType)) {
                $inputType = 'boolean';
            } elseif (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
                $inputType = 'number';
            } elseif (in_array($dataType, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
                $inputType = 'decimal';
            } elseif ($dataType === 'date') {
                $inputType = 'date';
            } elseif (in_array($dataType, ['datetime', 'timestamp'], true)) {
                $inputType = 'datetime';
            } elseif ($dataType === 'time') {
                $inputType = 'time';
            } elseif ($dataType === 'year') {
                $inputType = 'year';
            } elseif (in_array($dataType, ['text', 'tinytext', 'mediumtext', 'longtext', 'json'], true)) {
                $inputType = 'textarea';
            }
            if (isset($foreign[$name]) && !in_array($inputType, ['readonly'], true)) {
                $inputType = 'foreign';
            }

            $columns[] = [
                'name' => $name,
                'label' => ucwords(str_replace('_', ' ', $name)),
                'data_type' => $dataType,
                'column_type' => $columnType,
                'nullable' => (string) $row['is_nullable'] === 'YES',
                'default' => $row['column_default'],
                'is_primary' => $isPrimary,
                'is_auto_increment' => $isAuto,
                'is_generated' => $isGenerated,
                'is_binary' => $isBinary,
                'is_sensitive' => $isSensitive,
                'editable' => !$isAuto && !$isGenerated && !$isBinary && !$isSensitive,
                'required' => (string) $row['is_nullable'] !== 'YES'
                    && $row['column_default'] === null
                    && !$isAuto && !$isGenerated,
                'max_length' => $row['max_length'] !== null ? (int) $row['max_length'] : null,
                'numeric_precision' => $row['numeric_precision'] !== null ? (int) $row['numeric_precision'] : null,
                'numeric_scale' => $row['numeric_scale'] !== null ? (int) $row['numeric_scale'] : null,
                'input_type' => $inputType,
                'enum_values' => $enumValues,
                'foreign' => $foreign[$name] ?? null,
                'comment' => (string) ($row['comment'] ?? ''),
            ];
        }

        return $this->columnCache[$table] = $columns;
    }

    /**
     * @return array<int,string>
     */
    public function primaryKeys(string $table): array
    {
        $keys = [];
        foreach ($this->columns($table) as $column) {
            if ($column['is_primary']) {
                $keys[] = (string) $column['name'];
            }
        }
        return $keys;
    }

    public function primaryKey(string $table): ?string
    {
        $keys = $this->primaryKeys($table);
        return count($keys) === 1 ? $keys[0] : null;
    }

    public function hasCompositePrimaryKey(string $table): bool
    {
        return count($this->primaryKeys($table)) > 1;
    }

    /**
     * Paginated rows with an optional contains-search over safe text columns.
     */
    public function paginate(string $table, int $page = 1, int $perPage = 25, string $search = ''): array
    {
        $columns = $this->columns($table);
        if ($columns === []) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 1, 'columns' => []];
        }

        $where = '1 = 1';
        $params = [];
        $searchColumns = [];
        if ($search !== '') {
            $searchable = array_filter($columns, static function (array $column): bool {
                return !$column['is_binary'] && !$column['is_sensitive']
                    && in_array($column['data_type'], ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true);
            });
            foreach (array_slice(array_values($searchable), 0, 8) as $column) {
                $searchColumns[] = '`' . $column['name'] . '` LIKE ?';
                $params[] = '%' . $search . '%';
            }
            if ($searchColumns !== []) {
                $where = '(' . implode(' OR ', $searchColumns) . ')';
            }
        }

        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where,
            $params,
            0
        );
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $primary = $this->primaryKey($table);
        $order = $primary !== null
            ? '`' . $primary . '` DESC'
            : '`' . $columns[0]['name'] . '` ASC';

        $rows = Database::fetchAll(
            'SELECT * FROM `' . $table . '` WHERE ' . $where . ' ORDER BY ' . $order
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'columns' => $columns,
            'order_by' => $order,
            'search_columns' => $searchColumns,
        ];
    }

    public function find(string $table, string $id): ?array
    {
        $primary = $this->primaryKey($table);
        if ($primary === null) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM `' . $table . '` WHERE `' . $primary . '` = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return array{ok:bool,id?:int,affected?:int,error?:string}
     */
    public function create(string $table, array $input): array
    {
        $columns = $this->columns($table);
        if ($columns === []) {
            return ['ok' => false, 'error' => 'That table is not available.'];
        }

        $names = [];
        $values = [];
        foreach ($columns as $column) {
            if (!$column['editable'] || !array_key_exists($column['name'], $input)) {
                continue;
            }
            [$ok, $value] = $this->normalizeValue($column, $input[$column['name']]);
            if (!$ok) {
                return ['ok' => false, 'error' => (string) $value];
            }
            if ($value === self::OMIT) {
                continue;
            }
            $names[] = $column['name'];
            $values[] = $value;
        }

        try {
            if ($names === []) {
                Database::execute('INSERT INTO `' . $table . '` () VALUES ()');
                return ['ok' => true, 'id' => (int) Database::connection()->lastInsertId(), 'affected' => 1];
            }
            $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $names) . '`) VALUES ('
                . implode(', ', array_fill(0, count($names), '?')) . ')';
            Database::execute($sql, $values);
            return ['ok' => true, 'id' => (int) Database::connection()->lastInsertId(), 'affected' => 1];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,affected?:int,error?:string}
     */
    public function update(string $table, string $id, array $input): array
    {
        $primary = $this->primaryKey($table);
        if ($primary === null) {
            return ['ok' => false, 'error' => 'This table has no single-column primary key, so rows cannot be edited safely.'];
        }

        $sets = [];
        $values = [];
        foreach ($this->columns($table) as $column) {
            if ($column['is_primary'] || !$column['editable'] || !array_key_exists($column['name'], $input)) {
                continue;
            }
            [$ok, $value] = $this->normalizeValue($column, $input[$column['name']]);
            if (!$ok) {
                return ['ok' => false, 'error' => (string) $value];
            }
            if ($value === self::OMIT) {
                continue;
            }
            $sets[] = '`' . $column['name'] . '` = ?';
            $values[] = $value;
        }

        if ($sets === []) {
            return ['ok' => false, 'error' => 'There is nothing to update.'];
        }

        $values[] = $id;
        try {
            $affected = Database::execute(
                'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `' . $primary . '` = ?',
                $values
            );
            return ['ok' => true, 'affected' => $affected];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,affected?:int,error?:string}
     */
    public function delete(string $table, string $id): array
    {
        $primary = $this->primaryKey($table);
        if ($primary === null) {
            return ['ok' => false, 'error' => 'This table has no single-column primary key, so rows cannot be deleted safely.'];
        }
        try {
            $affected = Database::execute(
                'DELETE FROM `' . $table . '` WHERE `' . $primary . '` = ? LIMIT 1',
                [$id]
            );
            return ['ok' => true, 'affected' => $affected];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Options for a foreign-key select, limited to keep the form responsive.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function lookupOptions(array $column): array
    {
        $foreign = $column['foreign'] ?? null;
        if (!is_array($foreign)) {
            return [];
        }
        $refTable = (string) ($foreign['table'] ?? '');
        $refColumn = (string) ($foreign['column'] ?? '');
        if (!$this->tableExists($refTable)) {
            return [];
        }
        $refColumns = $this->columns($refTable);
        $names = array_column($refColumns, 'name');
        if (!in_array($refColumn, $names, true)) {
            return [];
        }

        $label = $refColumn;
        foreach (['name', 'title', 'code', 'label', 'username', 'email', 'slug'] as $candidate) {
            if (in_array($candidate, $names, true) && $candidate !== $refColumn) {
                $label = $candidate;
                break;
            }
        }

        try {
            $rows = Database::fetchAll(
                'SELECT `' . $refColumn . '` AS value, `' . $label . '` AS label FROM `' . $refTable . '` ORDER BY `' . $label . '` ASC LIMIT 500'
            );
            return array_map(static function (array $row): array {
                return [
                    'value' => (string) $row['value'],
                    'label' => (string) ($row['label'] ?? $row['value']),
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function databaseStats(): array
    {
        $size = 0;
        $rows = 0;
        $tables = $this->tables();
        foreach ($tables as $table) {
            $size += $table['data_length'] + $table['index_length'];
            $rows += $table['rows_estimate'];
        }
        return [
            'tables' => count($tables),
            'rows_estimate' => $rows,
            'size_bytes' => $size,
            'name' => (string) Database::scalar('SELECT DATABASE()', [], ''),
            'version' => (string) Database::scalar('SELECT VERSION()', [], ''),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function normalizeValue(array $column, $raw): array
    {
        if (is_array($raw)) {
            return [false, $column['label'] . ' must be a single value.'];
        }

        $dataType = (string) $column['data_type'];
        $rawString = is_string($raw) ? trim($raw) : trim((string) $raw);

        if ($column['input_type'] === 'boolean') {
            if ($rawString === '') {
                if ($column['nullable']) {
                    return [true, null];
                }
                return [true, 0];
            }
            if (!in_array($rawString, ['0', '1', 'on'], true)) {
                return [false, $column['label'] . ' must be 0 or 1.'];
            }
            return [true, $rawString === '0' ? 0 : 1];
        }

        if ($rawString === '') {
            if ($column['nullable']) {
                return [true, null];
            }
            if ($column['default'] !== null) {
                return [true, self::OMIT];
            }
            return [false, $column['label'] . ' is required.'];
        }

        if ($column['input_type'] === 'enum') {
            if (!in_array($rawString, $column['enum_values'], true)) {
                return [false, $column['label'] . ' contains a value outside the allowed list.'];
            }
            return [true, $rawString];
        }

        if (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year'], true)) {
            if (!preg_match('/^-?\d+$/', $rawString)) {
                return [false, $column['label'] . ' must be a whole number.'];
            }
            return [true, (int) $rawString];
        }

        if (in_array($dataType, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            if (!is_numeric($rawString)) {
                return [false, $column['label'] . ' must be a number.'];
            }
            return [true, $rawString];
        }

        if ($dataType === 'date') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawString) || strtotime($rawString) === false) {
                return [false, $column['label'] . ' must use YYYY-MM-DD.'];
            }
            return [true, $rawString];
        }

        if (in_array($dataType, ['datetime', 'timestamp'], true)) {
            $ts = strtotime($rawString);
            if ($ts === false) {
                return [false, $column['label'] . ' must be a valid date and time.'];
            }
            return [true, date('Y-m-d H:i:s', $ts)];
        }

        if ($dataType === 'time') {
            if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $rawString)) {
                return [false, $column['label'] . ' must use HH:MM or HH:MM:SS.'];
            }
            return [true, strlen($rawString) === 5 ? $rawString . ':00' : $rawString];
        }

        if ($dataType === 'json') {
            json_decode($rawString);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [false, $column['label'] . ' must be valid JSON.'];
            }
            return [true, $rawString];
        }

        if ($column['max_length'] !== null && mb_strlen($rawString) > $column['max_length']) {
            return [false, $column['label'] . ' may not exceed ' . $column['max_length'] . ' characters.'];
        }

        return [true, $rawString];
    }

    /**
     * Execute one SQL statement with conservative server-side guardrails.
     *
     * @return array<string,mixed>
     */
    public function executeSql(string $sql, string $confirm = '', string $confirmText = ''): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'error' => 'Enter a SQL statement.'];
        }

        // Allow exactly one trailing semicolon, then reject any interior one.
        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }
        if ($sql === '') {
            return ['ok' => false, 'error' => 'Enter a SQL statement.'];
        }
        if ($this->hasInteriorSemicolon($sql)) {
            return ['ok' => false, 'error' => 'Only one statement can be executed at a time.'];
        }

        $keyword = $this->firstKeyword($sql);
        if ($keyword === '') {
            return ['ok' => false, 'error' => 'The statement could not be classified.'];
        }
        if (in_array($keyword, self::BLOCKED_KEYWORDS, true)) {
            return ['ok' => false, 'error' => strtoupper($keyword) . ' statements are not permitted in the SQL console.'];
        }

        $upper = strtoupper($sql);
        foreach (['INTO OUTFILE', 'INTO DUMPFILE', 'LOAD DATA', 'LOAD XML', 'LOAD_FILE(', 'PREPARE ', 'EXECUTE '] as $fragment) {
            if (str_contains($upper, $fragment)) {
                return ['ok' => false, 'error' => 'That statement uses a server-level feature the console does not allow.'];
            }
        }
        if (preg_match('/\b(mysql|performance_schema|sys)\s*\./i', $sql)) {
            return ['ok' => false, 'error' => 'The console may only access the active application database.'];
        }

        $isWith = $keyword === 'WITH';
        $isWrite = $isWith
            ? (bool) preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)
            : !in_array($keyword, self::READ_KEYWORDS, true);

        if ($isWrite && $confirm !== '1') {
            return ['ok' => false, 'error' => 'Confirm the write/DDL statement before running it.'];
        }
        if (in_array($keyword, self::DESTRUCTIVE_KEYWORDS, true) && strtoupper(trim($confirmText)) !== 'CONFIRM') {
            return ['ok' => false, 'error' => 'Type CONFIRM to run a destructive schema statement.'];
        }
        if (in_array($keyword, ['UPDATE', 'DELETE'], true) && !preg_match('/\bWHERE\b/i', $sql)) {
            return ['ok' => false, 'error' => 'UPDATE and DELETE must include a WHERE clause in the SQL console.'];
        }

        $started = microtime(true);
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $columns = [];
            $rows = [];
            $truncated = false;
            if ($stmt->columnCount() > 0) {
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $meta = $stmt->getColumnMeta($i);
                    $columns[] = (string) ($meta['name'] ?? ('column_' . ($i + 1)));
                }
                while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    if (count($rows) >= 500) {
                        $truncated = true;
                        break;
                    }
                    $rows[] = $row;
                }
            }

            return [
                'ok' => true,
                'statement' => $keyword,
                'read' => $stmt->columnCount() > 0 || in_array($keyword, self::READ_KEYWORDS, true),
                'columns' => $columns,
                'rows' => $rows,
                'truncated' => $truncated,
                'affected' => $stmt->rowCount(),
                'last_insert_id' => (int) $pdo->lastInsertId(),
                'duration_ms' => round((microtime(true) - $started) * 1000, 2),
                'sql' => $sql,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'sql' => $sql,
                'duration_ms' => round((microtime(true) - $started) * 1000, 2),
            ];
        }
    }

    private function hasInteriorSemicolon(string $sql): bool
    {
        $length = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== null) {
                if ($char === '\\' && $quote !== '`') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '#' || ($char === '-' && $next === '-')) {
                $newline = strpos($sql, "\n", $i);
                if ($newline === false) {
                    break;
                }
                $i = $newline;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $i = $end + 1;
                continue;
            }
            if ($char === ';') {
                return true;
            }
        }
        return false;
    }

    private function firstKeyword(string $sql): string
    {
        $sql = ltrim($sql);
        while ($sql !== '') {
            if (str_starts_with($sql, '--') || str_starts_with($sql, '#')) {
                $newline = strpos($sql, "\n");
                $sql = $newline === false ? '' : ltrim(substr($sql, $newline + 1));
                continue;
            }
            if (str_starts_with($sql, '/*')) {
                $end = strpos($sql, '*/');
                $sql = $end === false ? '' : ltrim(substr($sql, $end + 2));
                continue;
            }
            break;
        }
        return preg_match('/^([A-Za-z]+)/', $sql, $matches) ? strtoupper($matches[1]) : '';
    }
}
