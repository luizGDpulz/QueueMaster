<?php

namespace QueueMaster\Services;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Queue;
use QueueMaster\Models\Service;

/**
 * QueueReadService
 *
 * Concentrates read-side assembly for queue list, status and active queue
 * summaries so controllers can stay focused on HTTP and authorization.
 */
class QueueReadService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function enrichQueuesList(array $queues): array
    {
        if (empty($queues)) {
            return [];
        }

        $establishmentIds = array_values(array_filter(array_unique(array_column($queues, 'establishment_id'))));
        $serviceIds = array_values(array_filter(array_unique(array_column($queues, 'service_id'))));
        $queueIds = array_values(array_column($queues, 'id'));

        $establishmentMap = $this->mapSimpleTable('establishments', $establishmentIds);
        $serviceMap = $this->mapSimpleTable('services', $serviceIds);
        $waitingMap = $this->mapWaitingCounts($queueIds);

        foreach ($queues as &$queue) {
            $queue['establishment_name'] = $establishmentMap[(int)$queue['establishment_id']] ?? null;
            $queue['service_name'] = $serviceMap[(int)$queue['service_id']] ?? null;
            $queue['waiting_count'] = $waitingMap[(int)$queue['id']] ?? 0;
        }
        unset($queue);

        return $queues;
    }

    public function buildQueueStatusPayload(
        array $queue,
        ?int $userId,
        bool $canViewPeopleDetails,
        bool $canViewCompletedPeople,
        ?string $completedDateFrom,
        ?string $completedDateTo
    ): array {
        $queueId = (int)$queue['id'];
        $now = time();
        $serviceDuration = (int)(Service::find((int)($queue['service_id'] ?? 0))['duration_minutes'] ?? 15);
        if ($serviceDuration <= 0) {
            $serviceDuration = 15;
        }

        $waitingEntries = $this->db->query(
            "
            SELECT qe.*, u.name AS user_name, u.email AS user_email
            FROM queue_entries qe
            LEFT JOIN users u ON u.id = qe.user_id
            WHERE qe.queue_id = ? AND qe.status = 'waiting'
            ORDER BY qe.priority DESC, qe.position ASC
            ",
            [$queueId]
        );

        $userEntry = null;

        foreach ($waitingEntries as $index => &$entry) {
            $visiblePosition = $index + 1;
            $entry['position'] = $visiblePosition;
            $entry['queue_position'] = $visiblePosition;
            $entry['people_ahead'] = $index;
            $entry['estimated_wait_minutes'] = max(0, $index * $serviceDuration);
            $entry['waiting_since_minutes'] = $this->minutesSince($entry['created_at'] ?? null, $now);
            $entry['user_name'] = $this->resolveEntryUserName($entry);

            if ($userId && !empty($entry['user_id']) && (int)$entry['user_id'] === $userId) {
                $userEntry = $this->buildUserEntryPayload(
                    $entry,
                    $visiblePosition,
                    $index,
                    max(0, $index * $serviceDuration)
                );
            }
        }
        unset($entry);

        $entries = $canViewPeopleDetails ? $waitingEntries : [];
        $waitingCount = count($waitingEntries);

        $entriesCalled = $this->db->query(
            "
            SELECT qe.*, u.name AS user_name, u.email AS user_email,
                   p.name AS professional_name, p.email AS professional_email
            FROM queue_entries qe
            LEFT JOIN users u ON u.id = qe.user_id
            LEFT JOIN users p ON p.id = qe.professional_id
            WHERE qe.queue_id = ? AND qe.status = 'called'
            ORDER BY qe.called_at ASC
            ",
            [$queueId]
        );

        foreach ($entriesCalled as &$entryCalled) {
            $entryCalled['user_name'] = $this->resolveEntryUserName($entryCalled);
            $entryCalled['called_since_minutes'] = $this->minutesSince($entryCalled['called_at'] ?? null, $now);

            if ($userId && !empty($entryCalled['user_id']) && (int)$entryCalled['user_id'] === $userId) {
                $userEntry = $this->buildUserEntryPayload(
                    $entryCalled,
                    null,
                    0,
                    0,
                    0,
                    $entryCalled['professional_name'] ?? null,
                    (int)$entryCalled['called_since_minutes']
                );
            }
        }
        unset($entryCalled);

        $calledCount = count($entriesCalled);
        if (!$canViewPeopleDetails) {
            $entriesCalled = [];
        }

        $entriesServing = $this->db->query(
            "
            SELECT qe.*, u.name AS user_name, u.email AS user_email,
                   p.name AS professional_name, p.email AS professional_email
            FROM queue_entries qe
            LEFT JOIN users u ON u.id = qe.user_id
            LEFT JOIN users p ON p.id = qe.professional_id
            WHERE qe.queue_id = ? AND qe.status = 'serving'
            ORDER BY COALESCE(qe.served_at, qe.called_at) ASC
            ",
            [$queueId]
        );

        foreach ($entriesServing as &$entryServing) {
            $entryServing['user_name'] = $this->resolveEntryUserName($entryServing);
            $entryServing['serving_since_minutes'] = $this->minutesSince(
                $entryServing['served_at'] ?? $entryServing['called_at'] ?? null,
                $now
            );

            if ($userId && !empty($entryServing['user_id']) && (int)$entryServing['user_id'] === $userId) {
                $userEntry = $this->buildUserEntryPayload(
                    $entryServing,
                    null,
                    0,
                    0,
                    (int)$entryServing['serving_since_minutes'],
                    $entryServing['professional_name'] ?? null,
                    $this->minutesSince($entryServing['called_at'] ?? null, $now)
                );
            }

            if (!$canViewPeopleDetails) {
                $entryServing = $this->toPublicServingEntry($entryServing);
            }
        }
        unset($entryServing);

        [$completedWhere, $completedParams] = $this->buildCompletedFilter($queueId, $completedDateFrom, $completedDateTo);

        $entriesCompleted = $this->db->query(
            "
            SELECT qe.*, u.name AS user_name, u.email AS user_email
            FROM queue_entries qe
            LEFT JOIN users u ON u.id = qe.user_id
            WHERE {$completedWhere}
            ORDER BY qe.completed_at DESC
            ",
            $completedParams
        );

        foreach ($entriesCompleted as &$entryCompleted) {
            $entryCompleted['user_name'] = $this->resolveEntryUserName($entryCompleted);
        }
        unset($entryCompleted);

        if (!$canViewCompletedPeople) {
            $entriesCompleted = [];
        }

        $avgWaitResult = $this->db->query(
            "
            SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, called_at)) AS avg_wait
            FROM queue_entries
            WHERE queue_id = ? AND called_at IS NOT NULL AND DATE(called_at) = CURDATE()
            ",
            [$queueId]
        );

        return [
            'entries' => $entries,
            'entries_called' => $entriesCalled,
            'entries_serving' => $entriesServing,
            'entries_completed' => $entriesCompleted,
            'statistics' => [
                'total_waiting' => $waitingCount,
                'total_called' => $calledCount,
                'total_being_served' => count($entriesServing),
                'total_completed_today' => count($entriesCompleted),
                'average_wait_time_minutes' => (int)($avgWaitResult[0]['avg_wait'] ?? 0),
            ],
            'user_entry' => $userEntry,
            'waiting_count' => $waitingCount,
        ];
    }

    public function buildCurrentActiveQueuePayload(array $activeEntry): ?array
    {
        $queue = Queue::find((int)$activeEntry['queue_id']);
        if (!$queue) {
            return null;
        }

        $establishmentName = null;
        if (!empty($queue['establishment_id'])) {
            $establishment = Establishment::find((int)$queue['establishment_id']);
            $establishmentName = $establishment['name'] ?? null;
        }

        $serviceName = null;
        if (!empty($queue['service_id'])) {
            $service = Service::find((int)$queue['service_id']);
            $serviceName = $service['name'] ?? null;
        }

        return [
            'queue' => [
                'id' => (int)$queue['id'],
                'name' => $queue['name'],
                'establishment_name' => $establishmentName,
                'service_name' => $serviceName,
                'status' => $queue['status'] ?? null,
            ],
            'entry' => [
                'public_id' => $activeEntry['public_id'] ?? null,
                'queue_id' => (int)$activeEntry['queue_id'],
                'status' => $activeEntry['status'] ?? 'waiting',
                'position' => isset($activeEntry['position']) ? (int)$activeEntry['position'] : null,
                'created_at' => $activeEntry['created_at'] ?? null,
                'called_at' => $activeEntry['called_at'] ?? null,
            ],
        ];
    }

    public function resolveRequeuePosition(int $queueId): int
    {
        $lastEntry = (new QueryBuilder())
            ->select('queue_entries', ['position'])
            ->where('queue_id', '=', $queueId)
            ->orderBy('position', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return ((int)($lastEntry['position'] ?? 0)) + 1;
    }

    private function mapSimpleTable(string $table, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "SELECT id, name FROM {$table} WHERE id IN ($placeholders)",
            array_values($ids)
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['name'];
        }

        return $map;
    }

    private function mapWaitingCounts(array $queueIds): array
    {
        if (empty($queueIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($queueIds), '?'));
        $rows = $this->db->query(
            "
            SELECT queue_id, COUNT(*) AS cnt
            FROM queue_entries
            WHERE queue_id IN ($placeholders) AND status = 'waiting'
            GROUP BY queue_id
            ",
            array_values($queueIds)
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['queue_id']] = (int)$row['cnt'];
        }

        return $map;
    }

    private function buildCompletedFilter(
        int $queueId,
        ?string $completedDateFrom,
        ?string $completedDateTo
    ): array {
        $where = "qe.queue_id = ? AND qe.status IN ('done', 'no_show')";
        $params = [$queueId];

        if ($completedDateFrom && $completedDateTo) {
            $where .= " AND DATE(qe.completed_at) BETWEEN ? AND ?";
            $params[] = $completedDateFrom;
            $params[] = $completedDateTo;
        } elseif ($completedDateFrom) {
            $where .= " AND DATE(qe.completed_at) >= ?";
            $params[] = $completedDateFrom;
        } else {
            $where .= " AND DATE(qe.completed_at) = CURDATE()";
        }

        return [$where, $params];
    }

    private function buildUserEntryPayload(
        array $entry,
        ?int $position,
        int $peopleAhead,
        int $estimatedWaitMinutes,
        int $servingSinceMinutes = 0,
        ?string $professionalName = null,
        int $calledSinceMinutes = 0
    ): array {
        return [
            'entry_public_id' => $entry['public_id'] ?? null,
            'status' => $entry['status'] ?? 'waiting',
            'position' => $position,
            'queue_position' => $position,
            'people_ahead' => $peopleAhead,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'joined_at' => $entry['created_at'] ?? null,
            'called_at' => $entry['called_at'] ?? null,
            'called_since_minutes' => $calledSinceMinutes,
            'serving_since_minutes' => $servingSinceMinutes,
            'professional_name' => $professionalName,
        ];
    }

    private function toPublicServingEntry(array $entry): array
    {
        return [
            'public_id' => $entry['public_id'] ?? null,
            'status' => $entry['status'] ?? 'serving',
            'created_at' => $entry['created_at'] ?? null,
            'called_at' => $entry['called_at'] ?? null,
            'serving_since_minutes' => (int)($entry['serving_since_minutes'] ?? 0),
            'professional_name' => $entry['professional_name'] ?? null,
            'user_name' => 'Pessoa em atendimento',
        ];
    }

    private function resolveEntryUserName(array $entry): string
    {
        if (!empty($entry['user_name'])) {
            return $entry['user_name'];
        }

        return $entry['guest_name'] ?? ('Usuário #' . ($entry['user_id'] ?? $entry['id']));
    }

    private function minutesSince(?string $dateTime, int $referenceTs): int
    {
        if (empty($dateTime)) {
            return 0;
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 0;
        }

        return max(0, (int)round(($referenceTs - $timestamp) / 60));
    }
}
