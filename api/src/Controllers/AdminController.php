<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\AuditLog;
use QueueMaster\Models\BusinessSubscription;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Business;
use QueueMaster\Utils\Logger;

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
                } else {
                    $filters['business_ids'] = $managerBusinessIds;
                }
            } else {
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
        } catch (\Exception $e) {
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
            } else {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Logger::error('Failed to list plans', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve plans', $request->requestId);
        }
    }
}
