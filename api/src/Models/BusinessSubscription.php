<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * BusinessSubscription Model - Links businesses to plans
 * 
 * Tracks active subscriptions and their status.
 */
class BusinessSubscription
{
    protected static string $table = 'business_subscriptions';
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
     * Get active subscription for a business
     */
    public static function getActiveForBusiness(int $businessId): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('status', '=', 'active')
            ->first();
    }

    /**
     * Create new subscription
     */
    public static function create(array $data): int
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Get all subscriptions (for admin view)
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
     * Update subscription
     */
    public static function update(int $id, array $data): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    /**
     * Delete subscription
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }
}
