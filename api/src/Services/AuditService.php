<?php

namespace QueueMaster\Services;

use QueueMaster\Models\AuditLog;
use QueueMaster\Core\Request;

/**
 * AuditService - Centralized audit logging
 * 
 * Provides convenient methods to log critical actions
 * with automatic IP extraction from request context.
 */
class AuditService
{
    /**
     * Log an action
     */
    public static function log(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        ?int $establishmentId = null,
        ?int $businessId = null,
        ?array $payload = null,
        ?string $ip = null
    ): void {
        try {
            AuditLog::log($userId, $action, $entity, $entityId, $establishmentId, $businessId, $payload, $ip);
        } catch (\Exception $e) {
            // Audit logging should never break the main flow
            error_log('AuditService error: ' . $e->getMessage());
        }
    }

    /**
     * Log from a request context (auto-extracts user ID and IP)
     */
    public static function logFromRequest(
        Request $request,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        ?int $establishmentId = null,
        ?int $businessId = null,
        ?array $payload = null
    ): void {
        $userId = $request->user ? (int)$request->user['id'] : null;
        $ip = $request->getIp();

        self::log($userId, $action, $entity, $entityId, $establishmentId, $businessId, $payload, $ip);
    }
}
