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

        // Map legacy 'attendant' to 'professional' for backward compatibility
        if ($userRole === 'attendant') {
            $userRole = 'professional';
        }

        // Expand allowed roles with backward-compatible equivalents
        $expandedAllowed = self::expandCompatibleRoles($this->allowedRoles);

        // Check if user has required role
        if (!in_array($userRole, $expandedAllowed)) {
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
     * Expand role list with backward-compatible equivalents.
     * 'attendant' and 'professional' are treated as interchangeable.
     */
    private static function expandCompatibleRoles(array $roles): array
    {
        $expanded = $roles;
        if (in_array('attendant', $expanded) && !in_array('professional', $expanded)) {
            $expanded[] = 'professional';
        }
        if (in_array('professional', $expanded) && !in_array('attendant', $expanded)) {
            $expanded[] = 'attendant';
        }
        return $expanded;
    }

    /**
     * Static factory method for convenience
     */
    public static function require(array|string $roles): self
    {
        return new self($roles);
    }
}
