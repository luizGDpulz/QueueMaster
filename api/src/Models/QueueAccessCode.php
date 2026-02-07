<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * QueueAccessCode Model - Join codes for queues (code/QR access)
 * 
 * Allows clients to join queues using a unique code or QR token.
 */
class QueueAccessCode
{
    protected static string $table = 'queue_access_codes';
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
     * Find by code string
     */
    public static function findByCode(string $code): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('code', '=', $code)
            ->first();
    }

    /**
     * Create new access code
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
     * Increment uses count
     */
    public static function incrementUses(int $id): int
    {
        $code = self::find($id);
        if (!$code) {
            throw new \InvalidArgumentException('Access code not found');
        }

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update(['uses' => $code['uses'] + 1]);
    }

    /**
     * Check if a code is valid (not expired, not exhausted, active)
     */
    public static function isValid(array $code): bool
    {
        if (!$code['is_active']) {
            return false;
        }

        if ($code['expires_at'] !== null && strtotime($code['expires_at']) < time()) {
            return false;
        }

        if ($code['max_uses'] !== null && $code['uses'] >= $code['max_uses']) {
            return false;
        }

        return true;
    }

    /**
     * Get codes for a queue
     */
    public static function getByQueue(int $queueId): array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('queue_id', '=', $queueId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Generate a unique code string
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Validate data
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['queue_id'])) {
            $errors['queue_id'] = 'Queue ID is required';
        }

        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        }

        return $errors;
    }
}
