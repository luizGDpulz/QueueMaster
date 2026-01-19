<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * AppointmentService - Appointment Management with Conflict Detection
 * 
 * Implements transaction-safe appointment creation with conflict checking:
 * - Prevents double-booking same professional/time slot
 * - Validates business logic (times, dates)
 * - Handles check-in and status updates
 * 
 * CONCURRENCY STRATEGY:
 * Uses SELECT ... FOR UPDATE on appointments to lock overlapping time slots
 * during creation, preventing race conditions.
 */
class AppointmentService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create appointment with conflict checking
     * 
     * Transaction-safe implementation:
     * 1. Start transaction
     * 2. Check for overlapping appointments (FOR UPDATE)
     * 3. If no conflict, insert appointment
     * 4. Commit transaction
     * 
     * @param array $data Appointment data
     * @return array Created appointment
     * @throws \Exception
     */
    public function create(array $data): array
    {
        $establishmentId = (int)$data['establishment_id'];
        $professionalId = (int)$data['professional_id'];
        $serviceId = (int)$data['service_id'];
        $userId = (int)$data['user_id'];
        $startAt = $data['start_at']; // Expected format: Y-m-d H:i:s or ISO8601

        try {
            $this->db->beginTransaction();

            // Validate start time
            $startTimestamp = strtotime($startAt);
            if ($startTimestamp === false) {
                throw new \Exception('Invalid start_at datetime');
            }

            // Get service duration
            $serviceSql = "SELECT duration_minutes FROM services WHERE id = ? LIMIT 1";
            $services = $this->db->query($serviceSql, [$serviceId]);
            
            if (empty($services)) {
                throw new \Exception('Service not found');
            }

            $durationMinutes = (int)$services[0]['duration_minutes'];

            // Calculate end time
            $endTimestamp = $startTimestamp + ($durationMinutes * 60);
            $endAt = date('Y-m-d H:i:s', $endTimestamp);

            // Check for overlapping appointments (lock rows with FOR UPDATE)
            // An appointment overlaps if:
            // - Same professional
            // - NOT cancelled/no_show
            // - Time ranges overlap: (start1 < end2) AND (end1 > start2)
            // Standard overlap formula: New [startAt, endAt] overlaps existing [start_at, end_at]
            $conflictSql = "
                SELECT id FROM appointments
                WHERE professional_id = ?
                  AND status NOT IN ('cancelled', 'no_show')
                  AND start_at < ?
                  AND end_at > ?
                FOR UPDATE
            ";
            
            $conflicts = $this->db->query($conflictSql, [
                $professionalId,
                $endAt,      // Existing start_at < new endAt
                $startAt     // Existing end_at > new startAt
            ]);

            if (!empty($conflicts)) {
                throw new \Exception('Time slot conflict - appointment already exists');
            }

            // Insert appointment
            $insertSql = "
                INSERT INTO appointments 
                (establishment_id, professional_id, service_id, user_id, start_at, end_at, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'booked', NOW())
            ";
            
            $this->db->execute($insertSql, [
                $establishmentId,
                $professionalId,
                $serviceId,
                $userId,
                $startAt,
                $endAt
            ]);

            $appointmentId = (int)$this->db->lastInsertId();

            $this->db->commit();

            // Fetch created appointment
            $appointment = $this->getAppointment($appointmentId);

            Logger::info('Appointment created', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'professional_id' => $professionalId,
                'start_at' => $startAt,
            ]);

            return $appointment;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            Logger::error('Appointment creation failed', [
                'user_id' => $data['user_id'] ?? null,
                'professional_id' => $data['professional_id'] ?? null,
                'start_at' => $data['start_at'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check-in appointment
     */
    public function checkIn(int $appointmentId, int $userId): array
    {
        try {
            $appointment = $this->getAppointment($appointmentId);

            if (!$appointment) {
                throw new \Exception('Appointment not found');
            }

            // Verify ownership
            if ($appointment['user_id'] != $userId) {
                throw new \Exception('Unauthorized');
            }

            // Verify status
            if ($appointment['status'] !== 'booked') {
                throw new \Exception('Cannot check-in appointment in current status');
            }

            // Check if within grace window
            $graceBefore = (int)($_ENV['QUEUE_GRACE_BEFORE_MINUTES'] ?? 10);
            $graceAfter = (int)($_ENV['QUEUE_GRACE_AFTER_MINUTES'] ?? 15);
            
            $startTimestamp = strtotime($appointment['start_at']);
            $now = time();
            $windowStart = $startTimestamp - ($graceBefore * 60);
            $windowEnd = $startTimestamp + ($graceAfter * 60);

            if ($now < $windowStart) {
                throw new \Exception('Too early to check-in');
            }

            if ($now > $windowEnd) {
                // Mark as no-show
                $this->markNoShow($appointmentId);
                throw new \Exception('Check-in window has passed - marked as no-show');
            }

            // Update status
            $sql = "UPDATE appointments SET status = 'checked_in', checkin_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$appointmentId]);

            Logger::info('Appointment checked-in', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
            ]);

            return $this->getAppointment($appointmentId);

        } catch (\Exception $e) {
            Logger::error('Check-in failed', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel appointment
     */
    public function cancel(int $appointmentId, int $userId): bool
    {
        try {
            $appointment = $this->getAppointment($appointmentId);

            if (!$appointment) {
                throw new \Exception('Appointment not found');
            }

            // Verify ownership
            if ($appointment['user_id'] != $userId) {
                throw new \Exception('Unauthorized');
            }

            // Can only cancel booked or checked_in appointments
            if (!in_array($appointment['status'], ['booked', 'checked_in'])) {
                throw new \Exception('Cannot cancel appointment in current status');
            }

            $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
            $this->db->execute($sql, [$appointmentId]);

            Logger::info('Appointment cancelled', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Cancel appointment failed', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark appointment as no-show
     */
    public function markNoShow(int $appointmentId): bool
    {
        try {
            $sql = "UPDATE appointments SET status = 'no_show' WHERE id = ?";
            $this->db->execute($sql, [$appointmentId]);

            Logger::info('Appointment marked as no-show', [
                'appointment_id' => $appointmentId,
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Mark no-show failed', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark appointment as completed
     */
    public function markCompleted(int $appointmentId): bool
    {
        try {
            $sql = "UPDATE appointments SET status = 'completed' WHERE id = ?";
            $this->db->execute($sql, [$appointmentId]);

            Logger::info('Appointment marked as completed', [
                'appointment_id' => $appointmentId,
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Mark completed failed', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get appointment by ID
     */
    public function getAppointment(int $appointmentId): ?array
    {
        $sql = "SELECT * FROM appointments WHERE id = ? LIMIT 1";
        $appointments = $this->db->query($sql, [$appointmentId]);
        return $appointments[0] ?? null;
    }

    /**
     * List appointments with filters
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (isset($filters['professional_id'])) {
            $where[] = "professional_id = ?";
            $params[] = $filters['professional_id'];
        }

        if (isset($filters['establishment_id'])) {
            $where[] = "establishment_id = ?";
            $params[] = $filters['establishment_id'];
        }

        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['date'])) {
            $where[] = "DATE(start_at) = ?";
            $params[] = $filters['date'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM appointments $whereClause";
        $countResult = $this->db->query($countSql, $params);
        $total = (int)$countResult[0]['total'];

        // Fetch appointments
        $sql = "
            SELECT * FROM appointments 
            $whereClause
            ORDER BY start_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = $offset;

        $appointments = $this->db->query($sql, $params);

        return [
            'appointments' => $appointments,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get available time slots for a professional/service on a specific date
     */
    public function getAvailableSlots(int $professionalId, int $serviceId, string $date): array
    {
        // Get service duration
        $serviceSql = "SELECT duration_minutes FROM services WHERE id = ? LIMIT 1";
        $services = $this->db->query($serviceSql, [$serviceId]);
        
        if (empty($services)) {
            throw new \Exception('Service not found');
        }

        $durationMinutes = (int)$services[0]['duration_minutes'];

        // Business hours (TODO: make configurable per establishment/professional)
        $startHour = 9; // 9 AM
        $endHour = 18;  // 6 PM

        // Get existing appointments for the day
        $appointmentsSql = "
            SELECT start_at, end_at FROM appointments
            WHERE professional_id = ?
              AND DATE(start_at) = ?
              AND status NOT IN ('cancelled', 'no_show')
            ORDER BY start_at ASC
        ";
        $appointments = $this->db->query($appointmentsSql, [$professionalId, $date]);

        // Generate available slots
        $slots = [];
        $currentTime = strtotime("$date $startHour:00:00");
        $endTime = strtotime("$date $endHour:00:00");

        while ($currentTime < $endTime) {
            $slotEnd = $currentTime + ($durationMinutes * 60);
            
            // Check if slot conflicts with existing appointment
            $hasConflict = false;
            foreach ($appointments as $appointment) {
                $appointmentStart = strtotime($appointment['start_at']);
                $appointmentEnd = strtotime($appointment['end_at']);
                
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
}
