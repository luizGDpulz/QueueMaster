<?php

namespace QueueMaster\Services;

use QueueMaster\Models\QueueEntryEvent;

/**
 * QueueEntryEventService
 *
 * Centralizes timeline writes for queue participations.
 */
class QueueEntryEventService
{
    public function record(
        int $queueEntryId,
        int $queueId,
        ?int $userId,
        string $eventType,
        array $payload = [],
        ?string $occurredAt = null,
        string $actorType = 'system',
        ?int $actorUserId = null
    ): int {
        return QueueEntryEvent::create([
            'queue_entry_id' => $queueEntryId,
            'queue_id' => $queueId,
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'actor_type' => $actorType,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => $occurredAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function ensureRecordedAt(
        int $queueEntryId,
        int $queueId,
        ?int $userId,
        string $eventType,
        array $payload = [],
        ?string $occurredAt = null,
        string $actorType = 'system',
        ?int $actorUserId = null
    ): ?int {
        $normalizedOccurredAt = $occurredAt ?? date('Y-m-d H:i:s');

        if (QueueEntryEvent::existsByIdentity($queueEntryId, $eventType, $normalizedOccurredAt)) {
            return null;
        }

        return $this->record(
            $queueEntryId,
            $queueId,
            $userId,
            $eventType,
            $payload,
            $normalizedOccurredAt,
            $actorType,
            $actorUserId
        );
    }
}
