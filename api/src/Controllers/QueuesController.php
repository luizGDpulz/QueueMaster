<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Services\QueueService;
use QueueMaster\Services\AuditService;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Models\Queue;
use QueueMaster\Models\QueueEntry;
use QueueMaster\Models\QueueAccessCode;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Service;

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
            $params = $request->getQuery();
            
            $conditions = [];

            if (isset($params['establishment_id'])) {
                $conditions['establishment_id'] = (int)$params['establishment_id'];
            }

            if (isset($params['status'])) {
                $conditions['status'] = $params['status'];
            }

            // Get queues using Model
            $queues = Queue::all($conditions, 'created_at', 'DESC');

            // Add related data and waiting count to each queue
            foreach ($queues as &$queue) {
                // Get establishment name
                if ($queue['establishment_id']) {
                    $establishment = Establishment::find($queue['establishment_id']);
                    $queue['establishment_name'] = $establishment['name'] ?? null;
                }

                // Get service name
                if ($queue['service_id']) {
                    $service = Service::find($queue['service_id']);
                    $queue['service_name'] = $service['name'] ?? null;
                } else {
                    $queue['service_name'] = null;
                }

                // Get waiting count
                $waitingEntries = QueueEntry::getWaitingByQueue($queue['id']);
                $queue['waiting_count'] = count($waitingEntries);
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
            // Find queue using Model
            $queue = Queue::find($id);

            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            // Add related data
            if ($queue['establishment_id']) {
                $establishment = Establishment::find($queue['establishment_id']);
                $queue['establishment_name'] = $establishment['name'] ?? null;
            }

            if ($queue['service_id']) {
                $service = Service::find($queue['service_id']);
                $queue['service_name'] = $service['name'] ?? null;
            } else {
                $queue['service_name'] = null;
            }

            // Get waiting count
            $waitingEntries = QueueEntry::getWaitingByQueue($id);
            $queue['waiting_count'] = count($waitingEntries);

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
     * Join queue (optionally with access_code)
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

        // Validate access code if provided
        if (isset($data['access_code']) && !empty($data['access_code'])) {
            $code = QueueAccessCode::findByCode($data['access_code']);
            if (!$code || $code['queue_id'] !== $id) {
                Response::error('INVALID_CODE', 'Invalid or expired access code', 400, $request->requestId);
                return;
            }
            if (!QueueAccessCode::isValid($code)) {
                Response::error('INVALID_CODE', 'Access code is expired or exhausted', 400, $request->requestId);
                return;
            }
            // Increment uses
            QueueAccessCode::incrementUses($code['id']);
        }

        $priority = (int)($data['priority'] ?? 0);

        try {
            $queueService = new QueueService();
            $entry = $queueService->join($id, $userId, $priority);

            AuditService::logFromRequest($request, 'queue_join', 'queue', (string)$id, null, null, [
                'entry_id' => $entry['id'],
            ]);

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
     * Call next person in queue (requires attendant/professional/manager or admin role)
     */
    public function callNext(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff members can call next', $request->requestId);
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

    /**
     * POST /api/v1/queues
     * 
     * Create queue (admin only)
     */
    public function create(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'name' => 'required|min:2|max:150',
            'status' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            // Verify establishment exists
            $establishment = Establishment::find((int)$data['establishment_id']);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $queueId = Queue::create([
                'establishment_id' => (int)$data['establishment_id'],
                'service_id' => isset($data['service_id']) ? (int)$data['service_id'] : null,
                'name' => trim($data['name']),
                'status' => $data['status'],
            ]);

            $queue = Queue::find($queueId);

            Logger::info('Queue created', [
                'queue_id' => $queueId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            Response::created([
                'queue' => $queue,
                'message' => 'Queue created successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to create queue', [
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create queue', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/queues/{id}
     * 
     * Update queue (admin only)
     */
    public function update(Request $request, int $id): void
    {
        try {
            $db = Database::getInstance();
            $body = $request->getBody();

            // Verify queue exists
            $queueCheck = $db->query("SELECT id FROM queues WHERE id = ?", [$id]);
            if (empty($queueCheck)) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $updateFields = [];
            $values = [];

            if (isset($body['name'])) {
                $updateFields[] = "name = ?";
                $values[] = $body['name'];
            }

            if (isset($body['status'])) {
                if (!in_array($body['status'], ['open', 'closed'])) {
                    Response::badRequest('Invalid status', $request->requestId);
                    return;
                }
                $updateFields[] = "status = ?";
                $values[] = $body['status'];
            }

            if (empty($updateFields)) {
                Logger::warning('Update attempt with no fields', [
                    'queue_id' => $id,
                    'user_id' => $request->user['id'] ?? null,
                    'received_data' => $body,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, status',
                    400,
                    $request->requestId
                );
                return;
            }

            $values[] = $id;
            $sql = "UPDATE queues SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $db->execute($sql, $values);

            Response::success(['message' => 'Queue updated successfully']);

        } catch (\Exception $e) {
            Logger::error('Failed to update queue', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update queue', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/queues/{id}
     * 
     * Delete queue (admin only)
     */
    public function delete(Request $request, int $id): void
    {
        try {
            $db = Database::getInstance();

            // Check if queue exists
            $queueCheck = $db->query("SELECT id FROM queues WHERE id = ?", [$id]);
            if (empty($queueCheck)) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $db->execute("DELETE FROM queues WHERE id = ?", [$id]);

            Response::success(['message' => 'Queue deleted successfully']);

        } catch (\Exception $e) {
            Logger::error('Failed to delete queue', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete queue', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/generate-code
     * 
     * Generate a join code for a queue (manager/admin only)
     */
    public function generateCode(Request $request, int $id): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $expiresAt = $data['expires_at'] ?? null;
            $maxUses = isset($data['max_uses']) ? (int)$data['max_uses'] : null;

            $code = QueueAccessCode::generateCode();

            $codeId = QueueAccessCode::create([
                'queue_id' => $id,
                'code' => $code,
                'expires_at' => $expiresAt,
                'max_uses' => $maxUses,
            ]);

            $codeRecord = QueueAccessCode::find($codeId);

            AuditService::logFromRequest($request, 'generate_code', 'queue', (string)$id, null, null, [
                'code_id' => $codeId,
            ]);

            Logger::info('Queue access code generated', [
                'queue_id' => $id,
                'code_id' => $codeId,
            ], $request->requestId);

            Response::created([
                'access_code' => $codeRecord,
                'message' => 'Access code generated successfully',
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to generate queue code', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to generate access code', $request->requestId);
        }
    }
}
