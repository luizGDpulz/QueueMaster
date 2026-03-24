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
use QueueMaster\Services\ContextAccessService;

/**
 * NotificationsController - Notification Management Endpoints
 * 
 * Handles notification listing and FCM token management
 */
class NotificationsController
{
    private Database $db;
    private ContextAccessService $accessService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->accessService = new ContextAccessService();
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

            $notifications = $this->enrichNotifications($notifications, $request->user);

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

            $notification = $this->enrichNotifications([$notification], $request->user)[0] ?? null;

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
     * POST /api/v1/notifications/batch-read
     *
     * Mark a selection of notifications as read.
     */
    public function batchRead(Request $request): void
    {
        $userId = (int)$request->user['id'];
        $ids = $this->sanitizeNotificationIds($request->all()['ids'] ?? []);

        if (empty($ids)) {
            Response::validationError(['ids' => 'Informe ao menos uma notificacao valida'], $request->requestId);
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$userId], $ids);

            $updated = $this->db->execute(
                "UPDATE notifications
                 SET read_at = NOW()
                 WHERE user_id = ?
                   AND id IN ($placeholders)
                   AND read_at IS NULL",
                $params
            );

            Logger::info('Batch notification read applied', [
                'user_id' => $userId,
                'notification_ids' => $ids,
                'updated_count' => $updated,
            ], $request->requestId);

