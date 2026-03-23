<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * BusinessUser Model - Pivot table for businesses <-> users
 * 
 * Manages the relationship between businesses and their owners/managers.
 */
class BusinessUser
{
    protected static string $table = 'business_users';
    protected static string $primaryKey = 'id';

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_PROFESSIONAL = 'professional';

    /**
     * Add a user to a business
     */
    public static function addUser(int $businessId, int $userId, string $role = self::ROLE_MANAGER): int
    {
        self::validateRole($role);

        if (self::exists($businessId, $userId)) {
            throw new \InvalidArgumentException('User is already part of this business');
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert([
            'business_id' => $businessId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    /**
     * Remove a user from a business
     */
    public static function removeUser(int $businessId, int $userId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('user_id', '=', $userId)
            ->delete();
    }

    /**
     * Check if a user belongs to a business
     */
    public static function exists(int $businessId, int $userId): bool
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('user_id', '=', $userId)
            ->first();

        return $result !== null;
    }

    /**
     * Get user's role in a business
     */
    public static function getRole(int $businessId, int $userId): ?string
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('user_id', '=', $userId)
            ->first();

        return $result['role'] ?? null;
    }

    /**
     * Find a specific business_user record
     */
    public static function findByBusinessAndUser(int $businessId, int $userId): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    /**
     * Get all users for a business
     */
    public static function getUsers(int $businessId): array
    {
        $db = Database::getInstance();

        return $db->query(
            "
            SELECT
                u.id,
                u.name,
                u.email,
                u.avatar_url,
                u.avatar_base64,
                u.email_verified,
                u.phone,
                u.role,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                bu.role AS business_role,
                GROUP_CONCAT(
                    DISTINCT CASE
                        WHEN eu.id IS NOT NULL OR pe.id IS NOT NULL OR p.id IS NOT NULL THEN e.name
                        ELSE NULL
                    END
                    ORDER BY e.name SEPARATOR ', '
                ) AS professional_establishments_label
            FROM business_users bu
            JOIN users u
              ON u.id = bu.user_id
            LEFT JOIN establishments e
              ON e.business_id = bu.business_id
            LEFT JOIN establishment_users eu
              ON eu.establishment_id = e.id
             AND eu.user_id = u.id
            LEFT JOIN professional_establishments pe
              ON pe.establishment_id = e.id
             AND pe.user_id = u.id
             AND pe.is_active = 1
            LEFT JOIN professionals p
              ON p.establishment_id = e.id
             AND p.user_id = u.id
             AND p.is_active = 1
            WHERE bu.business_id = ?
            GROUP BY
                u.id,
                u.name,
                u.email,
                u.avatar_url,
                u.avatar_base64,
                u.email_verified,
                u.phone,
                u.role,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                bu.role
            ORDER BY u.name ASC
            ",
            [$businessId]
        );
    }

    /**
     * Get all businesses for a user (returns raw link records)
     */
    public static function getBusinessesForUser(int $userId): array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->get();
    }

    /**
     * Count managers in a business
     */
    public static function countManagers(int $businessId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->where('role', '=', self::ROLE_MANAGER)
            ->count();
    }

    public static function getManagerUserIds(int $businessId): array
    {
        $qb = new QueryBuilder();
        $rows = $qb->select(self::$table, ['user_id'])
            ->where('business_id', '=', $businessId)
            ->whereIn('role', [self::ROLE_OWNER, self::ROLE_MANAGER])
            ->get();

        return array_map(
            static fn(array $row): int => (int)$row['user_id'],
            $rows
        );
    }

    /**
     * Validate role value
     */
    private static function validateRole(string $role): void
    {
        $validRoles = [self::ROLE_OWNER, self::ROLE_MANAGER, self::ROLE_PROFESSIONAL];
        if (!in_array($role, $validRoles)) {
            throw new \InvalidArgumentException('Invalid role. Must be: ' . implode(', ', $validRoles));
        }
    }
}
