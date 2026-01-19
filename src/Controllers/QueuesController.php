<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Services\QueueService;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;

/**
 * QueuesController - Queue Management Endpoints
 * 
 * Handles queue operations: list, get, join, status, leave, callNext
 */
class QueuesController
{
    /**
     * GET /api/v1/queues
     * 
     * List queues (optionally filter by establishment)
     */
    public function list(Request $request): void
    {
        try {
            $db = Database::getInstance();
            $params = $request->query();
            
            $where = [];
            $values = [];

            if (isset($params['establishment_id'])) {
                $where[] = "establishment_id = ?";
                $values[] = (int)$params['establishment_id'];
            }

            if (isset($params['status'])) {
                $where[] = "status = ?";
                $values[] = $params['status'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT q.*, s.name as service_name, e.name as establishment_name
                FROM queues q
                LEFT JOIN services s ON s.id = q.service_id
                LEFT JOIN establishments e ON e.id = q.establishment_id
                $whereClause
                ORDER BY q.created_at DESC
            ";
            $queues = $db->query($sql, $values);

            // Add waiting count to each queue
            foreach ($queues as &$queue) {
                $countSql = "SELECT COUNT(*) as total FROM queue_entries WHERE queue_id = ? AND status = 'waiting'";
                $countResult = $db->query($countSql, [$queue['id']]);
                $queue['waiting_count'] = (int)$countResult[0]['total'];
            }

            Response::success([
                'queues' => $queues,
                'total' => count($queues),
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list queues', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queues', $request->requestId);
        }
    }

    /**
     * GET /api/v1/queues/:id
     * 
     * Get single queue by ID
     */
    public function get(Request $request, int $id): void
    {
        try {
            $db = Database::getInstance();
            
            $sql = "
                SELECT q.*, s.name as service_name, e.name as establishment_name
                FROM queues q
                LEFT JOIN services s ON s.id = q.service_id
                LEFT JOIN establishments e ON e.id = q.establishment_id
                WHERE q.id = ?
                LIMIT 1
            ";
            $queues = $db->query($sql, [$id]);

            if (empty($queues)) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $queue = $queues[0];

            // Add waiting count
            $countSql = "SELECT COUNT(*) as total FROM queue_entries WHERE queue_id = ? AND status = 'waiting'";
            $countResult = $db->query($countSql, [$id]);
            $queue['waiting_count'] = (int)$countResult[0]['total'];

            Response::success([
                'queue' => $queue,
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get queue', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/join
     * 
     * Join queue
     */
    public function join(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $data = $request->all();
        $userId = (int)$request->user['id'];

        // Validate input
        $errors = Validator::make($data, [
            'priority' => 'integer|min:0|max:10',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $priority = (int)($data['priority'] ?? 0);

        try {
            $queueService = new QueueService();
            $entry = $queueService->join($id, $userId, $priority);

            Logger::info('User joined queue', [
                'queue_id' => $id,
                'user_id' => $userId,
                'entry_id' => $entry['id'],
            ], $request->requestId);

            Response::created([
                'entry' => $entry,
                'message' => 'Successfully joined queue',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to join queue', [
                'queue_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Queue not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'closed') !== false) {
                Response::error('QUEUE_CLOSED', 'Queue is closed', 400, $request->requestId);
            } else {
                Response::serverError('Failed to join queue', $request->requestId);
            }
        }
    }

    /**
     * GET /api/v1/queues/:id/status
     * 
     * Get queue status (waiting count, user position if authenticated)
     */
    public function status(Request $request, int $id): void
    {
        $userId = $request->user ? (int)$request->user['id'] : null;

        try {
            $queueService = new QueueService();
            $status = $queueService->getQueueStatus($id, $userId);

            Response::success($status);

        } catch (\Exception $e) {
            Logger::error('Failed to get queue status', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue status', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/entries/:entryId/leave
     * 
     * Leave queue (cancel entry)
     */
    public function leave(Request $request, int $entryId): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        try {
            $queueService = new QueueService();
            $queueService->leave($entryId, $userId);

            Logger::info('User left queue', [
                'entry_id' => $entryId,
                'user_id' => $userId,
            ], $request->requestId);

            Response::success([
                'message' => 'Successfully left queue',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to leave queue', [
                'entry_id' => $entryId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Queue entry not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot cancel this queue entry', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Cannot cancel') !== false) {
                Response::error('INVALID_STATUS', $e->getMessage(), 400, $request->requestId);
            } else {
                Response::serverError('Failed to leave queue', $request->requestId);
            }
        }
    }

    /**
     * POST /api/v1/queues/:id/call-next
     * 
     * Call next person in queue (requires attendant or admin role)
     */
    public function callNext(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'admin'])) {
            Response::forbidden('Only attendants and admins can call next', $request->requestId);
            return;
        }

        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'integer',
            'professional_id' => 'integer',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $establishmentId = isset($data['establishment_id']) ? (int)$data['establishment_id'] : null;
        $professionalId = isset($data['professional_id']) ? (int)$data['professional_id'] : null;

        try {
            $queueService = new QueueService();
            $result = $queueService->callNext($id, $establishmentId, $professionalId);

            if (!$result) {
                Response::success([
                    'message' => 'Queue is empty',
                    'called' => null,
                ]);
                return;
            }

            Logger::info('Called next in queue', [
                'queue_id' => $id,
                'type' => $result['type'],
                'called_by' => $request->user['id'],
            ], $request->requestId);

            Response::success([
                'message' => 'Successfully called next',
                'type' => $result['type'],
                'called' => $result['data'],
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to call next', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to call next', $request->requestId);
        }
    }
}
