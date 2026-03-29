<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

class AppointmentRequest
{
    protected static string $table = 'appointment_requests';
    protected static string $primaryKey = 'id';

    public const DIRECTION_CLIENT_TO_ESTABLISHMENT = 'client_to_establishment';
    public const DIRECTION_STAFF_TO_CLIENT = 'staff_to_client';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    public static function create(array $data): int
    {
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    public static function update(int $id, array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    public static function validate(array $data): array
    {
        $errors = [];

        foreach (['establishment_id', 'service_id', 'client_user_id', 'requested_by_user_id'] as $field) {
            if (empty($data[$field]) || !is_numeric($data[$field]) || (int)$data[$field] <= 0) {
                $errors[$field] = 'Invalid ' . $field;
            }
        }

        if (isset($data['professional_id']) && !is_null($data['professional_id'])) {
            if (!is_numeric($data['professional_id']) || (int)$data['professional_id'] <= 0) {
                $errors['professional_id'] = 'Invalid professional_id';
            }
        }

        if (empty($data['direction']) || !in_array($data['direction'], [
            self::DIRECTION_CLIENT_TO_ESTABLISHMENT,
            self::DIRECTION_STAFF_TO_CLIENT,
        ], true)) {
            $errors['direction'] = 'Invalid direction';
        }

        if (empty($data['requester_role']) || !in_array($data['requester_role'], ['client', 'professional', 'manager', 'admin'], true)) {
            $errors['requester_role'] = 'Invalid requester_role';
        }

        if (isset($data['status']) && !in_array($data['status'], [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ], true)) {
            $errors['status'] = 'Invalid status';
        }

        if (empty($data['proposed_start_at'])) {
            $errors['proposed_start_at'] = 'proposed_start_at is required';
        }

        if (empty($data['proposed_end_at'])) {
            $errors['proposed_end_at'] = 'proposed_end_at is required';
        }

        return $errors;
    }
}
