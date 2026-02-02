<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * NotificationService - Push Notification Management
 * 
 * Provides FCM (Firebase Cloud Messaging) integration for push notifications.
 * Also stores notifications in database for in-app display.
 * 
 * Production notes:
 * - Requires FCM_SERVER_KEY environment variable
 * - For production, consider using a queue (Redis/RabbitMQ) to send async
 * - Implement retry logic for failed sends
 * - Consider using FCM batch sending for multiple recipients
 */
class NotificationService
{
    private Database $db;
    private bool $fcmEnabled = false;
    private ?string $fcmServerKey = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->fcmEnabled = filter_var($_ENV['FCM_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->fcmServerKey = $_ENV['FCM_SERVER_KEY'] ?? null;
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
            // Store notification in database
            $notificationId = $this->storeNotification($userId, $title, $body, $data);

            // Send push notification if FCM is enabled
            if ($this->fcmEnabled && $this->fcmServerKey) {
                $this->sendPushNotification($userId, $title, $body, $data);
            } else {
                Logger::debug('FCM disabled - notification stored but not pushed', [
                    'user_id' => $userId,
                    'notification_id' => $notificationId,
                ]);
            }

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
     * Send push notification via FCM
     */
    private function sendPushNotification(int $userId, string $title, string $body, array $data): bool
    {
        // Get user's FCM token(s)
        $tokens = $this->getUserFcmTokens($userId);

        if (empty($tokens)) {
            Logger::debug('No FCM tokens for user', ['user_id' => $userId]);
            return false;
        }

        // Prepare FCM payload
        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'priority' => 'high',
        ];

        // Send to FCM
        try {
            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: key=' . $this->fcmServerKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Logger::info('FCM notification sent', [
                    'user_id' => $userId,
                    'tokens_count' => count($tokens),
                ]);
                return true;
            } else {
                Logger::warning('FCM send failed', [
                    'user_id' => $userId,
                    'http_code' => $httpCode,
                    'response' => $response,
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Logger::error('FCM request failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get user's FCM tokens
     * 
     * Note: This requires a fcm_tokens table (not in current schema)
     * For MVP, return empty array (implement when FCM is integrated)
     */
    private function getUserFcmTokens(int $userId): array
    {
        // TODO: Implement fcm_tokens table and retrieval
        // For now, return empty array
        return [];
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
