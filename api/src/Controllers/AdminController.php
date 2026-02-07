<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\AuditLog;
use QueueMaster\Models\BusinessSubscription;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Business;
use QueueMaster\Utils\Logger;

/**
 * AdminController - Admin-only endpoints
 * 
 * Provides audit logs viewing and subscription management.
 */
class AdminController
{
    /**
     * GET /api/v1/admin/audit-logs
     * List audit logs with optional filters
     */
    public function auditLogs(Request $request): void
    {
        try {
            $params = $request->getQuery();
            $conditions = [];

            if (isset($params['business_id'])) {
                $conditions['business_id'] = (int)$params['business_id'];
            }
            if (isset($params['establishment_id'])) {
                $conditions['establishment_id'] = (int)$params['establishment_id'];
            }
            if (isset($params['entity'])) {
                $conditions['entity'] = $params['entity'];
            }
            if (isset($params['user_id'])) {
                $conditions['user_id'] = (int)$params['user_id'];
            }

            $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

            $logs = AuditLog::all($conditions, 'created_at', 'DESC', $limit);

            Response::success([
                'audit_logs' => $logs,
                'total' => count($logs),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list audit logs', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve audit logs', $request->requestId);
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
