<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * AuditLog Model - Tracks critical actions for observability
 * 
 * Logs create/update/delete operations on businesses, establishments,
 * subscriptions, queue operations, etc.
 * Can be filtered by establishment or business for manager dashboards.
 */
class AuditLog
{
    protected static string $table = 'audit_logs';
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
     * Create a new audit log entry
     */
    public static function log(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        ?int $establishmentId = null,
        ?int $businessId = null,
        ?array $payload = null,
        ?string $ip = null
    ): int {
        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId !== null ? (string)$entityId : null,
            'establishment_id' => $establishmentId,
            'business_id' => $businessId,
            'payload' => $payload !== null ? json_encode($payload) : null,
            'ip' => $ip,
        ]);
    }

    /**
     * Get all records with optional filters
     */
    public static function all(
        array $conditions = [],
        string $orderBy = 'created_at',
        string $direction = 'DESC',
        ?int $limit = 50
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
     * Get logs for a specific business
     */
    public static function getByBusiness(int $businessId, ?int $limit = 50): array
    {
        return self::all(['business_id' => $businessId], 'created_at', 'DESC', $limit);
    }

    /**
     * Get logs for a specific establishment
     */
    public static function getByEstablishment(int $establishmentId, ?int $limit = 50): array
    {
        return self::all(['establishment_id' => $establishmentId], 'created_at', 'DESC', $limit);
    }
}
