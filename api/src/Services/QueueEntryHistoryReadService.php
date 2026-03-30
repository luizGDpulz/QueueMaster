<?php

namespace QueueMaster\Services;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * QueueEntryHistoryReadService
 *
 * Encapsulates queue entry history reads and response shaping so controllers
 * stay focused on authorization and HTTP concerns.
 */
class QueueEntryHistoryReadService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findLatestActiveEntryByUser(int $userId): ?array
    {
        return $this->baseEntryDetailsQuery()
            ->where('qe.user_id', '=', $userId)
            ->whereIn('qe.status', ['waiting', 'called', 'serving'])
            ->orderBy('qe.created_at', 'DESC')
            ->orderBy('qe.id', 'DESC')
            ->first();
    }

    public function findEntryByPublicId(string $publicId): ?array
    {
        return $this->baseEntryDetailsQuery()
            ->where('qe.public_id', '=', $this->normalizePublicId($publicId))
            ->first();
    }

    public function listHistoryByUser(
        int $userId,
        int $page,
        int $perPage,
        string $status = '',
        string $state = ''
    ): array {
        $offset = ($page - 1) * $perPage;
        $filters = $this->buildHistoryFilterSpec($userId, $status, $state);

        $countQuery = (new QueryBuilder())
            ->select('queue_entries qe')
            ->where('qe.user_id', '=', $userId);

        if ($filters['exact_status'] !== null) {
            $countQuery->where('qe.status', '=', $filters['exact_status']);
        } elseif (!empty($filters['statuses'])) {
            $countQuery->whereIn('qe.status', $filters['statuses']);
        }

        $total = $countQuery->count();

        $rows = $this->db->query(
            "
            SELECT
                qe.*,
                q.name AS queue_name,
                q.status AS queue_status,
                q.establishment_id,
                e.name AS establishment_name,
                e.slug AS establishment_slug,
                s.name AS service_name,
                s.duration_minutes AS service_duration_minutes,
                p.name AS professional_name,
                (
                    SELECT qee.event_type
                    FROM queue_entry_events qee
                    WHERE qee.queue_entry_id = qe.id
                    ORDER BY qee.occurred_at DESC, qee.id DESC
                    LIMIT 1
                ) AS last_event_type,
                (
                    SELECT qee.occurred_at
                    FROM queue_entry_events qee
                    WHERE qee.queue_entry_id = qe.id
                    ORDER BY qee.occurred_at DESC, qee.id DESC
                    LIMIT 1
                ) AS last_event_at,
                (
                    SELECT COUNT(*)
                    FROM queue_entry_events qee
                    WHERE qee.queue_entry_id = qe.id
                ) AS events_count
            FROM queue_entries qe
            INNER JOIN queues q ON q.id = qe.queue_id
            LEFT JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN services s ON s.id = q.service_id
            LEFT JOIN users p ON p.id = qe.professional_id
            WHERE {$filters['where_sql']}
            ORDER BY
                COALESCE(
                    (
                        SELECT qee.occurred_at
                        FROM queue_entry_events qee
                        WHERE qee.queue_entry_id = qe.id
                        ORDER BY qee.occurred_at DESC, qee.id DESC
                        LIMIT 1
                    ),
                    qe.completed_at,
                    qe.called_at,
                    qe.created_at
                ) DESC,
                qe.id DESC
            LIMIT ? OFFSET ?
            ",
            array_merge($filters['params'], [$perPage, $offset])
        );

        return [
            'items' => array_map(
                fn(array $row): array => $this->serializeHistoryItem($row),
                $rows
            ),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public function listEventsByEntryId(int $entryId): array
    {
        $rows = (new QueryBuilder())
            ->select('queue_entry_events qee', [
                'qee.*',
                'au.name AS actor_user_name',
            ])
            ->leftJoin('users au', 'au.id', '=', 'qee.actor_user_id')
            ->where('qee.queue_entry_id', '=', $entryId)
            ->orderBy('qee.occurred_at', 'ASC')
            ->orderBy('qee.id', 'ASC')
            ->get();

        return array_map(function (array $event): array {
            if (isset($event['payload']) && is_string($event['payload'])) {
                $event['payload'] = json_decode($event['payload'], true) ?? [];
            }

            return [
                'type' => $event['event_type'],
                'occurred_at' => $event['occurred_at'],
                'actor_type' => $event['actor_type'],
                'actor_user_name' => $event['actor_user_name'] ?? null,
                'payload' => $event['payload'] ?? [],
            ];
        }, $rows);
    }

    public function serializeEntry(array $entry): array
    {
        $isActive = in_array($entry['status'] ?? null, ['waiting', 'called', 'serving'], true);
        $liveMetrics = $this->resolveLiveMetrics($entry);

        return [
            'public_id' => $entry['public_id'],
            'status' => $entry['status'] ?? null,
            'is_active' => $isActive,
            'queue' => [
                'id' => (int)$entry['queue_id'],
                'name' => $entry['queue_name'] ?? null,
                'status' => $entry['queue_status'] ?? null,
            ],
            'establishment' => [
                'name' => $entry['establishment_name'] ?? null,
                'slug' => $entry['establishment_slug'] ?? null,
            ],
            'service_name' => $entry['service_name'] ?? null,
            'professional_name' => $entry['professional_name'] ?? null,
            'position' => $liveMetrics['position'],
            'people_ahead' => $liveMetrics['people_ahead'],
            'estimated_wait_minutes' => $liveMetrics['estimated_wait_minutes'],
            'joined_at' => $entry['created_at'] ?? null,
            'called_at' => $entry['called_at'] ?? null,
            'served_at' => $entry['served_at'] ?? null,
            'completed_at' => $entry['completed_at'] ?? null,
        ];
    }

    public function serializeHistoryItem(array $entry): array
    {
        $isActive = in_array($entry['status'] ?? null, ['waiting', 'called', 'serving'], true);

        return [
            'entry_public_id' => $entry['public_id'],
            'status' => $entry['status'] ?? null,
            'is_active' => $isActive,
            'joined_at' => $entry['created_at'] ?? null,
            'called_at' => $entry['called_at'] ?? null,
            'served_at' => $entry['served_at'] ?? null,
            'completed_at' => $entry['completed_at'] ?? null,
            'last_event_type' => $entry['last_event_type'] ?? $this->inferLastEventType($entry),
            'last_event_at' => $entry['last_event_at']
                ?? $entry['completed_at']
                ?? $entry['called_at']
                ?? $entry['created_at']
                ?? null,
            'events_count' => isset($entry['events_count']) ? (int)$entry['events_count'] : 0,
            'can_join_again' => !$isActive && ($entry['queue_status'] ?? null) === 'open',
            'queue' => [
                'id' => (int)$entry['queue_id'],
                'name' => $entry['queue_name'] ?? null,
                'status' => $entry['queue_status'] ?? null,
            ],
            'establishment' => [
                'name' => $entry['establishment_name'] ?? null,
                'slug' => $entry['establishment_slug'] ?? null,
            ],
            'service_name' => $entry['service_name'] ?? null,
            'professional_name' => $entry['professional_name'] ?? null,
        ];
    }

    private function baseEntryDetailsQuery(): QueryBuilder
    {
        return (new QueryBuilder())
            ->select('queue_entries qe', $this->baseEntrySelectColumns())
            ->join('queues q', 'q.id', '=', 'qe.queue_id')
            ->leftJoin('establishments e', 'e.id', '=', 'q.establishment_id')
            ->leftJoin('services s', 's.id', '=', 'q.service_id')
            ->leftJoin('users p', 'p.id', '=', 'qe.professional_id');
    }

    private function baseEntrySelectColumns(): array
    {
        return [
            'qe.*',
            'q.name AS queue_name',
            'q.status AS queue_status',
            'q.establishment_id',
            'e.name AS establishment_name',
            'e.slug AS establishment_slug',
            's.name AS service_name',
            's.duration_minutes AS service_duration_minutes',
            'p.name AS professional_name',
        ];
    }

    private function buildHistoryFilterSpec(int $userId, string $status, string $state): array
    {
        $exactStatus = $status !== '' ? trim($status) : null;
        $statuses = [];
        $whereSql = ['qe.user_id = ?'];
        $params = [$userId];

        if ($exactStatus !== null) {
            $whereSql[] = 'qe.status = ?';
            $params[] = $exactStatus;
        } elseif ($state === 'active') {
            $statuses = ['waiting', 'called', 'serving'];
            $whereSql[] = "qe.status IN ('waiting','called','serving')";
        } elseif ($state === 'finished') {
            $statuses = ['done', 'no_show', 'cancelled'];
            $whereSql[] = "qe.status IN ('done','no_show','cancelled')";
        }

        return [
            'where_sql' => implode(' AND ', $whereSql),
            'params' => $params,
            'exact_status' => $exactStatus,
            'statuses' => $statuses,
        ];
    }

    private function resolveLiveMetrics(array $entry): array
    {
        if (($entry['status'] ?? null) !== 'waiting') {
            return [
                'position' => null,
                'people_ahead' => 0,
                'estimated_wait_minutes' => 0,
            ];
        }

        $waitingEntries = (new QueryBuilder())
            ->select('queue_entries', ['id'])
            ->where('queue_id', '=', (int)$entry['queue_id'])
            ->where('status', '=', 'waiting')
            ->orderBy('priority', 'DESC')
            ->orderBy('position', 'ASC')
            ->get();

        $serviceDuration = max(0, (int)($entry['service_duration_minutes'] ?? 15));

        foreach ($waitingEntries as $index => $waitingEntry) {
            if ((int)$waitingEntry['id'] === (int)$entry['id']) {
                return [
                    'position' => $index + 1,
                    'people_ahead' => $index,
                    'estimated_wait_minutes' => max(0, $index * $serviceDuration),
                ];
            }
        }

        return [
            'position' => null,
            'people_ahead' => 0,
            'estimated_wait_minutes' => 0,
        ];
    }

    private function inferLastEventType(array $entry): string
    {
        return match ($entry['status'] ?? null) {
            'waiting' => 'joined',
            'called' => 'called',
            'serving' => 'serving_started',
            'done' => 'completed',
            'no_show' => 'no_show',
            'cancelled' => 'cancelled',
            default => 'updated',
        };
    }

    private function normalizePublicId(string $publicId): string
    {
        return strtoupper(trim($publicId));
    }
}
