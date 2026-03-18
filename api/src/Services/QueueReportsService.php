<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

class QueueReportsService
{
    private Database $db;
    private ContextAccessService $accessService;

    public function __construct(?ContextAccessService $accessService = null)
    {
        $this->db = Database::getInstance();
        $this->accessService = $accessService ?? new ContextAccessService();
    }

    public function build(array $user, array $params = [], ?int $forcedQueueId = null): array
    {
        $filters = $this->normalizeFilters($params, $forcedQueueId);
        $scope = $this->resolveScope($user, $filters);

        if ($scope['is_empty']) {
            return $this->emptyPayload($filters);
        }

        [$whereSql, $binds] = $this->buildWhereClause($filters, $scope['accessible_establishment_ids']);

        $summary = $this->fetchSummary($whereSql, $binds);
        $dailyBreakdown = $this->fetchDailyBreakdown($whereSql, $binds);
        $hourlyDistribution = $this->fetchHourlyDistribution($whereSql, $binds);
        $statusBreakdown = $this->fetchStatusBreakdown($whereSql, $binds);
        $priorityBreakdown = $this->fetchPriorityBreakdown($whereSql, $binds);
        $queueBreakdown = $this->fetchQueueBreakdown($whereSql, $binds);
        $establishmentBreakdown = $this->fetchEstablishmentBreakdown($whereSql, $binds);
        $businessBreakdown = $this->fetchBusinessBreakdown($whereSql, $binds);
        $professionalBreakdown = $this->fetchProfessionalBreakdown($whereSql, $binds);
        $tableRows = $this->fetchTableRows($whereSql, $binds);

        $totalWithOutcome = ((int)$summary['total_completed']) + ((int)$summary['total_no_show']);
        $summary['completion_rate'] = $totalWithOutcome > 0
            ? round((((int)$summary['total_completed']) / $totalWithOutcome) * 100, 1)
            : 0.0;

        return [
            'filters' => $filters,
            'summary' => $summary,
            'series' => [
                'daily' => $dailyBreakdown,
                'hourly' => $hourlyDistribution,
            ],
            'breakdowns' => [
                'status' => $statusBreakdown,
                'priority' => $priorityBreakdown,
                'queue' => $queueBreakdown,
                'establishment' => $establishmentBreakdown,
                'business' => $businessBreakdown,
                'professional' => $professionalBreakdown,
            ],
            'table_rows' => $tableRows,
            'export_meta' => [
                'generated_at' => $this->now()->format(DATE_ATOM),
                'timezone' => $this->getTimezone(),
                'scope' => $forcedQueueId ? 'queue' : 'global',
                'ready_for' => ['pdf'],
            ],
            'period' => $filters['period'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'daily_breakdown' => $dailyBreakdown,
            'hourly_distribution' => $hourlyDistribution,
            'priority_distribution' => $priorityBreakdown,
        ];
    }

    public function getFilterMetadata(array $user): array
    {
        return [
            'businesses' => $this->accessService->getAccessibleBusinesses($user),
            'establishments' => $this->accessService->getAccessibleEstablishments($user),
            'queues' => $this->accessService->getAccessibleQueues($user),
            'professionals' => $this->accessService->getAccessibleReportProfessionals($user),
            'statuses' => [
                ['value' => 'waiting', 'label' => 'Aguardando'],
                ['value' => 'called', 'label' => 'Chamado'],
                ['value' => 'serving', 'label' => 'Em atendimento'],
                ['value' => 'done', 'label' => 'Concluido'],
                ['value' => 'no_show', 'label' => 'Nao compareceu'],
                ['value' => 'cancelled', 'label' => 'Cancelado'],
            ],
            'priorities' => [
                ['value' => 0, 'label' => 'Normal'],
                ['value' => 1, 'label' => 'Prioritario'],
                ['value' => 2, 'label' => 'Muito prioritario'],
            ],
            'periods' => [
                ['value' => 'today', 'label' => 'Hoje'],
                ['value' => '7d', 'label' => 'Ultimos 7 dias'],
                ['value' => '30d', 'label' => 'Ultimos 30 dias'],
                ['value' => '90d', 'label' => 'Ultimos 90 dias'],
                ['value' => 'custom', 'label' => 'Periodo personalizado'],
            ],
        ];
    }

    private function normalizeFilters(array $params, ?int $forcedQueueId): array
    {
        $period = $params['period'] ?? '7d';
        $queueId = $forcedQueueId ?: $this->toNullableInt($params['queue_id'] ?? null);
        $businessId = $this->toNullableInt($params['business_id'] ?? null);
        $establishmentId = $this->toNullableInt($params['establishment_id'] ?? null);
        $professionalId = $this->toNullableInt($params['professional_id'] ?? null);
        $statusValues = $this->normalizeList($params['status'] ?? ($params['statuses'] ?? []));
        $priorityValues = array_values(array_filter(
            array_map(fn($value) => is_numeric($value) ? (int)$value : null, $this->normalizeList($params['priority'] ?? ($params['priorities'] ?? []))),
            static fn($value) => $value !== null
        ));

        [$dateFrom, $dateTo] = $this->resolveDateRange(
            $period,
            $params['date_from'] ?? null,
            $params['date_to'] ?? null
        );

        return [
            'period' => $period,
            'business_id' => $businessId,
            'establishment_id' => $establishmentId,
            'queue_id' => $queueId,
            'professional_id' => $professionalId,
            'statuses' => $statusValues,
            'priorities' => $priorityValues,
            'date_from' => substr($dateFrom, 0, 10),
            'date_to' => substr($dateTo, 0, 10),
            'date_from_sql' => $dateFrom,
            'date_to_sql' => $dateTo,
        ];
    }

    private function resolveScope(array $user, array $filters): array
    {
        if ($this->accessService->isAdmin($user)) {
            return [
                'is_empty' => false,
                'accessible_establishment_ids' => [],
            ];
        }

        $accessibleEstablishments = $this->accessService->getAccessibleEstablishmentIds($user);
        if (empty($accessibleEstablishments)) {
            return [
                'is_empty' => true,
                'accessible_establishment_ids' => [],
            ];
        }

        if (!empty($filters['establishment_id']) && !in_array($filters['establishment_id'], $accessibleEstablishments, true)) {
            return [
                'is_empty' => true,
                'accessible_establishment_ids' => [],
            ];
        }

        return [
            'is_empty' => false,
            'accessible_establishment_ids' => $accessibleEstablishments,
        ];
    }

    private function buildWhereClause(array $filters, array $accessibleEstablishmentIds): array
    {
        $where = [
            'qe.created_at >= ?',
            'qe.created_at <= ?',
        ];
        $binds = [
            $filters['date_from_sql'],
            $filters['date_to_sql'],
        ];

        if (!empty($filters['queue_id'])) {
            $where[] = 'qe.queue_id = ?';
            $binds[] = $filters['queue_id'];
        }

        if (!empty($filters['business_id'])) {
            $where[] = 'e.business_id = ?';
            $binds[] = $filters['business_id'];
        }

        if (!empty($filters['establishment_id'])) {
            $where[] = 'q.establishment_id = ?';
            $binds[] = $filters['establishment_id'];
        }

        if (!empty($filters['professional_id'])) {
            $where[] = 'qe.professional_id = ?';
            $binds[] = $filters['professional_id'];
        }

        if (!empty($filters['statuses'])) {
            $placeholders = implode(',', array_fill(0, count($filters['statuses']), '?'));
            $where[] = "qe.status IN ($placeholders)";
            array_push($binds, ...$filters['statuses']);
        }

        if (!empty($filters['priorities'])) {
            $placeholders = implode(',', array_fill(0, count($filters['priorities']), '?'));
            $where[] = "qe.priority IN ($placeholders)";
            array_push($binds, ...$filters['priorities']);
        }

        if (!empty($accessibleEstablishmentIds)) {
            $placeholders = implode(',', array_fill(0, count($accessibleEstablishmentIds), '?'));
            $where[] = "q.establishment_id IN ($placeholders)";
            array_push($binds, ...$accessibleEstablishmentIds);
        }

        return [implode(' AND ', $where), $binds];
    }

    private function fetchSummary(string $whereSql, array $binds): array
    {
        $rows = $this->db->query(
            "
            SELECT
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled,
                SUM(CASE WHEN qe.status = 'waiting' THEN 1 ELSE 0 END) AS total_waiting,
                SUM(CASE WHEN qe.status IN ('called', 'serving') THEN 1 ELSE 0 END) AS total_serving,
                AVG(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS avg_wait_minutes,
                AVG(CASE WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at) END) AS avg_service_minutes,
                MIN(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS min_wait_minutes,
                MAX(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS max_wait_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            ",
            $binds
        );

        $summary = $rows[0] ?? [];

        return [
            'total_entries' => (int)($summary['total_entries'] ?? 0),
            'total_completed' => (int)($summary['total_completed'] ?? 0),
            'total_no_show' => (int)($summary['total_no_show'] ?? 0),
            'total_cancelled' => (int)($summary['total_cancelled'] ?? 0),
            'total_waiting' => (int)($summary['total_waiting'] ?? 0),
            'total_serving' => (int)($summary['total_serving'] ?? 0),
            'avg_wait_minutes' => round((float)($summary['avg_wait_minutes'] ?? 0), 1),
            'avg_service_minutes' => round((float)($summary['avg_service_minutes'] ?? 0), 1),
            'min_wait_minutes' => (int)($summary['min_wait_minutes'] ?? 0),
            'max_wait_minutes' => (int)($summary['max_wait_minutes'] ?? 0),
        ];
    }

    private function fetchDailyBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                DATE(qe.created_at) AS date,
                COUNT(*) AS total,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                AVG(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS avg_wait,
                AVG(CASE WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at) END) AS avg_service
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY DATE(qe.created_at)
            ORDER BY DATE(qe.created_at) ASC
            ",
            $binds
        );
    }

    private function fetchHourlyDistribution(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                HOUR(qe.created_at) AS hour,
                COUNT(*) AS count
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY HOUR(qe.created_at)
            ORDER BY HOUR(qe.created_at) ASC
            ",
            $binds
        );
    }

