<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\AppointmentRequest;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Notification;
use QueueMaster\Models\Professional;
use QueueMaster\Models\Service;
use QueueMaster\Models\User;
use QueueMaster\Utils\Logger;

class AppointmentRequestService
{
    private Database $db;
    private ContextAccessService $accessService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->accessService = new ContextAccessService();
    }

    public function list(array $user, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildListScope($user, $filters);

        $countRows = $this->db->query(
            "SELECT COUNT(*) AS total FROM appointment_requests ar WHERE $whereSql",
            $params
        );
        $total = (int)($countRows[0]['total'] ?? 0);

        $rows = $this->db->query(
            "
            SELECT
                ar.*,
                e.name AS establishment_name,
                s.name AS service_name,
                p.name AS professional_name,
                p.user_id AS professional_user_id,
                cu.name AS client_name,
                cu.email AS client_email,
                ru.name AS requested_by_name,
                ru.email AS requested_by_email,
                uu.name AS responded_by_name
            FROM appointment_requests ar
            INNER JOIN establishments e ON e.id = ar.establishment_id
            INNER JOIN services s ON s.id = ar.service_id
            LEFT JOIN professionals p ON p.id = ar.professional_id
            INNER JOIN users cu ON cu.id = ar.client_user_id
            INNER JOIN users ru ON ru.id = ar.requested_by_user_id
            LEFT JOIN users uu ON uu.id = ar.responded_by_user_id
            WHERE $whereSql
            ORDER BY
                CASE WHEN ar.status = 'pending' THEN 0 ELSE 1 END ASC,
                ar.proposed_start_at DESC,
                ar.id DESC
            LIMIT ? OFFSET ?
            ",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'requests' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'pending_count' => $this->countPendingForScope($user),
        ];
    }

    public function getRequest(int $requestId): ?array
    {
        $rows = $this->db->query(
            "
            SELECT
                ar.*,
                e.name AS establishment_name,
                s.name AS service_name,
                p.name AS professional_name,
                p.user_id AS professional_user_id,
                cu.name AS client_name,
                cu.email AS client_email,
                ru.name AS requested_by_name,
                ru.email AS requested_by_email,
                uu.name AS responded_by_name
            FROM appointment_requests ar
            INNER JOIN establishments e ON e.id = ar.establishment_id
            INNER JOIN services s ON s.id = ar.service_id
            LEFT JOIN professionals p ON p.id = ar.professional_id
            INNER JOIN users cu ON cu.id = ar.client_user_id
            INNER JOIN users ru ON ru.id = ar.requested_by_user_id
            LEFT JOIN users uu ON uu.id = ar.responded_by_user_id
            WHERE ar.id = ?
            LIMIT 1
            ",
            [$requestId]
        );

        return $rows[0] ?? null;
    }

    public function create(array $data, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        $actorRole = (string)($actor['role'] ?? 'client');

        if ($actorId <= 0) {
            throw new \RuntimeException('Unauthorized');
        }

        $establishmentId = (int)($data['establishment_id'] ?? 0);
        $serviceId = (int)($data['service_id'] ?? 0);
        $startAt = (string)($data['start_at'] ?? '');

        if ($establishmentId <= 0 || $serviceId <= 0 || $startAt === '') {
            throw new \InvalidArgumentException('Missing required request data');
        }

        $establishment = $this->resolveEstablishmentForActor($actor, $establishmentId, $actorRole === 'client');
        $service = $this->resolveServiceForEstablishment($serviceId, $establishmentId);

        $clientUserId = $actorRole === 'client'
            ? $actorId
            : (int)($data['client_user_id'] ?? 0);

        if ($clientUserId <= 0 || !User::find($clientUserId)) {
            throw new \InvalidArgumentException('Client user not found');
        }

        if ($actorRole === 'professional' && $clientUserId === $actorId) {
            throw new \InvalidArgumentException('Professional must create requests for a client');
        }

        $professionalId = $this->resolveRequestedProfessionalId($actor, $establishmentId, $data);
        $startTimestamp = strtotime($startAt);
        if ($startTimestamp === false) {
            throw new \InvalidArgumentException('Invalid start_at datetime');
        }

        $endAt = date('Y-m-d H:i:s', $startTimestamp + ((int)($service['duration_minutes'] ?? 30) * 60));
        $normalizedStartAt = date('Y-m-d H:i:s', $startTimestamp);
        $direction = $actorRole === 'client'
            ? AppointmentRequest::DIRECTION_CLIENT_TO_ESTABLISHMENT
            : AppointmentRequest::DIRECTION_STAFF_TO_CLIENT;

        if ($direction === AppointmentRequest::DIRECTION_STAFF_TO_CLIENT && $professionalId <= 0) {
            throw new \InvalidArgumentException('Professional assignment is required for requests sent to a client');
        }

        $existingPending = $this->db->query(
            "
            SELECT id
            FROM appointment_requests
            WHERE establishment_id = ?
              AND service_id = ?
              AND client_user_id = ?
              AND proposed_start_at = ?
              AND status = 'pending'
            LIMIT 1
            ",
            [$establishmentId, $serviceId, $clientUserId, $normalizedStartAt]
        );

        if (!empty($existingPending)) {
            throw new \RuntimeException('A pending appointment request already exists for this slot');
        }

        $requestId = AppointmentRequest::create([
            'establishment_id' => $establishmentId,
            'service_id' => $serviceId,
            'professional_id' => $professionalId > 0 ? $professionalId : null,
            'client_user_id' => $clientUserId,
            'requested_by_user_id' => $actorId,
            'direction' => $direction,
            'requester_role' => in_array($actorRole, ['client', 'professional', 'manager', 'admin'], true) ? $actorRole : 'client',
            'status' => AppointmentRequest::STATUS_PENDING,
            'proposed_start_at' => $normalizedStartAt,
            'proposed_end_at' => $endAt,
            'notes' => $this->normalizeText($data['notes'] ?? null),
        ]);

        $request = $this->getRequest($requestId);
        if (!$request) {
            throw new \RuntimeException('Failed to load appointment request');
        }

        $this->notifyCreation($request, $actorId);

        Logger::info('Appointment request created', [
            'request_id' => $requestId,
            'actor_id' => $actorId,
            'direction' => $direction,
            'establishment_id' => $establishmentId,
            'service_id' => $serviceId,
            'professional_id' => $professionalId,
            'client_user_id' => $clientUserId,
        ]);

        return $request;
    }

    public function accept(int $requestId, array $actor, array $data = []): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        $startedTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $lockedRows = $this->db->query(
                'SELECT * FROM appointment_requests WHERE id = ? FOR UPDATE',
                [$requestId]
            );
            $request = $lockedRows[0] ?? null;

            if (!$request) {
                throw new \RuntimeException('Appointment request not found');
            }

            if (($request['status'] ?? null) !== AppointmentRequest::STATUS_PENDING) {
                throw new \RuntimeException('Appointment request is no longer pending');
            }

            $this->assertCanRespond($request, $actor);

            $professionalId = $this->resolveProfessionalForAcceptance($request, $actor, $data);
            $decisionNote = $this->normalizeText($data['decision_note'] ?? null);

            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->create([
                'establishment_id' => (int)$request['establishment_id'],
                'professional_id' => $professionalId,
                'service_id' => (int)$request['service_id'],
                'user_id' => (int)$request['client_user_id'],
                'start_at' => (string)$request['proposed_start_at'],
            ]);

            AppointmentRequest::update($requestId, [
                'professional_id' => $professionalId,
                'status' => AppointmentRequest::STATUS_ACCEPTED,
                'decision_note' => $decisionNote,
                'responded_by_user_id' => $actorId,
                'responded_at' => date('Y-m-d H:i:s'),
            ]);

            $updatedRequest = $this->getRequest($requestId);
            if ($updatedRequest) {
                $this->notifyDecision($updatedRequest, $actorId, true, $appointment['id'] ?? null);
                $this->notifyAssignedProfessional($updatedRequest, $appointment, $actorId);
            }

            if ($startedTransaction) {
                $this->db->commit();
            }

            return [
                'request' => $updatedRequest,
                'appointment' => $appointment,
            ];
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }

    public function reject(int $requestId, array $actor, ?string $decisionNote = null): array
    {
        $request = AppointmentRequest::find($requestId);
        if (!$request) {
            throw new \RuntimeException('Appointment request not found');
        }

        if (($request['status'] ?? null) !== AppointmentRequest::STATUS_PENDING) {
            throw new \RuntimeException('Appointment request is no longer pending');
        }

        $this->assertCanRespond($request, $actor);

        $actorId = (int)($actor['id'] ?? 0);
        AppointmentRequest::update($requestId, [
            'status' => AppointmentRequest::STATUS_REJECTED,
            'decision_note' => $this->normalizeText($decisionNote),
            'responded_by_user_id' => $actorId,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);

        $updatedRequest = $this->getRequest($requestId);
        if ($updatedRequest) {
            $this->notifyDecision($updatedRequest, $actorId, false, null);
        }

        return $updatedRequest ?? [];
    }

    public function cancel(int $requestId, array $actor): array
    {
        $request = AppointmentRequest::find($requestId);
        if (!$request) {
            throw new \RuntimeException('Appointment request not found');
        }

        if (($request['status'] ?? null) !== AppointmentRequest::STATUS_PENDING) {
            throw new \RuntimeException('Only pending requests can be cancelled');
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ((int)($request['requested_by_user_id'] ?? 0) !== $actorId) {
            throw new \RuntimeException('Only the sender can cancel this appointment request');
        }

        AppointmentRequest::update($requestId, [
            'status' => AppointmentRequest::STATUS_CANCELLED,
            'responded_by_user_id' => $actorId,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->getRequest($requestId) ?? [];
    }

    private function buildListScope(array $user, array $filters): array
    {
        $where = [];
        $params = [];
        $userId = (int)($user['id'] ?? 0);

        if ($this->accessService->isAdmin($user)) {
            $where[] = '1 = 1';
        } elseif ($this->accessService->isClient($user)) {
            $where[] = 'ar.client_user_id = ?';
            $params[] = $userId;
        } else {
            $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($user);
            if (empty($accessibleEstablishmentIds)) {
                return ['1 = 0', []];
            }

            $placeholders = implode(',', array_fill(0, count($accessibleEstablishmentIds), '?'));
            $where[] = "ar.establishment_id IN ($placeholders)";
            array_push($params, ...$accessibleEstablishmentIds);
        }

        if (!empty($filters['establishment_id'])) {
            $where[] = 'ar.establishment_id = ?';
            $params[] = (int)$filters['establishment_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'ar.status = ?';
            $params[] = (string)$filters['status'];
        }

        if (!empty($filters['direction'])) {
            $where[] = 'ar.direction = ?';
            $params[] = (string)$filters['direction'];
        }

        if (!empty($filters['date'])) {
            $where[] = 'DATE(ar.proposed_start_at) = ?';
            $params[] = (string)$filters['date'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function countPendingForScope(array $user): int
    {
        [$whereSql, $params] = $this->buildListScope($user, ['status' => AppointmentRequest::STATUS_PENDING]);
        $rows = $this->db->query(
            "SELECT COUNT(*) AS total FROM appointment_requests ar WHERE $whereSql",
            $params
        );

        return (int)($rows[0]['total'] ?? 0);
    }

    private function resolveEstablishmentForActor(array $actor, int $establishmentId, bool $allowDiscovery): array
    {
        $establishment = Establishment::find($establishmentId);
        if (!$establishment) {
            throw new \RuntimeException('Establishment not found');
        }

        if ($allowDiscovery) {
            if (empty($establishment['is_active'])) {
                throw new \RuntimeException('Establishment not available for scheduling');
            }
            return $establishment;
        }

        $this->accessService->requireEstablishmentAccess(
            $actor,
            $establishment,
            'Voce nao tem acesso a este estabelecimento'
        );

        return $establishment;
    }

    private function resolveServiceForEstablishment(int $serviceId, int $establishmentId): array
    {
        $service = Service::find($serviceId);
        if (!$service || (int)($service['establishment_id'] ?? 0) !== $establishmentId) {
            throw new \RuntimeException('Service not found for this establishment');
        }

        if (isset($service['is_active']) && !(bool)$service['is_active']) {
            throw new \RuntimeException('Service is inactive');
        }

        return $service;
    }

    private function resolveRequestedProfessionalId(array $actor, int $establishmentId, array $data): int
    {
        $actorRole = (string)($actor['role'] ?? 'client');
        $requestedProfessionalId = (int)($data['professional_id'] ?? 0);

        if ($actorRole === 'client') {
            return $requestedProfessionalId > 0 ? $this->validateProfessionalInEstablishment($requestedProfessionalId, $establishmentId) : 0;
        }

        if ($actorRole === 'professional') {
            $ownProfessionalId = $this->resolveActorProfessionalId((int)$actor['id'], $establishmentId);
            if ($ownProfessionalId <= 0) {
                throw new \RuntimeException('Seu usuario nao esta vinculado a um profissional deste estabelecimento');
            }
            if ($requestedProfessionalId > 0 && $requestedProfessionalId !== $ownProfessionalId) {
                throw new \RuntimeException('O profissional so pode se alocar a si mesmo');
            }

            return $ownProfessionalId;
        }

        if ($requestedProfessionalId > 0) {
            return $this->validateProfessionalInEstablishment($requestedProfessionalId, $establishmentId);
        }

        return 0;
    }

    private function resolveProfessionalForAcceptance(array $request, array $actor, array $data): int
    {
        $establishmentId = (int)($request['establishment_id'] ?? 0);
        $existingProfessionalId = (int)($request['professional_id'] ?? 0);
        $requestedProfessionalId = (int)($data['professional_id'] ?? 0);
        $actorRole = (string)($actor['role'] ?? 'client');

        if (($request['direction'] ?? null) === AppointmentRequest::DIRECTION_STAFF_TO_CLIENT) {
            $professionalId = $existingProfessionalId > 0 ? $existingProfessionalId : $requestedProfessionalId;
            if ($professionalId <= 0) {
                throw new \RuntimeException('Professional assignment is required');
            }
            return $this->validateProfessionalInEstablishment($professionalId, $establishmentId);
        }

        if ($actorRole === 'professional') {
            $ownProfessionalId = $this->resolveActorProfessionalId((int)$actor['id'], $establishmentId);
            if ($ownProfessionalId <= 0) {
                throw new \RuntimeException('Seu usuario nao esta vinculado a um profissional deste estabelecimento');
            }
            if ($requestedProfessionalId > 0 && $requestedProfessionalId !== $ownProfessionalId) {
                throw new \RuntimeException('O profissional so pode se alocar a si mesmo');
            }
            if ($existingProfessionalId > 0 && $existingProfessionalId !== $ownProfessionalId) {
                throw new \RuntimeException('Esta solicitacao esta atribuida a outro profissional');
            }

            return $ownProfessionalId;
        }

        $professionalId = $requestedProfessionalId > 0 ? $requestedProfessionalId : $existingProfessionalId;
        if ($professionalId <= 0) {
            throw new \RuntimeException('Selecione o profissional que atendera este agendamento');
        }

        return $this->validateProfessionalInEstablishment($professionalId, $establishmentId);
    }

    private function validateProfessionalInEstablishment(int $professionalId, int $establishmentId): int
    {
        $professional = Professional::find($professionalId);
        if (!$professional || (int)($professional['establishment_id'] ?? 0) !== $establishmentId) {
            throw new \RuntimeException('Professional not found for this establishment');
        }

        if (isset($professional['is_active']) && !(bool)$professional['is_active']) {
            throw new \RuntimeException('Selected professional is inactive');
        }

        return $professionalId;
    }

    private function resolveActorProfessionalId(int $actorId, int $establishmentId): int
    {
        $rows = Professional::all([
            'user_id' => $actorId,
            'establishment_id' => $establishmentId,
        ]);

        if (empty($rows)) {
            return 0;
        }

        return (int)($rows[0]['id'] ?? 0);
    }

    private function assertCanRespond(array $request, array $actor): void
    {
        $actorId = (int)($actor['id'] ?? 0);
        $direction = (string)($request['direction'] ?? '');

        if ($direction === AppointmentRequest::DIRECTION_STAFF_TO_CLIENT) {
            if ((int)($request['client_user_id'] ?? 0) !== $actorId) {
                throw new \RuntimeException('Only the client can respond to this appointment request');
            }
            return;
        }

        $establishment = Establishment::find((int)($request['establishment_id'] ?? 0));
        if (!$establishment) {
            throw new \RuntimeException('Establishment not found');
        }

        $this->accessService->requireEstablishmentAccess(
            $actor,
            $establishment,
            'Voce nao pode responder a esta solicitacao de agendamento'
        );
    }

    private function notifyCreation(array $request, int $actorId): void
    {
        $requestId = (int)($request['id'] ?? 0);
        $establishmentName = (string)($request['establishment_name'] ?? 'o estabelecimento');
        $serviceName = (string)($request['service_name'] ?? 'o servico');
        $startAt = $this->formatDateTimeLabel((string)($request['proposed_start_at'] ?? ''));
        $deepLink = '/app/appointments?tab=requests';

        Notification::create([
            'user_id' => $actorId,
            'type' => 'appointment_request_created',
            'title' => 'Solicitacao de agendamento criada',
            'body' => 'Sua solicitacao para ' . $serviceName . ' em ' . $establishmentName . ' foi registrada.',
            'data' => [
                'appointment_request_id' => $requestId,
                'deep_link' => $deepLink,
                'proposed_start_at' => $request['proposed_start_at'] ?? null,
            ],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        if (($request['direction'] ?? null) === AppointmentRequest::DIRECTION_CLIENT_TO_ESTABLISHMENT) {
            $recipientIds = $this->resolveEstablishmentRecipientIds(
                (int)($request['establishment_id'] ?? 0),
                !empty($request['professional_id']) ? (int)$request['professional_id'] : null,
                $actorId
            );

            foreach ($recipientIds as $recipientId) {
                Notification::create([
                    'user_id' => $recipientId,
                    'type' => 'appointment_request_received',
                    'title' => 'Nova solicitacao de agendamento',
                    'body' => ($request['client_name'] ?? 'Um cliente') . ' solicitou ' . $serviceName . ' para ' . $startAt . '.',
                    'data' => [
                        'appointment_request_id' => $requestId,
                        'deep_link' => $deepLink,
                        'direction' => $request['direction'] ?? null,
                        'proposed_start_at' => $request['proposed_start_at'] ?? null,
                    ],
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return;
        }

        Notification::create([
            'user_id' => (int)($request['client_user_id'] ?? 0),
            'type' => 'appointment_request_received',
            'title' => 'Nova proposta de agendamento',
            'body' => ($request['requested_by_name'] ?? 'A equipe') . ' propôs ' . $serviceName . ' para ' . $startAt . '.',
            'data' => [
                'appointment_request_id' => $requestId,
                'deep_link' => $deepLink,
                'direction' => $request['direction'] ?? null,
                'proposed_start_at' => $request['proposed_start_at'] ?? null,
            ],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function notifyDecision(array $request, int $actorId, bool $accepted, ?int $appointmentId): void
    {
        $targetUserId = (int)($request['requested_by_user_id'] ?? 0) === $actorId
            ? (int)($request['client_user_id'] ?? 0)
            : (int)($request['requested_by_user_id'] ?? 0);

        if ($targetUserId <= 0) {
            return;
        }

        $actor = User::find($actorId);
        $actorName = $actor['name'] ?? 'Uma pessoa';
        $deepLink = $appointmentId
            ? '/app/appointments/' . $appointmentId
            : '/app/appointments?tab=requests';

        Notification::create([
            'user_id' => $targetUserId,
            'type' => $accepted ? 'appointment_request_accepted' : 'appointment_request_rejected',
            'title' => $accepted ? 'Solicitacao de agendamento aceita' : 'Solicitacao de agendamento recusada',
            'body' => $accepted
                ? $actorName . ' aceitou a solicitacao de agendamento.'
                : $actorName . ' recusou a solicitacao de agendamento.',
            'data' => [
                'appointment_request_id' => (int)($request['id'] ?? 0),
                'appointment_id' => $appointmentId,
                'deep_link' => $deepLink,
            ],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function notifyAssignedProfessional(array $request, array $appointment, int $actorId): void
    {
        $professionalUserId = (int)($request['professional_user_id'] ?? 0);
        if (
            $professionalUserId <= 0
            || $professionalUserId === $actorId
            || $professionalUserId === (int)($request['requested_by_user_id'] ?? 0)
        ) {
            return;
        }

        $appointmentId = (int)($appointment['id'] ?? 0);
        Notification::create([
            'user_id' => $professionalUserId,
            'type' => 'appointment_created',
            'title' => 'Novo agendamento confirmado',
            'body' => ($request['client_name'] ?? 'Um cliente') . ' foi confirmado para este horario.',
            'data' => [
                'appointment_request_id' => (int)($request['id'] ?? 0),
                'appointment_id' => $appointmentId,
                'deep_link' => $appointmentId > 0
                    ? '/app/appointments/' . $appointmentId
                    : '/app/appointments?tab=requests',
            ],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveEstablishmentRecipientIds(int $establishmentId, ?int $professionalId, int $excludeUserId): array
    {
        $establishment = Establishment::find($establishmentId);
        if (!$establishment) {
            return [];
        }

        $businessId = (int)($establishment['business_id'] ?? 0);
        $params = [$establishmentId];
        $sql = "
            SELECT DISTINCT user_id
            FROM establishment_users
            WHERE establishment_id = ?
              AND role IN ('owner', 'manager')
        ";

        $recipientIds = array_map(
            static fn(array $row): int => (int)$row['user_id'],
            $this->db->query($sql, $params)
        );

        if (!empty($establishment['owner_id'])) {
            $recipientIds[] = (int)$establishment['owner_id'];
        }

        if ($businessId > 0) {
            $businessRows = $this->db->query(
                "
                SELECT DISTINCT user_id
                FROM business_users
                WHERE business_id = ?
                  AND role IN ('owner', 'manager')
                ",
                [$businessId]
            );
            foreach ($businessRows as $row) {
                $recipientIds[] = (int)$row['user_id'];
            }
        }

        if (!empty($professionalId)) {
            $professional = Professional::find($professionalId);
            if (!empty($professional['user_id'])) {
                $recipientIds[] = (int)$professional['user_id'];
            }
        }

        $recipientIds = array_values(array_unique(array_filter(
            array_map('intval', $recipientIds),
            static fn(int $userId): bool => $userId > 0 && $userId !== $excludeUserId
        )));

        return $recipientIds;
    }

    private function formatDateTimeLabel(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 'um horario';
        }

        return date('d/m/Y H:i', $timestamp);
    }

    private function normalizeText(mixed $value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }
}
