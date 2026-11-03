<?php

namespace App\Core;

use PDO;
use Throwable;

/**
 * Server-side validator with support for uniqueness/existence checks.
 * Rules are defined in code (never supplied by users).
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rules = explode('|', (string) $ruleString);
            $nullable = in_array('nullable', $rules, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }

                $error = $this->applyRule($field, $value, $rule);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->validate();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $out[$field] = $this->data[$field];
            }
        }
        return $out;
    }

    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $isEmpty = $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === []);

        if ($name === 'required') {
            return $isEmpty ? "{$label} is required." : null;
        }

        // All subsequent rules skip empty optional values.
        if ($isEmpty) {
            return null;
        }

        switch ($name) {
            case 'email':
                return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? null : "{$label} must be a valid email address.";

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "{$label} must be a whole number.";

            case 'numeric':
                return is_numeric($value) ? null : "{$label} must be a number.";

            case 'min':
                $min = (float) $param;
                $size = is_array($value) ? count($value) : (is_numeric($value) ? (float) $value : $this->textLength((string) $value));
                return $size >= $min ? null : "{$label} must be at least {$param}.";

            case 'max':
                $max = (float) $param;
                $size = is_array($value) ? count($value) : $this->textLength((string) $value);
                return $size <= $max ? null : "{$label} must not exceed {$param} characters.";

            case 'between':
                [$low, $high] = array_map('floatval', array_pad(explode(',', (string) $param), 2, '0'));
                return (is_numeric($value) && (float) $value >= $low && (float) $value <= $high)
                    ? null : "{$label} must be between {$low} and {$high}.";

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $param));
                return in_array((string) $value, $allowed, true) ? null : "{$label} contains an invalid selection.";

            case 'date':
                return $this->isDate((string) $value) ? null : "{$label} must be a valid date (YYYY-MM-DD).";

            case 'after_or_equal':
                $other = (string) ($this->data[$param] ?? '');
                return ($this->isDate((string) $value) && $this->isDate($other) && $value >= $other)
                    ? null : "{$label} must be on or after " . ($this->labels[$param] ?? $param) . '.';

            case 'before_or_equal':
                $other = (string) ($this->data[$param] ?? '');
                return ($this->isDate((string) $value) && $this->isDate($other) && $value <= $other)
                    ? null : "{$label} must be on or before " . ($this->labels[$param] ?? $param) . '.';

            case 'same':
                return (string) $value === (string) ($this->data[$param] ?? '') ? null : "{$label} does not match.";

            case 'different':
                return (string) $value !== (string) ($this->data[$param] ?? '') ? null : "{$label} must be different.";

            case 'confirmed':
                return (string) $value === (string) ($this->data[$field . '_confirmation'] ?? '') ? null : "{$label} confirmation does not match.";

            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1'], true) ? null : "{$label} must be yes or no.";

            case 'array':
                return is_array($value) ? null : "{$label} is invalid.";

            case 'username':
                return preg_match('/^[A-Za-z0-9._-]{3,60}$/', (string) $value)
                    ? null : "{$label} may only contain letters, numbers, dots, underscores and hyphens (3–60 characters).";

            case 'password_strength':
                return $this->passwordIsStrong((string) $value)
                    ? null : "{$label} must be at least 10 characters and include uppercase, lowercase, number and symbol.";

            case 'unique':
                return $this->uniqueCheck($value, (string) $param, $label);

            case 'exists':
                return $this->existsCheck($value, (string) $param, $label);

            default:
                throw new \RuntimeException('Unsupported validation rule: ' . $name);
        }
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }
        return (int) preg_match_all('/./us', $value);
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function passwordIsStrong(string $value): bool
    {
        return strlen($value) >= 10
            && preg_match('/[A-Z]/', $value)
            && preg_match('/[a-z]/', $value)
            && preg_match('/[0-9]/', $value)
            && preg_match('/[^A-Za-z0-9]/', $value);
    }

    private function uniqueCheck(mixed $value, string $param, string $label): ?string
    {
        $parts = explode(',', $param);
        $table = $parts[0] ?? '';
        $column = $parts[1] ?? '';
        $exceptId = $parts[2] ?? null;
        $idColumn = $parts[3] ?? 'id';

        if (!$this->safeIdentifier($table) || !$this->safeIdentifier($column)) {
            throw new \RuntimeException('Invalid unique validation target.');
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($exceptId !== null && $exceptId !== '') {
            if (!$this->safeIdentifier($idColumn)) {
                throw new \RuntimeException('Invalid unique validation key.');
            }
            $sql .= " AND `{$idColumn}` <> ?";
            $params[] = $exceptId;
        }

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            return ((int) $stmt->fetchColumn()) === 0 ? null : "{$label} is already in use.";
        } catch (Throwable $e) {
            app_log('error', 'Unique validation failed: ' . $e->getMessage());
            return "{$label} could not be validated. Please try again.";
        }
    }

    private function existsCheck(mixed $value, string $param, string $label): ?string
    {
        $parts = explode(',', $param);
        $table = $parts[0] ?? '';
        $column = $parts[1] ?? '';

        if (!$this->safeIdentifier($table) || !$this->safeIdentifier($column)) {
            throw new \RuntimeException('Invalid exists validation target.');
        }

        try {
            $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
            $stmt->execute([$value]);
            return ((int) $stmt->fetchColumn()) > 0 ? null : "{$label} is invalid.";
        } catch (Throwable $e) {
            app_log('error', 'Exists validation failed: ' . $e->getMessage());
            return "{$label} could not be validated. Please try again.";
        }
    }

    private function safeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $value);
    }
}