    private function fetchStatusBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT qe.status, COUNT(*) AS count
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY qe.status
            ORDER BY count DESC, qe.status ASC
            ",
            $binds
        );
    }

    private function fetchPriorityBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT qe.priority, COUNT(*) AS count
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY qe.priority
            ORDER BY qe.priority DESC
            ",
            $binds
        );
    }

    private function fetchQueueBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                q.id,
                q.name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled,
                AVG(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS avg_wait_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY q.id, q.name
            ORDER BY total_entries DESC, q.name ASC
            ",
            $binds
        );
    }

    private function fetchEstablishmentBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                e.id,
                e.name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY e.id, e.name
            ORDER BY total_entries DESC, e.name ASC
            ",
            $binds
        );
    }

    private function fetchBusinessBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                b.id,
                b.name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE $whereSql
            GROUP BY b.id, b.name
            ORDER BY total_entries DESC, b.name ASC
            ",
            $binds
        );
    }

    private function fetchProfessionalBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                qe.professional_id AS id,
                COALESCE(u.name, 'Sem profissional') AS name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                AVG(CASE WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at) END) AS avg_service_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            LEFT JOIN users u ON u.id = qe.professional_id
            WHERE $whereSql
            GROUP BY qe.professional_id, u.name
            ORDER BY total_entries DESC, name ASC
            ",
            $binds
        );
    }

    private function fetchTableRows(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                qe.id,
                qe.queue_id,
                qe.user_id,
                qe.guest_name,
                qe.status,
                qe.priority,
                qe.position,
                qe.created_at,
                qe.called_at,
                qe.served_at,
                qe.completed_at,
                q.name AS queue_name,
                e.id AS establishment_id,
                e.name AS establishment_name,
                b.id AS business_id,
                b.name AS business_name,
                u.name AS user_name,
                pu.name AS professional_name,
                CASE
                    WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at)
                    ELSE NULL
                END AS wait_minutes,
                CASE
                    WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at)
                    ELSE NULL
                END AS service_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            LEFT JOIN users u ON u.id = qe.user_id
            LEFT JOIN users pu ON pu.id = qe.professional_id
            WHERE $whereSql
            ORDER BY qe.created_at DESC, qe.id DESC
            LIMIT 100
            ",
            $binds
        );
    }

    private function emptyPayload(array $filters): array
    {
        return [
            'filters' => $filters,
            'summary' => [
                'total_entries' => 0,
                'total_completed' => 0,
                'total_no_show' => 0,
                'total_cancelled' => 0,
                'total_waiting' => 0,
                'total_serving' => 0,
                'avg_wait_minutes' => 0,
                'avg_service_minutes' => 0,
                'min_wait_minutes' => 0,
                'max_wait_minutes' => 0,
                'completion_rate' => 0,
            ],
            'series' => [
                'daily' => [],
                'hourly' => [],
            ],
            'breakdowns' => [
                'status' => [],
                'priority' => [],
                'queue' => [],
                'establishment' => [],
                'business' => [],
                'professional' => [],
            ],
            'table_rows' => [],
            'export_meta' => [
                'generated_at' => $this->now()->format(DATE_ATOM),
                'timezone' => $this->getTimezone(),
                'scope' => 'empty',
                'ready_for' => ['pdf'],
            ],
            'period' => $filters['period'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'daily_breakdown' => [],
            'hourly_distribution' => [],
            'priority_distribution' => [],
        ];
    }

    private function resolveDateRange(string $period, ?string $rawDateFrom, ?string $rawDateTo): array
    {
        $timezone = new DateTimeZone($this->getTimezone());

        if ($period === 'custom' && $rawDateFrom) {
            $dateFrom = (new DateTimeImmutable($rawDateFrom, $timezone))->setTime(0, 0, 0);
            $dateTo = $rawDateTo
                ? (new DateTimeImmutable($rawDateTo, $timezone))->setTime(23, 59, 59)
                : (new DateTimeImmutable('now', $timezone))->setTime(23, 59, 59);

            return [
                $dateFrom->format('Y-m-d H:i:s'),
                $dateTo->format('Y-m-d H:i:s'),
            ];
        }

        $now = new DateTimeImmutable('now', $timezone);
        $todayStart = $now->setTime(0, 0, 0);
        $todayEnd = $now->setTime(23, 59, 59);

        return match ($period) {
            'today' => [$todayStart->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')],
            '30d' => [$todayStart->modify('-30 days')->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')],
            '90d' => [$todayStart->modify('-90 days')->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')],
            default => [$todayStart->modify('-7 days')->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')],
        };
    }

    private function normalizeList(mixed $rawValue): array
    {
        if (is_array($rawValue)) {
            return array_values(array_filter(array_map('trim', $rawValue), static fn($value) => $value !== ''));
        }

        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string)$rawValue)),
            static fn($value) => $value !== ''
        ));
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function getTimezone(): string
    {
        return $_ENV['APP_TIMEZONE'] ?? date_default_timezone_get();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($this->getTimezone()));
    }
}
