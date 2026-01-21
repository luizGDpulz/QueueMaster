<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * User Model - Generated from 'users' table
 * 
 * Represents a user in the system (client, attendant, or admin).
 * Handles user authentication and profile management.
 */
class User
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    /**
     * Find record by primary key
     * 
     * @param int $id Primary key value
     * @return array|null Record data or null
     */
    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return array|null Record data or null
     */
    public static function findByEmail(string $email): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('email', '=', strtolower(trim($email)))
            ->first();
    }

    /**
     * Get all records
     * 
     * @param array $conditions Optional WHERE conditions ['column' => 'value']
     * @param string $orderBy Optional ORDER BY column
     * @param string $direction Sort direction (ASC|DESC)
     * @param int|null $limit Optional LIMIT
     * @return array Records array
     */
    public static function all(
        array $conditions = [],
        string $orderBy = '',
        string $direction = 'ASC',
        ?int $limit = null
    ): array {
        $qb = new QueryBuilder();
        $qb->select(self::$table);

        foreach ($conditions as $column => $value) {
            $qb->where($column, '=', $value);
        }

        if (!empty($orderBy)) {
            $qb->orderBy($orderBy, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb->get();
    }

    /**
     * Create new record
     * 
     * @param array $data Column => value pairs
     * @return int Inserted record ID
     */
    public static function create(array $data): int
    {
        $errors = self::validate($data);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        // Normalize email
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id Primary key value
     * @param array $data Column => value pairs to update
     * @return int Number of affected rows
     */
    public static function update(int $id, array $data): int
    {
        // Normalize email if present
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Don't allow updating password_hash directly (use changePassword method)
        unset($data['password_hash']);

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    /**
     * Delete record
     * 
     * @param int $id Primary key value
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }

    /**
     * Change user password
     * 
     * @param int $id User ID
     * @param string $newPassword New password (plain text)
     * @return int Number of affected rows
     */
    public static function changePassword(int $id, string $newPassword): int
    {
        // Hash password using Argon2id (or bcrypt fallback)
        if (defined('PASSWORD_ARGON2ID')) {
            $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update(['password_hash' => $passwordHash]);
    }

    /**
     * Verify user password
     * 
     * @param int $id User ID
     * @param string $password Password to verify
     * @return bool True if password matches
     */
    public static function verifyPassword(int $id, string $password): bool
    {
        $user = self::find($id);
        
        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }

    /**
     * Get user's queue entries (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of queue entries
     */
    public static function getQueueEntries(int $userId, ?string $status = null): array
    {
        return QueueEntry::getByUser($userId, $status);
    }

    /**
     * Get user's appointments (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of appointments
     */
    public static function getAppointments(int $userId, ?string $status = null): array
    {
        return Appointment::getByUser($userId, $status);
    }

    /**
     * Get user's notifications (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param bool $unreadOnly Get only unread notifications
     * @return array Array of notifications
     */
    public static function getNotifications(int $userId, bool $unreadOnly = false): array
    {
        return Notification::getByUser($userId, $unreadOnly);
    }

    /**
     * Get users by role
     * 
     * @param string $role User role (client|attendant|admin)
     * @return array Array of users
     */
    public static function getByRole(string $role): array
    {
        return self::all(['role' => $role], 'name', 'ASC');
    }

    /**
     * Validate data before create/update
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        
        // Validate name
        if (!$isUpdate && empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (isset($data['name'])) {
            if (strlen($data['name']) < 2) {
                $errors['name'] = 'Name must be at least 2 characters';
            }
            if (strlen($data['name']) > 150) {
                $errors['name'] = 'Name must not exceed 150 characters';
            }
        }

        // Validate email
        if (!$isUpdate && empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            if (strlen($data['email']) > 150) {
                $errors['email'] = 'Email must not exceed 150 characters';
            }
        }

        // Validate password_hash (only on creation)
        if (!$isUpdate && empty($data['password_hash'])) {
            $errors['password_hash'] = 'Password hash is required';
        }

        // Validate role
        if (isset($data['role'])) {
            $validRoles = ['client', 'attendant', 'admin'];
            if (!in_array($data['role'], $validRoles)) {
                $errors['role'] = 'Invalid role value';
            }
        }

        return $errors;
    }

    /**
     * Get safe user data (excludes password_hash)
     * 
     * @param array $user User data
     * @return array Safe user data
     */
    public static function getSafeData(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - name: varchar(150) NOT NULL
     * - email: varchar(150) NOT NULL [UNIQUE]
     * - password_hash: varchar(255) NOT NULL
     * - role: enum('client','attendant','admin') NOT NULL
     * - created_at: timestamp NOT NULL
     */
}
