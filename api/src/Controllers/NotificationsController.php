<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Models\FcmToken;
use QueueMaster\Models\Notification;
use QueueMaster\Models\NotificationPreference;

/**
 * NotificationsController - Notification Management Endpoints
 * 
 * Handles notification listing and FCM token management
 */
class NotificationsController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/v1/notifications
     * 
     * List user notifications (paginated)
     */
    public function list(Request $request): void
    {
        $userId = (int)$request->user['id'];
        $page = max((int)$request->getQuery('page', 1), 1);
        $perPage = min(max((int)$request->getQuery('per_page', 20), 1), 100);
        $offset = ($page - 1) * $perPage;
        $type = trim((string)$request->getQuery('type', ''));
        $search = trim((string)$request->getQuery('search', ''));
        $dateFrom = trim((string)$request->getQuery('date_from', ''));
        $dateTo = trim((string)$request->getQuery('date_to', ''));
        $unread = filter_var($request->getQuery('unread', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $where = ['user_id = ?'];
            $params = [$userId];

            if ($unread) {
                $where[] = 'read_at IS NULL';
            }

            if ($type !== '') {
                $where[] = 'type = ?';
                $params[] = $type;
            }

            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = '(title LIKE ? OR body LIKE ? OR CAST(data AS CHAR) LIKE ?)';
                array_push($params, $like, $like, $like);
            }

            if ($dateFrom !== '') {
                $where[] = 'DATE(COALESCE(sent_at, created_at)) >= ?';
                $params[] = $dateFrom;
            }

            if ($dateTo !== '') {
                $where[] = 'DATE(COALESCE(sent_at, created_at)) <= ?';
                $params[] = $dateTo;
            }

            $whereSql = implode(' AND ', $where);

            $countRows = $this->db->query(
                "SELECT COUNT(*) AS total FROM notifications WHERE $whereSql",
                $params
            );
            $total = (int)($countRows[0]['total'] ?? 0);

            $queryParams = array_merge($params, [$perPage, $offset]);
            $notifications = $this->db->query(
                "
                SELECT *
                FROM notifications
                WHERE $whereSql
                ORDER BY COALESCE(sent_at, created_at) DESC, id DESC
                LIMIT ? OFFSET ?
                ",
                $queryParams
            );

            $notifications = array_map([$this, 'normalizeNotification'], $notifications);

            Response::success($notifications, [
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ],
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve notifications', $request->requestId);
        }
    }

    /**
     * GET /api/v1/notifications/{id}
     * 
     * Get single notification
     */
    public function get(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $notification = Notification::find($id);

            if (!$notification) {
                Response::notFound('Notification not found', $request->requestId);
                return;
            }

            if ($notification['user_id'] != $userId) {
                Response::forbidden('You cannot view this notification', $request->requestId);
                return;
            }

            $notification = $this->normalizeNotification($notification);

            Response::success([
                'notification' => $notification,
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve notification', $request->requestId);
        }
    }

    /**
     * POST /api/v1/notifications/fcm-token
     * 
     * Save FCM token for user
     */
    public function saveFcmToken(Request $request): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];

        // Validate input
        $errors = Validator::make($data, [
            'token' => 'required|min:10',
            'device_id' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $token = trim($data['token']);
        $deviceId = trim($data['device_id']);

        try {
            $tokenId = FcmToken::upsert($userId, $deviceId, $token);

            Logger::info('FCM token saved', [
                'token_id' => $tokenId,
                'user_id' => $userId,
                'device_id' => $deviceId,
            ], $request->requestId);

            Response::success([
                'message' => 'FCM token saved successfully',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to save FCM token', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to save FCM token', $request->requestId);
        }
    }

    /**
     * POST /api/v1/notifications/:id/mark-read
     * 
     * Mark notification as read
     */
    public function markRead(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            // Find notification using Model
            $notification = Notification::find($id);

            if (!$notification) {
                Response::notFound('Notification not found', $request->requestId);
                return;
            }

            if ($notification['user_id'] != $userId) {
                Response::forbidden('You cannot mark this notification as read', $request->requestId);
                return;
            }

            // Mark as read using Model
            Notification::markAsRead($id);

            Logger::info('Notification marked as read', [
                'notification_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            Response::success([
                'message' => 'Notification marked as read',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to mark notification as read', [
                'notification_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to mark notification as read', $request->requestId);
        }
    }

    /**
     * POST /api/v1/notifications/mark-all-read
     * 
     * Mark all user notifications as read
     */
    public function markAllRead(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            Notification::markAllAsReadForUser($userId);

            Logger::info('All notifications marked as read', [
                'user_id' => $userId,
            ], $request->requestId);

            Response::success([
                'message' => 'All notifications marked as read',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to mark all notifications as read', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to mark all notifications as read', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * 
     * Delete notification
     */
    public function delete(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $notification = Notification::find($id);

            if (!$notification) {
                Response::notFound('Notification not found', $request->requestId);
                return;
            }

            if ($notification['user_id'] != $userId) {
                Response::forbidden('You cannot delete this notification', $request->requestId);
                return;
            }

            Notification::delete($id);

            Logger::info('Notification deleted', [
                'notification_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            Response::success(['message' => 'Notification deleted successfully']);

        } catch (\Exception $e) {
            Logger::error('Failed to delete notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete notification', $request->requestId);
        }
    }

    /**
     * GET /api/v1/notifications/unread-count
     * 
     * Get count of unread notifications for current user
     */
    public function unreadCount(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            $count = Notification::getUnreadCount($userId);

            Response::success([
                'unread_count' => $count,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get unread count', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve unread count', $request->requestId);
        }
    }

    public function getPreferences(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            Response::success([
                'preferences' => NotificationPreference::getByUser($userId),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve notification preferences', $request->requestId);
        }
    }

    public function updatePreferences(Request $request): void
    {
        $userId = (int)$request->user['id'];
        $data = $request->all();

        $errors = Validator::make($data, [
            'push_enabled' => 'required|boolean',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $preferences = NotificationPreference::saveForUser(
                $userId,
                filter_var($data['push_enabled'], FILTER_VALIDATE_BOOLEAN)
            );

            Response::success([
                'preferences' => $preferences,
                'message' => 'Notification preferences updated',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to update notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update notification preferences', $request->requestId);
        }
    }

    private function normalizeNotification(array $notification): array
    {
        if (isset($notification['data']) && is_string($notification['data'])) {
            $notification['data'] = json_decode($notification['data'], true) ?? [];
        }

        if (!isset($notification['data']) || !is_array($notification['data'])) {
            $notification['data'] = [];
        }

        $notification['is_read'] = !is_null($notification['read_at'] ?? null);
        return $notification;
    }
}
