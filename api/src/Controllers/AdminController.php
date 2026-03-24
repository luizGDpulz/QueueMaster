<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Models\AuditLog;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Business;
use QueueMaster\Models\RefreshToken;
use QueueMaster\Models\User;
use QueueMaster\Models\UserPlanSubscription;
use QueueMaster\Services\AdminScopeService;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\PlanService;
use QueueMaster\Services\QuotaService;
use QueueMaster\Services\UserAccessControlService;
use QueueMaster\Services\UserRoleService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

/**
 * AdminController - Admin/Manager endpoints
 * 
 * Provides audit logs viewing, subscription management, and plans.
 * Managers can only see logs scoped to their own businesses.
 */
class AdminController
{
    private PlanService $planService;
    private AdminScopeService $scopeService;
    private UserRoleService $userRoleService;
    private UserAccessControlService $userAccessControlService;

    public function __construct()
    {
        $this->planService = new PlanService();
        $this->scopeService = new AdminScopeService();
        $this->userRoleService = new UserRoleService();
        $this->userAccessControlService = new UserAccessControlService();
    }

    public function users(Request $request): void
    {
        try {
            $users = $this->scopeService->getVisibleUsers($request->user);
            $safeUsers = array_map(static function (array $user): array {
                $safeUser = User::getSafeData($user);
                $safeUser['effective_role'] = $safeUser['role'] ?? 'client';
                return $safeUser;
            }, $users);

            Response::success([
                'users' => $safeUsers,
                'total' => count($safeUsers),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list admin users', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve admin users', $request->requestId);
        }
    }

    public function getUserProfile(Request $request, int $id): void
    {
        try {
            if (!$this->scopeService->canViewAdminUser($request->user, $id)) {
                Response::forbidden('Você não tem acesso a este usuário', $request->requestId);
                return;
            }

            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            $safeUser = User::getSafeData($user);
            $memberships = $this->scopeService->getVisibleMembershipsForUser($request->user, $id);
            $isOwner = $this->userRoleService->userHasOwnershipSignals($id);
            $isPlanHolder = $this->planService->userCanHoldPlan($id);
            $currentSubscription = $this->planService->getCurrentSubscriptionForUser($id);
            $usageSnapshot = $isPlanHolder ? $this->planService->getUsageSnapshotForUser($id) : null;
            $roleOptions = $this->resolveRoleOptions($user, $memberships, $isOwner);
            $accessState = $this->userAccessControlService->buildUserAccessSnapshot($user);
            $blockedBy = !empty($user['login_blocked_by_user_id']) ? User::find((int)$user['login_blocked_by_user_id']) : null;
            $managementSummary = $this->buildUserManagementSummary($request, $user, $isOwner);
            $isAdminActor = ($request->user['role'] ?? null) === 'admin';

            Response::success([
                'user' => array_merge($safeUser, [
                    'effective_role' => $this->userRoleService->resolveEffectiveRole($id),
                    'contextual_roles' => $memberships,
                    'is_owner' => $isOwner,
                    'is_plan_holder' => $isPlanHolder,
                    'access_state' => array_merge($accessState, [
                        'blocked_by_name' => $blockedBy['name'] ?? null,
                        'blocked_by_email' => $blockedBy['email'] ?? null,
                    ]),
                    'editable_fields' => [
                        'name' => !($safeUser['is_google_managed_profile'] ?? false) && ($request->user['role'] ?? null) === 'admin',
                        'email' => !($safeUser['is_google_managed_profile'] ?? false) && ($request->user['role'] ?? null) === 'admin',
                        'phone' => ($request->user['role'] ?? null) === 'admin',
                        'address_line_1' => ($request->user['role'] ?? null) === 'admin',
                        'address_line_2' => ($request->user['role'] ?? null) === 'admin',
                        'role' => ($request->user['role'] ?? null) === 'admin' && !empty($roleOptions),
                        'plan' => $isAdminActor && $isPlanHolder,
                        'is_active' => false,
                        'block_access' => $isAdminActor && (bool)($managementSummary['can_block'] ?? false),
                        'unblock_access' => $isAdminActor && (bool)($managementSummary['can_unblock'] ?? false),
                        'revoke_sessions' => $isAdminActor && (bool)($managementSummary['can_revoke_sessions'] ?? false),
                        'delete_user' => $isAdminActor && (bool)($managementSummary['can_delete'] ?? false),
                    ],
                    'role_options' => $roleOptions,
                    'management_summary' => $managementSummary,
                ]),
                'memberships' => $memberships,
                'plan_assignment' => [
                    'subscription' => $currentSubscription,
                    'plan' => $currentSubscription['plan'] ?? null,
                    'usage' => $usageSnapshot,
                ],
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get admin user profile', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve user profile', $request->requestId);
        }
    }

    public function updateUserProfile(Request $request, int $id): void
    {
        try {
            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];
            $isGoogleManaged = !empty($user['google_id'] ?? null);

            if (!$isGoogleManaged && isset($data['name']) && trim((string)$data['name']) !== '') {
                $updateData['name'] = trim((string)$data['name']);
            }

            if (!$isGoogleManaged && isset($data['email'])) {
                $email = strtolower(trim((string)$data['email']));
                if ($email !== '' && $email !== strtolower((string)$user['email'])) {
                    $errors = Validator::make(['email' => $email], [
                        'email' => 'required|email|unique:users,email,' . $id,
                    ]);
                    if (!empty($errors)) {
                        Response::validationError($errors, $request->requestId);
                        return;
                    }
                    $updateData['email'] = $email;
                }
            }

            foreach (['phone', 'address_line_1', 'address_line_2'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field] === null ? null : trim((string)$data[$field]);
                }
            }

            if (array_key_exists('is_active', $data)) {
                $nextIsActive = (bool)$data['is_active'];
                if ((int)$request->user['id'] === $id && $nextIsActive === false) {
                    Response::error('CANNOT_BLOCK_SELF', 'Você não pode bloquear seu próprio acesso por esta tela.', 409, $request->requestId);
                    return;
                }

                if ($nextIsActive) {
                    $updateData['is_active'] = 1;
                    $updateData['login_blocked_at'] = null;
                    $updateData['login_block_reason'] = null;
                    $updateData['login_blocked_by_user_id'] = null;
                }
                else {
                    $updateData['is_active'] = 0;
                    $updateData['login_blocked_at'] = date('Y-m-d H:i:s');
                    $updateData['login_block_reason'] = trim((string)($data['login_block_reason'] ?? 'Bloqueado manualmente pela administração.'));
                    $updateData['login_blocked_by_user_id'] = (int)$request->user['id'];
                }
            }

            $roleChanged = false;
            if (isset($data['role']) && $data['role'] !== ($user['role'] ?? null)) {
                $this->applyRoleTransition($id, (string)$data['role']);
                $roleChanged = true;
            }

            if (!empty($updateData)) {
                $validationErrors = User::validate($updateData, true);
                if (!empty($validationErrors)) {
                    Response::validationError($validationErrors, $request->requestId);
                    return;
                }

                User::update($id, $updateData);
                if (array_key_exists('is_active', $updateData) && (int)$updateData['is_active'] === 0) {
                    RefreshToken::revokeAllForUser($id);
                }
            }

            if (empty($updateData) && !$roleChanged) {
                Response::validationError(['general' => 'No fields to update'], $request->requestId);
                return;
            }

            $this->userRoleService->syncUserRole($id);
            $updatedUser = User::getSafeData(User::find($id));

            AuditService::logFromRequest($request, 'update', 'admin_user_profile', (string)$id, null, null, [
                'changes' => array_keys($updateData),
                'role_changed' => $roleChanged,
            ]);

            Response::success([
                'user' => $updatedUser,
                'message' => 'User profile updated successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::error('ROLE_TRANSITION_BLOCKED', $e->getMessage(), 409, $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update admin user profile', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update user profile', $request->requestId);
        }
    }

    public function updateUserPlan(Request $request, int $id): void
    {
        $planId = (int)($request->all()['plan_id'] ?? 0);

        if ($planId <= 0) {
            Response::validationError(['plan_id' => 'Plan is required'], $request->requestId);
            return;
        }

        try {
            $subscription = $this->planService->assignPlanToUser($id, $planId);

            AuditService::logFromRequest($request, 'assign_plan', 'user', (string)$id, null, null, [
                'plan_id' => $planId,
                'subscription_id' => $subscription['id'] ?? null,
            ]);

            Response::success([
                'subscription' => $subscription,
                'message' => 'Plan updated successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::error('PLAN_ASSIGNMENT_BLOCKED', $e->getMessage(), 409, $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update user plan', [
                'user_id' => $id,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update user plan', $request->requestId);
        }
    }

    public function blockUser(Request $request, int $id): void
    {
        try {
            $user = $this->loadManageableUser($request, $id, false);
            if (!$user) {
                return;
            }

            if (!(bool)($user['is_active'] ?? false)) {
                Response::success([
                    'message' => 'Usuário já está bloqueado.',
                    'user' => User::getSafeData($user),
                ]);
                return;
            }

            $reason = trim((string)($request->all()['reason'] ?? ''));
            if ($reason !== '' && mb_strlen($reason) > 500) {
                Response::validationError(['reason' => 'O motivo do bloqueio deve ter no máximo 500 caracteres.'], $request->requestId);
                return;
            }

            User::update($id, [
                'is_active' => 0,
                'login_blocked_at' => date('Y-m-d H:i:s'),
                'login_block_reason' => $reason !== '' ? $reason : 'Bloqueado manualmente pela administração.',
                'login_blocked_by_user_id' => (int)$request->user['id'],
            ]);
            RefreshToken::revokeAllForUser($id);

            AuditService::logFromRequest($request, 'block_access', 'user', (string)$id, null, null, [
                'email' => $user['email'] ?? null,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            Response::success([
                'message' => 'Acesso do usuário bloqueado com sucesso.',
                'user' => User::getSafeData(User::find($id)),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to block user access', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to block user access', $request->requestId);
        }
    }

    public function unblockUser(Request $request, int $id): void
    {
        try {
            $user = $this->loadManageableUser($request, $id, false);
            if (!$user) {
                return;
            }

            if ((bool)($user['is_active'] ?? false)) {
                Response::success([
                    'message' => 'Usuário já está com acesso liberado.',
                    'user' => User::getSafeData($user),
                ]);
                return;
            }

            User::update($id, [
                'is_active' => 1,
                'login_blocked_at' => null,
                'login_block_reason' => null,
                'login_blocked_by_user_id' => null,
            ]);

            AuditService::logFromRequest($request, 'unblock_access', 'user', (string)$id, null, null, [
                'email' => $user['email'] ?? null,
            ]);

            Response::success([
                'message' => 'Acesso do usuário liberado com sucesso.',
                'user' => User::getSafeData(User::find($id)),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to unblock user access', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to unblock user access', $request->requestId);
        }
    }

    public function revokeUserSessions(Request $request, int $id): void
    {
        try {
            $user = $this->loadManageableUser($request, $id, false);
            if (!$user) {
                return;
            }

            RefreshToken::revokeAllForUser($id);

            AuditService::logFromRequest($request, 'revoke_sessions', 'user', (string)$id, null, null, [
                'email' => $user['email'] ?? null,
            ]);

            Response::success([
                'message' => 'Sessões do usuário encerradas com sucesso.',
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to revoke user sessions', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to revoke user sessions', $request->requestId);
        }
    }

    public function deleteUser(Request $request, int $id): void
    {
        $db = Database::getInstance();

        try {
            $user = $this->loadManageableUser($request, $id, false);
            if (!$user) {
                return;
            }

            $summary = $this->buildUserManagementSummary($request, $user, $this->userRoleService->userHasOwnershipSignals($id));
            if (!($summary['can_delete'] ?? false)) {
                Response::error(
                    'USER_DELETE_BLOCKED',
                    (string)($summary['delete_blockers'][0] ?? 'Este usuário não pode ser excluído agora.'),
                    409,
                    $request->requestId,
                    ['blockers' => $summary['delete_blockers'] ?? []]
                );
                return;
            }

            $db->beginTransaction();
            RefreshToken::revokeAllForUser($id);
            User::delete($id);
            $db->commit();

            AuditService::logFromRequest($request, 'delete', 'user', (string)$id, null, null, [
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? null,
            ]);

            Response::success([
                'message' => 'Cadastro do usuário excluído com sucesso.',
            ]);
        }
        catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            Logger::error('Failed to delete admin user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to delete user', $request->requestId);
        }
    }

    /**
     * GET /api/v1/admin/audit-logs
     * List audit logs with advanced filters & pagination.
     * Admin: sees all logs.
     * Manager: sees only logs from their businesses.
     *
     * Query params:
     *   page, per_page, business_id, establishment_id, user_id,
     *   action, entity, date_from, date_to, search
     */
    public function auditLogs(Request $request): void
    {
        try {
            $params = $request->getQuery();
            $filters = [];

            if (($request->user['role'] ?? null) === 'manager') {
                $scopedBusinessIds = $this->scopeService->getManageableBusinessIds($request->user);
                $scopedEstablishmentIds = $this->scopeService->getManageableEstablishmentIds($request->user);

                if (empty($scopedBusinessIds) && empty($scopedEstablishmentIds)) {
                    Response::success([
                        'logs' => [],
                        'total' => 0,
                        'page' => 1,
                        'per_page' => 25,
                        'total_pages' => 1,
                    ]);
                    return;
                }

                // If a specific business_id is requested, verify access
                if (!empty($params['business_id'])) {
                    $requestedBid = (int)$params['business_id'];
                    if (!in_array($requestedBid, $scopedBusinessIds, true)) {
                        Response::forbidden('Você não tem acesso a este negócio', $request->requestId);
                        return;
                    }
                    $filters['business_id'] = $requestedBid;
                }

                if (!empty($params['establishment_id'])) {
                    $requestedEstablishmentId = (int)$params['establishment_id'];
                    if (!in_array($requestedEstablishmentId, $scopedEstablishmentIds, true)) {
                        Response::forbidden('Você não tem acesso a este estabelecimento', $request->requestId);
                        return;
                    }
                    $filters['establishment_id'] = $requestedEstablishmentId;
                }

                $filters['scoped_business_ids'] = $scopedBusinessIds;
                $filters['scoped_establishment_ids'] = $scopedEstablishmentIds;
            }
            elseif (!empty($params['business_id'])) {
                $filters['business_id'] = (int)$params['business_id'];
            }

            if (!empty($params['establishment_id']) && ($request->user['role'] ?? null) !== 'manager') {
                $filters['establishment_id'] = (int)$params['establishment_id'];
            }
            if (!empty($params['user_id'])) {
                $filters['user_id'] = (int)$params['user_id'];
            }
            if (!empty($params['action'])) {
                $filters['action'] = $params['action'];
            }
            if (!empty($params['entity'])) {
                $filters['entity'] = $params['entity'];
            }
            if (!empty($params['date_from'])) {
                $filters['date_from'] = $params['date_from'];
            }
            if (!empty($params['date_to'])) {
                $filters['date_to'] = $params['date_to'];
            }
            if (!empty($params['search'])) {
                $filters['search'] = $params['search'];
            }

            $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
            $perPage = isset($params['per_page']) ? max(1, min(100, (int)$params['per_page'])) : 25;

            $result = AuditLog::search($filters, $page, $perPage);

            Response::success($result);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list audit logs', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve audit logs', $request->requestId);
        }
    }

    /**
     * GET /api/v1/admin/audit-logs/filters
     * Returns available filter options (distinct actions, entities, businesses).
     * Scoped by role — manager only sees their own businesses' data.
     */
    public function auditLogFilters(Request $request): void
    {
        try {
            $businessIds = null;
            $establishmentIds = null;

            if (($request->user['role'] ?? null) === 'manager') {
                $businessIds = $this->scopeService->getManageableBusinessIds($request->user);
                $establishmentIds = $this->scopeService->getManageableEstablishmentIds($request->user);
                if (empty($businessIds) && empty($establishmentIds)) {
                    Response::success([
                        'actions' => [],
                        'entities' => [],
                        'businesses' => [],
                    ]);
                    return;
                }
            }

            $actions = AuditLog::getDistinctActions($businessIds, $establishmentIds);
            $entities = AuditLog::getDistinctEntities($businessIds, $establishmentIds);

            $businesses = [];
            if ($businessIds !== null) {
                foreach ($businessIds as $bid) {
                    $b = Business::find($bid);
                    if ($b) {
                        $businesses[] = ['id' => (int)$b['id'], 'name' => $b['name']];
                    }
                }
            }
            else {
                $allBusinesses = Business::all([], 'name', 'ASC');
                foreach ($allBusinesses as $b) {
                    $businesses[] = ['id' => (int)$b['id'], 'name' => $b['name']];
                }
            }

            Response::success([
                'actions' => $actions,
                'entities' => $entities,
                'businesses' => $businesses,
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get audit log filters', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve filter options', $request->requestId);
        }
    }

    /**
     * GET /api/v1/admin/subscriptions
     * List all subscriptions
     */
    public function subscriptions(Request $request): void
    {
        try {
            $subscriptions = UserPlanSubscription::listDetailed();

            Response::success([
                'subscriptions' => $subscriptions,
                'total' => count($subscriptions),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list subscriptions', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve subscriptions', $request->requestId);
        }
    }

    /**
     * GET /api/v1/admin/plans
     * List all plans
     */
    public function plans(Request $request): void
    {
        try {
            $plans = Plan::all([], 'name', 'ASC');
            $isAdmin = ($request->user['role'] ?? null) === 'admin';

            foreach ($plans as &$plan) {
                $plan = array_merge($plan, $this->planService->getPlanStats((int)$plan['id']));
            }
            unset($plan);

            $currentPlan = null;
            $currentSubscription = null;
            if (!$isAdmin) {
                $currentSubscription = $this->planService->getCurrentSubscriptionForUser((int)$request->user['id']);
                $currentPlan = $currentSubscription['plan'] ?? null;
                $plans = array_values(array_filter(
                    $plans,
                    static fn(array $plan): bool => (bool)($plan['is_active'] ?? false)
                ));
            }

            Response::success([
                'plans' => $plans,
                'current_plan' => $currentPlan,
                'current_subscription' => $currentSubscription,
                'total' => count($plans),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list plans', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve plans', $request->requestId);
        }
    }

    /**
     * GET /api/v1/admin/plans/{id}
     * Get single plan
     */
    public function getPlan(Request $request, int $id): void
    {
        try {
            $plan = Plan::find($id);
            if (!$plan) {
                Response::notFound('Plan not found', $request->requestId);
                return;
            }

            Response::success([
                'plan' => array_merge($plan, $this->planService->getPlanStats($id)),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve plan', $request->requestId);
        }
    }

    /**
     * POST /api/v1/admin/plans
     * Create plan (admin only)
     */
    public function createPlan(Request $request): void
    {
        $data = $request->all();

        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:100',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $planData = $this->normalizePlanPayload($data, null, $request->requestId);
            if ($planData === null) {
                return;
            }

            if ($this->planNameExists($planData['name'])) {
                Response::validationError(['name' => 'A plan with this name already exists'], $request->requestId);
                return;
            }

            $planId = Plan::create($planData);
            $plan = Plan::find($planId);

            AuditService::logFromRequest($request, 'create', 'plan', (string)$planId, null, null, $planData);

            Response::created([
                'plan' => $plan,
                'message' => 'Plan created successfully',
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create plan', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to create plan', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/admin/plans/{id}
     * Update plan (admin only)
     */
    public function updatePlan(Request $request, int $id): void
    {
        try {
            $plan = Plan::find($id);
            if (!$plan) {
                Response::notFound('Plan not found', $request->requestId);
                return;
            }

            $updateData = $this->normalizePlanPayload($request->all(), $plan, $request->requestId);
            if ($updateData === null) {
                return;
            }

            if (isset($updateData['name']) && $this->planNameExists($updateData['name'], $id)) {
                Response::validationError(['name' => 'A plan with this name already exists'], $request->requestId);
                return;
            }

            $candidatePlan = array_merge($plan, $updateData);
            foreach ($this->planService->getLinkedUsersForPlan($id, true) as $linkedUser) {
                $violations = $this->planService->getUsageViolationsForPlan((int)$linkedUser['id'], $candidatePlan);
                if (!empty($violations)) {
                    Response::error(
                        'PLAN_IN_USE_CONFLICT',
                        'Não é possível reduzir este plano abaixo do uso atual dos gerentes vinculados.',
                        409,
                        $request->requestId,
                        ['violations' => $violations, 'user_id' => (int)$linkedUser['id']]
                    );
                    return;
                }
            }

            Plan::update($id, $updateData);
            $updated = Plan::find($id);

            AuditService::logFromRequest($request, 'update', 'plan', (string)$id, null, null, $updateData);

            Response::success([
                'plan' => $updated,
                'message' => 'Plan updated successfully',
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update plan', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/admin/plans/{id}
     * Delete plan (admin only)
     */
    public function deletePlan(Request $request, int $id): void
    {
        try {
            $plan = Plan::find($id);
            if (!$plan) {
                Response::notFound('Plan not found', $request->requestId);
                return;
            }

            $deletionCheck = $this->planService->canDeletePlan($id);
            if (!($deletionCheck['allowed'] ?? false)) {
                Response::error('PLAN_DELETE_BLOCKED', (string)$deletionCheck['message'], 409, $request->requestId, $deletionCheck);
                return;
            }

            Plan::delete($id);

            AuditService::logFromRequest($request, 'delete', 'plan', (string)$id, null, null, [
                'name' => $plan['name'],
            ]);

            Response::success(['message' => 'Plan deleted successfully']);
        }
        catch (\Exception $e) {
            Logger::error('Failed to delete plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to delete plan', $request->requestId);
        }
    }

    // =========================================================================
    // Subscriptions CRUD
    // =========================================================================

    /**
     * GET /api/v1/admin/subscriptions/{id}
     * Get single subscription
     */
    public function getSubscription(Request $request, int $id): void
    {
        try {
            $subscription = UserPlanSubscription::find($id);
            if (!$subscription) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            $user = User::find((int)$subscription['user_id']);
            $plan = Plan::find((int)$subscription['plan_id']);

            Response::success([
                'subscription' => array_merge($subscription, [
                    'user_name' => $user['name'] ?? null,
                    'user_email' => $user['email'] ?? null,
                    'plan_name' => $plan['name'] ?? null,
                ]),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get subscription', [
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve subscription', $request->requestId);
        }
    }

    /**
     * POST /api/v1/admin/subscriptions
     * Create subscription (admin only)
     */
    public function createSubscription(Request $request): void
    {
        $data = $request->all();
        $planId = (int)($data['plan_id'] ?? 0);
        $userId = (int)($data['user_id'] ?? 0);

        if ($userId <= 0 && !empty($data['business_id'])) {
            $userId = (int)($this->planService->getHolderUserIdForBusiness((int)$data['business_id']) ?? 0);
        }

        if ($userId <= 0 || $planId <= 0) {
            Response::validationError([
                'user_id' => 'User is required',
                'plan_id' => 'Plan is required',
            ], $request->requestId);
            return;
        }

        try {
            $subscription = $this->planService->assignPlanToUser($userId, $planId);

            AuditService::logFromRequest($request, 'create', 'user_plan_subscription', (string)($subscription['id'] ?? 0), null, null, [
                'user_id' => $userId,
                'plan_id' => $planId,
            ]);

            Response::created([
                'subscription' => $subscription,
                'message' => 'Subscription created successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::error('PLAN_ASSIGNMENT_BLOCKED', $e->getMessage(), 409, $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create subscription', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to create subscription', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/admin/subscriptions/{id}
     * Update subscription (admin only)
     */
    public function updateSubscription(Request $request, int $id): void
    {
        try {
            $subscription = UserPlanSubscription::find($id);
            if (!$subscription) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            if (isset($data['plan_id'])) {
                $newSubscription = $this->planService->assignPlanToUser((int)$subscription['user_id'], (int)$data['plan_id']);
                Response::success([
                    'subscription' => $newSubscription,
                    'message' => 'Subscription updated successfully',
                ]);
                return;
            }

            if (isset($data['status'])) {
                $validStatuses = ['active', 'cancelled', 'past_due'];
                if (!in_array($data['status'], $validStatuses)) {
                    Response::validationError(['status' => 'Invalid status. Allowed: ' . implode(', ', $validStatuses)], $request->requestId);
                    return;
                }
                $updateData['status'] = $data['status'];
            }

            if (isset($data['starts_at'])) {
                $updateData['starts_at'] = $data['starts_at'];
            }
            if (array_key_exists('ends_at', $data)) {
                $updateData['ends_at'] = $data['ends_at'];
            }

            if (empty($updateData)) {
                Response::validationError(['general' => 'No fields to update'], $request->requestId);
                return;
            }

            UserPlanSubscription::update($id, $updateData);
            $updated = UserPlanSubscription::find($id);

            AuditService::logFromRequest($request, 'update', 'user_plan_subscription', (string)$id, null, null, $updateData);

            Response::success([
                'subscription' => $updated,
                'message' => 'Subscription updated successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::error('PLAN_ASSIGNMENT_BLOCKED', $e->getMessage(), 409, $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update subscription', [
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update subscription', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/admin/subscriptions/{id}
     * Delete subscription (admin only)
     */
    public function deleteSubscription(Request $request, int $id): void
    {
        try {
            $subscription = UserPlanSubscription::find($id);
            if (!$subscription) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            if (in_array($subscription['status'], ['active', 'past_due'], true)) {
                Response::error('CONFLICT', 'Active subscriptions must be cancelled before deletion', 409, $request->requestId);
                return;
            }

            UserPlanSubscription::delete($id);

            AuditService::logFromRequest($request, 'delete', 'user_plan_subscription', (string)$id, null, null, [
                'plan_id' => $subscription['plan_id'],
            ]);

            Response::success(['message' => 'Subscription deleted successfully']);
        }
        catch (\Exception $e) {
            Logger::error('Failed to delete subscription', [
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to delete subscription', $request->requestId);
        }
    }

    private function normalizePlanPayload(array $data, ?array $currentPlan, ?string $requestId): ?array
    {
        $payload = [];

        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if ($name === '' || strlen($name) < 2 || strlen($name) > 100) {
                Response::validationError(['name' => 'Plan name must have between 2 and 100 characters'], $requestId);
                return null;
            }
            $payload['name'] = $name;
        }
        elseif ($currentPlan === null) {
            $payload['name'] = '';
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (int)((bool)$data['is_active']);
        }
        elseif ($currentPlan === null) {
            $payload['is_active'] = 1;
        }

        foreach ([
            'max_businesses',
            'max_establishments_per_business',
            'max_managers',
            'max_professionals_per_establishment',
        ] as $field) {
            if (!array_key_exists($field, $data) && $currentPlan !== null) {
                continue;
            }

            $value = $data[$field] ?? null;
            if ($value === '' || $value === null) {
                $payload[$field] = null;
                continue;
            }

            $intValue = (int)$value;
            if ($intValue <= 0) {
                Response::validationError([$field => 'Plan limits must be positive integers or empty for unlimited'], $requestId);
                return null;
            }

            $payload[$field] = $intValue;
        }

        if ($currentPlan !== null && empty($payload)) {
            Response::validationError(['general' => 'No fields to update'], $requestId);
            return null;
        }

        return $payload;
    }

    private function planNameExists(string $name, ?int $ignoreId = null): bool
    {
        $normalized = mb_strtolower(trim($name));
        foreach (Plan::all([], 'name', 'ASC') as $plan) {
            if ($ignoreId !== null && (int)$plan['id'] === $ignoreId) {
                continue;
            }

            if (mb_strtolower(trim((string)$plan['name'])) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function applyRoleTransition(int $userId, string $newRole): void
    {
        if ($newRole === 'admin') {
            throw new \RuntimeException('Promoção para admin não é suportada por esta tela.');
        }

        $user = User::find($userId);
        if (!$user) {
            throw new \RuntimeException('Usuário não encontrado.');
        }

        $isOwner = $this->userRoleService->userHasOwnershipSignals($userId);
        $memberships = $this->scopeService->getVisibleMembershipsForUser(['role' => 'admin', 'id' => 0], $userId);
        $hasStaffMemberships = !empty($memberships['businesses']) || !empty($memberships['establishments']);
        $hasManagerGrant = (bool)($user['manager_access_granted'] ?? false);

        if ($newRole === 'client') {
            if ($this->userRoleService->hasActivePlanSubscription($userId)) {
                throw new \RuntimeException('Cancele o plano ativo antes de transformar o usuário em cliente.');
            }
            if ($hasStaffMemberships || $isOwner) {
                throw new \RuntimeException('Não é permitido transformar um usuário vinculado em cliente.');
            }

            User::update($userId, [
                'manager_access_granted' => 0,
                'manager_access_granted_at' => null,
                'role' => 'client',
            ]);
            return;
        }

        if (!in_array($newRole, ['manager', 'professional'], true)) {
            throw new \RuntimeException('Somente transições entre manager e professional são permitidas.');
        }

        if (!$hasStaffMemberships && !$hasManagerGrant) {
            throw new \RuntimeException('O usuário não possui vínculos contextuais para esta transição de papel.');
        }

        if ($newRole === 'professional' && $isOwner) {
            throw new \RuntimeException('O dono do negócio não pode ser transformado em profissional.');
        }

        if ($newRole === 'professional' && !$hasStaffMemberships) {
            throw new \RuntimeException('O usuário precisa ter vínculos profissionais para ser mantido como profissional.');
        }

        if ($newRole === 'professional') {
            User::update($userId, [
                'manager_access_granted' => 0,
                'manager_access_granted_at' => null,
            ]);
        }

        if ($newRole === 'manager') {
            foreach ($this->userRoleService->getProfessionalBusinessIdsForPromotion($userId) as $businessId) {
                if ($businessId <= 0 || $this->userRoleService->isAlreadyManagerInBusiness($userId, $businessId)) {
                    continue;
                }

                $quotaCheck = QuotaService::canAddManager($businessId);
                if (!($quotaCheck['allowed'] ?? false)) {
                    throw new \RuntimeException($quotaCheck['message'] ?? 'O plano deste negócio não suporta mais gerentes.');
                }
            }
        }

        $this->userRoleService->applyContextualRoleTransition($userId, $newRole);
    }

    private function loadManageableUser(Request $request, int $id, bool $allowSelf): ?array
    {
        if (!$this->scopeService->canViewAdminUser($request->user, $id)) {
            Response::forbidden('Você não tem acesso a este usuário', $request->requestId);
            return null;
        }

        if (!$allowSelf && (int)$request->user['id'] === $id) {
            Response::error('SELF_ACTION_BLOCKED', 'Esta ação não pode ser aplicada no seu próprio usuário.', 409, $request->requestId);
            return null;
        }

        $user = User::find($id);
        if (!$user) {
            Response::notFound('User not found', $request->requestId);
            return null;
        }

        return $user;
    }

    private function buildUserManagementSummary(Request $request, array $user, bool $isOwner): array
    {
        $userId = (int)$user['id'];
        $actorId = (int)($request->user['id'] ?? 0);
        $deleteBlockers = [];

        if ($actorId === $userId) {
            $deleteBlockers[] = 'Você não pode excluir seu próprio usuário por esta tela.';
        }

        if ($this->userRoleService->hasActivePlanSubscription($userId)) {
            $deleteBlockers[] = 'Cancele ou remova o plano ativo antes de excluir o usuário.';
        }

        if ($isOwner) {
            $deleteBlockers[] = 'Remova ou transfira os negócios/estabelecimentos de titularidade antes de excluir o usuário.';
        }

        return [
            'can_block' => $actorId !== $userId && (bool)($user['is_active'] ?? false),
            'can_unblock' => $actorId !== $userId && !(bool)($user['is_active'] ?? false),
            'can_revoke_sessions' => $actorId !== $userId,
            'can_delete' => empty($deleteBlockers),
            'delete_blockers' => $deleteBlockers,
        ];
    }

    private function resolveRoleOptions(array $user, array $memberships, bool $isOwner): array
    {
        if (($user['role'] ?? null) === 'admin') {
            return [];
        }

        if ($isOwner) {
            return [
                ['label' => 'Gerente', 'value' => 'manager'],
            ];
        }

        $hasMemberships = !empty($memberships['businesses']) || !empty($memberships['establishments']);
        if ($hasMemberships) {
            return [
                ['label' => 'Gerente', 'value' => 'manager'],
                ['label' => 'Profissional', 'value' => 'professional'],
            ];
        }

        if ((bool)($user['manager_access_granted'] ?? false)) {
            return [
                ['label' => 'Gerente', 'value' => 'manager'],
                ['label' => 'Cliente', 'value' => 'client'],
            ];
        }

        return [
            ['label' => 'Cliente', 'value' => 'client'],
        ];
    }
}
