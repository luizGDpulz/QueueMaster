<?php

namespace QueueMaster\Middleware;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Utils\Logger;

/**
 * BusinessMiddleware - Business-Scope Authorization
 * 
 * Validates that the authenticated user has manager/owner access
 * to the business specified in the route parameter.
 * Admin users bypass this check.
 */
class BusinessMiddleware
{
    /**
     * Handle business authorization
     */
    public function __invoke(Request $request, callable $next): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'] ?? null;

        // Admin bypass
        if ($userRole === 'admin') {
            return $next($request);
        }

        // Get business ID from route params or body
        $businessId = $request->getParam('businessId')
            ?? $request->getParam('business_id')
            ?? ($request->all()['business_id'] ?? null);

        if (!$businessId) {
            Response::forbidden('Business context required', $request->requestId);
            return;
        }

        $businessId = (int)$businessId;

        // Check if user belongs to this business
        $bu = BusinessUser::findByBusinessAndUser($businessId, (int)$request->user['id']);

        if (!$bu) {
            Logger::logSecurity('Business access denied', [
                'user_id' => $request->user['id'],
                'business_id' => $businessId,
                'path' => $request->getPath(),
            ], $request->requestId);

            Response::forbidden('You do not have access to this business', $request->requestId);
            return;
        }

        // Attach business user role to request for downstream use
        $request->businessUserRole = $bu['role'];

        $next($request);
    }
}
