<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * Service Model - Generated from 'services' table
 * 
 * Represents a service offered by an establishment.
 * Each service has a duration and belongs to an establishment.
 */
class Service
{
    protected static string $table = 'services';
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
     * Get establishment that owns this service (belongsTo relationship)
     * 
     * @param int $serviceId Service ID
     * @return array|null Establishment data or null
     */
    public static function getEstablishment(int $serviceId): ?array
    {
        $service = self::find($serviceId);
        
        if (!$service || !$service['establishment_id']) {
            return null;
        }

        return Establishment::find($service['establishment_id']);
    }

    /**
     * Get services by establishment
     * 
     * @param int $establishmentId Establishment ID
     * @return array Array of services
     */
    public static function getByEstablishment(int $establishmentId): array
    {
        return self::all(['establishment_id' => $establishmentId], 'name', 'ASC');
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

        // Validate duration_minutes
        if (isset($data['duration_minutes'])) {
            if (!is_numeric($data['duration_minutes']) || $data['duration_minutes'] < 5) {
                $errors['duration_minutes'] = 'Duration must be at least 5 minutes';
            }
            if ($data['duration_minutes'] > 480) {
                $errors['duration_minutes'] = 'Duration must not exceed 480 minutes (8 hours)';
            }
        }

        return $errors;
    }

    /**
     * Get professionals who offer this service
     * 
     * @param int $serviceId Service ID
     * @return array Array of professionals
     */
    public static function getProfessionals(int $serviceId): array
    {
        return ProfessionalService::getProfessionalsForService($serviceId);
    }

    /**
     * Get active services for an establishment
     * 
     * @param int $establishmentId Establishment ID
     * @return array Array of active services
     */
    public static function getActiveByEstablishment(int $establishmentId): array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->where('is_active', '=', 1)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - establishment_id: bigint NOT NULL [FK -> establishments]
     * - name: varchar(150) NOT NULL
     * - description: text NULL
     * - duration_minutes: int NOT NULL DEFAULT 30
     * - price: decimal(10,2) NULL
     * - is_active: boolean NOT NULL DEFAULT TRUE
     * - sort_order: int NOT NULL DEFAULT 0
     * - created_at: timestamp NOT NULL
     * - updated_at: timestamp NULL
     */
}
