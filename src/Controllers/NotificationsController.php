<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Models\Notification;

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
        $params = $request->getQuery();

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;
        $perPage = min($perPage, 100); // Max 100 per page

        $offset = ($page - 1) * $perPage;

        try {
            // Get all notifications for user
            $allNotifications = Notification::getByUser($userId);
            $total = count($allNotifications);

            // Apply pagination manually (since Model doesn't support offset)
            $notifications = array_slice($allNotifications, $offset, $perPage);

            // Parse JSON data field and add is_read flag
            foreach ($notifications as &$notification) {
                if (is_string($notification['data'])) {
                    $notification['data'] = json_decode($notification['data'], true) ?? [];
                }
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
     * GET /api/v1/notifications/{id}
     * 
     * Get single notification
     */
    public function get(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

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

            // Parse JSON data field
            if (is_string($notification['data'])) {
                $notification['data'] = json_decode($notification['data'], true) ?? [];
            }
            $notification['is_read'] = !is_null($notification['read_at']);

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

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
}
