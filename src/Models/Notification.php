<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * Notification Model - Generated from 'notifications' table
 * 
 * Represents a notification sent to a user.
 * Tracks read status and notification data.
 */
class Notification
{
    protected static string $table = 'notifications';
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

        // Encode data field if it's an array
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
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
        // Encode data field if it's an array
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

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
     * Mark notification as read
     * 
     * @param int $id Notification ID
     * @return int Number of affected rows
     */
    public static function markAsRead(int $id): int
    {
        return self::update($id, ['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return int Number of affected rows
     */
    public static function markAllAsReadForUser(int $userId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('read_at', 'IS', null)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Get user who owns this notification (belongsTo relationship)
     * 
     * @param int $notificationId Notification ID
     * @return array|null User data or null
     */
    public static function getUser(int $notificationId): ?array
    {
        $notification = self::find($notificationId);
        
        if (!$notification || !$notification['user_id']) {
            return null;
        }

        return User::find($notification['user_id']);
    }

    /**
     * Get notifications by user
     * 
     * @param int $userId User ID
     * @param bool $unreadOnly Get only unread notifications
     * @return array Array of notifications
     */
    public static function getByUser(int $userId, bool $unreadOnly = false): array
    {
        $db = Database::getInstance();
        
        if ($unreadOnly) {
            $sql = "SELECT * FROM " . self::$table . " 
                    WHERE user_id = ? AND read_at IS NULL 
                    ORDER BY sent_at DESC";
            return $db->query($sql, [$userId]);
        }
        
        return self::all(['user_id' => $userId], 'sent_at', 'DESC');
    }

    /**
     * Get unread count for user
     * 
     * @param int $userId User ID
     * @return int Unread notification count
     */
    public static function getUnreadCount(int $userId): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM " . self::$table . " 
                WHERE user_id = ? AND read_at IS NULL";
        $result = $db->query($sql, [$userId]);
        
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * Validate data before create/update
     * 
     * @param array $data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array $data): array
    {
        $errors = [];
        
        // Validate title
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (strlen($data['title']) > 255) {
            $errors['title'] = 'Title must not exceed 255 characters';
        }

        // Validate data field (optional JSON)
        if (isset($data['data']) && !is_null($data['data'])) {
            if (is_string($data['data'])) {
                json_decode($data['data']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['data'] = 'Data must be valid JSON';
                }
            }
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - user_id: bigint NULL
     * - title: varchar(255) NOT NULL
     * - body: text NULL
     * - data: json NULL
     * - read_at: timestamp NULL
     * - sent_at: timestamp NULL
     */
}
