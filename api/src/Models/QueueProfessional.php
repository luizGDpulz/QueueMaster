<?php

namespace QueueMaster\Models;

use QueueMaster\Core\Database;

/**
 * QueueProfessional Model
 * 
 * Manages professionals assigned to work specific queues.
 * A professional can be active/inactive per queue.
 */
class QueueProfessional
{
    protected static string $table = 'queue_professionals';

    /**
     * Find by ID
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $rows = $db->query("SELECT * FROM queue_professionals WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    /**
     * Find by queue and user
     */
    public static function findByQueueAndUser(int $queueId, int $userId): ?array
    {
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT * FROM queue_professionals WHERE queue_id = ? AND user_id = ?",
            [$queueId, $userId]
        );
        return $rows[0] ?? null;
    }

    /**
     * List all professionals for a queue with user info
     */
    public static function findByQueue(int $queueId): array
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT qp.*, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar
            FROM queue_professionals qp
            JOIN users u ON u.id = qp.user_id
            WHERE qp.queue_id = ?
            ORDER BY qp.created_at ASC
        ", [$queueId]);
    }

    /**
     * List active professionals for a queue
     */
    public static function getActiveByQueue(int $queueId): array
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT qp.*, u.name as user_name, u.email as user_email
            FROM queue_professionals qp
            JOIN users u ON u.id = qp.user_id
            WHERE qp.queue_id = ? AND qp.is_active = 1
            ORDER BY qp.created_at ASC
        ", [$queueId]);
    }

    /**
     * Create a queue professional assignment
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO queue_professionals (queue_id, user_id, is_active) VALUES (?, ?, ?)",
            [$data['queue_id'], $data['user_id'], $data['is_active'] ?? true]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Update a queue professional record
     */
    public static function update(int $id, array $data): void
    {
        $db = Database::getInstance();
        $sets = [];
        $params = [];

        if (array_key_exists('is_active', $data)) {
            $sets[] = "is_active = ?";
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($sets)) return;

        $params[] = $id;
        $db->execute(
            "UPDATE queue_professionals SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    /**
     * Delete a queue professional assignment
     */
    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM queue_professionals WHERE id = ?", [$id]);
    }
}
