<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

class FcmToken
{
    protected static string $table = 'fcm_tokens';
    protected static string $primaryKey = 'id';

    public static function findByUserAndDevice(int $userId, string $deviceId): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('device_id', '=', $deviceId)
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

    public static function upsert(int $userId, string $deviceId, string $token): int
    {
        $existing = self::findByUserAndDevice($userId, $deviceId);
        if ($existing) {
            self::update((int)$existing['id'], [
                'token' => $token,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return (int)$existing['id'];
        }

        return self::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'token' => $token,
        ]);
    }
}
