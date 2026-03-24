<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Notification;
use QueueMaster\Models\User;
use QueueMaster\Models\UserRoleRequest;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\PlanService;
use QueueMaster\Services\UserRoleService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

class RoleRequestsController
{
    private UserRoleService $userRoleService;
    private PlanService $planService;

    public function __construct()
    {
        $this->userRoleService = new UserRoleService();
        $this->planService = new PlanService();
    }

    public function listMine(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            Response::success([
                'requests' => UserRoleRequest::getByUser($userId),
                'role_summary' => $this->userRoleService->getRoleSummary($userId),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list role requests', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve role requests', $request->requestId);
        }
    }

    public function requestManagerAccess(Request $request): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];

        $errors = Validator::make($data, [
            'business_name' => 'required|max:150',
            'business_segment' => 'required|max:120',
            'motivation' => 'required|max:2000',
            'website_url' => 'max:255',
            'linkedin_url' => 'max:255',
            'notes' => 'max:2000',
        ]);

        $websiteUrl = $this->normalizeOptionalUrl($data['website_url'] ?? null, 'website_url', $errors);
        $linkedinUrl = $this->normalizeOptionalUrl($data['linkedin_url'] ?? null, 'linkedin_url', $errors);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            if (($user['role'] ?? null) === 'admin') {
                Response::error('INVALID_ROLE', 'Administrators do not need this request', 409, $request->requestId);
                return;
            }

            $roleSummary = $this->userRoleService->getRoleSummary($userId);
            if (!empty($roleSummary['can_manage_own_businesses'])) {
                Response::error('ALREADY_ALLOWED', 'You already have management access', 409, $request->requestId);
                return;
            }

            if (UserRoleRequest::findPendingByUser($userId, 'manager')) {
                Response::error('REQUEST_ALREADY_PENDING', 'You already have a pending manager request', 409, $request->requestId);
                return;
            }

            $payload = [
                'business_name' => trim((string)$data['business_name']),
                'business_segment' => trim((string)$data['business_segment']),
                'motivation' => trim((string)$data['motivation']),
                'website_url' => $websiteUrl,
                'linkedin_url' => $linkedinUrl,
                'notes' => trim((string)($data['notes'] ?? '')),
            ];

            $requestId = UserRoleRequest::create([
                'user_id' => $userId,
                'requested_role' => 'manager',
                'status' => 'pending',
                'message' => $this->buildManagerRequestMessage($payload),
                'payload' => $payload,
            ]);

            $this->notifyAdminsAboutManagerRequest($requestId, $user, $payload);

            Notification::create([
                'user_id' => $userId,
                'type' => 'manager_request_created',
                'title' => 'Solicitação de gerência criada',
                'body' => 'Seu pedido para liberar gestão de negócios foi enviado aos administradores.',
                'data' => [
                    'request_id' => $requestId,
                    'requested_role' => 'manager',
                    'deep_link' => '/app/settings?tab=roles',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'request_manager_access', 'user_role_request', (string)$requestId, null, null, [
                'user_id' => $userId,
                'requested_role' => 'manager',
                'business_name' => $payload['business_name'],
                'business_segment' => $payload['business_segment'],
            ]);

            Response::created([
                'request_id' => $requestId,
                'message' => 'Manager request created successfully',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to create manager role request', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to create manager request', $request->requestId);
        }
    }

    public function approve(Request $request, int $id): void
    {
        $adminId = (int)$request->user['id'];

        try {
            $roleRequest = UserRoleRequest::find($id);
            if (!$roleRequest) {
                Response::notFound('Role request not found', $request->requestId);
                return;
            }

            if (($roleRequest['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Request is no longer pending', 409, $request->requestId);
                return;
            }

            $userId = (int)$roleRequest['user_id'];
            UserRoleRequest::update($id, [
                'status' => 'accepted',
                'reviewed_by_user_id' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            User::update($userId, [
                'manager_access_granted' => 1,
                'manager_access_granted_at' => date('Y-m-d H:i:s'),
                'role' => 'manager',
            ]);
            $this->userRoleService->syncUserRole($userId);

            if ($this->planService->getCurrentSubscriptionForUser($userId) === null) {
                $defaultPlan = $this->planService->getDefaultPlan();
                if ($defaultPlan) {
                    $this->planService->assignPlanToUser($userId, (int)$defaultPlan['id']);
                }
            }

            Notification::create([
                'user_id' => $userId,
                'type' => 'manager_request_accepted',
                'title' => 'Solicitação de gerência aprovada',
                'body' => 'Seu acesso para criar e gerir negócios foi liberado.',
                'data' => [
                    'request_id' => $id,
                    'requested_role' => 'manager',
                    'deep_link' => '/app/settings?tab=roles',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'approve_manager_access', 'user_role_request', (string)$id, null, null, [
                'user_id' => $userId,
                'requested_role' => 'manager',
            ]);

            Response::success(['message' => 'Role request approved']);
        } catch (\Exception $e) {
            Logger::error('Failed to approve role request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to approve role request', $request->requestId);
        }
    }

    public function reject(Request $request, int $id): void
    {
        $adminId = (int)$request->user['id'];
        $data = $request->all();

        $errors = Validator::make($data, [
            'decision_note' => 'max:2000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $roleRequest = UserRoleRequest::find($id);
            if (!$roleRequest) {
                Response::notFound('Role request not found', $request->requestId);
                return;
            }

            if (($roleRequest['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Request is no longer pending', 409, $request->requestId);
                return;
            }

            $decisionNote = $this->resolveDecisionNote($data['decision_note'] ?? null);
            $payload = is_array($roleRequest['payload'] ?? null)
                ? $roleRequest['payload']
                : (json_decode((string)($roleRequest['payload'] ?? ''), true) ?? []);
            $payload['review_note'] = $decisionNote;

            UserRoleRequest::update($id, [
                'status' => 'rejected',
                'reviewed_by_user_id' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'payload' => $payload,
            ]);

            Notification::create([
                'user_id' => (int)$roleRequest['user_id'],
                'type' => 'manager_request_rejected',
                'title' => 'Solicitação de gerência recusada',
                'body' => 'Seu pedido de acesso para gestão de negócios não foi aprovado.',
                'data' => [
                    'request_id' => $id,
                    'requested_role' => 'manager',
                    'deep_link' => '/app/settings?tab=roles',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'reject_manager_access', 'user_role_request', (string)$id, null, null, [
                'user_id' => (int)$roleRequest['user_id'],
                'requested_role' => 'manager',
                'decision_note' => $decisionNote,
            ]);

            Response::success(['message' => 'Role request rejected']);
        } catch (\Exception $e) {
            Logger::error('Failed to reject role request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to reject role request', $request->requestId);
        }
    }

    public function cancel(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $roleRequest = UserRoleRequest::find($id);
            if (!$roleRequest) {
                Response::notFound('Role request not found', $request->requestId);
                return;
            }

            if ((int)($roleRequest['user_id'] ?? 0) !== $userId) {
                Response::forbidden('You cannot cancel this request', $request->requestId);
                return;
            }

            if (($roleRequest['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Only pending requests can be cancelled', 409, $request->requestId);
                return;
            }

            UserRoleRequest::update($id, [
                'status' => 'cancelled',
            ]);

            AuditService::logFromRequest($request, 'cancel_manager_access_request', 'user_role_request', (string)$id, null, null, [
                'user_id' => $userId,
                'requested_role' => 'manager',
            ]);

            Response::success(['message' => 'Role request cancelled']);
        } catch (\Exception $e) {
            Logger::error('Failed to cancel role request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to cancel role request', $request->requestId);
        }
    }

    public function revertToClient(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            $user = User::find($userId);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            if (($user['role'] ?? null) === 'admin') {
                Response::forbidden('Administrators cannot use this flow', $request->requestId);
                return;
            }

            $summary = $this->userRoleService->getRoleSummary($userId);
            if (!empty($summary['revert_blockers'])) {
                Response::error('REVERT_BLOCKED', implode(' ', $summary['revert_blockers']), 409, $request->requestId);
                return;
            }

            User::update($userId, [
                'manager_access_granted' => 0,
                'manager_access_granted_at' => null,
                'role' => 'client',
            ]);
            $newRole = $this->userRoleService->syncUserRole($userId);

            Notification::create([
                'user_id' => $userId,
                'type' => 'role_reverted_client',
                'title' => 'Conta ajustada para cliente',
                'body' => 'Seu perfil voltou para cliente.',
                'data' => [
                    'effective_role' => $newRole,
                    'deep_link' => '/app/settings?tab=roles',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'revert_to_client', 'user', (string)$userId, null, null, [
                'effective_role' => $newRole,
            ]);

            Response::success([
                'effective_role' => $newRole,
                'message' => 'User reverted to client successfully',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to revert user to client', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to revert user to client', $request->requestId);
        }
    }

    private function notifyAdminsAboutManagerRequest(int $requestId, array $user, array $payload): void
    {
        $admins = User::getByRole('admin');
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => (int)$admin['id'],
                'type' => 'manager_role_request',
                'title' => 'Nova solicitação de gerência',
                'body' => ($user['name'] ?? 'Um usuário') . ' solicitou liberação para criar e gerir negócios.',
                'data' => [
                    'request_id' => $requestId,
                    'requested_role' => 'manager',
                    'requester_user_id' => (int)$user['id'],
                    'requester_name' => $user['name'] ?? null,
                    'requester_email' => $user['email'] ?? null,
                    'business_name' => $payload['business_name'] ?? null,
                    'business_segment' => $payload['business_segment'] ?? null,
                    'deep_link' => '/app/settings?tab=notifications',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function buildManagerRequestMessage(array $payload): string
    {
        $sections = [
            'Negócio pretendido: ' . ($payload['business_name'] ?? ''),
            'Segmento: ' . ($payload['business_segment'] ?? ''),
            'Motivação: ' . ($payload['motivation'] ?? ''),
        ];

        if (!empty($payload['website_url'])) {
            $sections[] = 'Site: ' . $payload['website_url'];
        }
        if (!empty($payload['linkedin_url'])) {
            $sections[] = 'LinkedIn: ' . $payload['linkedin_url'];
        }
        if (!empty($payload['notes'])) {
            $sections[] = 'Observações: ' . $payload['notes'];
        }

        return implode("\n\n", $sections);
    }

    private function normalizeOptionalUrl(mixed $value, string $field, array &$errors): ?string
    {
        $url = trim((string)($value ?? ''));
        if ($url === '') {
            return null;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[$field] = 'Informe uma URL válida';
            return null;
        }

        return $url;
    }

    private function resolveDecisionNote(mixed $value): string
    {
        $note = trim((string)($value ?? ''));
        if ($note !== '') {
            return $note;
        }

        return 'No momento seu pedido nao foi aprovado, mas voce pode complementar as informacoes e enviar uma nova solicitacao quando quiser.';
    }
}
