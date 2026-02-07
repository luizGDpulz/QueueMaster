<?php

namespace QueueMaster\Stream;

use QueueMaster\Core\Request;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * SseController - Server-Sent Events for Real-Time Updates
 * 
 * Provides SSE endpoints for real-time queue and appointment updates.
 * 
 * IMPORTANT PRODUCTION NOTES:
 * - SSE keeps connection open, which can exhaust server resources
 * - Apache: Configure MaxClients appropriately
 * - PHP: Increase max_execution_time for SSE endpoints
 * - For production, consider:
 *   1. Using a dedicated Node.js/Go service for SSE
 *   2. Using Redis pub/sub for event distribution
 *   3. Using a queue system (RabbitMQ) for scalability
 *   4. Using managed services (Pusher, Ably, AWS AppSync)
 * 
 * This implementation is for MVP/development. Not recommended for high-traffic production.
 */
class SseController
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
     * Initialize Redis for pub/sub
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
            
        } catch (\Exception $e) {
            Logger::warning('Redis unavailable for SSE', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/v1/streams/queue/{id}
     * 
     * Stream queue events (join, call, leave)
     * Requires authentication
     */
    public function streamQueue(Request $request): void
    {
        $queueId = (int)$request->getParam('id');

        if (!$queueId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid queue ID']);
            exit;
        }

        // Verify queue exists
        $queueSql = "SELECT id, name, status FROM queues WHERE id = ? LIMIT 1";
        $queues = $this->db->query($queueSql, [$queueId]);

        if (empty($queues)) {
            http_response_code(404);
            echo json_encode(['error' => 'Queue not found']);
            exit;
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Disable output buffering for SSE
        if (ob_get_level()) {
            ob_end_clean();
        }

        Logger::info('SSE stream started', [
            'queue_id' => $queueId,
            'user_id' => $request->user['id'] ?? null,
        ]);

        // Initial queue status
        $this->sendEvent('connected', [
            'queue_id' => $queueId,
            'message' => 'Connected to queue stream',
        ]);

        // Stream loop
        $this->streamLoop($queueId, $request);
    }

    /**
     * Main streaming loop
     */
    private function streamLoop(int $queueId, Request $request): void
    {
        $lastCheck = time();
        $heartbeatInterval = 30; // Send heartbeat every 30 seconds

        // For Redis pub/sub (if available)
        if ($this->redisAvailable) {
            $this->streamWithRedis($queueId, $request, $heartbeatInterval);
        } else {
            $this->streamWithPolling($queueId, $request, $lastCheck, $heartbeatInterval);
        }
    }

    /**
     * Stream using Redis pub/sub (production-ready)
     */
    private function streamWithRedis(int $queueId, Request $request, int $heartbeatInterval): void
    {
        try {
            $pubsub = $this->redis->pubSubLoop();
            $pubsub->subscribe('queue_events');

            $lastHeartbeat = time();

            foreach ($pubsub as $message) {
                // Check connection
                if (connection_aborted()) {
                    break;
                }

                // Send heartbeat
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    $this->sendEvent('heartbeat', ['timestamp' => time()]);
                    $lastHeartbeat = time();
                }

                // Process message
                if ($message->kind === 'message') {
                    $data = json_decode($message->payload, true);
                    
                    // Filter events for this queue
                    if (isset($data['data']['queue_id']) && $data['data']['queue_id'] == $queueId) {
                        $this->sendEvent($data['event'], $data['data']);
                    }
                }
            }

            $pubsub->unsubscribe();

        } catch (\Exception $e) {
            Logger::error('SSE Redis streaming error', [
                'queue_id' => $queueId,
                'error' => $e->getMessage(),
            ]);
            
            $this->sendEvent('error', [
                'message' => 'Streaming error occurred',
            ]);
        }
    }

    /**
     * Stream using polling (fallback for development)
     * 
     * WARNING: Not efficient for production. Use Redis pub/sub instead.
     */
    private function streamWithPolling(int $queueId, Request $request, int &$lastCheck, int $heartbeatInterval): void
    {
        $pollInterval = 3; // Poll every 3 seconds
        $maxDuration = 300; // Max 5 minutes per connection
        $startTime = time();

        while (true) {
            // Check connection
            if (connection_aborted() || (time() - $startTime) > $maxDuration) {
                break;
            }

            $now = time();

            // Send heartbeat
            if ($now - $lastCheck >= $heartbeatInterval) {
                $this->sendEvent('heartbeat', ['timestamp' => $now]);
                $lastCheck = $now;
            }

            // Poll for queue changes
            $this->pollQueueChanges($queueId);

            // Sleep before next poll
            sleep($pollInterval);
        }

        Logger::info('SSE stream ended', [
            'queue_id' => $queueId,
            'duration' => time() - $startTime,
        ]);
    }

    /**
     * Poll database for queue changes
     */
    private function pollQueueChanges(int $queueId): void
    {
        // Get current queue status
        $statusSql = "
            SELECT COUNT(*) as total 
            FROM queue_entries 
            WHERE queue_id = ? AND status = 'waiting'
        ";
        $result = $this->db->query($statusSql, [$queueId]);
        $totalWaiting = (int)$result[0]['total'];

        // Get recently called entries (last minute)
        $calledSql = "
            SELECT * FROM queue_entries
            WHERE queue_id = ?
              AND status = 'called'
              AND called_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ";
        $calledEntries = $this->db->query($calledSql, [$queueId]);

        if (!empty($calledEntries)) {
            foreach ($calledEntries as $entry) {
                $this->sendEvent('queue.called', [
                    'entry_id' => $entry['id'],
                    'position' => $entry['position'],
                ]);
            }
        }

        // Send queue status update
        $this->sendEvent('queue.status', [
            'queue_id' => $queueId,
            'total_waiting' => $totalWaiting,
        ]);
    }

    /**
     * Send SSE event
     */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        // Flush output to client
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * GET /api/v1/streams/appointments/{establishmentId}
     * 
     * Stream appointment events for an establishment
     * Requires professional/manager/admin role
     */
    public function streamAppointments(Request $request): void
    {
        $establishmentId = (int)$request->getParam('establishmentId');

        if (!$establishmentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid establishment ID']);
            exit;
        }

        // Check role (professional, manager, or admin)
        if (!in_array($request->user['role'] ?? '', ['professional', 'manager', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (ob_get_level()) {
            ob_end_clean();
        }

        Logger::info('SSE appointment stream started', [
            'establishment_id' => $establishmentId,
            'user_id' => $request->user['id'] ?? null,
        ]);

        $this->sendEvent('connected', [
            'establishment_id' => $establishmentId,
            'message' => 'Connected to appointment stream',
        ]);

        // Stream appointments (simplified polling implementation)
        $this->streamAppointmentsLoop($establishmentId);
    }

    /**
     * Stream appointments loop
     */
    private function streamAppointmentsLoop(int $establishmentId): void
    {
        $pollInterval = 5;
        $maxDuration = 300;
        $startTime = time();

        while (true) {
            if (connection_aborted() || (time() - $startTime) > $maxDuration) {
                break;
            }

            // Get upcoming appointments
            $sql = "
                SELECT * FROM appointments
                WHERE establishment_id = ?
                  AND DATE(start_at) = CURDATE()
                  AND status IN ('booked', 'checked_in')
                ORDER BY start_at ASC
            ";
            $appointments = $this->db->query($sql, [$establishmentId]);

            $this->sendEvent('appointments.update', [
                'establishment_id' => $establishmentId,
                'count' => count($appointments),
                'appointments' => $appointments,
            ]);

            sleep($pollInterval);
        }
    }
}
