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
    public const DIRECTION_BUSINESS_TO_PROFESSIONAL = 'business_to_professional';
    public const DIRECTION_PROFESSIONAL_TO_BUSINESS = 'professional_to_business';

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
            (int)$data['to_user_id'],
            isset($data['establishment_id']) ? (int)$data['establishment_id'] : null
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
    public static function findPending(int $businessId, int $fromUserId, int $toUserId, ?int $establishmentId = null): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . self::$table . "
                WHERE business_id = ? AND status = 'pending'
                  AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))";
        $params = [$businessId, $fromUserId, $toUserId, $toUserId, $fromUserId];

        if ($establishmentId === null) {
            $sql .= " AND establishment_id IS NULL";
        } else {
            $sql .= " AND establishment_id = ?";
            $params[] = $establishmentId;
        }

        $sql .= " LIMIT 1";

        $results = $db->query($sql, $params);
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
        $qb = new QueryBuilder();
        return $qb->select(self::$table . ' bi', [
            'bi.id',
            'bi.business_id',
            'bi.establishment_id',
            'bi.from_user_id',
            'bi.to_user_id',
            'bi.direction',
            'bi.role',
            'bi.status',
            'bi.message',
            'bi.responded_at',
            'bi.created_at',
            'bi.updated_at',
            'b.name AS business_name',
            'e.name AS establishment_name',
            'fu.name AS from_user_name',
            'fu.email AS from_user_email',
            'tu.name AS to_user_name',
            'tu.email AS to_user_email',
        ])
            ->join('businesses b', 'b.id', '=', 'bi.business_id')
            ->leftJoin('establishments e', 'e.id', '=', 'bi.establishment_id')
            ->join('users fu', 'fu.id', '=', 'bi.from_user_id')
            ->join('users tu', 'tu.id', '=', 'bi.to_user_id')
            ->where('bi.to_user_id', '=', $userId)
            ->where('bi.status', '=', $status)
            ->orderBy('bi.created_at', 'DESC')
            ->get();
    }

    /**
     * Get invitations sent by a user (any status)
     */
    public static function getSentByUser(int $userId): array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table . ' bi', [
            'bi.id',
            'bi.business_id',
            'bi.establishment_id',
            'bi.from_user_id',
            'bi.to_user_id',
            'bi.direction',
            'bi.role',
            'bi.status',
            'bi.message',
            'bi.responded_at',
            'bi.created_at',
            'bi.updated_at',
            'b.name AS business_name',
            'e.name AS establishment_name',
            'tu.name AS to_user_name',
            'tu.email AS to_user_email',
            'fu.name AS from_user_name',
            'fu.email AS from_user_email',
        ])
            ->join('businesses b', 'b.id', '=', 'bi.business_id')
            ->leftJoin('establishments e', 'e.id', '=', 'bi.establishment_id')
            ->join('users tu', 'tu.id', '=', 'bi.to_user_id')
            ->join('users fu', 'fu.id', '=', 'bi.from_user_id')
            ->where('bi.from_user_id', '=', $userId)
            ->orderBy('bi.created_at', 'DESC')
            ->get();
    }

    /**
     * Get invitations for a business (managers view)
     */
    public static function getByBusiness(int $businessId, ?string $status = null): array
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table . ' bi', [
            'bi.id',
            'bi.business_id',
            'bi.establishment_id',
            'bi.from_user_id',
            'bi.to_user_id',
            'bi.direction',
            'bi.role',
            'bi.status',
            'bi.message',
            'bi.responded_at',
            'bi.created_at',
            'bi.updated_at',
            'fu.name AS from_user_name',
            'fu.email AS from_user_email',
            'tu.name AS to_user_name',
            'tu.email AS to_user_email',
            'e.name AS establishment_name',
        ])
            ->join('users fu', 'fu.id', '=', 'bi.from_user_id')
            ->join('users tu', 'tu.id', '=', 'bi.to_user_id')
            ->leftJoin('establishments e', 'e.id', '=', 'bi.establishment_id')
            ->where('bi.business_id', '=', $businessId);

        if ($status !== null && $status !== '') {
            $qb->where('bi.status', '=', $status);
        }

        return $qb->orderBy('bi.created_at', 'DESC')->get();
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
        } elseif (!in_array($data['direction'], [self::DIRECTION_BUSINESS_TO_PROFESSIONAL, self::DIRECTION_PROFESSIONAL_TO_BUSINESS], true)) {
            $errors['direction'] = 'Invalid direction';
        }

        if (isset($data['establishment_id']) && !is_null($data['establishment_id']) && (!is_numeric($data['establishment_id']) || (int)$data['establishment_id'] <= 0)) {
            $errors['establishment_id'] = 'Establishment ID must be a positive integer';
        }

        if (isset($data['role']) && !in_array($data['role'], ['professional', 'manager'], true)) {
            $errors['role'] = 'Invalid invitation role';
        }

        // Can't invite yourself
        if (!empty($data['from_user_id']) && !empty($data['to_user_id']) 
            && $data['from_user_id'] == $data['to_user_id']) {
            $errors['to_user_id'] = 'Cannot invite yourself';
        }

        return $errors;
    }
}
