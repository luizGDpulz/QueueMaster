<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\User;
use QueueMaster\Services\QuotaService;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Services\PlanService;
use QueueMaster\Services\UserRoleService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

/**
 * BusinessController - Business Management Endpoints
 * 
 * Handles business CRUD, user management, and establishment listing.
 */
class BusinessController
{
    private ContextAccessService $accessService;
    private PlanService $planService;
    private UserRoleService $userRoleService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
        $this->planService = new PlanService();
        $this->userRoleService = new UserRoleService();
    }

    /**
     * GET /api/v1/businesses
     * Admin => all businesses, Manager => only their businesses
     */
    public function list(Request $request): void
    {
        try {
            $businesses = $this->accessService->getAccessibleBusinesses($request->user);

            if ($this->accessService->isAdmin($request->user)) {
                foreach ($businesses as &$business) {
                    $business['user_role'] = 'admin';
                }
                unset($business);
            }

            Response::success([
                'businesses' => $businesses,
                'total' => count($businesses),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list businesses', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve businesses', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/search
     * Search/discover active businesses (any authenticated user)
     */
    public function search(Request $request): void
    {
        try {
            $query = trim((string)$request->getQuery('q', ''));
            $limit = min((int)$request->getQuery('limit', 20), 50);
            $accessibleBusinessIds = $this->accessService->getAccessibleBusinessIds($request->user);
            $accessibleBusinessMap = array_flip($accessibleBusinessIds);

            $params = [];
            $businesses = Business::searchDiscoverable($query, $limit);

            $results = array_map(function (array $business) use ($accessibleBusinessMap) {
                $businessId = (int)$business['id'];
                return [
                    'id' => $businessId,
                    'name' => $business['name'],
                    'slug' => $business['slug'] ?? null,
                    'description' => $business['description'] ?? null,
                    'establishment_count' => (int)($business['establishment_count'] ?? 0),
                    'is_linked' => isset($accessibleBusinessMap[$businessId]),
                ];
            }, $businesses);

            Response::success([
                'businesses' => $results,
                'total' => count($results),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to search businesses', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to search businesses', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/:id/discover
     * Public authenticated read-only business detail.
     */
    public function discover(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business || !($business['is_active'] ?? false)) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $accessibleBusinessIds = $this->accessService->getAccessibleBusinessIds($request->user);
            $establishments = array_values(array_filter(
                Business::getEstablishments($id),
                static fn(array $establishment): bool => (bool)($establishment['is_active'] ?? false)
            ));

            Response::success([
                'business' => [
                    'id' => (int)$business['id'],
                    'name' => $business['name'],
                    'slug' => $business['slug'] ?? null,
                    'description' => $business['description'] ?? null,
                    'is_active' => (bool)($business['is_active'] ?? false),
                    'is_linked' => in_array($id, $accessibleBusinessIds, true),
                ],
                'establishments' => array_map(static function (array $establishment): array {
                    return [
                        'id' => (int)$establishment['id'],
                        'business_id' => (int)$establishment['business_id'],
                        'name' => $establishment['name'],
                        'slug' => $establishment['slug'] ?? null,
                        'description' => $establishment['description'] ?? null,
                        'address' => $establishment['address'] ?? null,
                        'phone' => $establishment['phone'] ?? null,
                        'email' => $establishment['email'] ?? null,
                        'timezone' => $establishment['timezone'] ?? 'America/Sao_Paulo',
                        'opens_at' => $establishment['opens_at'] ?? null,
                        'closes_at' => $establishment['closes_at'] ?? null,
                        'is_active' => (bool)($establishment['is_active'] ?? false),
                    ];
                }, $establishments),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to discover business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve business', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/:id/discover-establishments
     * Discover active establishments for a business by name.
     */
    public function discoverEstablishments(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business || !($business['is_active'] ?? false)) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $query = trim((string)($request->getQuery()['q'] ?? ''));
            $limit = min(max((int)($request->getQuery()['limit'] ?? 20), 1), 50);

            $qb = new \QueueMaster\Builders\QueryBuilder();
            $qb->select('establishments', [
                'id',
                'business_id',
                'name',
                'slug',
                'address',
                'is_active',
            ])
                ->where('business_id', '=', $id)
                ->where('is_active', '=', 1);

            if ($query !== '') {
                $qb->where('name', 'LIKE', '%' . $query . '%');
            }

            $establishments = $qb
                ->orderBy('name', 'ASC')
                ->limit($limit)
                ->get();

            Response::success([
                'establishments' => $establishments,
                'total' => count($establishments),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to discover business establishments', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve establishments', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/:id
     */
    public function get(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessAccess(
                $request->user,
                $id,
                'Voce nao tem acesso a este negocio'
            );

            Response::success([
                'business' => $business,
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve business', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses
     * Create business (manager/admin only)
     */
    public function create(Request $request): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'] ?? 'client';
        $currentUser = User::find($userId);
        $hasManagerGrant = (bool)($currentUser['manager_access_granted'] ?? false);
        $alreadyOwnsBusiness = $this->userRoleService->userOwnsBusiness($userId);

        // Only managers and admins can create businesses
        if (!in_array($userRole, ['manager', 'admin'])) {
            Response::forbidden('Only managers and administrators can create businesses', $request->requestId);
            return;
        }

        if ($userRole !== 'admin' && !$hasManagerGrant && !$alreadyOwnsBusiness) {
            Response::forbidden('Only approved managers can create their own businesses', $request->requestId);
            return;
        }

        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:255',
            'slug' => 'max:120',
            'description' => 'max:5000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            // Check SaaS quota
            $quotaCheck = QuotaService::canCreateBusiness($userId);
            if (!$quotaCheck['allowed']) {
                Response::error($quotaCheck['error'], $quotaCheck['message'], 403, $request->requestId);
                return;
            }

            $businessData = [
                'owner_user_id' => $userId,
                'name' => trim($data['name']),
            ];

            if (isset($data['slug'])) {
                $businessData['slug'] = trim($data['slug']);
            }
            if (isset($data['description'])) {
                $businessData['description'] = trim($data['description']);
            }

            $businessId = Business::create($businessData);

            // Add creator as owner in business_users
            BusinessUser::addUser($businessId, $userId, BusinessUser::ROLE_OWNER);

            if ($userRole !== 'admin' && $this->planService->getCurrentSubscriptionForUser($userId) === null) {
                $freePlan = $this->planService->getDefaultPlan();
                if ($freePlan) {
                    $this->planService->assignPlanToUser($userId, (int)$freePlan['id']);
                }
            }

            $this->userRoleService->syncUserRole($userId);

            $business = Business::find($businessId);

            AuditService::logFromRequest($request, 'create', 'business', (string)$businessId, null, $businessId, [
                'name' => $businessData['name'] ?? null,
                'slug' => $businessData['slug'] ?? null,
                'description' => $businessData['description'] ?? null,
            ]);

            Logger::info('Business created', [
                'business_id' => $businessId,
                'created_by' => $userId,
            ], $request->requestId);

            Response::created([
                'business' => $business,
                'message' => 'Business created successfully',
            ]);
        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create business', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to create business', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/businesses/:id
     */
    public function update(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $id,
                'Voce nao tem permissao para editar este negocio'
            );

            $data = $request->all();
            $updateData = [];

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['slug'])) {
                $updateData['slug'] = trim($data['slug']);
            }
            if (isset($data['description'])) {
                $updateData['description'] = trim($data['description']);
            }

            if (empty($updateData)) {
                Response::error('NO_FIELDS_TO_UPDATE', 'No valid fields provided for update', 400, $request->requestId);
                return;
            }

            Business::update($id, $updateData);
            $updatedBusiness = Business::find($id);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $business[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'business', (string)$id, null, $id, [
                'entity_name' => $business['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'business' => $updatedBusiness,
                'message' => 'Business updated successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update business', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/:id/establishments
     */
    public function listEstablishments(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessAccess(
                $request->user,
                $id,
                'Voce nao tem acesso aos estabelecimentos deste negocio'
            );

            $establishments = Business::getEstablishments($id);

            if (!$this->accessService->isAdmin($request->user)) {
                $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($request->user);
                $establishments = array_values(array_filter(
                    $establishments,
                    static fn(array $establishment): bool => in_array((int)$establishment['id'], $accessibleEstablishmentIds, true)
                ));
            }

            Response::success([
                'establishments' => $establishments,
                'total' => count($establishments),
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list business establishments', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve establishments', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses/:id/establishments
     * Create establishment within a business (with quota check)
     */
    public function createEstablishment(Request $request, int $id): void
    {
        $data = $request->all();

        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:255',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $id,
                'Voce nao tem permissao para criar estabelecimentos neste negocio'
            );

            // Check SaaS quota
            $quotaCheck = QuotaService::canCreateEstablishment($id);
            if (!$quotaCheck['allowed']) {
                Response::error($quotaCheck['error'], $quotaCheck['message'], 403, $request->requestId);
                return;
            }

            $establishmentData = [
                'name' => trim($data['name']),
                'business_id' => $id,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
            ];

            $optionalFields = ['slug', 'description', 'address', 'phone', 'email', 'logo_url', 'opens_at', 'closes_at'];
            foreach ($optionalFields as $field) {
                if (isset($data[$field])) {
                    $establishmentData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            $establishmentId = Establishment::create($establishmentData);
            $establishment = Establishment::find($establishmentId);

            AuditService::logFromRequest($request, 'create', 'establishment', (string)$establishmentId, $establishmentId, $id);

            Response::created([
                'establishment' => $establishment,
                'message' => 'Establishment created successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create establishment', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to create establishment', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses/:id/users
     * Invite/add a user (manager) to a business
     */
    public function addUser(Request $request, int $id): void
    {
        $data = $request->all();

        $errors = Validator::make($data, [
            'user_id' => 'required|integer',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $id,
                'Voce nao tem permissao para gerenciar usuarios deste negocio'
            );

            $role = $data['role'] ?? 'manager';
            if (!in_array($role, ['owner', 'manager', 'professional'], true)) {
                Response::validationError(['role' => 'Invalid role. Must be owner, manager, or professional'], $request->requestId);
                return;
            }

            // Check quota for managers
            if ($role === 'manager') {
                $quotaCheck = QuotaService::canAddManager($id);
                if (!$quotaCheck['allowed']) {
                    Response::error($quotaCheck['error'], $quotaCheck['message'], 403, $request->requestId);
                    return;
                }
            }

            $targetUserId = (int)$data['user_id'];
            $user = \QueueMaster\Models\User::find($targetUserId);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            BusinessUser::addUser($id, $targetUserId, $role);

            $this->userRoleService->syncUserRole($targetUserId);

            AuditService::logFromRequest($request, 'add_user', 'business', (string)$id, null, $id, [
                'target_user_id' => $targetUserId,
                'role' => $role,
            ]);

            Response::created([
                'message' => 'User added to business successfully',
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to add user to business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to add user to business', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/businesses/:id/users/:userId
     * Remove a user from a business
     */
    public function removeUser(Request $request, int $id, int $userId): void
    {
        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $id,
                'Voce nao tem permissao para gerenciar usuarios deste negocio'
            );

            if (!BusinessUser::exists($id, $userId)) {
                Response::notFound('User is not part of this business', $request->requestId);
                return;
            }

            Business::removeUserFromContexts($id, $userId);

            $this->userRoleService->syncUserRole($userId);

            AuditService::logFromRequest($request, 'remove_user', 'business', (string)$id, null, $id, [
                'removed_user_id' => $userId,
            ]);

            Response::success(['message' => 'User removed from business successfully']);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to remove user from business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to remove user from business', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/:id/users
     * List users in a business
     */
    public function listUsers(Request $request, int $id): void
    {
        try {
            $business = Business::find($id);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessAccess(
                $request->user,
                $id,
                'Voce nao tem acesso aos usuarios deste negocio'
            );

            $users = Business::getUsers($id);

            Response::success([
                'users' => $users,
                'total' => count($users),
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list business users', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve business users', $request->requestId);
        }
    }
}
