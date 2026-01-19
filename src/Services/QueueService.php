<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * QueueService - Transaction-Safe Queue Operations
 * 
 * Implements atomic operations for queue management with concurrency protection:
 * - join: Add user to queue with atomic position calculation
 * - callNext: Select next entry with priority rules (appointments first)
 * 
 * CONCURRENCY STRATEGY:
 * Uses SELECT ... FOR UPDATE to lock rows during transactions, preventing:
 * - Double-booking same position
 * - Multiple attendants calling same entry
 * - Race conditions in position calculation
 * 
 * PRIORITY RULES:
 * 1. Checked-in appointments within grace window (start_at - grace_before to start_at + grace_after)
 * 2. Walk-in queue entries ordered by priority DESC, created_at ASC
 */
class QueueService
{
    private Database $db;
    private ?object $redis = null;
    private bool $redisAvailable = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initializeRedis();
    }

    /**
     * Initialize Redis for pub/sub (optional)
     */
    private function initializeRedis(): void
    {
        $redisEnabled = filter_var($_ENV['REDIS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$redisEnabled) {
            return;
        }

        try {
            $this->redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'database' => (int)($_ENV['REDIS_DB'] ?? 0),
            ]);

            $this->redis->ping();
            $this->redisAvailable = true;
            
            Logger::debug('Redis pub/sub initialized for QueueService');
        } catch (\Exception $e) {
            Logger::warning('Redis unavailable for queue events', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Join queue atomically
     * 
     * Transaction-safe implementation:
     * 1. Start transaction
     * 2. Lock queue row for position calculation
     * 3. Calculate next position (MAX + 1)
     * 4. Insert queue_entry
     * 5. Commit transaction
     * 
     * @param int $queueId Queue ID
     * @param int|null $userId User ID (null for anonymous)
     * @param int $priority Priority level (0 = normal, higher = priority)
     * @return array Queue entry data
     * @throws \Exception
     */
    public function join(int $queueId, ?int $userId = null, int $priority = 0): array
    {
        try {
            $this->db->beginTransaction();

            // Lock queue row to prevent concurrent position conflicts
            // Using FOR UPDATE ensures no other transaction can read/modify until commit
            $queueSql = "SELECT id, name, status FROM queues WHERE id = ? FOR UPDATE";
            $queues = $this->db->query($queueSql, [$queueId]);

            if (empty($queues)) {
                throw new \Exception('Queue not found');
            }

            $queue = $queues[0];

            if ($queue['status'] !== 'open') {
                throw new \Exception('Queue is closed');
            }

            // Calculate next position atomically
            // Lock all entries to ensure accurate MAX calculation
            $positionSql = "
                SELECT COALESCE(MAX(position), 0) as max_position 
                FROM queue_entries 
                WHERE queue_id = ? 
                FOR UPDATE
            ";
            $positionResult = $this->db->query($positionSql, [$queueId]);
            $nextPosition = ((int)$positionResult[0]['max_position']) + 1;

            // Insert queue entry
            $insertSql = "
                INSERT INTO queue_entries (queue_id, user_id, position, status, priority, created_at)
                VALUES (?, ?, ?, 'waiting', ?, NOW())
            ";
            $this->db->execute($insertSql, [$queueId, $userId, $nextPosition, $priority]);

            $entryId = (int)$this->db->lastInsertId();

            // Commit transaction
            $this->db->commit();

            // Fetch created entry
            $entry = $this->getEntry($entryId);

            // Calculate estimated wait time
            $estimatedWaitMinutes = $this->calculateEstimatedWait($queueId, $nextPosition);
            $entry['estimated_wait_minutes'] = $estimatedWaitMinutes;

            // Publish event (non-blocking)
            $this->publishEvent('queue.joined', [
                'queue_id' => $queueId,
                'entry_id' => $entryId,
                'position' => $nextPosition,
                'user_id' => $userId,
            ]);

            Logger::info('User joined queue', [
                'queue_id' => $queueId,
                'entry_id' => $entryId,
                'position' => $nextPosition,
                'user_id' => $userId,
            ]);

            return $entry;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            Logger::error('Queue join failed', [
                'queue_id' => $queueId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Call next person in queue with priority rules
     * 
     * Transaction-safe implementation with priority:
     * 1. Start transaction
     * 2. Check for checked-in appointments within grace window (FOR UPDATE)
     * 3. If appointment found, mark as called/in_progress
     * 4. Else, select next waiting queue_entry (priority DESC, created_at ASC) (FOR UPDATE)
     * 5. Mark as called
     * 6. Commit transaction
     * 
     * @param int $queueId Queue ID
     * @param int|null $establishmentId Optional establishment filter
     * @param int|null $professionalId Optional professional filter
     * @return array|null Called entry or appointment, null if queue empty
     * @throws \Exception
     */
    public function callNext(int $queueId, ?int $establishmentId = null, ?int $professionalId = null): ?array
    {
        try {
            $this->db->beginTransaction();

            // Grace window settings (from env or defaults)
            $graceBefore = (int)($_ENV['QUEUE_GRACE_BEFORE_MINUTES'] ?? 10);
            $graceAfter = (int)($_ENV['QUEUE_GRACE_AFTER_MINUTES'] ?? 15);

            // Step 1: Check for priority appointments (checked-in, within grace window)
            // Only if establishment/professional are specified
            if ($establishmentId && $professionalId) {
                // Calculate grace window bounds in PHP for better index usage
                $now = time();
                $windowStart = date('Y-m-d H:i:s', $now - ($graceBefore * 60));
                $windowEnd = date('Y-m-d H:i:s', $now + ($graceAfter * 60));
                
                $appointmentSql = "
                    SELECT * FROM appointments
                    WHERE establishment_id = ?
                      AND professional_id = ?
                      AND status = 'checked_in'
                      AND start_at BETWEEN ? AND ?
                    ORDER BY start_at ASC
                    LIMIT 1
                    FOR UPDATE
                ";
                $appointments = $this->db->query(
                    $appointmentSql,
                    [$establishmentId, $professionalId, $windowStart, $windowEnd]
                );

                if (!empty($appointments)) {
                    $appointment = $appointments[0];

                    // Mark appointment as in_progress
                    $updateSql = "UPDATE appointments SET status = 'in_progress' WHERE id = ?";
                    $this->db->execute($updateSql, [$appointment['id']]);

                    $this->db->commit();

                    // Publish event
                    $this->publishEvent('appointment.called', [
                        'appointment_id' => $appointment['id'],
                        'user_id' => $appointment['user_id'],
                    ]);

                    Logger::info('Appointment called (priority)', [
                        'appointment_id' => $appointment['id'],
                        'queue_id' => $queueId,
                    ]);

                    return [
                        'type' => 'appointment',
                        'data' => $appointment,
                    ];
                }
            }

            // Step 2: No priority appointment, select next from queue
            $entrySql = "
                SELECT * FROM queue_entries
                WHERE queue_id = ?
                  AND status = 'waiting'
                ORDER BY priority DESC, created_at ASC
                LIMIT 1
                FOR UPDATE
            ";
            $entries = $this->db->query($entrySql, [$queueId]);

            if (empty($entries)) {
                $this->db->commit();
                Logger::info('No entries in queue', ['queue_id' => $queueId]);
                return null; // Queue is empty
            }

            $entry = $entries[0];

            // Mark as called
            $updateSql = "UPDATE queue_entries SET status = 'called', called_at = NOW() WHERE id = ?";
            $this->db->execute($updateSql, [$entry['id']]);

            $this->db->commit();

            // Fetch updated entry
            $calledEntry = $this->getEntry((int)$entry['id']);

            // Publish event
            $this->publishEvent('queue.called', [
                'queue_id' => $queueId,
                'entry_id' => $entry['id'],
                'user_id' => $entry['user_id'],
            ]);

            Logger::info('Queue entry called', [
                'queue_id' => $queueId,
                'entry_id' => $entry['id'],
            ]);

            return [
                'type' => 'queue_entry',
                'data' => $calledEntry,
            ];

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            Logger::error('Call next failed', [
                'queue_id' => $queueId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Leave queue (cancel entry)
     */
    public function leave(int $entryId, int $userId): bool
    {
        try {
            // Verify ownership
            $entry = $this->getEntry($entryId);
            
            if (!$entry) {
                throw new \Exception('Entry not found');
            }

            if ($entry['user_id'] != $userId) {
                throw new \Exception('Unauthorized');
            }

            if (!in_array($entry['status'], ['waiting', 'called'])) {
                throw new \Exception('Cannot cancel entry in current status');
            }

            // Update status
            $sql = "UPDATE queue_entries SET status = 'cancelled' WHERE id = ?";
            $this->db->execute($sql, [$entryId]);

            // Publish event
            $this->publishEvent('queue.left', [
                'queue_id' => $entry['queue_id'],
                'entry_id' => $entryId,
                'user_id' => $userId,
            ]);

            Logger::info('User left queue', [
                'entry_id' => $entryId,
                'user_id' => $userId,
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Leave queue failed', [
                'entry_id' => $entryId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get queue entry by ID
     */
    public function getEntry(int $entryId): ?array
    {
        $sql = "SELECT * FROM queue_entries WHERE id = ? LIMIT 1";
        $entries = $this->db->query($sql, [$entryId]);
        return $entries[0] ?? null;
    }

    /**
     * Get queue status (waiting count, position for user)
     */
    public function getQueueStatus(int $queueId, ?int $userId = null): array
    {
        $sql = "SELECT COUNT(*) as total FROM queue_entries WHERE queue_id = ? AND status = 'waiting'";
        $result = $this->db->query($sql, [$queueId]);
        $totalWaiting = (int)$result[0]['total'];

        $userPosition = null;
        $estimatedWait = null;

        if ($userId) {
            $positionSql = "
                SELECT position FROM queue_entries 
                WHERE queue_id = ? AND user_id = ? AND status = 'waiting'
                ORDER BY created_at DESC
                LIMIT 1
            ";
            $positionResult = $this->db->query($positionSql, [$queueId, $userId]);
            
            if (!empty($positionResult)) {
                $userPosition = (int)$positionResult[0]['position'];
                $estimatedWait = $this->calculateEstimatedWait($queueId, $userPosition);
            }
        }

        return [
            'queue_id' => $queueId,
            'total_waiting' => $totalWaiting,
            'user_position' => $userPosition,
            'estimated_wait_minutes' => $estimatedWait,
        ];
    }

    /**
     * Calculate estimated wait time based on position
     */
    private function calculateEstimatedWait(int $queueId, int $position): int
    {
        // Get service duration for this queue
        $serviceSql = "
            SELECT s.duration_minutes 
            FROM queues q 
            LEFT JOIN services s ON s.id = q.service_id
            WHERE q.id = ?
            LIMIT 1
        ";
        $serviceResult = $this->db->query($serviceSql, [$queueId]);
        $serviceDuration = (int)($serviceResult[0]['duration_minutes'] ?? 15);

        // Calculate: (position - 1) * avg service duration
        // -1 because position 1 is next (minimal wait)
        $estimatedMinutes = max(0, ($position - 1) * $serviceDuration);

        return $estimatedMinutes;
    }

    /**
     * Publish event to Redis pub/sub (if available)
     */
    private function publishEvent(string $event, array $data): void
    {
        if (!$this->redisAvailable) {
            return;
        }

        try {
            $payload = json_encode([
                'event' => $event,
                'data' => $data,
                'timestamp' => time(),
            ]);

            $this->redis->publish('queue_events', $payload);
        } catch (\Exception $e) {
            // Non-critical, log and continue
            Logger::warning('Failed to publish queue event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
