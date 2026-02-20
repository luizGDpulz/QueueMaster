<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * BusinessInvitation Model
 * 
 * Handles invitations between businesses and professionals.
 * 
 * Two directions:
 *   - business_to_professional: Manager invites a professional to join
 *   - professional_to_business: Professional requests to join a business
 * 
 * Table columns:
 * - id: bigint [PRI]
 * - business_id: bigint NOT NULL
 * - from_user_id: bigint NOT NULL (who initiated)
 * - to_user_id: bigint NOT NULL (who receives)
 * - direction: enum('business_to_professional','professional_to_business')
 * - status: enum('pending','accepted','rejected','cancelled')
 * - message: text NULL
 * - responded_at: timestamp NULL
 * - created_at: timestamp
 * - updated_at: timestamp NULL
 */
class BusinessInvitation
{
    protected static string $table = 'business_invitations';
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    /**
     * Create a new invitation
     */
    public static function create(array $data): int
    {
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        // Check for existing pending invitation
        $existing = self::findPending(
            (int)$data['business_id'],
            (int)$data['from_user_id'],
            (int)$data['to_user_id']
        );
        if ($existing) {
            throw new \InvalidArgumentException('A pending invitation already exists');
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Find existing pending invitation between users for a business
     */
    public static function findPending(int $businessId, int $fromUserId, int $toUserId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . self::$table . "
                WHERE business_id = ? AND status = 'pending'
                AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
                LIMIT 1";
        $results = $db->query($sql, [$businessId, $fromUserId, $toUserId, $toUserId, $fromUserId]);
        return $results[0] ?? null;
    }

    /**
     * Accept an invitation
     */
    public static function accept(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update([
                'status' => 'accepted',
                'responded_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Reject an invitation
     */
    public static function reject(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update([
                'status' => 'rejected',
                'responded_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Cancel an invitation (by the sender)
     */
    public static function cancel(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update([
                'status' => 'cancelled',
                'responded_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get invitations received by a user (pending)
     */
    public static function getReceivedByUser(int $userId, string $status = 'pending'): array
    {
        $db = Database::getInstance();
        $sql = "SELECT bi.*, b.name as business_name, 
                       fu.name as from_user_name, fu.email as from_user_email
                FROM " . self::$table . " bi
                JOIN businesses b ON b.id = bi.business_id
                JOIN users fu ON fu.id = bi.from_user_id
                WHERE bi.to_user_id = ? AND bi.status = ?
                ORDER BY bi.created_at DESC";
        return $db->query($sql, [$userId, $status]);
    }

    /**
     * Get invitations sent by a user (any status)
     */
    public static function getSentByUser(int $userId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT bi.*, b.name as business_name,
                       tu.name as to_user_name, tu.email as to_user_email
                FROM " . self::$table . " bi
                JOIN businesses b ON b.id = bi.business_id
                JOIN users tu ON tu.id = bi.to_user_id
                WHERE bi.from_user_id = ?
                ORDER BY bi.created_at DESC";
        return $db->query($sql, [$userId]);
    }

    /**
     * Get invitations for a business (managers view)
     */
    public static function getByBusiness(int $businessId, ?string $status = null): array
    {
        $db = Database::getInstance();
        $sql = "SELECT bi.*, 
                       fu.name as from_user_name, fu.email as from_user_email,
                       tu.name as to_user_name, tu.email as to_user_email
                FROM " . self::$table . " bi
                JOIN users fu ON fu.id = bi.from_user_id
                JOIN users tu ON tu.id = bi.to_user_id
                WHERE bi.business_id = ?";
        $params = [$businessId];

        if ($status) {
            $sql .= " AND bi.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY bi.created_at DESC";
        return $db->query($sql, $params);
    }

    /**
     * Validate invitation data
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['business_id'])) {
            $errors['business_id'] = 'Business ID is required';
        }
        if (empty($data['from_user_id'])) {
            $errors['from_user_id'] = 'Sender user ID is required';
        }
        if (empty($data['to_user_id'])) {
            $errors['to_user_id'] = 'Recipient user ID is required';
        }
        if (empty($data['direction'])) {
            $errors['direction'] = 'Direction is required';
        } elseif (!in_array($data['direction'], ['business_to_professional', 'professional_to_business'])) {
            $errors['direction'] = 'Invalid direction';
        }

        // Can't invite yourself
        if (!empty($data['from_user_id']) && !empty($data['to_user_id']) 
            && $data['from_user_id'] == $data['to_user_id']) {
            $errors['to_user_id'] = 'Cannot invite yourself';
        }

        return $errors;
    }
}
