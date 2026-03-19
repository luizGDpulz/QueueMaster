<?php

namespace QueueMaster\Services;

use DateTimeImmutable;
use DateTimeZone;
use QueueMaster\Core\Database;

class ReportsService
{
    private Database $db;
    private ContextAccessService $accessService;

    public function __construct(?ContextAccessService $accessService = null)
    {
        $this->db = Database::getInstance();
        $this->accessService = $accessService ?? new ContextAccessService();
    }

    public function build(array $user, array $params = []): array
    {
        $filters = $this->normalizeFilters($params);
        $scope = $this->resolveScope($user);

        [$queueWhereSql, $queueBinds] = $this->buildQueueWhereClause($filters, $scope);
        [$appointmentWhereSql, $appointmentBinds] = $this->buildAppointmentWhereClause($filters, $scope);

        $queue = $this->buildQueueSection($queueWhereSql, $queueBinds, $filters);
        $appointment = $this->buildAppointmentSection($appointmentWhereSql, $appointmentBinds, $filters);

        return [
            'filters' => $filters,
            'scope' => [
                'role' => $user['role'] ?? '',
                'is_client' => $scope['is_client'],
                'is_professional' => $scope['is_professional'],
                'is_manager' => $scope['is_manager'],
                'is_admin' => $scope['is_admin'],
            ],
            'overview' => [
                'total_records' => (int)$queue['summary']['total_entries'] + (int)$appointment['summary']['total_appointments'],
                'total_completed' => (int)$queue['summary']['total_completed'] + (int)$appointment['summary']['total_completed'],
                'total_no_show' => (int)$queue['summary']['total_no_show'] + (int)$appointment['summary']['total_no_show'],
                'total_cancelled' => (int)$queue['summary']['total_cancelled'] + (int)$appointment['summary']['total_cancelled'],
                'active_now' => (int)$queue['summary']['total_serving'] + (int)$appointment['summary']['total_in_progress'],
                'upcoming_appointments' => (int)$appointment['summary']['total_upcoming'],
            ],
            'queue' => $queue,
            'appointment' => $appointment,
            'export_meta' => [
                'generated_at' => $this->now()->format(DATE_ATOM),
                'timezone' => $this->getTimezone(),
                'ready_for' => ['pdf'],
            ],
        ];
    }

    public function getFilterMetadata(array $user): array
    {
        return [
            'businesses' => $this->fetchBusinessOptions($user),
            'establishments' => $this->fetchEstablishmentOptions($user),
            'queues' => $this->fetchQueueOptions($user),
            'queue_professionals' => $this->fetchQueueProfessionalOptions($user),
            'appointment_professionals' => $this->fetchAppointmentProfessionalOptions($user),
            'services' => $this->fetchServiceOptions($user),
            'queue_statuses' => [
                ['value' => 'waiting', 'label' => 'Aguardando'],
                ['value' => 'called', 'label' => 'Chamado'],
                ['value' => 'serving', 'label' => 'Em atendimento'],
                ['value' => 'done', 'label' => 'Concluído'],
                ['value' => 'no_show', 'label' => 'Não compareceu'],
                ['value' => 'cancelled', 'label' => 'Cancelado'],
            ],
            'appointment_statuses' => [
                ['value' => 'booked', 'label' => 'Agendado'],
                ['value' => 'confirmed', 'label' => 'Confirmado'],
                ['value' => 'checked_in', 'label' => 'Check-in'],
                ['value' => 'in_progress', 'label' => 'Em andamento'],
                ['value' => 'completed', 'label' => 'Concluído'],
                ['value' => 'no_show', 'label' => 'Não compareceu'],
                ['value' => 'cancelled', 'label' => 'Cancelado'],
            ],
            'periods' => [
                ['value' => 'today', 'label' => 'Hoje'],
                ['value' => '7d', 'label' => 'Últimos 7 dias'],
                ['value' => '30d', 'label' => 'Últimos 30 dias'],
                ['value' => '90d', 'label' => 'Últimos 90 dias'],
                ['value' => 'custom', 'label' => 'Período personalizado'],
            ],
        ];
    }

