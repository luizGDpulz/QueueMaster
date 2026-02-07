<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\BusinessSubscription;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Establishment;
use QueueMaster\Services\QuotaService;
use QueueMaster\Services\AuditService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

/**
 * BusinessController - Business Management Endpoints
 * 
 * Handles business CRUD, user management, and establishment listing.
 */
class BusinessController
{
    /**
     * GET /api/v1/businesses
     * Admin => all businesses, Manager => only their businesses
     */
    public function list(Request $request): void
    {
        try {
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole === 'admin') {
                $businesses = Business::all([], 'name', 'ASC');
            } else {
                $businesses = Business::getByUser($userId);
            }

            Response::success([
                'businesses' => $businesses,
                'total' => count($businesses),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list businesses', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve businesses', $request->requestId);
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

            // Check access (admin or business user)
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole !== 'admin' && !BusinessUser::exists($id, $userId)) {
                Response::forbidden('You do not have access to this business', $request->requestId);
                return;
            }

            Response::success([
                'business' => $business,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get business', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve business', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses
     * Create business (any authenticated user)
     */
    public function create(Request $request): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];

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

            // Auto-assign Free plan subscription
            $freePlan = Plan::all(['name' => 'Free'], '', 'ASC', 1);
            if (!empty($freePlan)) {
                BusinessSubscription::create([
                    'business_id' => $businessId,
                    'plan_id' => $freePlan[0]['id'],
                    'status' => 'active',
                    'starts_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $business = Business::find($businessId);

            // Update user role to manager if currently client
            if ($request->user['role'] === 'client') {
                \QueueMaster\Models\User::update($userId, ['role' => 'manager']);
            }

            AuditService::logFromRequest($request, 'create', 'business', (string)$businessId, null, $businessId);

            Logger::info('Business created', [
                'business_id' => $businessId,
                'created_by' => $userId,
            ], $request->requestId);

            Response::created([
                'business' => $business,
                'message' => 'Business created successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\Exception $e) {
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

            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole !== 'admin' && !BusinessUser::exists($id, $userId)) {
                Response::forbidden('You do not have access to this business', $request->requestId);
                return;
            }

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

            AuditService::logFromRequest($request, 'update', 'business', (string)$id, null, $id, $updateData);

            Response::success([
                'business' => $updatedBusiness,
                'message' => 'Business updated successfully',
            ]);
        } catch (\Exception $e) {
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

            $establishments = Business::getEstablishments($id);

            Response::success([
                'establishments' => $establishments,
                'total' => count($establishments),
            ]);
        } catch (\Exception $e) {
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
        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\Exception $e) {
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

            $role = $data['role'] ?? 'manager';
            if (!in_array($role, ['owner', 'manager'])) {
                Response::validationError(['role' => 'Invalid role. Must be owner or manager'], $request->requestId);
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

            // Update user's global role if needed
            if ($user['role'] === 'client') {
                \QueueMaster\Models\User::update($targetUserId, ['role' => 'manager']);
            }

            AuditService::logFromRequest($request, 'add_user', 'business', (string)$id, null, $id, [
                'target_user_id' => $targetUserId,
                'role' => $role,
            ]);

            Response::created([
                'message' => 'User added to business successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        } catch (\Exception $e) {
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

            if (!BusinessUser::exists($id, $userId)) {
                Response::notFound('User is not part of this business', $request->requestId);
                return;
            }

            BusinessUser::removeUser($id, $userId);

            AuditService::logFromRequest($request, 'remove_user', 'business', (string)$id, null, $id, [
                'removed_user_id' => $userId,
            ]);

            Response::success(['message' => 'User removed from business successfully']);
        } catch (\Exception $e) {
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

            $users = Business::getUsers($id);

            Response::success([
                'users' => $users,
                'total' => count($users),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list business users', [
                'business_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve business users', $request->requestId);
        }
    }
}
