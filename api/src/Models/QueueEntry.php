<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * QueueEntry Model - Generated from 'queue_entries' table
 * 
 * Represents a queue entry (walk-in customer in a queue).
 * Includes relationships and validation for queue management.
 */
class QueueEntry
{
    protected static string $table = 'queue_entries';
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
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
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
     * Get user who owns this queue entry (belongsTo relationship)
     * 
     * @param int $entryId Queue entry ID
     * @return array|null User data or null
     */
    public static function getUser(int $entryId): ?array
    {
        $entry = self::find($entryId);
        
        if (!$entry || !$entry['user_id']) {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
        $users = $db->query($sql, [$entry['user_id']]);
        
        return $users[0] ?? null;
    }

    /**
     * Get queue that this entry belongs to (belongsTo relationship)
     * 
     * @param int $entryId Queue entry ID
     * @return array|null Queue data or null
     */
    public static function getQueue(int $entryId): ?array
    {
        $entry = self::find($entryId);
        
        if (!$entry || !$entry['queue_id']) {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM queues WHERE id = ? LIMIT 1";
        $queues = $db->query($sql, [$entry['queue_id']]);
        
        return $queues[0] ?? null;
    }

    /**
     * Get waiting entries for a queue
     * 
     * @param int $queueId Queue ID
     * @return array Array of queue entries
     */
    public static function getWaitingByQueue(int $queueId): array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('queue_id', '=', $queueId)
            ->where('status', '=', 'waiting')
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    /**
     * Get entries by user
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of queue entries
     */
    public static function getByUser(int $userId, ?string $status = null): array
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table)
            ->where('user_id', '=', $userId);

        if ($status !== null) {
            $qb->where('status', '=', $status);
        }

        return $qb->orderBy('created_at', 'DESC')->get();
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
        
        // Validate queue_id
        if (empty($data['queue_id'])) {
            $errors['queue_id'] = 'Queue ID is required';
        } elseif (!is_numeric($data['queue_id']) || $data['queue_id'] <= 0) {
            $errors['queue_id'] = 'Queue ID must be a positive integer';
        }

        // Validate position
        if (isset($data['position'])) {
            if (!is_numeric($data['position']) || $data['position'] < 1) {
                $errors['position'] = 'Position must be a positive integer';
            }
        }

        // Validate status
        if (isset($data['status'])) {
            $validStatuses = ['waiting', 'called', 'serving', 'done', 'no_show', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status value';
            }
        }

        // Validate priority
        if (isset($data['priority'])) {
            if (!is_numeric($data['priority'])) {
                $errors['priority'] = 'Priority must be a number';
            }
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - queue_id: bigint NOT NULL
     * - user_id: bigint NULL
     * - position: int NOT NULL
     * - status: enum NOT NULL
     * - created_at: timestamp NOT NULL
     * - called_at: timestamp NULL
     * - served_at: timestamp NULL
     * - priority: int NOT NULL
     */
}
