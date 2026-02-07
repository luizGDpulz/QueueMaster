<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

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
        $qb = new QueryBuilder();
        $links = $qb->select(self::$table)
            ->where('business_id', '=', $businessId)
            ->get();

        $users = [];
        foreach ($links as $link) {
            $user = User::find($link['user_id']);
            if ($user) {
                $user['business_role'] = $link['role'];
                unset($user['google_id']);
                $users[] = $user;
            }
        }

        return $users;
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

    /**
     * Validate role value
     */
    private static function validateRole(string $role): void
    {
        $validRoles = [self::ROLE_OWNER, self::ROLE_MANAGER];
        if (!in_array($role, $validRoles)) {
            throw new \InvalidArgumentException('Invalid role. Must be: ' . implode(', ', $validRoles));
        }
    }
}
