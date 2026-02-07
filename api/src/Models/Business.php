<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * Business Model - Represents a brand/company in the multi-tenant hierarchy
 * 
 * A business can own multiple establishments.
 * Users are linked to businesses via business_users table.
 */
class Business
{
    protected static string $table = 'businesses';
    protected static string $primaryKey = 'id';

    /**
     * Find record by primary key
     */
    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    /**
     * Find business by slug
     */
    public static function findBySlug(string $slug): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('slug', '=', $slug)
            ->first();
    }

    /**
     * Get all records
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
     * Create new business
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
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }

    /**
     * Get businesses owned by a user (via business_users)
     */
    public static function getByUser(int $userId): array
    {
        $links = BusinessUser::getBusinessesForUser($userId);
        $businesses = [];
        foreach ($links as $link) {
            $business = self::find($link['business_id']);
            if ($business) {
                $business['user_role'] = $link['role'];
                $businesses[] = $business;
            }
        }
        return $businesses;
    }

    /**
     * Get establishments for this business (hasMany)
     */
    public static function getEstablishments(int $businessId): array
    {
        return Establishment::all(['business_id' => $businessId], 'name', 'ASC');
    }

    /**
     * Get business users (hasMany)
     */
    public static function getUsers(int $businessId): array
    {
        return BusinessUser::getUsers($businessId);
    }

    /**
     * Validate data before create/update
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Name must not exceed 255 characters';
        }

        if (empty($data['owner_user_id'])) {
            $errors['owner_user_id'] = 'Owner user ID is required';
        }

        return $errors;
    }
}
