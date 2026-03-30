<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * QueueEntryEvent Model
 *
 * Immutable timeline events for a single queue participation.
 */
class QueueEntryEvent
{
    protected static string $table = 'queue_entry_events';
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?array
    {
        return (new QueryBuilder())
            ->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    public static function create(array $data): int
    {
        $errors = self::validate($data);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload']);
        }

        $data['occurred_at'] = $data['occurred_at'] ?? date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        return (new QueryBuilder())
            ->select(self::$table)
            ->insert($data);
    }

    public static function listByQueueEntryId(int $queueEntryId): array
    {
        return (new QueryBuilder())
            ->select(self::$table)
            ->where('queue_entry_id', '=', $queueEntryId)
            ->orderBy('occurred_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    public static function existsByIdentity(int $queueEntryId, string $eventType, string $occurredAt): bool
    {
        return (new QueryBuilder())
            ->select(self::$table)
            ->where('queue_entry_id', '=', $queueEntryId)
            ->where('event_type', '=', $eventType)
            ->where('occurred_at', '=', $occurredAt)
            ->count() > 0;
    }

    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['queue_entry_id']) || !is_numeric($data['queue_entry_id'])) {
            $errors['queue_entry_id'] = 'Queue entry ID is required';
        }

        if (empty($data['queue_id']) || !is_numeric($data['queue_id'])) {
            $errors['queue_id'] = 'Queue ID is required';
        }

        if (empty($data['event_type'])) {
            $errors['event_type'] = 'Event type is required';
        } elseif (strlen((string)$data['event_type']) > 50) {
            $errors['event_type'] = 'Event type must not exceed 50 characters';
        }

        if (isset($data['actor_type']) && !in_array($data['actor_type'], ['system', 'client', 'staff'], true)) {
            $errors['actor_type'] = 'Actor type is invalid';
        }

        if (isset($data['payload']) && is_string($data['payload'])) {
            json_decode($data['payload']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['payload'] = 'Payload must be valid JSON';
            }
        }

        return $errors;
    }
}