            Response::success([
                'updated_count' => $updated,
                'message' => 'Notifications marked as read',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to mark notifications as read in batch', [
                'user_id' => $userId,
                'notification_ids' => $ids,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to mark notifications as read', $request->requestId);
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
     * POST /api/v1/notifications/batch-delete
     *
     * Delete a selection of notifications.
     */
    public function batchDelete(Request $request): void
    {
        $userId = (int)$request->user['id'];
        $ids = $this->sanitizeNotificationIds($request->all()['ids'] ?? []);

        if (empty($ids)) {
            Response::validationError(['ids' => 'Informe ao menos uma notificacao valida'], $request->requestId);
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$userId], $ids);

            $deleted = $this->db->execute(
                "DELETE FROM notifications
                 WHERE user_id = ?
                   AND id IN ($placeholders)",
                $params
            );

            Logger::info('Batch notification delete applied', [
                'user_id' => $userId,
                'notification_ids' => $ids,
                'deleted_count' => $deleted,
            ], $request->requestId);

            Response::success([
                'deleted_count' => $deleted,
                'message' => 'Notifications deleted successfully',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to delete notifications in batch', [
                'user_id' => $userId,
                'notification_ids' => $ids,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete notifications', $request->requestId);
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

    private function enrichNotifications(array $notifications, array $user): array
    {
        if (empty($notifications)) {
            return [];
        }

        $normalized = array_map([$this, 'normalizeNotification'], $notifications);
        $invitationIds = [];
        $roleRequestIds = [];

        foreach ($normalized as $notification) {
            $data = $notification['data'] ?? [];
            $type = (string)($notification['type'] ?? '');

            if (isset($data['invitation_id']) && in_array($type, ['business_invitation', 'professional_request_created', 'invitation_accepted', 'invitation_rejected'], true)) {
                $invitationIds[] = (int)$data['invitation_id'];
            }

            if (isset($data['request_id']) && in_array($type, ['manager_role_request', 'manager_request_created', 'manager_request_accepted', 'manager_request_rejected'], true)) {
                $roleRequestIds[] = (int)$data['request_id'];
            }
        }

        $invitationMap = $this->loadInvitationMap($invitationIds);
        $roleRequestMap = $this->loadRoleRequestMap($roleRequestIds);
        $accessibleBusinessMap = array_flip($this->accessService->getAccessibleBusinessIds($user));

        return array_map(function (array $notification) use ($accessibleBusinessMap, $invitationMap, $roleRequestMap): array {
            $data = $notification['data'] ?? [];
            $type = (string)($notification['type'] ?? '');
            $workflow = $this->buildDefaultWorkflow($notification);
            $details = [
                'kind' => 'generic',
                'business_name' => $data['business_name'] ?? null,
                'establishment_name' => $data['establishment_name'] ?? null,
                'decision_note' => $this->cleanText($data['decision_note'] ?? null),
            ];

            if (isset($data['invitation_id']) && in_array($type, ['business_invitation', 'professional_request_created', 'invitation_accepted', 'invitation_rejected'], true)) {
                $invitation = $invitationMap[(int)$data['invitation_id']] ?? null;
                $businessId = (int)($invitation['business_id'] ?? ($data['business_id'] ?? 0));
                $hasBusinessAccess = $businessId > 0 && isset($accessibleBusinessMap[$businessId]);
                $invitationStatus = (string)($invitation['status'] ?? $this->inferInvitationStatus($type));
                $canOpenBusiness = $businessId > 0 && $invitationStatus === 'accepted' && $hasBusinessAccess;

                if ($invitationStatus === 'accepted' && !$hasBusinessAccess && $businessId > 0) {
                    $workflow = [
                        'status_code' => 'access_removed',
                        'status_label' => 'Acesso removido',
                        'status_color' => 'negative',
                        'status_hint' => 'O vínculo foi aceito anteriormente, mas o acesso atual ao negócio não está mais disponível.',
                        'can_open_business' => false,
                        'business_route' => null,
                        'available_actions' => [],
                    ];
                } else {
                    $workflow = [
                        'status_code' => $invitationStatus,
                        'status_label' => $this->getInvitationStatusLabel($invitationStatus),
                        'status_color' => $this->getInvitationStatusColor($invitationStatus),
                        'status_hint' => $this->getInvitationStatusHint($invitationStatus),
                        'can_open_business' => $canOpenBusiness,
                        'business_route' => $canOpenBusiness ? $this->buildBusinessNotificationRoute($businessId) : null,
                        'available_actions' => $this->resolveInvitationActions($type, $invitationStatus, $canOpenBusiness),
                    ];
                }

                $details = [
                    'kind' => 'invitation',
                    'direction' => $invitation['direction'] ?? null,
                    'role' => $invitation['role'] ?? null,
                    'business_id' => $businessId > 0 ? $businessId : null,
                    'business_name' => $invitation['business_name'] ?? ($data['business_name'] ?? null),
                    'establishment_id' => isset($invitation['establishment_id']) ? (int)$invitation['establishment_id'] : ($data['establishment_id'] ?? null),
                    'establishment_name' => $invitation['establishment_name'] ?? ($data['establishment_name'] ?? null),
                    'from_user_name' => $invitation['from_user_name'] ?? null,
                    'from_user_email' => $invitation['from_user_email'] ?? null,
                    'to_user_name' => $invitation['to_user_name'] ?? null,
                    'to_user_email' => $invitation['to_user_email'] ?? null,
                    'request_message' => $this->cleanText($invitation['message'] ?? null),
                    'decision_note' => $this->cleanText($data['decision_note'] ?? null),
                    'created_at' => $invitation['created_at'] ?? null,
                    'responded_at' => $invitation['responded_at'] ?? null,
                ];
            } elseif (isset($data['request_id']) && in_array($type, ['manager_role_request', 'manager_request_created', 'manager_request_accepted', 'manager_request_rejected'], true)) {
                $roleRequest = $roleRequestMap[(int)$data['request_id']] ?? null;
                $requestStatus = (string)($roleRequest['status'] ?? $this->inferManagerRequestStatus($type));

                $workflow = [
                    'status_code' => $requestStatus,
                    'status_label' => $this->getManagerRequestStatusLabel($requestStatus),
                    'status_color' => $this->getManagerRequestStatusColor($requestStatus),
                    'status_hint' => $this->getManagerRequestStatusHint($requestStatus),
                    'can_open_business' => false,
                    'business_route' => null,
                    'available_actions' => $this->resolveManagerRequestActions($type, $requestStatus),
                ];

                $payload = $roleRequest['payload'] ?? [];
                $details = [
                    'kind' => 'manager_request',
                    'requester_name' => $roleRequest['user_name'] ?? ($data['requester_name'] ?? null),
                    'requester_email' => $roleRequest['user_email'] ?? ($data['requester_email'] ?? null),
                    'business_name' => $payload['business_name'] ?? ($data['business_name'] ?? null),
                    'business_segment' => $payload['business_segment'] ?? ($data['business_segment'] ?? null),
                    'motivation' => $this->cleanText($payload['motivation'] ?? null),
                    'notes' => $this->cleanText($payload['notes'] ?? null),
                    'decision_note' => $this->cleanText($payload['review_note'] ?? ($data['decision_note'] ?? null)),
                    'reviewed_by_name' => $roleRequest['reviewed_by_user_name'] ?? null,
                    'created_at' => $roleRequest['created_at'] ?? null,
                    'reviewed_at' => $roleRequest['reviewed_at'] ?? null,
                ];
            }

            $notification['workflow'] = $workflow;
            $notification['details'] = $details;

            return $notification;
        }, $normalized);
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

    private function sanitizeNotificationIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn($item): int => (int)$item,
            $value
        ), static fn(int $id): bool => $id > 0)));

        return array_slice($ids, 0, 200);
    }

