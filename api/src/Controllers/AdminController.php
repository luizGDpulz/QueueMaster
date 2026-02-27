<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\AuditLog;
use QueueMaster\Models\BusinessSubscription;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Business;
use QueueMaster\Services\AuditService;
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
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            $filters = [];

            // --- Role-based scoping ---
            if ($userRole === 'manager') {
                // Manager can only see logs for businesses they belong to
                $businessUsers = BusinessUser::getBusinessesForUser($userId);
                $managerBusinessIds = array_map(fn($bu) => (int)$bu['business_id'], $businessUsers);

                if (empty($managerBusinessIds)) {
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
                    if (!in_array($requestedBid, $managerBusinessIds)) {
                        Response::forbidden('Você não tem acesso a este negócio');
                        return;
                    }
                    $filters['business_id'] = $requestedBid;
                }
                else {
                    $filters['business_ids'] = $managerBusinessIds;
                }
            }
            else {
                // Admin: optional business_id filter
                if (!empty($params['business_id'])) {
                    $filters['business_id'] = (int)$params['business_id'];
                }
            }

            // --- Optional filters ---
            if (!empty($params['establishment_id'])) {
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
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];
            $businessIds = null;

            if ($userRole === 'manager') {
                $businessUsers = BusinessUser::getBusinessesForUser($userId);
                $businessIds = array_map(fn($bu) => (int)$bu['business_id'], $businessUsers);
                if (empty($businessIds)) {
                    Response::success([
                        'actions' => [],
                        'entities' => [],
                        'businesses' => [],
                    ]);
                    return;
                }
            }

            $actions = AuditLog::getDistinctActions($businessIds);
            $entities = AuditLog::getDistinctEntities($businessIds);

            // Get business list for the filter dropdown
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
                // Admin gets all businesses
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
            $subscriptions = BusinessSubscription::all([], 'created_at', 'DESC');

            // Enrich with business and plan names
            foreach ($subscriptions as &$sub) {
                $business = Business::find($sub['business_id']);
                $sub['business_name'] = $business['name'] ?? null;
                $plan = Plan::find($sub['plan_id']);
                $sub['plan_name'] = $plan['name'] ?? null;
            }

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

            Response::success([
                'plans' => $plans,
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

            Response::success(['plan' => $plan]);
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
            $planData = [
                'name' => trim($data['name']),
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ];

            // Optional numeric limits
            $limitFields = [
                'max_businesses', 'max_establishments_per_business',
                'max_professionals_per_establishment', 'max_managers',
            ];
            foreach ($limitFields as $field) {
                if (array_key_exists($field, $data)) {
                    $planData[$field] = $data[$field] === null ? null : (int)$data[$field];
                }
            }

            $planId = Plan::create($planData);
            $plan = Plan::find($planId);

            AuditService::logFromRequest($request, 'create', 'plan', (string)$planId, null, null, $planData);

            Logger::info('Plan created', ['plan_id' => $planId], $request->requestId);

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

            $data = $request->all();
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (array_key_exists('is_active', $data)) {
                $updateData['is_active'] = (int)$data['is_active'];
            }

            $limitFields = [
                'max_businesses', 'max_establishments_per_business',
                'max_professionals_per_establishment', 'max_managers',
            ];
            foreach ($limitFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field] === null ? null : (int)$data[$field];
                }
            }

            if (empty($updateData)) {
                Response::validationError(['general' => 'No fields to update'], $request->requestId);
                return;
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

            // Check if plan is in use by active subscriptions
            $activeSubs = BusinessSubscription::all(['plan_id' => $id, 'status' => 'active']);
            if (!empty($activeSubs)) {
                Response::error('conflict', 'Cannot delete plan with active subscriptions (' . count($activeSubs) . ')', 409, $request->requestId);
                return;
            }

            Plan::delete($id);

            AuditService::logFromRequest($request, 'delete', 'plan', (string)$id, null, null, [
                'name' => $plan['name'],
            ]);

            Logger::info('Plan deleted', ['plan_id' => $id], $request->requestId);

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
            $sub = BusinessSubscription::find($id);
            if (!$sub) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            // Enrich
            $business = Business::find($sub['business_id']);
            $sub['business_name'] = $business['name'] ?? null;
            $plan = Plan::find($sub['plan_id']);
            $sub['plan_name'] = $plan['name'] ?? null;

            Response::success(['subscription' => $sub]);
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

        $errors = Validator::make($data, [
            'business_id' => 'required',
            'plan_id' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $businessId = (int)$data['business_id'];
            $planId = (int)$data['plan_id'];

            // Validate business exists
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            // Validate plan exists
            $plan = Plan::find($planId);
            if (!$plan) {
                Response::notFound('Plan not found', $request->requestId);
                return;
            }

            // Deactivate current active subscription if exists
            $currentSub = BusinessSubscription::getActiveForBusiness($businessId);
            if ($currentSub) {
                BusinessSubscription::update((int)$currentSub['id'], [
                    'status' => 'cancelled',
                    'ends_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $subData = [
                'business_id' => $businessId,
                'plan_id' => $planId,
                'status' => $data['status'] ?? 'active',
                'starts_at' => $data['starts_at'] ?? date('Y-m-d H:i:s'),
            ];

            if (isset($data['ends_at'])) {
                $subData['ends_at'] = $data['ends_at'];
            }

            $subId = BusinessSubscription::create($subData);
            $sub = BusinessSubscription::find($subId);

            AuditService::logFromRequest($request, 'create', 'subscription', (string)$subId, null, $businessId, [
                'plan_id' => $planId,
                'plan_name' => $plan['name'],
            ]);

            Logger::info('Subscription created', [
                'subscription_id' => $subId,
                'business_id' => $businessId,
            ], $request->requestId);

            Response::created([
                'subscription' => $sub,
                'message' => 'Subscription created successfully',
            ]);
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
            $sub = BusinessSubscription::find($id);
            if (!$sub) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            if (isset($data['plan_id'])) {
                $plan = Plan::find((int)$data['plan_id']);
                if (!$plan) {
                    Response::notFound('Plan not found', $request->requestId);
                    return;
                }
                $updateData['plan_id'] = (int)$data['plan_id'];
            }

            if (isset($data['status'])) {
                $validStatuses = ['active', 'cancelled', 'expired', 'past_due'];
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

            BusinessSubscription::update($id, $updateData);
            $updated = BusinessSubscription::find($id);

            AuditService::logFromRequest($request, 'update', 'subscription', (string)$id, null, (int)$sub['business_id'], $updateData);

            Response::success([
                'subscription' => $updated,
                'message' => 'Subscription updated successfully',
            ]);
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
            $sub = BusinessSubscription::find($id);
            if (!$sub) {
                Response::notFound('Subscription not found', $request->requestId);
                return;
            }

            BusinessSubscription::delete($id);

            AuditService::logFromRequest($request, 'delete', 'subscription', (string)$id, null, (int)$sub['business_id'], [
                'plan_id' => $sub['plan_id'],
            ]);

            Logger::info('Subscription deleted', ['subscription_id' => $id], $request->requestId);

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
}
