<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * EstablishmentUser Model - Pivot table for establishments <-> users (staff)
 * 
 * Manages the relationship between establishments and their staff members.
 * Each user can have a role within the establishment (owner, manager, professional).
 */
class EstablishmentUser
{
    protected static string $table = 'establishment_users';
    protected static string $primaryKey = 'id';

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_PROFESSIONAL = 'professional';

    /**
     * Add a user to an establishment's staff
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @param string $role Role (owner, manager, professional)
     * @return int Inserted record ID
     */
    public static function addStaff(int $establishmentId, int $userId, string $role = self::ROLE_PROFESSIONAL): int
    {
        self::validateRole($role);

        // Check if already linked
        if (self::exists($establishmentId, $userId)) {
            throw new \InvalidArgumentException('User is already part of this establishment');
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert([
            'establishment_id' => $establishmentId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    /**
     * Remove a user from an establishment's staff
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @return int Affected rows
     */
    public static function removeStaff(int $establishmentId, int $userId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->where('user_id', '=', $userId)
            ->delete();
    }

    /**
     * Update a staff member's role
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @param string $role New role
     * @return int Affected rows
     */
    public static function updateRole(int $establishmentId, int $userId, string $role): int
    {
        self::validateRole($role);

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->where('user_id', '=', $userId)
            ->update(['role' => $role]);
    }

    /**
     * Check if a user is part of an establishment's staff
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @return bool
     */
    public static function exists(int $establishmentId, int $userId): bool
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->where('user_id', '=', $userId)
            ->first();
        
        return $result !== null;
    }

    /**
     * Get user's role in an establishment
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @return string|null Role or null if not found
     */
    public static function getRole(int $establishmentId, int $userId): ?string
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->where('user_id', '=', $userId)
            ->first();
        
        return $result['role'] ?? null;
    }

    /**
     * Check if user has specific role or higher in establishment
     * 
     * @param int $establishmentId Establishment ID
     * @param int $userId User ID
     * @param string $minimumRole Minimum required role
     * @return bool
     */
    public static function hasMinimumRole(int $establishmentId, int $userId, string $minimumRole): bool
    {
        $roleHierarchy = [
            self::ROLE_PROFESSIONAL => 1,
            self::ROLE_MANAGER => 2,
            self::ROLE_OWNER => 3,
        ];

        $userRole = self::getRole($establishmentId, $userId);
        
        if (!$userRole) {
            return false;
        }

        return ($roleHierarchy[$userRole] ?? 0) >= ($roleHierarchy[$minimumRole] ?? 0);
    }

    /**
     * Get all staff members for an establishment
     * 
     * @param int $establishmentId Establishment ID
     * @return array Array of users with their roles
     */
    public static function getStaff(int $establishmentId): array
    {
        $qb = new QueryBuilder();
        $links = $qb->select(self::$table)
            ->where('establishment_id', '=', $establishmentId)
            ->get();

        $staff = [];
        foreach ($links as $link) {
            $user = User::find($link['user_id']);
            if ($user) {
                $user['establishment_role'] = $link['role'];
                unset($user['google_id']); // Don't expose
                $staff[] = $user;
            }
        }

        return $staff;
    }

    /**
     * Get all establishments a user is staff of
     * 
     * @param int $userId User ID
     * @return array Array of establishments with user's role
     */
    public static function getEstablishmentsForUser(int $userId): array
    {
        $qb = new QueryBuilder();
        $links = $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->get();

        $establishments = [];
        foreach ($links as $link) {
            $establishment = Establishment::find($link['establishment_id']);
            if ($establishment) {
                $establishment['user_role'] = $link['role'];
                $establishments[] = $establishment;
            }
        }

        return $establishments;
    }

    /**
     * Validate role value
     * 
     * @param string $role Role to validate
     * @throws \InvalidArgumentException if invalid
     */
    private static function validateRole(string $role): void
    {
        $validRoles = [self::ROLE_OWNER, self::ROLE_MANAGER, self::ROLE_PROFESSIONAL];
        if (!in_array($role, $validRoles)) {
            throw new \InvalidArgumentException('Invalid role. Must be: ' . implode(', ', $validRoles));
        }
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - establishment_id: bigint NOT NULL [FK -> establishments]
     * - user_id: bigint NOT NULL [FK -> users]
     * - role: enum('owner','manager','professional') NOT NULL DEFAULT 'professional'
     * - created_at: timestamp NOT NULL
     * - UNIQUE(establishment_id, user_id)
     */
}
