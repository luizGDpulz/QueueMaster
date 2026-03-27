<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

class UserPlanSubscription
{
    protected static string $table = 'user_plan_subscriptions';
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    public static function create(array $data): int
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    public static function update(int $id, array $data): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }

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

        if ($orderBy !== '') {
            $qb->orderBy($orderBy, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb->get();
    }

    public static function listDetailed(): array
    {
        return Database::getInstance()->query(
            "
            SELECT
                ups.*,
                u.name AS user_name,
                u.email AS user_email,
                p.name AS plan_name
            FROM user_plan_subscriptions ups
            JOIN users u
              ON u.id = ups.user_id
            JOIN plans p
              ON p.id = ups.plan_id
            ORDER BY ups.created_at DESC, ups.id DESC
            "
        );
    }
}
