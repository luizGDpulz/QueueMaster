<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * Plan Model - SaaS plan definitions
 * 
 * Defines limits for businesses, establishments, managers, professionals.
 */
class Plan
{
    protected static string $table = 'plans';
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
     * Get all plans
     */
    public static function all(
        array $conditions = [],
        string $orderBy = '',
        string $direction = 'ASC',
        ?int $limit = null
        ): array
    {
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
     * Get active plans
     */
    public static function getActive(): array
    {
        return self::all(['is_active' => 1], 'name', 'ASC');
    }

    /**
     * Create new plan
     */
    public static function create(array $data): int
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Update plan
     */
    public static function update(int $id, array $data): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    /**
     * Delete plan
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }
}
