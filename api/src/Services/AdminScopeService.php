<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\Business;
use QueueMaster\Models\Establishment;

class AdminScopeService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function isAdmin(array $user): bool
    {
        return ($user['role'] ?? null) === 'admin';
    }

    public function getManageableBusinessIds(array $user): array
    {
        if ($this->isAdmin($user)) {
            return array_map(
                static fn(array $row): int => (int)$row['id'],
                Business::all([], 'name', 'ASC')
            );
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->query(
            "
            SELECT DISTINCT b.id
            FROM businesses b
            LEFT JOIN business_users bu
              ON bu.business_id = b.id
             AND bu.user_id = ?
             AND bu.role IN ('owner', 'manager')
            WHERE b.owner_user_id = ?
               OR bu.id IS NOT NULL
            ORDER BY b.id ASC
            ",
            [$userId, $userId]
        );

        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $rows
        );
    }

    public function getManageableEstablishmentIds(array $user): array
    {
        if ($this->isAdmin($user)) {
            return array_map(
                static fn(array $row): int => (int)$row['id'],
                Establishment::all([], 'name', 'ASC')
            );
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $businessIds = $this->getManageableBusinessIds($user);
        $businessPlaceholders = empty($businessIds)
            ? 'NULL'
            : implode(',', array_fill(0, count($businessIds), '?'));

        $params = [$userId, $userId];
        foreach ($businessIds as $businessId) {
            $params[] = $businessId;
        }

        $rows = $this->db->query(
            "
            SELECT DISTINCT e.id
            FROM establishments e
            LEFT JOIN establishment_users eu
              ON eu.establishment_id = e.id
             AND eu.user_id = ?
             AND eu.role IN ('owner', 'manager')
            WHERE e.owner_id = ?
               OR eu.id IS NOT NULL
               OR e.business_id IN ($businessPlaceholders)
            ORDER BY e.id ASC
            ",
            $params
        );

        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $rows
        );
    }

    public function canViewAdminUser(array $actor, int $targetUserId): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId === $targetUserId) {
            return true;
        }

        $visibleUserIds = array_map(
            static fn(array $user): int => (int)$user['id'],
            $this->getVisibleUsers($actor)
        );

        return in_array($targetUserId, $visibleUserIds, true);
    }

    public function getVisibleUsers(array $actor): array
    {
        if ($this->isAdmin($actor)) {
            return $this->db->query(
                "
                SELECT
                    u.id,
                    u.name,
                    u.email,
                    u.google_id,
                    u.phone,
                    u.address_line_1,
                    u.address_line_2,
                    u.role,
                    u.is_active,
                    u.email_verified,
                    u.avatar_url,
                    u.created_at,
                    u.last_login_at
                FROM users u
                ORDER BY u.name ASC
                "
            );
        }

        $businessIds = $this->getManageableBusinessIds($actor);
        $establishmentIds = $this->getManageableEstablishmentIds($actor);

        if (empty($businessIds) && empty($establishmentIds)) {
            return [];
        }

        $businessClause = empty($businessIds)
            ? 'NULL'
            : implode(',', array_fill(0, count($businessIds), '?'));
        $establishmentClause = empty($establishmentIds)
            ? 'NULL'
            : implode(',', array_fill(0, count($establishmentIds), '?'));

        $params = [];
        foreach ($businessIds as $businessId) {
            $params[] = $businessId;
        }
        foreach ($establishmentIds as $establishmentId) {
            $params[] = $establishmentId;
        }
        foreach ($establishmentIds as $establishmentId) {
            $params[] = $establishmentId;
        }
        foreach ($establishmentIds as $establishmentId) {
            $params[] = $establishmentId;
        }

        return $this->db->query(
            "
            SELECT DISTINCT
                u.id,
                u.name,
                u.email,
                u.google_id,
                u.phone,
                u.address_line_1,
                u.address_line_2,
                u.role,
                u.is_active,
                u.email_verified,
                u.avatar_url,
                u.created_at,
                u.last_login_at
            FROM users u
            LEFT JOIN business_users bu
              ON bu.user_id = u.id
             AND bu.business_id IN ($businessClause)
             AND bu.role IN ('owner', 'manager', 'professional')
            LEFT JOIN establishment_users eu
              ON eu.user_id = u.id
             AND eu.establishment_id IN ($establishmentClause)
             AND eu.role IN ('owner', 'manager', 'professional')
            LEFT JOIN professional_establishments pe
              ON pe.user_id = u.id
             AND pe.establishment_id IN ($establishmentClause)
             AND pe.is_active = 1
            LEFT JOIN professionals p
              ON p.user_id = u.id
             AND p.establishment_id IN ($establishmentClause)
             AND p.is_active = 1
            WHERE (
                    bu.id IS NOT NULL
                 OR eu.id IS NOT NULL
                 OR pe.id IS NOT NULL
                 OR p.id IS NOT NULL
                  )
              AND u.role IN ('manager', 'professional')
            ORDER BY u.name ASC
            ",
            $params
        );
    }

    public function getVisibleMembershipsForUser(array $actor, int $userId): array
    {
        $businessIds = $this->getManageableBusinessIds($actor);
        $establishmentIds = $this->getManageableEstablishmentIds($actor);

        $memberships = [
            'businesses' => [],
            'establishments' => [],
        ];

        if ($this->isAdmin($actor) || !empty($businessIds)) {
            $businessClause = $this->isAdmin($actor)
                ? ''
                : 'AND bu.business_id IN (' . implode(',', array_fill(0, count($businessIds), '?')) . ')';
            $params = [$userId];
            if (!$this->isAdmin($actor)) {
                foreach ($businessIds as $businessId) {
                    $params[] = $businessId;
                }
            }

            $memberships['businesses'] = $this->db->query(
                "
                SELECT
                    bu.business_id,
                    bu.role,
                    b.name AS business_name,
                    b.owner_user_id
                FROM business_users bu
                JOIN businesses b
                  ON b.id = bu.business_id
                WHERE bu.user_id = ?
                $businessClause
                ORDER BY b.name ASC
                ",
                $params
            );
        }

        if ($this->isAdmin($actor) || !empty($establishmentIds)) {
            $establishmentClause = $this->isAdmin($actor)
                ? ''
                : 'AND eu.establishment_id IN (' . implode(',', array_fill(0, count($establishmentIds), '?')) . ')';
            $params = [$userId];
            if (!$this->isAdmin($actor)) {
                foreach ($establishmentIds as $establishmentId) {
                    $params[] = $establishmentId;
                }
            }

            $memberships['establishments'] = $this->db->query(
                "
                SELECT
                    eu.establishment_id,
                    eu.role,
                    e.name AS establishment_name,
                    e.business_id,
                    b.name AS business_name
                FROM establishment_users eu
                JOIN establishments e
                  ON e.id = eu.establishment_id
                LEFT JOIN businesses b
                  ON b.id = e.business_id
                WHERE eu.user_id = ?
                $establishmentClause
                ORDER BY b.name ASC, e.name ASC
                ",
                $params
            );
        }

        return $memberships;
    }
}
