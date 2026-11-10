<?php

namespace App\Core;

/**
 * Small, explicit validator with server-side rules. Every form is validated
 * independently of any browser-side attributes.
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $clean = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    public function validated(): array
    {
        return $this->clean;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $rules = explode('|', $ruleString);
            $required = in_array('required', $rules, true);
            $nullable = in_array('nullable', $rules, true);

            if ($required && ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && count($value) === 0))) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required.');
                continue;
            }

            if (!$required && ($value === null || $value === '')) {
                $this->clean[$field] = $nullable ? null : ($value === '' ? '' : $value);
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucfirst(str_replace('_', ' ', $field));
                $failed = false;

                switch ($name) {
                    case 'email':
                        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, $label . ' must be a valid email address.');
                            $failed = true;
                        }
                        break;
                    case 'min':
                        if (is_numeric($value) && !preg_match('/[a-zA-Z]/', (string) $value)) {
                            if ((float) $value < (float) $param) {
                                $this->addError($field, $label . ' must be at least ' . $param . '.');
                                $failed = true;
                            }
                        } elseif (mb_strlen((string) $value) < (int) $param) {
                            $this->addError($field, $label . ' must be at least ' . $param . ' characters.');
                            $failed = true;
                        }
                        break;
                    case 'max':
                        if (is_numeric($value) && !preg_match('/[a-zA-Z]/', (string) $value)) {
                            if ((float) $value > (float) $param) {
                                $this->addError($field, $label . ' may not exceed ' . $param . '.');
                                $failed = true;
                            }
                        } elseif (mb_strlen((string) $value) > (int) $param) {
                            $this->addError($field, $label . ' may not exceed ' . $param . ' characters.');
                            $failed = true;
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $this->addError($field, $label . ' must be a number.');
                            $failed = true;
                        }
                        break;
                    case 'integer':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->addError($field, $label . ' must be a whole number.');
                            $failed = true;
                        }
                        break;
                    case 'date':
                        $ts = strtotime((string) $value);
                        if ($ts === false) {
                            $this->addError($field, $label . ' must be a valid date.');
                            $failed = true;
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', (string) $param);
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->addError($field, $label . ' contains an invalid selection.');
                            $failed = true;
                        }
                        break;
                    case 'regex':
                        if (@preg_match((string) $param, (string) $value) !== 1) {
                            $this->addError($field, $label . ' has an invalid format.');
                            $failed = true;
                        }
                        break;
                    case 'confirmed':
                        if ((string) $value !== (string) ($this->data[$field . '_confirmation'] ?? '')) {
                            $this->addError($field, $label . ' confirmation does not match.');
                            $failed = true;
                        }
                        break;
                    case 'same':
                        if ((string) $value !== (string) ($this->data[$param] ?? '')) {
                            $this->addError($field, $label . ' must match ' . $param . '.');
                            $failed = true;
                        }
                        break;
                    case 'after':
                        $compare = $this->data[$param] ?? $param;
                        if (strtotime((string) $value) === false || strtotime((string) $compare) === false || strtotime((string) $value) <= strtotime((string) $compare)) {
                            $this->addError($field, $label . ' must be after ' . str_replace('_', ' ', (string) $param) . '.');
                            $failed = true;
                        }
                        break;
                    case 'before':
                        $compare = $this->data[$param] ?? $param;
                        if (strtotime((string) $value) === false || strtotime((string) $compare) === false || strtotime((string) $value) >= strtotime((string) $compare)) {
                            $this->addError($field, $label . ' must be before ' . str_replace('_', ' ', (string) $param) . '.');
                            $failed = true;
                        }
                        break;
                    case 'boolean':
                        if (!in_array((string) $value, ['0', '1', 'on', 'true', 'false'], true)) {
                            $this->addError($field, $label . ' must be yes or no.');
                            $failed = true;
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $this->addError($field, $label . ' must be a list.');
                            $failed = true;
                        }
                        break;
                    case 'alpha_dash':
                        if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $value)) {
                            $this->addError($field, $label . ' may only contain letters, numbers, dashes and underscores.');
                            $failed = true;
                        }
                        break;
                }

                if ($failed) {
                    break;
                }
            }

            if (!$this->hasError($field)) {
                $this->clean[$field] = is_string($value) ? trim($value) : $value;
            }
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}