    private function loadInvitationMap(array $ids): array
    {
        $ids = $this->sanitizeNotificationIds($ids);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "
            SELECT
                bi.*,
                b.name AS business_name,
                e.name AS establishment_name,
                fu.name AS from_user_name,
                fu.email AS from_user_email,
                tu.name AS to_user_name,
                tu.email AS to_user_email
            FROM business_invitations bi
            LEFT JOIN businesses b ON b.id = bi.business_id
            LEFT JOIN establishments e ON e.id = bi.establishment_id
            LEFT JOIN users fu ON fu.id = bi.from_user_id
            LEFT JOIN users tu ON tu.id = bi.to_user_id
            WHERE bi.id IN ($placeholders)
            ",
            $ids
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }

        return $map;
    }

    private function loadRoleRequestMap(array $ids): array
    {
        $ids = $this->sanitizeNotificationIds($ids);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "
            SELECT
                urr.*,
                u.name AS user_name,
                u.email AS user_email,
                ru.name AS reviewed_by_user_name
            FROM user_role_requests urr
            LEFT JOIN users u ON u.id = urr.user_id
            LEFT JOIN users ru ON ru.id = urr.reviewed_by_user_id
            WHERE urr.id IN ($placeholders)
            ",
            $ids
        );

        $map = [];
        foreach ($rows as $row) {
            if (isset($row['payload']) && is_string($row['payload'])) {
                $row['payload'] = json_decode($row['payload'], true) ?? [];
            }
            if (!isset($row['payload']) || !is_array($row['payload'])) {
                $row['payload'] = [];
            }
            $map[(int)$row['id']] = $row;
        }

        return $map;
    }

    private function buildDefaultWorkflow(array $notification): array
    {
        $isRead = !empty($notification['read_at']);

        return [
            'status_code' => $isRead ? 'read' : 'unread',
            'status_label' => $isRead ? 'Lida' : 'Nao lida',
            'status_color' => $isRead ? 'positive' : 'warning',
            'status_hint' => $isRead ? 'Notificacao ja visualizada.' : 'Ainda nao visualizada.',
            'can_open_business' => false,
            'business_route' => null,
            'available_actions' => [],
        ];
    }

    private function inferInvitationStatus(string $type): string
    {
        return match ($type) {
            'invitation_accepted' => 'accepted',
            'invitation_rejected' => 'rejected',
            default => 'pending',
        };
    }

    private function inferManagerRequestStatus(string $type): string
    {
        return match ($type) {
            'manager_request_accepted' => 'accepted',
            'manager_request_rejected' => 'rejected',
            default => 'pending',
        };
    }

    private function getInvitationStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'accepted' => 'Aceito',
            'rejected' => 'Recusado',
            'cancelled' => 'Cancelado',
            default => 'Atualizado',
        };
    }

    private function getInvitationStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'accepted' => 'positive',
            'rejected' => 'negative',
            'cancelled' => 'grey-7',
            default => 'grey-7',
        };
    }

    private function getInvitationStatusHint(string $status): string
    {
        return match ($status) {
            'pending' => 'A solicitacao ainda aguarda decisao.',
            'accepted' => 'O vinculo foi aprovado.',
            'rejected' => 'O vinculo nao foi aprovado.',
            'cancelled' => 'A solicitacao foi cancelada.',
            default => 'O status desta notificacao foi atualizado.',
        };
    }

    private function resolveInvitationActions(string $type, string $status, bool $canOpenBusiness): array
    {
        $actions = [];

        if ($type === 'business_invitation' && $status === 'pending') {
            $actions[] = 'accept_invitation';
            $actions[] = 'reject_invitation';
        }

        if ($status === 'accepted' && $canOpenBusiness) {
            $actions[] = 'open_business';
        }

        return $actions;
    }

    private function getManagerRequestStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'accepted' => 'Aprovado',
            'rejected' => 'Recusado',
            'cancelled' => 'Cancelado',
            default => 'Atualizado',
        };
    }

    private function getManagerRequestStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'accepted' => 'positive',
            'rejected' => 'negative',
            'cancelled' => 'grey-7',
            default => 'grey-7',
        };
    }

    private function getManagerRequestStatusHint(string $status): string
    {
        return match ($status) {
            'pending' => 'A solicitacao ainda aguarda analise.',
            'accepted' => 'O acesso solicitado foi liberado.',
            'rejected' => 'O acesso solicitado nao foi liberado.',
            'cancelled' => 'A solicitacao foi cancelada.',
            default => 'O status desta solicitacao foi atualizado.',
        };
    }

    private function resolveManagerRequestActions(string $type, string $status): array
    {
        if ($type === 'manager_role_request' && $status === 'pending') {
            return ['approve_manager_request', 'reject_manager_request'];
        }

        return [];
    }

    private function buildBusinessNotificationRoute(int $businessId): string
    {
        return '/app/businesses/' . $businessId . '?tab=professionals';
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }
}
