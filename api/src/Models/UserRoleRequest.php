<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

class UserRoleRequest
{
    protected static string $table = 'user_role_requests';
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?array
    {
        return (new QueryBuilder())
            ->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    public static function create(array $data): int
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload']);
        }

        return (new QueryBuilder())
            ->select(self::$table)
            ->insert($data);
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload']);
        }

        return (new QueryBuilder())
            ->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    public static function findPendingByUser(int $userId, string $requestedRole): ?array
    {
        return (new QueryBuilder())
            ->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('requested_role', '=', $requestedRole)
            ->where('status', '=', 'pending')
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public static function getByUser(int $userId): array
    {
        $rows = (new QueryBuilder())
            ->select(self::$table . ' urr', [
                'urr.id',
                'urr.user_id',
                'urr.requested_role',
                'urr.status',
                'urr.message',
                'urr.payload',
                'urr.reviewed_by_user_id',
                'urr.reviewed_at',
                'urr.created_at',
                'urr.updated_at',
                'u.name AS user_name',
                'u.email AS user_email',
                'ru.name AS reviewed_by_user_name',
            ])
            ->join('users u', 'u.id', '=', 'urr.user_id')
            ->leftJoin('users ru', 'ru.id', '=', 'urr.reviewed_by_user_id')
            ->where('urr.user_id', '=', $userId)
            ->orderBy('urr.created_at', 'DESC')
            ->get();

        return array_map([self::class, 'normalize'], $rows);
    }

    public static function normalize(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }

        if (!isset($row['payload']) || !is_array($row['payload'])) {
            $row['payload'] = [];
        }

        return $row;
    }
}
