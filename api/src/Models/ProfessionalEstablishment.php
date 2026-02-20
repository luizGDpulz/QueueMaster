<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * ProfessionalEstablishment Model - Pivot table for N:N relationship
 * 
 * Links professional users to establishments.
 * A professional can work at multiple establishments across multiple businesses.
 * An establishment can have multiple professionals.
 */
class ProfessionalEstablishment
{
    protected static string $table = 'professional_establishments';
    protected static string $primaryKey = 'id';

    /**
     * Link a professional user to an establishment
     * 
     * @param int $userId Professional user ID
     * @param int $establishmentId Establishment ID
     * @return int Inserted record ID
     * @throws \InvalidArgumentException if link already exists
     */
    public static function link(int $userId, int $establishmentId): int
    {
        if (self::exists($userId, $establishmentId)) {
            throw new \InvalidArgumentException('Professional is already linked to this establishment');
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert([
            'user_id' => $userId,
            'establishment_id' => $establishmentId,
            'is_active' => true,
        ]);
    }

    /**
     * Unlink a professional from an establishment
     * 
     * @param int $userId Professional user ID
     * @param int $establishmentId Establishment ID
     * @return int Affected rows
     */
    public static function unlink(int $userId, int $establishmentId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('establishment_id', '=', $establishmentId)
            ->delete();
    }

    /**
     * Check if link exists
     */
    public static function exists(int $userId, int $establishmentId): bool
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('establishment_id', '=', $establishmentId)
            ->first();
        return $result !== null;
    }

    /**
     * Get all establishments for a professional user
     * 
     * @param int $userId Professional user ID
     * @return array Establishments with link info
     */
    public static function getEstablishmentsForUser(int $userId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT e.*, pe.is_active as link_active, pe.created_at as linked_at,
                       b.id as business_id, b.name as business_name
                FROM " . self::$table . " pe
                JOIN establishments e ON e.id = pe.establishment_id
                LEFT JOIN businesses b ON b.id = e.business_id
                WHERE pe.user_id = ?
                ORDER BY b.name, e.name";
        return $db->query($sql, [$userId]);
    }

    /**
     * Get all professional users for an establishment
     * 
     * @param int $establishmentId Establishment ID
     * @return array Users with link info
     */
    public static function getProfessionalsForEstablishment(int $establishmentId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT u.id, u.name, u.email, u.avatar_url, u.role, u.phone,
                       pe.is_active as link_active, pe.created_at as linked_at
                FROM " . self::$table . " pe
                JOIN users u ON u.id = pe.user_id
                WHERE pe.establishment_id = ? AND u.is_active = 1
                ORDER BY u.name";
        return $db->query($sql, [$establishmentId]);
    }

    /**
     * Get all professional users for a business (across all its establishments)
     * 
     * @param int $businessId Business ID
     * @return array Unique users with establishment info
     */
    public static function getProfessionalsForBusiness(int $businessId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.avatar_url, u.role, u.phone,
                       GROUP_CONCAT(e.name SEPARATOR ', ') as establishment_names
                FROM " . self::$table . " pe
                JOIN users u ON u.id = pe.user_id
                JOIN establishments e ON e.id = pe.establishment_id
                WHERE e.business_id = ? AND u.is_active = 1
                GROUP BY u.id, u.name, u.email, u.avatar_url, u.role, u.phone
                ORDER BY u.name";
        return $db->query($sql, [$businessId]);
    }

    /**
     * Toggle active status
     */
    public static function setActive(int $userId, int $establishmentId, bool $active): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('establishment_id', '=', $establishmentId)
            ->update(['is_active' => $active]);
    }
}
