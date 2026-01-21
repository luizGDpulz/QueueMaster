<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * Queue Model - Generated from 'queues' table
 * 
 * Represents a queue for walk-in customers at an establishment.
 * Can be associated with a specific service.
 */
class Queue
{
    protected static string $table = 'queues';
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
     * Get establishment that owns this queue (belongsTo relationship)
     * 
     * @param int $queueId Queue ID
     * @return array|null Establishment data or null
     */
    public static function getEstablishment(int $queueId): ?array
    {
        $queue = self::find($queueId);
        
        if (!$queue || !$queue['establishment_id']) {
            return null;
        }

        return Establishment::find($queue['establishment_id']);
    }

    /**
     * Get service associated with this queue (belongsTo relationship)
     * 
     * @param int $queueId Queue ID
     * @return array|null Service data or null
     */
    public static function getService(int $queueId): ?array
    {
        $queue = self::find($queueId);
        
        if (!$queue || !$queue['service_id']) {
            return null;
        }

        return Service::find($queue['service_id']);
    }

    /**
     * Get queue entries (hasMany relationship)
     * 
     * @param int $queueId Queue ID
     * @param string|null $status Optional status filter
     * @return array Array of queue entries
     */
    public static function getEntries(int $queueId, ?string $status = null): array
    {
        $conditions = ['queue_id' => $queueId];
        
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return QueueEntry::all($conditions, 'position', 'ASC');
    }

    /**
     * Get waiting entries for queue
     * 
     * @param int $queueId Queue ID
     * @return array Array of waiting queue entries
     */
    public static function getWaitingEntries(int $queueId): array
    {
        return QueueEntry::getWaitingByQueue($queueId);
    }

    /**
     * Get queues by establishment
     * 
     * @param int $establishmentId Establishment ID
     * @param string|null $status Optional status filter
     * @return array Array of queues
     */
    public static function getByEstablishment(int $establishmentId, ?string $status = null): array
    {
        $conditions = ['establishment_id' => $establishmentId];
        
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return self::all($conditions, 'name', 'ASC');
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
        
        // Validate establishment_id
        if (empty($data['establishment_id'])) {
            $errors['establishment_id'] = 'Establishment ID is required';
        } elseif (!is_numeric($data['establishment_id']) || $data['establishment_id'] <= 0) {
            $errors['establishment_id'] = 'Establishment ID must be a positive integer';
        }

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 150) {
            $errors['name'] = 'Name must not exceed 150 characters';
        }

        // Validate status (optional)
        if (isset($data['status'])) {
            $validStatuses = ['open', 'closed'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status value';
            }
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - establishment_id: bigint NOT NULL
     * - service_id: bigint NULL
     * - name: varchar(150) NOT NULL
     * - status: enum('open','closed') NOT NULL
     * - created_at: timestamp NOT NULL
     */
}
