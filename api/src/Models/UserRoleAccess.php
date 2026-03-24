<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

class UserRoleAccess
{
    public static function getMetrics(int $userId): array
    {
        $businessCount = self::countOwnedBusinesses($userId);
        $ownedEstablishmentCount = self::countOwnedEstablishments($userId);
        $managerLinkCount = self::countManagementLinks($userId);
        $professionalLinkCount = self::countProfessionalLinks($userId);

        return [
            'business_count' => $businessCount,
            'owned_establishment_count' => $ownedEstablishmentCount,
            'manager_link_count' => $managerLinkCount,
            'professional_link_count' => $professionalLinkCount,
            'has_management_signals' => ($businessCount + $ownedEstablishmentCount + $managerLinkCount) > 0,
            'has_professional_signals' => $professionalLinkCount > 0,
        ];
    }

    public static function countOwnedBusinesses(int $userId): int
    {
        return (new QueryBuilder())
            ->select('businesses')
            ->where('owner_user_id', '=', $userId)
            ->count();
    }

    public static function hasOwnershipSignals(int $userId): bool
    {
        $rows = Database::getInstance()->query(
            "
            SELECT 1
            FROM users u
            LEFT JOIN businesses b
              ON b.owner_user_id = u.id
            LEFT JOIN business_users bu
              ON bu.user_id = u.id
             AND bu.role = 'owner'
            LEFT JOIN establishments e
              ON e.owner_id = u.id
            LEFT JOIN establishment_users eu
              ON eu.user_id = u.id
             AND eu.role = 'owner'
            WHERE u.id = ?
              AND (
                    b.id IS NOT NULL
                 OR bu.id IS NOT NULL
                 OR e.id IS NOT NULL
                 OR eu.id IS NOT NULL
              )
            LIMIT 1
            ",
            [$userId]
        );

        return !empty($rows);
    }

    public static function getProfessionalBusinessIds(int $userId): array
    {
        $rows = Database::getInstance()->query(
            "
            SELECT DISTINCT business_id
            FROM (
                SELECT bu.business_id
                FROM business_users bu
                WHERE bu.user_id = ?
                  AND bu.role = 'professional'

                UNION

                SELECT e.business_id
                FROM establishment_users eu
                JOIN establishments e
                  ON e.id = eu.establishment_id
                WHERE eu.user_id = ?
                  AND eu.role = 'professional'
                  AND e.business_id IS NOT NULL
            ) AS professional_contexts
            ",
            [$userId, $userId]
        );

        return array_values(array_map(
            static fn(array $row): int => (int)$row['business_id'],
            $rows
        ));
    }

    public static function isManagerInBusiness(int $userId, int $businessId): bool
    {
        $rows = Database::getInstance()->query(
            "
            SELECT 1
            FROM (
                SELECT bu.user_id
                FROM business_users bu
                WHERE bu.business_id = ?
                  AND bu.user_id = ?
                  AND bu.role IN ('owner', 'manager')

                UNION

                SELECT eu.user_id
                FROM establishment_users eu
                JOIN establishments e
                  ON e.id = eu.establishment_id
                WHERE e.business_id = ?
                  AND eu.user_id = ?
                  AND eu.role = 'manager'
            ) AS manager_contexts
            LIMIT 1
            ",
            [$businessId, $userId, $businessId, $userId]
        );

        return !empty($rows);
    }

    public static function updateContextualRole(int $userId, string $fromRole, string $toRole): void
    {
        $db = Database::getInstance();
        $db->execute(
            "UPDATE business_users SET role = ? WHERE user_id = ? AND role = ?",
            [$toRole, $userId, $fromRole]
        );
        $db->execute(
            "UPDATE establishment_users SET role = ? WHERE user_id = ? AND role = ?",
            [$toRole, $userId, $fromRole]
        );
    }

    public static function countOwnedEstablishments(int $userId): int
    {
        return (new QueryBuilder())
            ->select('establishments')
            ->where('owner_id', '=', $userId)
            ->count();
    }

    public static function countManagementLinks(int $userId): int
    {
        return self::countBusinessManagementLinks($userId)
            + self::countEstablishmentManagementLinks($userId);
    }

    public static function countProfessionalLinks(int $userId): int
    {
        return self::countBusinessProfessionalLinks($userId)
            + self::countEstablishmentProfessionalLinks($userId)
            + self::countProfessionalEstablishmentLinks($userId)
            + self::countProfessionalRecords($userId);
    }

    private static function countBusinessManagementLinks(int $userId): int
    {
        return (new QueryBuilder())
            ->select('business_users')
            ->where('user_id', '=', $userId)
            ->whereIn('role', [BusinessUser::ROLE_OWNER, BusinessUser::ROLE_MANAGER])
            ->count();
    }

    private static function countEstablishmentManagementLinks(int $userId): int
    {
        return (new QueryBuilder())
            ->select('establishment_users')
            ->where('user_id', '=', $userId)
            ->whereIn('role', [EstablishmentUser::ROLE_OWNER, EstablishmentUser::ROLE_MANAGER])
            ->count();
    }

    private static function countBusinessProfessionalLinks(int $userId): int
    {
        return (new QueryBuilder())
            ->select('business_users')
            ->where('user_id', '=', $userId)
            ->where('role', '=', BusinessUser::ROLE_PROFESSIONAL)
            ->count();
    }

    private static function countEstablishmentProfessionalLinks(int $userId): int
    {
        return (new QueryBuilder())
            ->select('establishment_users')
            ->where('user_id', '=', $userId)
            ->where('role', '=', EstablishmentUser::ROLE_PROFESSIONAL)
            ->count();
    }

    private static function countProfessionalEstablishmentLinks(int $userId): int
    {
        return (new QueryBuilder())
            ->select('professional_establishments')
            ->where('user_id', '=', $userId)
            ->where('is_active', '=', 1)
            ->count();
    }

    private static function countProfessionalRecords(int $userId): int
    {
        return (new QueryBuilder())
            ->select('professionals')
            ->where('user_id', '=', $userId)
            ->where('is_active', '=', 1)
            ->count();
    }
}
