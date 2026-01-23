<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * Appointment Model - Generated from 'appointments' table
 * 
 * Represents a scheduled appointment for a service with a professional.
 * Manages booking, check-in, and completion of appointments.
 */
class Appointment
{
    protected static string $table = 'appointments';
    protected static string $primaryKey = 'id';

    /**
     * Find record by primary key
     * 
     * @param int $id Primary key value
     * @return array|null Record data or null
     */
    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    /**
     * Get all records
     * 
     * @param array $conditions Optional WHERE conditions ['column' => 'value']
     * @param string $orderBy Optional ORDER BY column
     * @param string $direction Sort direction (ASC|DESC)
     * @param int|null $limit Optional LIMIT
     * @return array Records array
     */
    public static function all(
        array $conditions = [],
        string $orderBy = '',
        string $direction = 'ASC',
        ?int $limit = null
    ): array {
        $qb = new QueryBuilder();
        $qb->select(self::$table);

        foreach ($conditions as $column => $value) {
            $qb->where($column, '=', $value);
        }

        if (!empty($orderBy)) {
            $qb->orderBy($orderBy, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb->get();
    }

    /**
     * Create new record
     * 
     * @param array $data Column => value pairs
     * @return int Inserted record ID
     */
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

    /**
     * Update existing record
     * 
     * @param int $id Primary key value
     * @param array $data Column => value pairs to update
     * @return int Number of affected rows
     */
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

    /**
     * Delete record
     * 
     * @param int $id Primary key value
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }

    /**
     * Get user who owns this appointment (belongsTo relationship)
     * 
     * @param int $appointmentId Appointment ID
     * @return array|null User data or null
     */
    public static function getUser(int $appointmentId): ?array
    {
        $appointment = self::find($appointmentId);
        
        if (!$appointment || !$appointment['user_id']) {
            return null;
        }

        return User::find($appointment['user_id']);
    }

    /**
     * Get professional assigned to this appointment (belongsTo relationship)
     * 
     * @param int $appointmentId Appointment ID
     * @return array|null Professional data or null
     */
    public static function getProfessional(int $appointmentId): ?array
    {
        $appointment = self::find($appointmentId);
        
        if (!$appointment || !$appointment['professional_id']) {
            return null;
        }

        return Professional::find($appointment['professional_id']);
    }

    /**
     * Get service for this appointment (belongsTo relationship)
     * 
     * @param int $appointmentId Appointment ID
     * @return array|null Service data or null
     */
    public static function getService(int $appointmentId): ?array
    {
        $appointment = self::find($appointmentId);
        
        if (!$appointment || !$appointment['service_id']) {
            return null;
        }

        return Service::find($appointment['service_id']);
    }

    /**
     * Get establishment for this appointment (belongsTo relationship)
     * 
     * @param int $appointmentId Appointment ID
     * @return array|null Establishment data or null
     */
    public static function getEstablishment(int $appointmentId): ?array
    {
        $appointment = self::find($appointmentId);
        
        if (!$appointment || !$appointment['establishment_id']) {
            return null;
        }

        return Establishment::find($appointment['establishment_id']);
    }

    /**
     * Get appointments by user
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of appointments
     */
    public static function getByUser(int $userId, ?string $status = null): array
    {
        $conditions = ['user_id' => $userId];
        
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return self::all($conditions, 'start_at', 'ASC');
    }

    /**
     * Get appointments by professional
     * 
     * @param int $professionalId Professional ID
     * @param string|null $date Optional date filter (Y-m-d format)
     * @return array Array of appointments
     */
    public static function getByProfessional(int $professionalId, ?string $date = null): array
    {
        $db = Database::getInstance();
        
        if ($date !== null) {
            $sql = "SELECT * FROM " . self::$table . " 
                    WHERE professional_id = ? AND DATE(start_at) = ? 
                    ORDER BY start_at ASC";
            return $db->query($sql, [$professionalId, $date]);
        }
        
        return self::all(['professional_id' => $professionalId], 'start_at', 'ASC');
    }

    /**
     * Get appointments by establishment
     * 
     * @param int $establishmentId Establishment ID
     * @param string|null $date Optional date filter (Y-m-d format)
     * @return array Array of appointments
     */
    public static function getByEstablishment(int $establishmentId, ?string $date = null): array
    {
        $db = Database::getInstance();
        
        if ($date !== null) {
            $sql = "SELECT * FROM " . self::$table . " 
                    WHERE establishment_id = ? AND DATE(start_at) = ? 
                    ORDER BY start_at ASC";
            return $db->query($sql, [$establishmentId, $date]);
        }
        
        return self::all(['establishment_id' => $establishmentId], 'start_at', 'ASC');
    }

    /**
     * Check for appointment conflicts
     * 
     * @param int $professionalId Professional ID
     * @param string $startAt Start datetime (Y-m-d H:i:s)
     * @param string $endAt End datetime (Y-m-d H:i:s)
     * @param int|null $excludeAppointmentId Appointment ID to exclude (for updates)
     * @return bool True if conflict exists
     */
    public static function hasConflict(
        int $professionalId,
        string $startAt,
        string $endAt,
        ?int $excludeAppointmentId = null
    ): bool {
        $db = Database::getInstance();
        
        $sql = "SELECT COUNT(*) as count FROM " . self::$table . " 
                WHERE professional_id = ? 
                AND status NOT IN ('cancelled', 'no_show', 'completed')
                AND (
                    (start_at < ? AND end_at > ?) OR
                    (start_at >= ? AND start_at < ?) OR
                    (end_at > ? AND end_at <= ?)
                )";
        
        $params = [$professionalId, $endAt, $startAt, $startAt, $endAt, $startAt, $endAt];
        
        if ($excludeAppointmentId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeAppointmentId;
        }
        
        $result = $db->query($sql, $params);
        
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Validate data before create/update
     * 
     * @param array $data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array $data): array
    {
        $errors = [];
        
        // Validate establishment_id
        if (empty($data['establishment_id'])) {
            $errors['establishment_id'] = 'Establishment ID is required';
        } elseif (!is_numeric($data['establishment_id']) || $data['establishment_id'] <= 0) {
            $errors['establishment_id'] = 'Establishment ID must be a positive integer';
        }

        // Validate professional_id
        if (empty($data['professional_id'])) {
            $errors['professional_id'] = 'Professional ID is required';
        } elseif (!is_numeric($data['professional_id']) || $data['professional_id'] <= 0) {
            $errors['professional_id'] = 'Professional ID must be a positive integer';
        }

        // Validate service_id
        if (empty($data['service_id'])) {
            $errors['service_id'] = 'Service ID is required';
        } elseif (!is_numeric($data['service_id']) || $data['service_id'] <= 0) {
            $errors['service_id'] = 'Service ID must be a positive integer';
        }

        // Validate user_id
        if (empty($data['user_id'])) {
            $errors['user_id'] = 'User ID is required';
        } elseif (!is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            $errors['user_id'] = 'User ID must be a positive integer';
        }

        // Validate start_at
        if (empty($data['start_at'])) {
            $errors['start_at'] = 'Start time is required';
        }

        // Validate end_at
        if (empty($data['end_at'])) {
            $errors['end_at'] = 'End time is required';
        }

        // Validate status (optional)
        if (isset($data['status'])) {
            $validStatuses = ['booked', 'checked_in', 'in_progress', 'completed', 'no_show', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status value';
            }
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - establishment_id: bigint NOT NULL
     * - professional_id: bigint NOT NULL
     * - service_id: bigint NOT NULL
     * - user_id: bigint NOT NULL
     * - start_at: datetime NOT NULL
     * - end_at: datetime NOT NULL
     * - status: enum NOT NULL
     * - created_at: timestamp NOT NULL
     * - checkin_at: timestamp NULL
     */
}
