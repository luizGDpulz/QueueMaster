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

            if (!empty($queues)) {
                // Batch load related data to avoid N+1 queries
                $establishmentIds = array_filter(array_unique(array_column($queues, 'establishment_id')));
                $serviceIds = array_filter(array_unique(array_column($queues, 'service_id')));
                $queueIds = array_column($queues, 'id');

                // Fetch all establishments in one query
                $establishmentMap = [];
                if (!empty($establishmentIds)) {
                    $db = \QueueMaster\Core\Database::getInstance();
                    $placeholders = implode(',', array_fill(0, count($establishmentIds), '?'));
                    $establishments = $db->query(
                        "SELECT id, name FROM establishments WHERE id IN ($placeholders)",
                        array_values($establishmentIds)
                    );
                    foreach ($establishments as $est) {
                        $establishmentMap[$est['id']] = $est['name'];
                    }
                }

                // Fetch all services in one query
                $serviceMap = [];
                if (!empty($serviceIds)) {
                    $db = $db ?? \QueueMaster\Core\Database::getInstance();
                    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
                    $services = $db->query(
                        "SELECT id, name FROM services WHERE id IN ($placeholders)",
                        array_values($serviceIds)
                    );
                    foreach ($services as $svc) {
                        $serviceMap[$svc['id']] = $svc['name'];
                    }
                }

                // Fetch waiting counts in one aggregated query
                $waitingMap = [];
                if (!empty($queueIds)) {
                    $db = $db ?? \QueueMaster\Core\Database::getInstance();
                    $placeholders = implode(',', array_fill(0, count($queueIds), '?'));
                    $waitingCounts = $db->query(
                        "SELECT queue_id, COUNT(*) as cnt FROM queue_entries WHERE queue_id IN ($placeholders) AND status = 'waiting' GROUP BY queue_id",
                        array_values($queueIds)
                    );
                    foreach ($waitingCounts as $wc) {
                        $waitingMap[$wc['queue_id']] = (int)$wc['cnt'];
                    }
                }

                // Map data onto queues (O(1) lookups instead of N queries)
                foreach ($queues as &$queue) {
                    $queue['establishment_name'] = $establishmentMap[$queue['establishment_id']] ?? null;
                    $queue['service_name'] = $serviceMap[$queue['service_id']] ?? null;
                    $queue['waiting_count'] = $waitingMap[$queue['id']] ?? 0;
                }
                unset($queue);
            }

            Response::success([
                'queues' => $queues,
                'total' => count($queues),
            ]);

        }
        catch (\Exception $e) {
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
            }
            else {
                $queue['service_name'] = null;
            }

            // Get waiting count
            $waitingEntries = QueueEntry::getWaitingByQueue($id);
            $queue['waiting_count'] = count($waitingEntries);

            Response::success([
                'queue' => $queue,
            ]);

        }
        catch (\Exception $e) {
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

        }
        catch (\Exception $e) {
            Logger::error('Failed to join queue', [
                'queue_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Queue not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'closed') !== false) {
                Response::error('QUEUE_CLOSED', 'Queue is closed', 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to join queue', $request->requestId);
            }
        }
    }

    /**
     * GET /api/v1/queues/:id/status
     * 
     * Get queue status with full queue data, entries list, and statistics
     */
    public function status(Request $request, int $id): void
    {
        $userId = $request->user ? (int)$request->user['id'] : null;

        try {
            // 1. Get queue object
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            // Add related names
            if ($queue['establishment_id']) {
                $establishment = Establishment::find($queue['establishment_id']);
                $queue['establishment_name'] = $establishment['name'] ?? null;
            }
            if ($queue['service_id']) {
                $service = Service::find($queue['service_id']);
                $queue['service_name'] = $service['name'] ?? null;
            }
            else {
                $queue['service_name'] = null;
            }

            $db = Database::getInstance();

            // 2. Get waiting entries with user names
            $entriesSql = "
                SELECT qe.*, u.name as user_name, u.email as user_email
                FROM queue_entries qe
                LEFT JOIN users u ON u.id = qe.user_id
                WHERE qe.queue_id = ? AND qe.status = 'waiting'
                ORDER BY qe.priority DESC, qe.created_at ASC
            ";
            $entries = $db->query($entriesSql, [$id]);

            // Calculate estimated wait per entry and real wait time
            $serviceSql = "
                SELECT s.duration_minutes 
                FROM queues q LEFT JOIN services s ON s.id = q.service_id
                WHERE q.id = ? LIMIT 1
            ";
            $svcResult = $db->query($serviceSql, [$id]);
            $serviceDuration = (int)($svcResult[0]['duration_minutes'] ?? 15);

            $now = time();
            foreach ($entries as $idx => &$entry) {
                $entry['estimated_wait_minutes'] = max(0, $idx * $serviceDuration);
                if (!empty($entry['created_at'])) {
                    $createdTs = strtotime($entry['created_at']);
                    $entry['waiting_since_minutes'] = max(0, (int)round(($now - $createdTs) / 60));
                }
                else {
                    $entry['waiting_since_minutes'] = 0;
                }
                if (empty($entry['user_name'])) {
                    $entry['user_name'] = $entry['guest_name'] ?? ('Usuário #' . ($entry['user_id'] ?? $entry['id']));
                }
            }
            unset($entry);

            $queue['waiting_count'] = count($entries);

            // 2b. Get serving entries (called + serving)
            $servingSql = "
                SELECT qe.*, u.name as user_name, u.email as user_email
                FROM queue_entries qe
                LEFT JOIN users u ON u.id = qe.user_id
                WHERE qe.queue_id = ? AND qe.status IN ('called','serving')
                ORDER BY qe.called_at ASC
            ";
            $entriesServing = $db->query($servingSql, [$id]);
            foreach ($entriesServing as &$es) {
                if (empty($es['user_name'])) {
                    $es['user_name'] = $es['guest_name'] ?? ('Usuário #' . ($es['user_id'] ?? $es['id']));
                }
                if (!empty($es['called_at'])) {
                    $es['serving_since_minutes'] = max(0, (int)round(($now - strtotime($es['called_at'])) / 60));
                }
                else {
                    $es['serving_since_minutes'] = 0;
                }
            }
            unset($es);

            // 2c. Get completed entries (today)
            $completedSql = "
                SELECT qe.*, u.name as user_name, u.email as user_email
                FROM queue_entries qe
                LEFT JOIN users u ON u.id = qe.user_id
                WHERE qe.queue_id = ? AND qe.status = 'done' AND DATE(qe.completed_at) = CURDATE()
                ORDER BY qe.completed_at DESC
            ";
            $entriesCompleted = $db->query($completedSql, [$id]);
            foreach ($entriesCompleted as &$ec) {
                if (empty($ec['user_name'])) {
                    $ec['user_name'] = $ec['guest_name'] ?? ('Usuário #' . ($ec['user_id'] ?? $ec['id']));
                }
            }
            unset($ec);

            // 3. Statistics
            $beingServed = count($entriesServing);
            $completedToday = count($entriesCompleted);

            $avgWaitSql = "
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, called_at)) as avg_wait
                FROM queue_entries
                WHERE queue_id = ? AND called_at IS NOT NULL AND DATE(called_at) = CURDATE()
            ";
            $avgResult = $db->query($avgWaitSql, [$id]);
            $avgWait = (int)($avgResult[0]['avg_wait'] ?? 0);

            // 4. User entry (if authenticated)
            $userEntry = null;
            if ($userId) {
                $userEntrySql = "
                    SELECT * FROM queue_entries
                    WHERE queue_id = ? AND user_id = ? AND status = 'waiting'
                    ORDER BY created_at DESC LIMIT 1
                ";
                $userEntryResult = $db->query($userEntrySql, [$id, $userId]);
                if (!empty($userEntryResult)) {
                    $ue = $userEntryResult[0];
                    $pos = $ue['position'];
                    $userEntry = [
                        'entry_id' => (int)$ue['id'],
                        'position' => (int)$pos,
                        'estimated_wait_minutes' => max(0, ((int)$pos - 1) * $serviceDuration),
                    ];
                }
            }

            Response::success([
                'queue' => $queue,
                'entries' => $entries,
                'entries_serving' => $entriesServing,
                'entries_completed' => $entriesCompleted,
                'statistics' => [
                    'total_waiting' => count($entries),
                    'total_being_served' => $beingServed,
                    'total_completed_today' => $completedToday,
                    'average_wait_time_minutes' => $avgWait,
                ],
                'user_entry' => $userEntry,
            ]);

        }
        catch (\Exception $e) {
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
        $userId = (int)$request->user['id'];

        try {
            $queueService = new QueueService();
            $queueService->leave($entryId, $userId);

            Logger::info('User left queue', [
                'entry_id' => $entryId,
                'user_id' => $userId,
            ], $request->requestId);

            AuditService::logFromRequest($request, 'queue_leave', 'queue', (string)$entryId, null, null, [
                'entry_id' => $entryId,
            ]);

            Response::success([
                'message' => 'Successfully left queue',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to leave queue', [
                'entry_id' => $entryId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Queue entry not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot cancel this queue entry', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Cannot cancel') !== false) {
                Response::error('INVALID_STATUS', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to leave queue', $request->requestId);
            }
        }
    }

    /**
     * POST /api/v1/queues/:id/call-next
     * 
     * Call next person in queue (requires professional/manager or admin role)
     */
    public function callNext(Request $request, int $id): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
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

            AuditService::logFromRequest($request, 'queue_call_next', 'queue', (string)$id, null, null, [
                'type' => $result['type'],
            ]);

            Response::success([
                'message' => 'Successfully called next',
                'type' => $result['type'],
                'called' => $result['data'],
            ]);

        }
        catch (\Exception $e) {
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

            AuditService::logFromRequest($request, 'create', 'queue', (string)$queueId, (int)$data['establishment_id'], null, [
                'name' => trim($data['name']),
            ]);

            Response::created([
                'queue' => $queue,
                'message' => 'Queue created successfully',
            ]);

        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
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
            $body = $request->getBody();

            // Verify queue exists using Model
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $updateData = [];

            if (isset($body['name'])) {
                $updateData['name'] = $body['name'];
            }

            if (isset($body['status'])) {
                if (!in_array($body['status'], ['open', 'closed'])) {
                    Response::error('BAD_REQUEST', 'Invalid status. Must be: open or closed', 400, $request->requestId);
                    return;
                }
                $updateData['status'] = $body['status'];
            }

            if (empty($updateData)) {
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

            Queue::update($id, $updateData);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $queue[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'queue', (string)$id, null, null, [
                'entity_name' => $queue['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success(['message' => 'Queue updated successfully']);

        }
        catch (\Exception $e) {
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
            // Check if queue exists using Model
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            Queue::delete($id);

            AuditService::logFromRequest($request, 'delete', 'queue', (string)$id, null, null, [
                'name' => $queue['name'] ?? null,
                'establishment_id' => $queue['establishment_id'] ?? null,
                'status' => $queue['status'] ?? null,
            ]);

            Response::success(['message' => 'Queue deleted successfully']);

        }
        catch (\Exception $e) {
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
        }
        catch (\Exception $e) {
            Logger::error('Failed to generate queue code', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to generate access code', $request->requestId);
        }
    }

    /**
     * GET /api/v1/queues/:id/access-codes
     * 
     * List access codes for a queue (manager/admin only)
     */
    public function listCodes(Request $request, int $id): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $codes = QueueAccessCode::getByQueue($id);

            Response::success([
                'access_codes' => $codes,
                'total' => count($codes),
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to list queue access codes', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve access codes', $request->requestId);
        }
    }

    /**
     * GET /api/v1/queues/:id/access-codes/:codeId
     * 
     * Get single access code (manager/admin only)
     */
    public function getCode(Request $request, int $id, int $codeId): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $code = QueueAccessCode::find($codeId);
            if (!$code || (int)$code['queue_id'] !== $id) {
                Response::notFound('Access code not found', $request->requestId);
                return;
            }

            Response::success(['access_code' => $code]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get access code', [
                'queue_id' => $id,
                'code_id' => $codeId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve access code', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/access-codes/:codeId/deactivate
     * 
     * Deactivate an access code (manager/admin only)
     */
    public function deactivateCode(Request $request, int $id, int $codeId): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $code = QueueAccessCode::find($codeId);
            if (!$code || (int)$code['queue_id'] !== $id) {
                Response::notFound('Access code not found', $request->requestId);
                return;
            }

            QueueAccessCode::deactivate($codeId);
            $updated = QueueAccessCode::find($codeId);

            AuditService::logFromRequest($request, 'deactivate_code', 'queue', (string)$id, null, null, [
                'code_id' => $codeId,
            ]);

            Logger::info('Access code deactivated', [
                'queue_id' => $id,
                'code_id' => $codeId,
            ], $request->requestId);

            Response::success([
                'access_code' => $updated,
                'message' => 'Access code deactivated successfully',
            ]);
        }
        catch (\Exception $e) {
            Logger::error('Failed to deactivate access code', [
                'queue_id' => $id,
                'code_id' => $codeId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to deactivate access code', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/queues/:id/access-codes/:codeId
     * 
     * Delete an access code (manager/admin only)
     */
    public function deleteCode(Request $request, int $id, int $codeId): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $code = QueueAccessCode::find($codeId);
            if (!$code || (int)$code['queue_id'] !== $id) {
                Response::notFound('Access code not found', $request->requestId);
                return;
            }

            QueueAccessCode::delete($codeId);

            AuditService::logFromRequest($request, 'delete_code', 'queue', (string)$id, null, null, [
                'code_id' => $codeId,
                'code' => $code['code'],
            ]);

            Logger::info('Access code deleted', [
                'queue_id' => $id,
                'code_id' => $codeId,
            ], $request->requestId);

            Response::success(['message' => 'Access code deleted successfully']);
        }
        catch (\Exception $e) {
            Logger::error('Failed to delete access code', [
                'queue_id' => $id,
                'code_id' => $codeId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to delete access code', $request->requestId);
        }
    }
}