    private function buildQueueSection(string $whereSql, array $binds, array $filters): array
    {
        return [
            'summary' => $this->fetchQueueSummary($whereSql, $binds),
            'series' => [
                'daily' => $this->fetchQueueDailyBreakdown($whereSql, $binds),
            ],
            'breakdowns' => [
                'queue' => $this->fetchQueueBreakdown($whereSql, $binds),
                'establishment' => $this->fetchQueueEstablishmentBreakdown($whereSql, $binds),
                'professional' => $this->fetchQueueProfessionalBreakdown($whereSql, $binds),
            ],
            'table_rows' => $this->fetchQueueTableRows($whereSql, $binds),
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
    }

    private function buildAppointmentSection(string $whereSql, array $binds, array $filters): array
    {
        return [
            'summary' => $this->fetchAppointmentSummary($whereSql, $binds),
            'series' => [
                'daily' => $this->fetchAppointmentDailyBreakdown($whereSql, $binds),
            ],
            'breakdowns' => [
                'service' => $this->fetchAppointmentServiceBreakdown($whereSql, $binds),
                'establishment' => $this->fetchAppointmentEstablishmentBreakdown($whereSql, $binds),
                'professional' => $this->fetchAppointmentProfessionalBreakdown($whereSql, $binds),
            ],
            'table_rows' => $this->fetchAppointmentTableRows($whereSql, $binds),
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
    }

    private function normalizeFilters(array $params): array
    {
        $period = $params['period'] ?? '7d';

        [$dateFrom, $dateTo] = $this->resolveDateRange(
            $period,
            $params['date_from'] ?? null,
            $params['date_to'] ?? null
        );

        return [
            'period' => $period,
            'business_id' => $this->toNullableInt($params['business_id'] ?? null),
            'establishment_id' => $this->toNullableInt($params['establishment_id'] ?? null),
            'queue_id' => $this->toNullableInt($params['queue_id'] ?? null),
            'queue_professional_user_id' => $this->toNullableInt($params['queue_professional_user_id'] ?? null),
            'appointment_professional_id' => $this->toNullableInt($params['appointment_professional_id'] ?? null),
            'service_id' => $this->toNullableInt($params['service_id'] ?? null),
            'queue_statuses' => $this->normalizeList($params['queue_statuses'] ?? []),
            'appointment_statuses' => $this->normalizeList($params['appointment_statuses'] ?? []),
            'date_from' => substr($dateFrom, 0, 10),
            'date_to' => substr($dateTo, 0, 10),
            'date_from_sql' => $dateFrom,
            'date_to_sql' => $dateTo,
        ];
    }

    private function resolveScope(array $user): array
    {
        $isAdmin = $this->accessService->isAdmin($user);
        $isManager = $this->accessService->isManager($user);
        $isProfessional = $this->accessService->isProfessional($user);
        $isClient = $this->accessService->isClient($user);
        $userId = (int)($user['id'] ?? 0);
        $establishmentIds = $isClient ? [] : $this->accessService->getAccessibleEstablishmentIds($user);

        $professionalIds = [];
        if ($isProfessional && $userId > 0) {
            $whereSql = '';
            $params = [$userId];

            if (!empty($establishmentIds)) {
                $placeholders = implode(',', array_fill(0, count($establishmentIds), '?'));
                $whereSql = " AND establishment_id IN ($placeholders)";
                array_push($params, ...$establishmentIds);
            }

            $rows = $this->db->query(
                "
                SELECT id
                FROM professionals
                WHERE user_id = ? $whereSql
                ORDER BY id ASC
                ",
                $params
            );

            $professionalIds = array_map(
                static fn(array $row): int => (int)$row['id'],
                $rows
            );
        }

        return [
            'user_id' => $userId,
            'establishment_ids' => $establishmentIds,
            'professional_ids' => $professionalIds,
            'is_admin' => $isAdmin,
            'is_manager' => $isManager,
            'is_professional' => $isProfessional,
            'is_client' => $isClient,
        ];
    }

    private function buildQueueWhereClause(array $filters, array $scope): array
    {
        $where = [
            'qe.created_at >= ?',
            'qe.created_at <= ?',
        ];
        $binds = [
            $filters['date_from_sql'],
            $filters['date_to_sql'],
        ];

        if (!empty($filters['business_id'])) {
            $where[] = 'e.business_id = ?';
            $binds[] = $filters['business_id'];
        }

        if (!empty($filters['establishment_id'])) {
            $where[] = 'q.establishment_id = ?';
            $binds[] = $filters['establishment_id'];
        }

        if (!empty($filters['queue_id'])) {
            $where[] = 'qe.queue_id = ?';
            $binds[] = $filters['queue_id'];
        }

        if (!empty($filters['queue_professional_user_id'])) {
            $where[] = 'qe.professional_id = ?';
            $binds[] = $filters['queue_professional_user_id'];
        }

        if (!empty($filters['queue_statuses'])) {
            $placeholders = implode(',', array_fill(0, count($filters['queue_statuses']), '?'));
            $where[] = "qe.status IN ($placeholders)";
            array_push($binds, ...$filters['queue_statuses']);
        }

        if ($scope['is_client']) {
            $where[] = 'qe.user_id = ?';
            $binds[] = $scope['user_id'];
        } elseif (!empty($scope['establishment_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['establishment_ids']), '?'));
            $where[] = "q.establishment_id IN ($placeholders)";
            array_push($binds, ...$scope['establishment_ids']);
        } elseif (!$scope['is_admin']) {
            $where[] = '1 = 0';
        }

        return [implode(' AND ', $where), $binds];
    }

    private function buildAppointmentWhereClause(array $filters, array $scope): array
    {
        $where = [
            'a.start_at >= ?',
            'a.start_at <= ?',
        ];
        $binds = [
            $filters['date_from_sql'],
            $filters['date_to_sql'],
        ];

        if (!empty($filters['business_id'])) {
            $where[] = 'e.business_id = ?';
            $binds[] = $filters['business_id'];
        }

        if (!empty($filters['establishment_id'])) {
            $where[] = 'a.establishment_id = ?';
            $binds[] = $filters['establishment_id'];
        }

        if (!empty($filters['appointment_professional_id'])) {
            $where[] = 'a.professional_id = ?';
            $binds[] = $filters['appointment_professional_id'];
        }

        if (!empty($filters['service_id'])) {
            $where[] = 'a.service_id = ?';
            $binds[] = $filters['service_id'];
        }

        if (!empty($filters['appointment_statuses'])) {
            $placeholders = implode(',', array_fill(0, count($filters['appointment_statuses']), '?'));
            $where[] = "a.status IN ($placeholders)";
            array_push($binds, ...$filters['appointment_statuses']);
        }

        if ($scope['is_client']) {
            $where[] = 'a.user_id = ?';
            $binds[] = $scope['user_id'];
        } elseif ($scope['is_professional']) {
            if (empty($scope['professional_ids'])) {
                $where[] = '1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($scope['professional_ids']), '?'));
                $where[] = "a.professional_id IN ($placeholders)";
                array_push($binds, ...$scope['professional_ids']);
            }
        } elseif (!empty($scope['establishment_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['establishment_ids']), '?'));
            $where[] = "a.establishment_id IN ($placeholders)";
            array_push($binds, ...$scope['establishment_ids']);
        } elseif (!$scope['is_admin']) {
            $where[] = '1 = 0';
        }

        return [implode(' AND ', $where), $binds];
    }

    private function fetchQueueSummary(string $whereSql, array $binds): array
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
                AVG(CASE WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at) END) AS avg_service_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
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
        ];
    }

    private function fetchQueueDailyBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                DATE(qe.created_at) AS date,
                COUNT(*) AS total,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS no_show,
                SUM(CASE WHEN qe.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            WHERE $whereSql
            GROUP BY DATE(qe.created_at)
            ORDER BY DATE(qe.created_at) ASC
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
                e.name AS establishment_name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                AVG(CASE WHEN qe.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.created_at, qe.called_at) END) AS avg_wait_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            WHERE $whereSql
            GROUP BY q.id, q.name, e.name
            ORDER BY total_entries DESC, q.name ASC
            ",
            $binds
        );
    }

    private function fetchQueueEstablishmentBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                e.id,
                e.name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN qe.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            WHERE $whereSql
            GROUP BY e.id, e.name
            ORDER BY total_entries DESC, e.name ASC
            ",
            $binds
        );
    }

    private function fetchQueueProfessionalBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                qe.professional_id AS id,
                COALESCE(pu.name, 'Sem profissional') AS name,
                COUNT(*) AS total_entries,
                SUM(CASE WHEN qe.status = 'done' THEN 1 ELSE 0 END) AS total_completed,
                AVG(CASE WHEN qe.served_at IS NOT NULL AND qe.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, qe.served_at, qe.completed_at) END) AS avg_service_minutes
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN users pu ON pu.id = qe.professional_id
            WHERE $whereSql
            GROUP BY qe.professional_id, pu.name
            ORDER BY total_entries DESC, name ASC
            ",
            $binds
        );
    }

    private function fetchQueueTableRows(string $whereSql, array $binds): array
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
                qe.created_at,
                q.name AS queue_name,
                e.name AS establishment_name,
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

    private function fetchAppointmentSummary(string $whereSql, array $binds): array
    {
        $rows = $this->db->query(
            "
            SELECT
                COUNT(*) AS total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled,
                SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS total_in_progress,
                SUM(CASE WHEN a.status IN ('booked', 'confirmed', 'checked_in') AND a.start_at >= NOW() THEN 1 ELSE 0 END) AS total_upcoming,
                AVG(TIMESTAMPDIFF(MINUTE, a.start_at, a.end_at)) AS avg_duration_minutes
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            WHERE $whereSql
            ",
            $binds
        );

        $summary = $rows[0] ?? [];

        return [
            'total_appointments' => (int)($summary['total_appointments'] ?? 0),
            'total_completed' => (int)($summary['total_completed'] ?? 0),
            'total_no_show' => (int)($summary['total_no_show'] ?? 0),
            'total_cancelled' => (int)($summary['total_cancelled'] ?? 0),
            'total_in_progress' => (int)($summary['total_in_progress'] ?? 0),
            'total_upcoming' => (int)($summary['total_upcoming'] ?? 0),
            'avg_duration_minutes' => round((float)($summary['avg_duration_minutes'] ?? 0), 1),
        ];
    }

    private function fetchAppointmentDailyBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                DATE(a.start_at) AS date,
                COUNT(*) AS total,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS no_show,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            WHERE $whereSql
            GROUP BY DATE(a.start_at)
            ORDER BY DATE(a.start_at) ASC
            ",
            $binds
        );
    }

    private function fetchAppointmentServiceBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                s.id,
                s.name,
                COUNT(*) AS total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            LEFT JOIN services s ON s.id = a.service_id
            WHERE $whereSql
            GROUP BY s.id, s.name
            ORDER BY total_appointments DESC, name ASC
            ",
            $binds
        );
    }

    private function fetchAppointmentEstablishmentBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                e.id,
                e.name,
                COUNT(*) AS total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            WHERE $whereSql
            GROUP BY e.id, e.name
            ORDER BY total_appointments DESC, e.name ASC
            ",
            $binds
        );
    }

    private function fetchAppointmentProfessionalBreakdown(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                p.id,
                COALESCE(u.name, p.name, 'Sem profissional') AS name,
                COUNT(*) AS total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            LEFT JOIN professionals p ON p.id = a.professional_id
            LEFT JOIN users u ON u.id = p.user_id
            WHERE $whereSql
            GROUP BY p.id, u.name, p.name
            ORDER BY total_appointments DESC, name ASC
            ",
            $binds
        );
    }

    private function fetchAppointmentTableRows(string $whereSql, array $binds): array
    {
        return $this->db->query(
            "
            SELECT
                a.id,
                a.user_id,
                a.professional_id,
                a.service_id,
                a.status,
                a.start_at,
                a.end_at,
                a.created_at,
                b.name AS business_name,
                e.name AS establishment_name,
                COALESCE(u.name, CONCAT('Usuário #', a.user_id)) AS user_name,
                COALESCE(pu.name, p.name, 'Sem profissional') AS professional_name,
                COALESCE(s.name, CONCAT('Serviço #', a.service_id)) AS service_name,
                TIMESTAMPDIFF(MINUTE, a.start_at, a.end_at) AS duration_minutes
            FROM appointments a
            JOIN establishments e ON e.id = a.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN professionals p ON p.id = a.professional_id
            LEFT JOIN users pu ON pu.id = p.user_id
            LEFT JOIN services s ON s.id = a.service_id
            WHERE $whereSql
            ORDER BY a.start_at DESC, a.id DESC
            LIMIT 100
            ",
            $binds
        );
    }

    private function fetchBusinessOptions(array $user): array
    {
        if ($this->accessService->isAdmin($user)) {
            return $this->db->query("SELECT id, name FROM businesses ORDER BY name ASC");
        }

        if ($this->accessService->isClient($user)) {
            return $this->db->query(
                "
                SELECT DISTINCT b.id, b.name
                FROM businesses b
                JOIN establishments e ON e.business_id = b.id
                LEFT JOIN queues q ON q.establishment_id = e.id
                LEFT JOIN queue_entries qe ON qe.queue_id = q.id AND qe.user_id = ?
                LEFT JOIN appointments a ON a.establishment_id = e.id AND a.user_id = ?
                WHERE qe.id IS NOT NULL OR a.id IS NOT NULL
                ORDER BY b.name ASC
                ",
                [(int)$user['id'], (int)$user['id']]
            );
        }

        $establishmentIds = $this->accessService->getAccessibleEstablishmentIds($user);
        if (empty($establishmentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($establishmentIds), '?'));

        return $this->db->query(
            "
            SELECT DISTINCT b.id, b.name
            FROM businesses b
            JOIN establishments e ON e.business_id = b.id
            WHERE e.id IN ($placeholders)
            ORDER BY b.name ASC
            ",
            $establishmentIds
        );
    }

    private function fetchEstablishmentOptions(array $user): array
    {
        if ($this->accessService->isAdmin($user)) {
            return $this->db->query(
                "
                SELECT e.id, e.business_id, e.name, b.name AS business_name
                FROM establishments e
                LEFT JOIN businesses b ON b.id = e.business_id
                ORDER BY b.name ASC, e.name ASC
                "
            );
        }

        if ($this->accessService->isClient($user)) {
            return $this->db->query(
                "
                SELECT DISTINCT e.id, e.business_id, e.name, b.name AS business_name
                FROM establishments e
                LEFT JOIN businesses b ON b.id = e.business_id
                LEFT JOIN queues q ON q.establishment_id = e.id
                LEFT JOIN queue_entries qe ON qe.queue_id = q.id AND qe.user_id = ?
                LEFT JOIN appointments a ON a.establishment_id = e.id AND a.user_id = ?
                WHERE qe.id IS NOT NULL OR a.id IS NOT NULL
                ORDER BY b.name ASC, e.name ASC
                ",
                [(int)$user['id'], (int)$user['id']]
            );
        }

        return $this->accessService->getAccessibleEstablishments($user);
    }

    private function fetchQueueOptions(array $user): array
    {
        if ($this->accessService->isClient($user)) {
            return $this->db->query(
                "
                SELECT DISTINCT q.id, q.name, q.establishment_id, e.business_id,
                       e.name AS establishment_name, b.name AS business_name
                FROM queue_entries qe
                JOIN queues q ON q.id = qe.queue_id
                JOIN establishments e ON e.id = q.establishment_id
                LEFT JOIN businesses b ON b.id = e.business_id
                WHERE qe.user_id = ?
                ORDER BY b.name ASC, e.name ASC, q.name ASC
                ",
                [(int)$user['id']]
            );
        }

        return $this->accessService->getAccessibleQueues($user);
    }

    private function fetchQueueProfessionalOptions(array $user): array
    {
        if ($this->accessService->isProfessional($user)) {
            return [[
                'id' => (int)$user['id'],
                'name' => $user['name'] ?? 'Profissional',
                'email' => $user['email'] ?? null,
            ]];
        }

        $scope = $this->resolveScope($user);
        $where = [];
        $params = [];

        if ($scope['is_client']) {
            $where[] = 'qe.user_id = ?';
            $params[] = (int)$user['id'];
        } elseif (!empty($scope['establishment_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['establishment_ids']), '?'));
            $where[] = "q.establishment_id IN ($placeholders)";
            array_push($params, ...$scope['establishment_ids']);
        } elseif (!$scope['is_admin']) {
            return [];
        }

        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "
            SELECT DISTINCT pu.id, pu.name, pu.email
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN users pu ON pu.id = qe.professional_id
            $whereSql
            ORDER BY pu.name ASC
            ",
            $params
        );
    }

    private function fetchAppointmentProfessionalOptions(array $user): array
    {
        $scope = $this->resolveScope($user);

        if ($scope['is_professional'] && !empty($scope['professional_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['professional_ids']), '?'));

            return $this->db->query(
                "
                SELECT p.id, COALESCE(u.name, p.name) AS name, u.email
                FROM professionals p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE p.id IN ($placeholders)
                ORDER BY name ASC
                ",
                $scope['professional_ids']
            );
        }

        $where = [];
        $params = [];

        if ($scope['is_client']) {
            $where[] = 'a.user_id = ?';
            $params[] = (int)$user['id'];
        } elseif (!empty($scope['establishment_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['establishment_ids']), '?'));
            $where[] = "a.establishment_id IN ($placeholders)";
            array_push($params, ...$scope['establishment_ids']);
        } elseif (!$scope['is_admin']) {
            return [];
        }

        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "
            SELECT DISTINCT p.id, COALESCE(u.name, p.name) AS name, u.email
            FROM appointments a
            JOIN professionals p ON p.id = a.professional_id
            LEFT JOIN users u ON u.id = p.user_id
            $whereSql
            ORDER BY name ASC
            ",
            $params
        );
    }

    private function fetchServiceOptions(array $user): array
    {
        if ($this->accessService->isClient($user)) {
            return $this->db->query(
                "
                SELECT DISTINCT s.id, s.name, s.establishment_id
                FROM appointments a
                JOIN services s ON s.id = a.service_id
                WHERE a.user_id = ?
                ORDER BY s.name ASC
                ",
                [(int)$user['id']]
            );
        }

        if ($this->accessService->isAdmin($user)) {
            return $this->db->query(
                "
                SELECT id, name, establishment_id
                FROM services
                ORDER BY name ASC
                "
            );
        }

        $establishmentIds = $this->accessService->getAccessibleEstablishmentIds($user);
        if (empty($establishmentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($establishmentIds), '?'));

        return $this->db->query(
            "
            SELECT id, name, establishment_id
            FROM services
            WHERE establishment_id IN ($placeholders)
            ORDER BY name ASC
            ",
            $establishmentIds
        );
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
