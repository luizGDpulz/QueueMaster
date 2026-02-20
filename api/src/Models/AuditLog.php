<?php

namespace QueueMaster\Models;

use QueueMaster\Core\Database;

/**
 * AuditLog Model - Tracks critical actions for observability
 * 
 * Logs create/update/delete operations on businesses, establishments,
 * subscriptions, queue operations, etc.
 * Supports advanced filtering, pagination, and date-range queries.
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
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . self::$table . " WHERE id = ? LIMIT 1";
        $results = $db->query($sql, [$id]);
        return $results[0] ?? null;
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
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        $db = Database::getInstance();
        $sql = "INSERT INTO " . self::$table
            . " (user_id, action, entity, entity_id, establishment_id, business_id, payload, ip, user_agent)"
            . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $db->execute($sql, [
            $userId,
            $action,
            $entity,
            $entityId !== null ? (string)$entityId : null,
            $establishmentId,
            $businessId,
            $payload !== null ? json_encode($payload) : null,
            $ip,
            $userAgent,
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Paginated search with advanced filters
     *
     * Supported filters (all optional):
     *   business_id, establishment_id, user_id, action, entity,
     *   date_from (Y-m-d), date_to (Y-m-d), search (free-text on action/entity/entity_id)
     *
     * @return array{logs: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function search(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db = Database::getInstance();

        $where = [];
        $bindings = [];

        // Exact-match filters
        if (!empty($filters['business_id'])) {
            $where[] = 'al.business_id = ?';
            $bindings[] = (int)$filters['business_id'];
        }
        if (!empty($filters['establishment_id'])) {
            $where[] = 'al.establishment_id = ?';
            $bindings[] = (int)$filters['establishment_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $bindings[] = (int)$filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $bindings[] = $filters['action'];
        }
        if (!empty($filters['entity'])) {
            $where[] = 'al.entity = ?';
            $bindings[] = $filters['entity'];
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_at >= ?';
            $bindings[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_at <= ?';
            $bindings[] = $filters['date_to'] . ' 23:59:59';
        }

        // Free-text search
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = '(al.action LIKE ? OR al.entity LIKE ? OR al.entity_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }

        // Multi-business filter (for managers with multiple businesses)
        if (!empty($filters['business_ids']) && is_array($filters['business_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['business_ids']), '?'));
            $where[] = "al.business_id IN ($placeholders)";
            foreach ($filters['business_ids'] as $bid) {
                $bindings[] = (int)$bid;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM " . self::$table . " al"
            . " LEFT JOIN users u ON u.id = al.user_id"
            . " $whereClause";
        $countResult = $db->query($countSql, $bindings);
        $total = (int)($countResult[0]['total'] ?? 0);

        // Pagination
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        // Fetch logs with user info
        $sql = "SELECT al.*, u.name AS user_name, u.email AS user_email, u.avatar_url AS user_avatar"
            . " FROM " . self::$table . " al"
            . " LEFT JOIN users u ON u.id = al.user_id"
            . " $whereClause"
            . " ORDER BY al.created_at DESC"
            . " LIMIT $perPage OFFSET $offset";

        $logs = $db->query($sql, $bindings);

        // Decode JSON payload
        foreach ($logs as &$log) {
            if (!empty($log['payload']) && is_string($log['payload'])) {
                $decoded = json_decode($log['payload'], true);
                $log['payload'] = $decoded !== null ? $decoded : $log['payload'];
            }
        }

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get distinct action values for filter dropdowns
     */
    public static function getDistinctActions(?array $businessIds = null): array
    {
        $db = Database::getInstance();
        $bindings = [];

        $sql = "SELECT DISTINCT action FROM " . self::$table;

        if (!empty($businessIds)) {
            $placeholders = implode(',', array_fill(0, count($businessIds), '?'));
            $sql .= " WHERE business_id IN ($placeholders)";
            $bindings = array_map('intval', $businessIds);
        }

        $sql .= " ORDER BY action ASC";
        $results = $db->query($sql, $bindings);
        return array_column($results, 'action');
    }

    /**
     * Get distinct entity values for filter dropdowns
     */
    public static function getDistinctEntities(?array $businessIds = null): array
    {
        $db = Database::getInstance();
        $bindings = [];

        $sql = "SELECT DISTINCT entity FROM " . self::$table . " WHERE entity IS NOT NULL";

        if (!empty($businessIds)) {
            $placeholders = implode(',', array_fill(0, count($businessIds), '?'));
            $sql .= " AND business_id IN ($placeholders)";
            $bindings = array_map('intval', $businessIds);
        }

        $sql .= " ORDER BY entity ASC";
        $results = $db->query($sql, $bindings);
        return array_column($results, 'entity');
    }

    /**
     * Get all records with optional filters (backward compat)
     */
    public static function all(
        array $conditions = [],
        string $orderBy = 'created_at',
        string $direction = 'DESC',
        ?int $limit = 50
    ): array {
        $db = Database::getInstance();
        $where = [];
        $bindings = [];

        foreach ($conditions as $column => $value) {
            $where[] = "$column = ?";
            $bindings[] = $value;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM " . self::$table . " $whereClause ORDER BY $orderBy $direction";

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        return $db->query($sql, $bindings);
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
