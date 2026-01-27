<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * Establishment Model - Generated from 'establishments' table
 * 
 * Represents a business establishment that offers services.
 * Can have multiple services, professionals, and queues.
 */
class Establishment
{
    protected static string $table = 'establishments';
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
     * Get establishment services (hasMany relationship)
     * 
     * @param int $establishmentId Establishment ID
     * @return array Array of services
     */
    public static function getServices(int $establishmentId): array
    {
        return Service::getByEstablishment($establishmentId);
    }

    /**
     * Get establishment professionals (hasMany relationship)
     * 
     * @param int $establishmentId Establishment ID
     * @return array Array of professionals
     */
    public static function getProfessionals(int $establishmentId): array
    {
        return Professional::getByEstablishment($establishmentId);
    }

    /**
     * Get establishment queues (hasMany relationship)
     * 
     * @param int $establishmentId Establishment ID
     * @param string|null $status Optional status filter
     * @return array Array of queues
     */
    public static function getQueues(int $establishmentId, ?string $status = null): array
    {
        return Queue::getByEstablishment($establishmentId, $status);
    }

    /**
     * Get establishment appointments (hasMany relationship)
     * 
     * @param int $establishmentId Establishment ID
     * @param string|null $date Optional date filter (Y-m-d format)
     * @return array Array of appointments
     */
    public static function getAppointments(int $establishmentId, ?string $date = null): array
    {
        return Appointment::getByEstablishment($establishmentId, $date);
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
        
        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Name must not exceed 255 characters';
        }

        // Validate timezone (optional)
        if (isset($data['timezone'])) {
            $validTimezones = \DateTimeZone::listIdentifiers();
            if (!in_array($data['timezone'], $validTimezones)) {
                $errors['timezone'] = 'Invalid timezone';
            }
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - name: varchar(255) NOT NULL
     * - address: varchar(255) NULL
     * - timezone: varchar(50) NOT NULL
     * - created_at: timestamp NOT NULL
     */
}
