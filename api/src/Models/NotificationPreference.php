<?php

namespace QueueMaster\Models;

use QueueMaster\Core\Database;

class NotificationPreference
{
    public static function getByUser(int $userId): array
    {
        $db = Database::getInstance();
        $rows = $db->query(
            'SELECT user_id, push_enabled, created_at, updated_at FROM notification_preferences WHERE user_id = ? LIMIT 1',
            [$userId]
        );

        if (empty($rows)) {
            return [
                'user_id' => $userId,
                'push_enabled' => false,
            ];
        }

        $row = $rows[0];
        $row['push_enabled'] = (bool)($row['push_enabled'] ?? false);
        return $row;
    }

    public static function saveForUser(int $userId, bool $pushEnabled): array
    {
        $db = Database::getInstance();
        $existing = $db->query(
            'SELECT user_id FROM notification_preferences WHERE user_id = ? LIMIT 1',
            [$userId]
        );

        if (!empty($existing)) {
            $db->execute(
                'UPDATE notification_preferences SET push_enabled = ?, updated_at = NOW() WHERE user_id = ?',
                [$pushEnabled ? 1 : 0, $userId]
            );
        } else {
            $db->execute(
                'INSERT INTO notification_preferences (user_id, push_enabled, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
                [$userId, $pushEnabled ? 1 : 0]
            );
        }

        return self::getByUser($userId);
    }
}
