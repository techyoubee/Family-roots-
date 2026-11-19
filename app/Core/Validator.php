<?php

namespace App\Core;

/**
 * Server-side validation. Every form in the application validates its input
 * here (or in a dedicated service that uses this class) - the browser is never
 * trusted.
 */
final class Validator
{
    /** @var array */
    private $data;
    /** @var array */
    private $rules;
    /** @var array<string,array<string>> */
    private $errors = [];
    /** @var array */
    private $validated = [];
    /** @var array<string,string> */
    private $labels = [];

    private function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = [])
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();
        return $validator;
    }

    private function run()
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', (string) $ruleString);
            $present = array_key_exists($field, $this->data);
            $value = $present ? $this->data[$field] : null;
            $isNullable = in_array('nullable', $rules, true);
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            if ($isEmpty && $isNullable) {
                $this->validated[$field] = null;
                continue;
            }

            $failed = false;
            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }
                if ($failed) {
                    break;
                }
                $failed = $this->apply($field, $value, $rule, $present);
            }

            if (!$failed) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function apply($field, $value, $rule, $present)
    {
        $name = $rule;
        $param = '';
        if (strpos($rule, ':') !== false) {
            list($name, $param) = explode(':', $rule, 2);
        }
        $label = $this->labels[$field] ?? $this->humanize($field);
        $isArray = is_array($value);

        switch ($name) {
            case 'required':
                if (!$present || $value === null || ($isArray && count($value) === 0) || (!is_array($value) && trim((string) $value) === '')) {
                    return $this->fail($field, $label . ' is required.');
                }
                return false;

            case 'accepted':
                if (!in_array($value, ['1', 1, true, 'on', 'yes'], true)) {
                    return $this->fail($field, $label . ' must be accepted.');
                }
                return false;

            case 'string':
                if ($isArray || (!is_string($value) && !is_numeric($value))) {
                    return $this->fail($field, $label . ' must be text.');
                }
                return false;

            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 190) {
                    return $this->fail($field, $label . ' must be a valid email address.');
                }
                return false;

            case 'url':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                    return $this->fail($field, $label . ' must be a valid URL.');
                }
                return false;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return $this->fail($field, $label . ' must be a whole number.');
                }
                return false;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->fail($field, $label . ' must be a number.');
                }
                return false;

            case 'boolean':
                if (!in_array($value, ['0', '1', 0, 1, true, false, 'true', 'false'], true)) {
                    return $this->fail($field, $label . ' must be true or false.');
                }
                return false;

            case 'date':
                $parsed = is_string($value) ? date_create($value) : false;
                if ($parsed === false) {
                    return $this->fail($field, $label . ' must be a valid date.');
                }
                return false;

            case 'date_format':
                $parsed = is_string($value) ? \DateTime::createFromFormat($param, $value) : false;
                if ($parsed === false || $parsed->format($param) !== $value) {
                    return $this->fail($field, $label . ' must match the format ' . $param . '.');
                }
                return false;

            case 'min':
                $len = $isArray ? count($value) : mb_strlen((string) $value);
                if ($len < (int) $param) {
                    return $this->fail($field, $label . ' must be at least ' . (int) $param . ' characters.');
                }
                return false;

            case 'max':
                $len = $isArray ? count($value) : mb_strlen((string) $value);
                if ($len > (int) $param) {
                    return $this->fail($field, $label . ' may not be longer than ' . (int) $param . ' characters.');
                }
                return false;

            case 'between':
                $parts = explode(',', $param);
                $len = $isArray ? count($value) : mb_strlen((string) $value);
                if ($len < (int) $parts[0] || $len > (int) ($parts[1] ?? $parts[0])) {
                    return $this->fail($field, sprintf('%s must be between %d and %d characters.', $label, (int) $parts[0], (int) ($parts[1] ?? $parts[0])));
                }
                return false;

            case 'in':
                if (!in_array((string) $value, array_map('strval', explode(',', $param)), true)) {
                    return $this->fail($field, $label . ' contains an unsupported value.');
                }
                return false;

            case 'same':
                if (!array_key_exists($param, $this->data) || (string) $this->data[$param] !== (string) $value) {
                    return $this->fail($field, $label . ' does not match ' . $this->humanize($param) . '.');
                }
                return false;

            case 'different':
                if (array_key_exists($param, $this->data) && (string) $this->data[$param] === (string) $value) {
                    return $this->fail($field, $label . ' must be different from ' . $this->humanize($param) . '.');
                }
                return false;

            case 'confirmed':
                if (!array_key_exists($field . '_confirmation', $this->data) || (string) $this->data[$field . '_confirmation'] !== (string) $value) {
                    return $this->fail($field, $label . ' confirmation does not match.');
                }
                return false;

            case 'regex':
                if (!is_string($value) || @preg_match($param, $value) !== 1) {
                    return $this->fail($field, $label . ' has an invalid format.');
                }
                return false;

            case 'alpha_num':
                if (!is_string($value) || !preg_match('/^[A-Za-z0-9]+$/', $value)) {
                    return $this->fail($field, $label . ' may only contain letters and numbers.');
                }
                return false;

            case 'unique':
                return $this->checkUnique($field, $value, $param, false, $label);

            case 'exists':
                return $this->checkUnique($field, $value, $param, true, $label);

            default:
                return $this->fail($field, 'Unsupported validation rule: ' . $name);
        }
    }

    private function checkUnique($field, $value, $param, $mustExist, $label)
    {
        $parts = explode(',', $param);
        $table = preg_replace('/[^a-z0-9_]/i', '', $parts[0] ?? '');
        $column = preg_replace('/[^a-z0-9_]/i', '', $parts[1] ?? $field);
        $exceptKey = $parts[2] ?? null;
        $exceptValue = null;
        if ($exceptKey !== null) {
            $exceptValue = $this->data[$exceptKey] ?? null;
        }

        if ($table === '' || $column === '') {
            return $this->fail($field, 'Invalid ' . ($mustExist ? 'exists' : 'unique') . ' rule configuration.');
        }

        $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = ?';
        $params = [$value];
        if ($exceptValue !== null && $exceptValue !== '') {
            $sql .= ' AND `id` <> ?';
            $params[] = (int) $exceptValue;
        }

        try {
            $count = (int) Database::value($sql, $params);
        } catch (\Throwable $e) {
            Logger::error('Validation database check failed: ' . $e->getMessage());
            return $this->fail($field, $label . ' could not be validated right now.');
        }

        if ($mustExist && $count === 0) {
            return $this->fail($field, 'The selected ' . $label . ' could not be found.');
        }
        if (!$mustExist && $count > 0) {
            return $this->fail($field, $label . ' is already registered.');
        }
        return false;
    }

    private function fail($field, $message)
    {
        $this->errors[$field][] = $message;
        return true;
    }

    private function humanize($field)
    {
        return ucfirst(str_replace(['_', '.'], ' ', (string) $field));
    }

    public function fails()
    {
        return count($this->errors) > 0;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function firstError()
    {
        foreach ($this->errors as $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }
        return null;
    }

    public function validated()
    {
        return $this->validated;
    }

    /** Field value after validation (falls back to the raw input). */
    public function value($field, $default = null)
    {
        return array_key_exists($field, $this->validated) ? $this->validated[$field] : $default;
    }
}
