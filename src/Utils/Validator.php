<?php

namespace QueueMaster\Utils;

/**
 * Validator - Request Payload Validation Helper
 * 
 * Provides common validation rules for API requests.
 */
class Validator
{
    private array $errors = [];

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if valid, false otherwise
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $rulesArray = explode('|', $fieldRules);

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a validation rule
     */
    private function applyRule(string $field, mixed $value, string $rule, array $data): void
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field][] = "The $field field is required";
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "The $field must be a valid email address";
                }
                break;

            case 'min':
                $min = (int)($params[0] ?? 0);
                if (is_string($value) && strlen($value) < $min) {
                    $this->errors[$field][] = "The $field must be at least $min characters";
                } elseif (is_numeric($value) && $value < $min) {
                    $this->errors[$field][] = "The $field must be at least $min";
                }
                break;

            case 'max':
                $max = (int)($params[0] ?? 0);
                if (is_string($value) && strlen($value) > $max) {
                    $this->errors[$field][] = "The $field must not exceed $max characters";
                } elseif (is_numeric($value) && $value > $max) {
                    $this->errors[$field][] = "The $field must not exceed $max";
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field][] = "The $field must be a number";
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field][] = "The $field must be an integer";
                }
                break;

            case 'in':
                if ($value !== null && !in_array($value, $params)) {
                    $allowed = implode(', ', $params);
                    $this->errors[$field][] = "The $field must be one of: $allowed";
                }
                break;

            case 'date':
                if ($value !== null) {
                    $timestamp = strtotime($value);
                    if ($timestamp === false) {
                        $this->errors[$field][] = "The $field must be a valid date";
                    }
                }
                break;

            case 'datetime':
                if ($value !== null) {
                    $timestamp = strtotime($value);
                    if ($timestamp === false) {
                        $this->errors[$field][] = "The $field must be a valid datetime";
                    }
                }
                break;

            case 'exists':
                // Check if value exists in database table
                // Format: exists:table,column
                if ($value !== null && count($params) >= 2) {
                    $table = $params[0];
                    $column = $params[1];
                    if (!$this->checkExists($table, $column, $value)) {
                        $this->errors[$field][] = "The selected $field is invalid";
                    }
                }
                break;

            case 'unique':
                // Check if value is unique in database table
                // Format: unique:table,column,ignoreId
                if ($value !== null && count($params) >= 2) {
                    $table = $params[0];
                    $column = $params[1];
                    $ignoreId = $params[2] ?? null;
                    if (!$this->checkUnique($table, $column, $value, $ignoreId)) {
                        $this->errors[$field][] = "The $field has already been taken";
                    }
                }
                break;

            case 'confirmed':
                // Check if field matches field_confirmation
                $confirmField = $field . '_confirmation';
                if ($value !== ($data[$confirmField] ?? null)) {
                    $this->errors[$field][] = "The $field confirmation does not match";
                }
                break;

            case 'regex':
                if ($value !== null && count($params) >= 1) {
                    $pattern = $params[0];
                    if (!preg_match($pattern, $value)) {
                        $this->errors[$field][] = "The $field format is invalid";
                    }
                }
                break;

            case 'alpha':
                if ($value !== null && !ctype_alpha($value)) {
                    $this->errors[$field][] = "The $field may only contain letters";
                }
                break;

            case 'alphanumeric':
                if ($value !== null && !ctype_alnum($value)) {
                    $this->errors[$field][] = "The $field may only contain letters and numbers";
                }
                break;

            case 'url':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$field][] = "The $field must be a valid URL";
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->errors[$field][] = "The $field must be an array";
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    $this->errors[$field][] = "The $field must be true or false";
                }
                break;
        }
    }

    /**
     * Check if value exists in database
     */
    private function checkExists(string $table, string $column, mixed $value): bool
    {
        try {
            $db = \QueueMaster\Core\Database::getInstance();
            $sql = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ?";
            $result = $db->query($sql, [$value]);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if value is unique in database
     */
    private function checkUnique(string $table, string $column, mixed $value, ?string $ignoreId = null): bool
    {
        try {
            $db = \QueueMaster\Core\Database::getInstance();
            
            if ($ignoreId) {
                $sql = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ? AND id != ?";
                $result = $db->query($sql, [$value, $ignoreId]);
            } else {
                $sql = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ?";
                $result = $db->query($sql, [$value]);
            }
            
            return ($result[0]['count'] ?? 0) === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Static helper to validate and return errors
     */
    public static function make(array $data, array $rules): array
    {
        $validator = new self();
        $validator->validate($data, $rules);
        return $validator->getErrors();
    }
}
