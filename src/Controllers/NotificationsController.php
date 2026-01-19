<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;

/**
 * NotificationsController - Notification Management Endpoints
 * 
 * Handles notification listing and FCM token management
 */
class NotificationsController
{
    /**
     * GET /api/v1/notifications
     * 
     * List user notifications (paginated)
     */
    public function list(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];
        $params = $request->query();

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;
        $perPage = min($perPage, 100); // Max 100 per page

        $offset = ($page - 1) * $perPage;

        try {
            $db = Database::getInstance();

            // Count total notifications
            $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
            $countResult = $db->query($countSql, [$userId]);
            $total = (int)$countResult[0]['total'];

            // Fetch notifications
            $sql = "
                SELECT id, title, body, data, read_at, sent_at 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY sent_at DESC 
                LIMIT ? OFFSET ?
            ";
            $notifications = $db->query($sql, [$userId, $perPage, $offset]);

            // Parse JSON data field
            foreach ($notifications as &$notification) {
                $notification['data'] = json_decode($notification['data'], true) ?? [];
                $notification['is_read'] = !is_null($notification['read_at']);
            }

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
     * POST /api/v1/notifications/fcm-token
     * 
     * Save FCM token for user
     */
    public function saveFcmToken(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

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
            $db = Database::getInstance();

            // Check if token already exists for this user/device
            $checkSql = "SELECT id FROM fcm_tokens WHERE user_id = ? AND device_id = ? LIMIT 1";
            $existing = $db->query($checkSql, [$userId, $deviceId]);

            if (!empty($existing)) {
                // Update existing token
                $updateSql = "UPDATE fcm_tokens SET token = ?, updated_at = NOW() WHERE user_id = ? AND device_id = ?";
                $db->execute($updateSql, [$token, $userId, $deviceId]);

                Logger::info('FCM token updated', [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                ], $request->requestId);

            } else {
                // Insert new token
                $insertSql = "INSERT INTO fcm_tokens (user_id, token, device_id, created_at) VALUES (?, ?, ?, NOW())";
                $db->execute($insertSql, [$userId, $token, $deviceId]);

                Logger::info('FCM token saved', [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                ], $request->requestId);
            }

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        try {
            $db = Database::getInstance();

            // Verify notification belongs to user
            $notificationSql = "SELECT id, user_id FROM notifications WHERE id = ? LIMIT 1";
            $notifications = $db->query($notificationSql, [$id]);

            if (empty($notifications)) {
                Response::notFound('Notification not found', $request->requestId);
                return;
            }

            $notification = $notifications[0];

            if ($notification['user_id'] != $userId) {
                Response::forbidden('You cannot mark this notification as read', $request->requestId);
                return;
            }

            // Mark as read
            $updateSql = "UPDATE notifications SET read_at = NOW() WHERE id = ?";
            $db->execute($updateSql, [$id]);

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        try {
            $db = Database::getInstance();

            // Mark all unread notifications as read
            $updateSql = "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL";
            $db->execute($updateSql, [$userId]);

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
}
