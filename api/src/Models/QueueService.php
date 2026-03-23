<?php

namespace QueueMaster\Models;

use QueueMaster\Core\Database;

class QueueService
{
    /**
     * Find a queue_service record by ID
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $rows = $db->query("SELECT * FROM queue_services WHERE id = ? LIMIT 1", [$id]);
        return $rows[0] ?? null;
    }

    /**
     * Find by queue_id and service_id
     */
    public static function findByQueueAndService(int $queueId, int $serviceId): ?array
    {
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT * FROM queue_services WHERE queue_id = ? AND service_id = ? LIMIT 1",
            [$queueId, $serviceId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Get all services linked to a queue (with service details)
     */
    public static function findByQueue(int $queueId): array
    {
        $db = Database::getInstance();
        return $db->query(
            "SELECT qs.id as queue_service_id, qs.queue_id, qs.service_id, qs.created_at as linked_at,
                    s.name, s.description, s.duration_minutes, s.price, s.is_active,
                    s.icon, s.image_url, s.sort_order
             FROM queue_services qs
             JOIN services s ON s.id = qs.service_id
             WHERE qs.queue_id = ?
             ORDER BY s.sort_order ASC, s.name ASC",
            [$queueId]
        );
    }

    /**
     * Link a service to a queue
     */
    public static function create(int $queueId, int $serviceId): int
    {
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO queue_services (queue_id, service_id) VALUES (?, ?)",
            [$queueId, $serviceId]
        );
        return (int)$db->lastInsertId();
    }

    /**
     * Unlink a service from a queue
     */
    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM queue_services WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Unlink by queue_id and service_id
     */
    public static function deleteByQueueAndService(int $queueId, int $serviceId): bool
    {
        $db = Database::getInstance();
        $db->execute(
            "DELETE FROM queue_services WHERE queue_id = ? AND service_id = ?",
            [$queueId, $serviceId]
        );
        return true;
    }
}
