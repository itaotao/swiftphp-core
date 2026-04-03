<?php

namespace SwiftPHP\Validate;

use SwiftPHP\Response\Response;

class Validate
{
    protected $rules = [];
    protected $messages = [];
    protected $errors = [];
    protected $data = [];
    protected $scene = '';
    protected $only = [];
    protected $remove = [];
    protected $append = [];

    public function __construct(array $rules = [], array $messages = [])
    {
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public static function make(array $rules, array $messages = []): self
    {
        return new self($rules, $messages);
    }

    public function check(array $data): bool
    {
        $this->errors = [];
        $this->data = $data;

        foreach ($this->rules as $field => $rule) {
            if ($this->shouldSkip($field)) {
                continue;
            }

            $value = $this->getValue($field);
            $rules = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($rules as $r) {
                $result = $this->parseRule($field, $value, $r);
                if ($result !== true) {
                    $this->addError($field, $result);
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    protected function shouldSkip(string $field): bool
    {
        if (!empty($this->only) && !in_array($field, $this->only)) {
            return true;
        }

        if (in_array($field, $this->remove)) {
            return true;
        }

        return false;
    }

    protected function parseRule(string $field, $value, string $rule): bool
    {
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($rule, $paramStr) = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        $method = 'validate' . ucfirst($rule);
        if (method_exists($this, $method)) {
            return $this->$method($field, $value, $params);
        }

        return true;
    }

    protected function getValue(string $field)
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    protected function addError(string $field, string $message): void
    {
        $key = $this->scene ? $this->scene . '.' . $field : $field;
        $message = $this->formatMessage($field, $message);
        $this->errors[$key] = $message;
    }

    protected function formatMessage(string $field, string $message): string
    {
        $label = $this->getFieldLabel($field);
        return str_replace(['{field}', '{label}'], [$field, $label], $message);
    }

    protected function getFieldLabel(string $field): string
    {
        return $field;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getError(): string
    {
        return reset($this->errors) ?: '';
    }

    public function scene(string $scene): self
    {
        $this->scene = $scene;
        return $this;
    }

    public function only(array $fields): self
    {
        $this->only = $fields;
        return $this;
    }

    public function remove(string $field, ...$rules): self
    {
        $this->remove[] = $field;
        return $this;
    }

    public function append(string $field, $rule): self
    {
        $this->append[$field] = $rule;
        return $this;
    }

    protected function validateRequired($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return 'The {field} field is required';
        }
        return true;
    }

    protected function validateEmail($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'The {field} must be a valid email address';
        }
        return true;
    }

    protected function validateMin($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $min = $params[0] ?? 0;
        if (is_string($value) && strlen($value) < $min) {
            return "The {field} must be at least {$min} characters";
        }
        if (is_numeric($value) && $value < $min) {
            return "The {field} must be at least {$min}";
        }
        return true;
    }

    protected function validateMax($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $max = $params[0] ?? PHP_INT_MAX;
        if (is_string($value) && strlen($value) > $max) {
            return "The {field} must not exceed {$max} characters";
        }
        if (is_numeric($value) && $value > $max) {
            return "The {field} must not exceed {$max}";
        }
        return true;
    }

    protected function validateNumeric($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_numeric($value)) {
            return 'The {field} must be numeric';
        }
        return true;
    }

    protected function validateInteger($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return 'The {field} must be an integer';
        }
        return true;
    }

    protected function validateUrl($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return 'The {field} must be a valid URL';
        }
        return true;
    }

    protected function validateIp($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            return 'The {field} must be a valid IP address';
        }
        return true;
    }

    protected function validateAlpha($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[a-zA-Z]+$/', $value)) {
            return 'The {field} must only contain letters';
        }
        return true;
    }

    protected function validateAlphaNum($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            return 'The {field} must only contain letters and numbers';
        }
        return true;
    }

    protected function validateAlphaDash($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            return 'The {field} must only contain letters, numbers, dashes and underscores';
        }
        return true;
    }

    protected function validateIn($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!in_array($value, $params)) {
            return "The {field} must be one of: " . implode(', ', $params);
        }
        return true;
    }

    protected function validateNotIn($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (in_array($value, $params)) {
            return "The {field} must not be one of: " . implode(', ', $params);
        }
        return true;
    }

    protected function validateBetween($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $min = $params[0] ?? 0;
        $max = $params[1] ?? PHP_INT_MAX;
        $len = is_string($value) ? strlen($value) : $value;
        if ($len < $min || $len > $max) {
            return "The {field} must be between {$min} and {$max}";
        }
        return true;
    }

    protected function validateLength($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $len = strlen($value);
        if (count($params) === 1) {
            if ($len != $params[0]) {
                return "The {field} must be exactly {$params[0]} characters";
            }
        } else {
            $min = $params[0];
            $max = $params[1];
            if ($len < $min || $len > $max) {
                return "The {field} must be between {$min} and {$max} characters";
            }
        }
        return true;
    }

    protected function validateConfirm($field, $value, $params): bool
    {
        $confirmField = $params[0] ?? $field . '_confirm';
        $confirmValue = $this->getValue($confirmField);
        if ($value !== $confirmValue) {
            return "The {field} and {$confirmField} must match";
        }
        return true;
    }

    protected function validateRegex($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $pattern = $params[0] ?? '';
        if (!preg_match($pattern, $value)) {
            return "The {field} format is invalid";
        }
        return true;
    }

    protected function validateDate($field, $value, $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $format = $params[0] ?? 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);
        if (!($d && $d->format($format) === $value)) {
            return "The {field} must be a valid date";
        }
        return true;
    }
}
