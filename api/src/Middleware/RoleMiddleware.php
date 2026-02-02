<?php

namespace QueueMaster\Middleware;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Utils\Logger;

/**
 * RoleMiddleware - Role-Based Access Control
 * 
 * Checks if authenticated user has required role(s).
 * Must be used after AuthMiddleware.
 */
class RoleMiddleware
{
    private array $allowedRoles;

    /**
     * @param array|string $allowedRoles Role(s) allowed to access route
     */
    public function __construct(array|string $allowedRoles)
    {
        $this->allowedRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    }

    /**
     * Handle role authorization
     */
    public function __invoke(Request $request, callable $next): void
    {
        // Ensure user is authenticated (should be set by AuthMiddleware)
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'] ?? null;

        // Check if user has required role
        if (!in_array($userRole, $this->allowedRoles)) {
            Logger::logSecurity('Insufficient permissions', [
                'user_id' => $request->user['id'],
                'user_role' => $userRole,
                'required_roles' => $this->allowedRoles,
                'path' => $request->getPath(),
            ], $request->requestId);
            
            Response::forbidden('Insufficient permissions', $request->requestId);
            return;
        }

        // Continue to next middleware/handler
        $next($request);
    }

    /**
     * Static factory method for convenience
     */
    public static function require(array|string $roles): self
    {
        return new self($roles);
    }
}
