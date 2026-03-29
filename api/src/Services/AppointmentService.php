<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\Professional;
use QueueMaster\Models\Service;
use QueueMaster\Utils\Logger;

class AppointmentService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): array
    {
        $establishmentId = (int)($data['establishment_id'] ?? 0);
        $professionalId = (int)($data['professional_id'] ?? 0);
        $serviceId = (int)($data['service_id'] ?? 0);
        $userId = (int)($data['user_id'] ?? 0);
        $startAt = (string)($data['start_at'] ?? '');
        $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;

        if ($establishmentId <= 0 || $professionalId <= 0 || $serviceId <= 0 || $userId <= 0 || $startAt === '') {
            throw new \InvalidArgumentException('Missing required appointment data');
        }

        $startTimestamp = strtotime($startAt);
        if ($startTimestamp === false) {
            throw new \Exception('Invalid start_at datetime');
        }

        $normalizedStartAt = date('Y-m-d H:i:s', $startTimestamp);

        $startedTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $service = $this->resolveService($serviceId, $establishmentId);
            $this->resolveProfessional($professionalId, $establishmentId);

            $durationMinutes = (int)($service['duration_minutes'] ?? 30);
            $endAt = date('Y-m-d H:i:s', $startTimestamp + ($durationMinutes * 60));

            $conflicts = $this->findConflicts($professionalId, $normalizedStartAt, $endAt);
            if (!empty($conflicts)) {
                throw new \Exception('Time slot conflict - appointment already exists');
            }

            $insertSql = "
                INSERT INTO appointments
                (establishment_id, professional_id, service_id, user_id, start_at, end_at, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'booked', ?, NOW())
            ";

            $this->db->execute($insertSql, [
                $establishmentId,
                $professionalId,
                $serviceId,
                $userId,
                $normalizedStartAt,
                $endAt,
                $notes !== '' ? $notes : null,
            ]);

            $appointmentId = (int)$this->db->lastInsertId();
            if ($startedTransaction) {
                $this->db->commit();
            }

            $appointment = $this->getAppointment($appointmentId);
            if (!$appointment) {
                throw new \RuntimeException('Created appointment could not be loaded');
            }

            Logger::info('Appointment created', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'professional_id' => $professionalId,
                'service_id' => $serviceId,
                'start_at' => $normalizedStartAt,
            ]);

            return $appointment;
        } catch (\Exception $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            Logger::error('Appointment creation failed', [
                'user_id' => $userId,
                'professional_id' => $professionalId,
                'service_id' => $serviceId,
                'start_at' => $startAt,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function update(int $appointmentId, array $data, int $actorId): array
    {
        $appointment = $this->getAppointmentRaw($appointmentId);
        if (!$appointment) {
            throw new \RuntimeException('Appointment not found');
        }

        $currentStatus = (string)($appointment['status'] ?? 'booked');
        $updateData = [];

        try {
            $this->db->beginTransaction();

            $serviceId = isset($data['service_id']) ? (int)$data['service_id'] : (int)$appointment['service_id'];
            $professionalId = isset($data['professional_id']) ? (int)$data['professional_id'] : (int)$appointment['professional_id'];
            $startAt = isset($data['start_at']) ? (string)$data['start_at'] : (string)$appointment['start_at'];

            $startTimestamp = strtotime($startAt);
            if ($startTimestamp === false) {
                throw new \Exception('Invalid start_at datetime');
            }

            $normalizedStartAt = date('Y-m-d H:i:s', $startTimestamp);
            $service = $this->resolveService($serviceId, (int)$appointment['establishment_id']);
            $this->resolveProfessional($professionalId, (int)$appointment['establishment_id']);

            $durationMinutes = (int)($service['duration_minutes'] ?? 30);
            $endAt = date('Y-m-d H:i:s', $startTimestamp + ($durationMinutes * 60));

            if (
                $normalizedStartAt !== (string)$appointment['start_at']
                || $endAt !== (string)$appointment['end_at']
                || $serviceId !== (int)$appointment['service_id']
                || $professionalId !== (int)$appointment['professional_id']
            ) {
                $conflicts = $this->findConflicts($professionalId, $normalizedStartAt, $endAt, $appointmentId);
                if (!empty($conflicts)) {
                    throw new \Exception('Time slot conflict - appointment already exists');
                }

                $updateData['start_at'] = $normalizedStartAt;
                $updateData['end_at'] = $endAt;
                $updateData['service_id'] = $serviceId;
                $updateData['professional_id'] = $professionalId;
            }

            if (isset($data['status'])) {
                $nextStatus = (string)$data['status'];
                if ($nextStatus !== $currentStatus) {
                    $this->assertStatusTransitionAllowed($currentStatus, $nextStatus);
                    $updateData['status'] = $nextStatus;
                }
            }

            if (empty($updateData)) {
                $this->db->rollback();
                return $this->getAppointment($appointmentId) ?? $appointment;
            }

            $setSql = [];
            $params = [];
            foreach ($updateData as $column => $value) {
                $setSql[] = $column . ' = ?';
                $params[] = $value;
            }
            $params[] = $appointmentId;

            $this->db->execute(
                'UPDATE appointments SET ' . implode(', ', $setSql) . ' WHERE id = ?',
                $params
            );

            $this->db->commit();

            Logger::info('Appointment updated', [
                'appointment_id' => $appointmentId,
                'actor_id' => $actorId,
                'updated_fields' => array_keys($updateData),
            ]);

            return $this->getAppointment($appointmentId) ?? [];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            Logger::error('Appointment update failed', [
                'appointment_id' => $appointmentId,
                'actor_id' => $actorId,
                'payload' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function checkIn(int $appointmentId, int $userId): array
    {
        $appointment = $this->getAppointmentRaw($appointmentId);
        if (!$appointment) {
            throw new \Exception('Appointment not found');
        }

        if ((int)$appointment['user_id'] !== $userId) {
            throw new \Exception('Unauthorized');
        }

        if (($appointment['status'] ?? null) !== 'booked') {
            throw new \Exception('Cannot check-in appointment in current status');
        }

        $graceBefore = (int)($_ENV['QUEUE_GRACE_BEFORE_MINUTES'] ?? 10);
        $graceAfter = (int)($_ENV['QUEUE_GRACE_AFTER_MINUTES'] ?? 15);

        $startTimestamp = strtotime((string)$appointment['start_at']);
        $now = time();
        $windowStart = $startTimestamp - ($graceBefore * 60);
        $windowEnd = $startTimestamp + ($graceAfter * 60);

        if ($now < $windowStart) {
            throw new \Exception('Too early to check-in');
        }

        if ($now > $windowEnd) {
            $this->markNoShow($appointmentId);
            throw new \Exception('Check-in window has passed - marked as no-show');
        }

        $this->db->execute(
            "UPDATE appointments SET status = 'checked_in', checkin_at = NOW() WHERE id = ?",
            [$appointmentId]
        );

        Logger::info('Appointment checked-in', [
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
        ]);

        return $this->getAppointment($appointmentId) ?? [];
    }

    public function cancel(int $appointmentId, int $userId, bool $allowPrivileged = false): bool
    {
        $appointment = $this->getAppointmentRaw($appointmentId);
        if (!$appointment) {
            throw new \Exception('Appointment not found');
        }

        if (!$allowPrivileged && (int)$appointment['user_id'] !== $userId) {
            throw new \Exception('Unauthorized');
        }

        if (!in_array($appointment['status'], ['booked', 'checked_in'], true)) {
            throw new \Exception('Cannot cancel appointment in current status');
        }

        $this->db->execute(
            "UPDATE appointments SET status = 'cancelled' WHERE id = ?",
            [$appointmentId]
        );

        Logger::info('Appointment cancelled', [
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
        ]);

        return true;
    }

    public function complete(int $appointmentId): array
    {
        $appointment = $this->getAppointmentRaw($appointmentId);
        if (!$appointment) {
            throw new \Exception('Appointment not found');
        }

        if (!in_array($appointment['status'], ['checked_in', 'in_progress'], true)) {
            throw new \Exception('Cannot complete appointment in current status');
        }

        $this->markCompleted($appointmentId);
        return $this->getAppointment($appointmentId) ?? [];
    }

    public function noShow(int $appointmentId): array
    {
        $appointment = $this->getAppointmentRaw($appointmentId);
        if (!$appointment) {
            throw new \Exception('Appointment not found');
        }

        if (!in_array($appointment['status'], ['booked', 'checked_in'], true)) {
            throw new \Exception('Cannot mark no-show appointment in current status');
        }

        $this->markNoShow($appointmentId);
        return $this->getAppointment($appointmentId) ?? [];
    }

    public function markNoShow(int $appointmentId): bool
    {
        $this->db->execute("UPDATE appointments SET status = 'no_show' WHERE id = ?", [$appointmentId]);
        Logger::info('Appointment marked as no-show', ['appointment_id' => $appointmentId]);
        return true;
    }

    public function markCompleted(int $appointmentId): bool
    {
        $this->db->execute("UPDATE appointments SET status = 'completed' WHERE id = ?", [$appointmentId]);
        Logger::info('Appointment marked as completed', ['appointment_id' => $appointmentId]);
        return true;
    }

    public function getAppointment(int $appointmentId): ?array
    {
        $rows = $this->db->query(
            "
            SELECT
                a.*,
                u.name AS user_name,
                u.email AS user_email,
                p.name AS professional_name,
                p.user_id AS professional_user_id,
                p.specialty AS specialization,
                s.name AS service_name,
                e.name AS establishment_name
            FROM appointments a
            INNER JOIN users u ON u.id = a.user_id
            INNER JOIN professionals p ON p.id = a.professional_id
            INNER JOIN services s ON s.id = a.service_id
            INNER JOIN establishments e ON e.id = a.establishment_id
            WHERE a.id = ?
            LIMIT 1
            ",
            [$appointmentId]
        );

        return $rows[0] ?? null;
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['professional_id'])) {
            $where[] = 'a.professional_id = ?';
            $params[] = (int)$filters['professional_id'];
        }

        if (!empty($filters['professional_user_id'])) {
            $where[] = 'p.user_id = ?';
            $params[] = (int)$filters['professional_user_id'];
        }

        if (!empty($filters['establishment_id'])) {
            $where[] = 'a.establishment_id = ?';
            $params[] = (int)$filters['establishment_id'];
        }

        if (!empty($filters['establishment_ids']) && is_array($filters['establishment_ids'])) {
            $establishmentIds = array_values(array_filter(
                array_map('intval', $filters['establishment_ids']),
                static fn(int $id): bool => $id > 0
            ));

            if (empty($establishmentIds)) {
                $where[] = '1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($establishmentIds), '?'));
                $where[] = "a.establishment_id IN ($placeholders)";
                array_push($params, ...$establishmentIds);
            }
        }

        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = (string)$filters['status'];
        }

        if (!empty($filters['bucket'])) {
            if ($filters['bucket'] === 'scheduled') {
                $where[] = "a.status IN ('booked', 'confirmed', 'checked_in', 'in_progress')";
            } elseif ($filters['bucket'] === 'completed') {
                $where[] = "a.status IN ('completed', 'no_show', 'cancelled')";
            }
        }

        if (!empty($filters['date'])) {
            $where[] = 'DATE(a.start_at) = ?';
            $params[] = (string)$filters['date'];
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countRows = $this->db->query(
            "SELECT COUNT(*) AS total FROM appointments a INNER JOIN professionals p ON p.id = a.professional_id $whereSql",
            $params
        );
        $total = (int)($countRows[0]['total'] ?? 0);

        $rows = $this->db->query(
            "
            SELECT
                a.*,
                u.name AS user_name,
                u.email AS user_email,
                p.name AS professional_name,
                p.user_id AS professional_user_id,
                p.specialty AS specialization,
                s.name AS service_name,
                e.name AS establishment_name
            FROM appointments a
            INNER JOIN users u ON u.id = a.user_id
            INNER JOIN professionals p ON p.id = a.professional_id
            INNER JOIN services s ON s.id = a.service_id
            INNER JOIN establishments e ON e.id = a.establishment_id
            $whereSql
            ORDER BY a.start_at DESC, a.id DESC
            LIMIT ? OFFSET ?
            ",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'appointments' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getAvailableSlots(int $professionalId, int $serviceId, string $date): array
    {
        $service = Service::find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        $durationMinutes = (int)($service['duration_minutes'] ?? 30);
        $startHour = 9;
        $endHour = 18;

        $appointments = $this->db->query(
            "
            SELECT start_at, end_at
            FROM appointments
            WHERE professional_id = ?
              AND DATE(start_at) = ?
              AND status NOT IN ('cancelled', 'no_show', 'completed')
            ORDER BY start_at ASC
            ",
            [$professionalId, $date]
        );

        $slots = [];
        $currentTime = strtotime("$date $startHour:00:00");
        $endTime = strtotime("$date $endHour:00:00");

        while ($currentTime < $endTime) {
            $slotEnd = $currentTime + ($durationMinutes * 60);
            $hasConflict = false;

            foreach ($appointments as $appointment) {
                $appointmentStart = strtotime((string)$appointment['start_at']);
                $appointmentEnd = strtotime((string)$appointment['end_at']);

                if ($currentTime < $appointmentEnd && $slotEnd > $appointmentStart) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                $slots[] = [
                    'start_at' => date('Y-m-d H:i:s', $currentTime),
                    'end_at' => date('Y-m-d H:i:s', $slotEnd),
                ];
            }

            $currentTime += ($durationMinutes * 60);
        }

        return $slots;
    }

    private function getAppointmentRaw(int $appointmentId): ?array
    {
        $rows = $this->db->query(
            "SELECT * FROM appointments WHERE id = ? LIMIT 1",
            [$appointmentId]
        );

        return $rows[0] ?? null;
    }

    private function resolveService(int $serviceId, int $establishmentId): array
    {
        $service = Service::find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        if ((int)($service['establishment_id'] ?? 0) !== $establishmentId) {
            throw new \Exception('Invalid service for establishment');
        }

        return $service;
    }

    private function resolveProfessional(int $professionalId, int $establishmentId): array
    {
        $professional = Professional::find($professionalId);
        if (!$professional) {
            throw new \Exception('Professional not found');
        }

        if ((int)($professional['establishment_id'] ?? 0) !== $establishmentId) {
            throw new \Exception('Invalid professional for establishment');
        }

        if (isset($professional['is_active']) && !(bool)$professional['is_active']) {
            throw new \Exception('Professional is inactive');
        }

        return $professional;
    }

    private function findConflicts(int $professionalId, string $startAt, string $endAt, ?int $excludeAppointmentId = null): array
    {
        $sql = "
            SELECT id
            FROM appointments
            WHERE professional_id = ?
              AND status NOT IN ('cancelled', 'no_show', 'completed')
              AND start_at < ?
              AND end_at > ?
        ";
        $params = [$professionalId, $endAt, $startAt];

        if ($excludeAppointmentId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeAppointmentId;
        }

        $sql .= ' FOR UPDATE';

        return $this->db->query($sql, $params);
    }

    private function assertStatusTransitionAllowed(string $currentStatus, string $nextStatus): void
    {
        $allowedTransitions = [
            'booked' => ['confirmed'],
            'confirmed' => ['checked_in'],
            'checked_in' => ['in_progress'],
            'in_progress' => ['completed'],
        ];

        $allowed = $allowedTransitions[$currentStatus] ?? [];
        if (!in_array($nextStatus, $allowed, true)) {
            throw new \Exception('Cannot update appointment to the requested status');
        }
    }
}
