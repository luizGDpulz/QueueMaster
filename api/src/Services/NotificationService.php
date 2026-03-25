<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * NotificationService - Notification Management
 *
 * Stores notifications in the database for in-app display.
 */
class NotificationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Send notification to user
     * 
     * @param int $userId Target user ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool Success status
     */
    public function send(int $userId, string $title, string $body, array $data = []): bool
    {
        try {
            $this->storeNotification($userId, $title, $body, $data);
            return true;

        } catch (\Exception $e) {
            Logger::error('Failed to send notification', [
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store notification in database
     */
    private function storeNotification(int $userId, string $title, string $body, array $data): int
    {
        $sql = "
            INSERT INTO notifications (user_id, title, body, data, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        $this->db->execute($sql, [
            $userId,
            $title,
            $body,
            json_encode($data)
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * List user notifications
     */
    public function list(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
        $countResult = $this->db->query($countSql, [$userId]);
        $total = (int)$countResult[0]['total'];

        // Fetch notifications
        $sql = "
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY sent_at DESC
            LIMIT ? OFFSET ?
        ";
        $notifications = $this->db->query($sql, [$userId, $perPage, $offset]);

        return [
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Notification templates for common events
     */
    public static function templates(): array
    {
        return [
            'queue_joined' => [
                'title' => 'Você entrou na fila',
                'body' => 'Sua posição é %d. Tempo estimado: %d minutos.',
            ],
            'queue_called' => [
                'title' => 'É sua vez!',
                'body' => 'Por favor, dirija-se ao atendimento.',
            ],
            'queue_reminder' => [
                'title' => 'Você é o próximo',
                'body' => 'Faltam %d pessoas na sua frente.',
            ],
            'appointment_created' => [
                'title' => 'Agendamento confirmado',
                'body' => 'Seu agendamento para %s foi confirmado.',
            ],
            'appointment_reminder' => [
                'title' => 'Lembrete de agendamento',
                'body' => 'Seu agendamento começa em %d minutos.',
            ],
            'appointment_checkin' => [
                'title' => 'Check-in realizado',
                'body' => 'Check-in confirmado. Aguarde ser chamado.',
            ],
        ];
    }

    /**
     * Send templated notification
     */
    public function sendTemplate(int $userId, string $template, array $params = [], array $data = []): bool
    {
        $templates = self::templates();

        if (!isset($templates[$template])) {
            Logger::warning('Unknown notification template', ['template' => $template]);
            return false;
        }

        $config = $templates[$template];
        $title = $config['title'];
        $body = vsprintf($config['body'], $params);

        return $this->send($userId, $title, $body, array_merge(['template' => $template], $data));
    }
}
