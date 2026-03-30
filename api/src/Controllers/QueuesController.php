<?php

namespace QueueMaster\Controllers;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Services\QueueService;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\QueueEntryEventService;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Models\Queue;
use QueueMaster\Models\QueueEntry;
use QueueMaster\Models\QueueAccessCode;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Service;
use QueueMaster\Models\QueueProfessional;
use QueueMaster\Models\QueueService as QueueServiceModel;
use QueueMaster\Models\User;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Services\ProfessionalMembershipService;
use QueueMaster\Services\QueueReadService;

/**
 * QueuesController - Queue Management Endpoints
 * 
 * Handles queue operations: list, get, join, status, leave, callNext
 */
class QueuesController
{
    private ContextAccessService $accessService;
    private ProfessionalMembershipService $membershipService;
    private QueueEntryEventService $queueEntryEventService;
    private QueueReadService $queueReadService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
        $this->membershipService = new ProfessionalMembershipService();
        $this->queueEntryEventService = new QueueEntryEventService();
        $this->queueReadService = new QueueReadService();
    }

    /**
     * GET /api/v1/queues
     * 
     * List queues (optionally filter by establishment)
     */
    public function list(Request $request): void
    {
        try {
            $params = $request->getQuery();
            $isStaff = $this->accessService->isStaff($request->user);

            $conditions = [];

            if (isset($params['establishment_id'])) {
                $conditions['establishment_id'] = (int)$params['establishment_id'];
            }

            if (isset($params['status'])) {
                $conditions['status'] = $params['status'];
            } elseif (!$isStaff) {
                $conditions['status'] = 'open';
            }

            // Get queues using Model
            $queues = Queue::all($conditions, 'created_at', 'DESC');

            if ($isStaff && !$this->accessService->isAdmin($request->user)) {
                $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($request->user);
                $queues = empty($accessibleEstablishmentIds)
                    ? []
                    : array_values(array_filter(
                        $queues,
                        static fn(array $queue): bool => in_array((int)$queue['establishment_id'], $accessibleEstablishmentIds, true)
                    ));
            }

            $queues = $this->queueReadService->enrichQueuesList($queues);

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

            if ($this->accessService->isStaff($request->user)) {
                $this->accessService->requireQueueAccess(
                    $request->user,
                    $queue,
                    'Você não tem acesso a esta fila'
                );
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

            $queue['permissions'] = $this->buildQueuePermissions($request->user, $queue);

            // Get waiting count
            $waitingEntries = QueueEntry::getWaitingByQueue($id);
            $queue['waiting_count'] = count($waitingEntries);

            Response::success([
                'queue' => $queue,
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
    public function join(Request $request, ?int $id = null): void
    {
        $data = $request->all();
        $joinContext = $this->resolveJoinContext($data, $id, $request->requestId);
        if ($joinContext === null) {
            return;
        }

        $id = $joinContext['queue_id'];
        if ($joinContext['access_code'] !== null) {
            $data['access_code'] = $joinContext['access_code'];
        }

        $currentUserId = (int)$request->user['id'];
        $currentRole = $request->user['role'] ?? 'user';

        // Staff can add other users by passing user_id in body
        $userId = $currentUserId;
        if (isset($data['user_id']) && !empty($data['user_id']) && in_array($currentRole, ['admin', 'manager', 'professional'])) {
            $userId = (int)$data['user_id'];
        }

        // Validate input
        $errors = Validator::make($data, [
            'priority' => 'integer|min:0|max:10',
            'status' => 'in:waiting,serving',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        // Only staff can set initial status other than 'waiting'
        $status = 'waiting';
        if (isset($data['status']) && in_array($currentRole, ['admin', 'manager', 'professional'])) {
            $status = $data['status'];
        }

        $queue = Queue::find($id);
        if (!$queue) {
            Response::notFound('Queue not found', $request->requestId);
            return;
        }

        $isStaffMutation = $userId !== $currentUserId || $status !== 'waiting';
        if ($isStaffMutation) {
            try {
                $this->accessService->requireQueueAccess(
                    $request->user,
                    $queue,
                    'Você não tem permissão para adicionar pessoas nesta fila'
                );
            } catch (\RuntimeException $e) {
                Response::forbidden($e->getMessage(), $request->requestId);
                return;
            }
        }

        $activeEntry = $this->findLatestActiveEntryForAnyQueue($userId);
        if ($activeEntry) {
            $activeQueueId = (int)$activeEntry['queue_id'];
            $activeQueueName = $activeQueueId === $id
                ? ($queue['name'] ?? null)
                : (Queue::find($activeQueueId)['name'] ?? null);
            $errorCode = $activeQueueId === $id ? 'ALREADY_IN_QUEUE' : 'ALREADY_IN_ACTIVE_QUEUE';
            $errorMessage = $activeQueueId === $id
                ? 'Este usuario ja possui uma entrada ativa nesta fila'
                : 'Este usuario ja possui uma entrada ativa em outra fila';

            Response::error(
                $errorCode,
                $errorMessage,
                409,
                $request->requestId,
                [
                    'queue_id' => $activeQueueId,
                    'queue_name' => $activeQueueName,
                    'entry_public_id' => $activeEntry['public_id'] ?? null,
                    'entry_status' => $activeEntry['status'] ?? null,
                    'requested_queue_id' => $id,
                ]
            );
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
            $entry = $queueService->join($id, $userId, $priority, $status);

            AuditService::logFromRequest($request, 'queue_join', 'queue', (string)$id, null, null, [
                'entry_id' => $entry['id'],
            ]);

            Logger::info('User joined queue', [
                'queue_id' => $id,
                'user_id' => $userId,
                'entry_id' => $entry['id'],
            ], $request->requestId);

            Response::created([
                'entry' => $this->buildClientJoinEntryPayload($entry),
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
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            if ($request->user && $this->accessService->isStaff($request->user)) {
                $this->accessService->requireQueueAccess(
                    $request->user,
                    $queue,
                    'Você não tem acesso a esta fila'
                );
            }

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

            $queue['permissions'] = $this->buildQueuePermissions($request->user, $queue);
            $canViewPeopleDetails = !empty($queue['permissions']['can_view_people_details']);
            $canViewCompletedPeople = !empty($queue['permissions']['can_view_completed_people']);

            $queryParams = $request->getQuery();
            $statusPayload = $this->queueReadService->buildQueueStatusPayload(
                $queue,
                $userId,
                $canViewPeopleDetails,
                $canViewCompletedPeople,
                $queryParams['completed_from'] ?? null,
                $queryParams['completed_to'] ?? null
            );

            $queue['waiting_count'] = $statusPayload['waiting_count'];

            Response::success([
                'queue' => $queue,
                'entries' => $statusPayload['entries'],
                'entries_serving' => $statusPayload['entries_serving'],
                'entries_completed' => $statusPayload['entries_completed'],
                'statistics' => $statusPayload['statistics'],
                'user_entry' => $statusPayload['user_entry'],
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
     * GET /api/v1/queues/current
     *
     * Return the current user's latest active queue entry across all queues.
     */
    public function current(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            $activeEntry = $this->findLatestActiveEntryForAnyQueue($userId);
            if (!$activeEntry) {
                Response::error('NO_ACTIVE_QUEUE', 'No active queue found', 404, $request->requestId);
                return;
            }

            $payload = $this->queueReadService->buildCurrentActiveQueuePayload($activeEntry);
            if ($payload === null) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            Response::success($payload);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get current active queue', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve current active queue', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/leave
     * 
     * Leave queue (cancel current user's active entry in this queue)
     */
    public function leave(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $activeEntry = $this->findLatestActiveEntryForUser($id, $userId);
            if (!$activeEntry) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $entryId = (int)$activeEntry['id'];
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
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para operar esta fila'
            );

            $queueService = new QueueService();
            $result = $queueService->callNext($id, $establishmentId, $professionalId, (int)$request->user['id']);

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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
     * Create queue
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

            $this->accessService->requireEstablishmentAccess(
                $request->user,
                $establishment,
                'Você não tem permissão para criar filas neste estabelecimento'
            );

            if (!in_array($data['status'], ['open', 'closed', 'paused'], true)) {
                Response::error('BAD_REQUEST', 'Invalid status. Must be: open, closed or paused', 400, $request->requestId);
                return;
            }

            $serviceId = isset($data['service_id']) && $data['service_id'] !== '' ? (int)$data['service_id'] : null;
            if ($serviceId !== null && !$this->accessService->serviceBelongsToEstablishment($serviceId, (int)$data['establishment_id'])) {
                Response::error('INVALID_SERVICE', 'Service does not belong to this establishment', 422, $request->requestId);
                return;
            }

            $queueId = Queue::create([
                'establishment_id' => (int)$data['establishment_id'],
                'service_id' => $serviceId,
                'name' => trim($data['name']),
                'status' => $data['status'],
                'description' => isset($data['description']) ? trim((string)$data['description']) : null,
                'max_capacity' => isset($data['max_capacity']) && $data['max_capacity'] !== '' ? (int)$data['max_capacity'] : null,
            ]);

            $this->syncPrimaryQueueServiceLink($queueId, $serviceId);

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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
     * Update queue
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para editar esta fila'
            );

            $updateData = [];

            if (isset($body['name'])) {
                $updateData['name'] = $body['name'];
            }

            if (isset($body['status'])) {
                if (!in_array($body['status'], ['open', 'closed', 'paused'])) {
                    Response::error('BAD_REQUEST', 'Invalid status. Must be: open, closed or paused', 400, $request->requestId);
                    return;
                }
                $updateData['status'] = $body['status'];
            }

            if (isset($body['description'])) {
                $updateData['description'] = $body['description'];
            }

            if (isset($body['max_capacity'])) {
                $updateData['max_capacity'] = $body['max_capacity'] !== null && $body['max_capacity'] !== '' ? (int)$body['max_capacity'] : null;
            }

            if (isset($body['service_id'])) {
                $serviceId = $body['service_id'] !== null && $body['service_id'] !== '' ? (int)$body['service_id'] : null;
                if ($serviceId !== null && !$this->accessService->serviceBelongsToEstablishment($serviceId, (int)$queue['establishment_id'])) {
                    Response::error('INVALID_SERVICE', 'Service does not belong to this establishment', 422, $request->requestId);
                    return;
                }
                $updateData['service_id'] = $serviceId;
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
            if (array_key_exists('service_id', $updateData)) {
                $this->syncPrimaryQueueServiceLink($id, $updateData['service_id']);
            }

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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
     * Delete queue
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para excluir esta fila'
            );

            Queue::delete($id);

            AuditService::logFromRequest($request, 'delete', 'queue', (string)$id, null, null, [
                'name' => $queue['name'] ?? null,
                'establishment_id' => $queue['establishment_id'] ?? null,
                'status' => $queue['status'] ?? null,
            ]);

            Response::success(['message' => 'Queue deleted successfully']);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para gerenciar códigos desta fila'
            );

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
                'code' => $code,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para visualizar códigos desta fila'
            );

            $codes = QueueAccessCode::getByQueue($id);

            Response::success([
                'access_codes' => $codes,
                'total' => count($codes),
            ]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para visualizar este código'
            );

            $code = QueueAccessCode::find($codeId);
            if (!$code || (int)$code['queue_id'] !== $id) {
                Response::notFound('Access code not found', $request->requestId);
                return;
            }

            Response::success(['access_code' => $code]);
        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para gerenciar códigos desta fila'
            );

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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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
     * PUT /api/v1/queues/entries/:entryId/status
     * 
     * Update entry status (staff only)
     * Supports transitions: waiting→serving, serving→done, serving→no_show,
     * called→serving, called→no_show, done→waiting (reallocate),
     * serving→waiting (return to queue), any→cancelled (remove)
     */
    public function updateEntryStatus(Request $request, int $entryId): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff members can update entry status', $request->requestId);
            return;
        }

        $data = $request->all();

        $errors = Validator::make($data, [
            'status' => 'required',
            'notes' => 'max:1000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $newStatus = $data['status'];
        $validStatuses = ['waiting', 'called', 'serving', 'done', 'no_show', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            Response::error('INVALID_STATUS', 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400, $request->requestId);
            return;
        }

        try {
            $entry = QueueEntry::find($entryId);
            if (!$entry) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $queue = Queue::find((int)$entry['queue_id']);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para atualizar esta fila'
            );

            $db = Database::getInstance();
            $oldStatus = $entry['status'];
            $updateData = ['status' => $newStatus];

            // Set timestamps based on transition
            switch ($newStatus) {
                case 'called':
                    $updateData['called_at'] = date('Y-m-d H:i:s');
                    break;
                case 'serving':
                    $updateData['served_at'] = date('Y-m-d H:i:s');
                    if (empty($entry['called_at'])) {
                        $updateData['called_at'] = date('Y-m-d H:i:s');
                    }
                    // Assign professional: use provided professional_id or the current authenticated user
                    $assignedProfessionalId = isset($data['professional_id'])
                        ? (int)$data['professional_id']
                        : (int)$request->user['id'];

                    if (!$this->accessService->userBelongsToEstablishment($assignedProfessionalId, (int)$queue['establishment_id'])) {
                        Response::error('INVALID_PROFESSIONAL', 'Professional does not belong to this establishment', 422, $request->requestId);
                        return;
                    }
                    $updateData['professional_id'] = $assignedProfessionalId;
                    break;
                case 'done':
                    $updateData['completed_at'] = date('Y-m-d H:i:s');
                    if (empty($entry['served_at'])) {
                        $updateData['served_at'] = date('Y-m-d H:i:s');
                    }
                    break;
                case 'no_show':
                    $updateData['completed_at'] = date('Y-m-d H:i:s');
                    break;
                case 'waiting':
                    // Re-enqueue: clear progress timestamps, assign new position at END, reset priority
                    $updateData['called_at'] = null;
                    $updateData['served_at'] = null;
                    $updateData['completed_at'] = null;
                    $updateData['professional_id'] = null;
                    $updateData['priority'] = 0; // Reset priority when returning to queue
                    $updateData['created_at'] = date('Y-m-d H:i:s'); // Reset created_at for fair ordering
                    $updateData['position'] = $this->queueReadService->resolveRequeuePosition((int)$entry['queue_id']);
                    break;
            }

            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            $db->beginTransaction();
            QueueEntry::update($entryId, $updateData);

            $updatedEntry = QueueEntry::find($entryId);
            $this->recordQueueEntryStatusEvents($entry, $updatedEntry, (int)$request->user['id']);
            $db->commit();

            AuditService::logFromRequest($request, 'update', 'queue_entry', (string)$entryId, null, null, [
                'queue_id' => $entry['queue_id'],
                'changes' => ['status' => ['from' => $oldStatus, 'to' => $newStatus]],
            ]);

            Logger::info('Queue entry status updated', [
                'entry_id' => $entryId,
                'from' => $oldStatus,
                'to' => $newStatus,
            ], $request->requestId);

            Response::success([
                'entry' => $updatedEntry,
                'message' => 'Entry status updated successfully',
            ]);

        } catch (\RuntimeException $e) {
            $db = Database::getInstance();
            if ($db->inTransaction()) {
                $db->rollback();
            }
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            $db = Database::getInstance();
            if ($db->inTransaction()) {
                $db->rollback();
            }
            Logger::error('Failed to update entry status', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update entry status', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/queues/entries/:entryId
     * 
     * Remove entry from queue (staff can remove any, users can only remove their own)
     */
    public function removeEntry(Request $request, int $entryId): void
    {
        $userRole = $request->user['role'];
        $userId = (int)$request->user['id'];

        try {
            $entry = QueueEntry::find($entryId);
            if (!$entry) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $queue = Queue::find((int)$entry['queue_id']);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            // Staff can remove any entry; regular users can only cancel their own
            $isStaff = in_array($userRole, ['professional', 'manager', 'admin']);
            if ($isStaff) {
                $this->accessService->requireQueueAccess(
                    $request->user,
                    $queue,
                    'Você não tem permissão para remover pessoas desta fila'
                );
            }

            if (!$isStaff && (int)$entry['user_id'] !== $userId) {
                Response::forbidden('You cannot remove this entry', $request->requestId);
                return;
            }

            if (!in_array($entry['status'], ['waiting', 'called', 'serving'], true)) {
                Response::error('INVALID_STATUS', 'Only active queue entries can be removed', 400, $request->requestId);
                return;
            }

            $this->cancelQueueEntry(
                $entry,
                'cancelled',
                $isStaff ? 'staff' : 'client',
                $userId
            );

            AuditService::logFromRequest($request, 'queue_remove', 'queue_entry', (string)$entryId, null, null, [
                'queue_id' => $entry['queue_id'],
                'removed_user_id' => $entry['user_id'],
                'changes' => [
                    'status' => [
                        'from' => $entry['status'] ?? null,
                        'to' => 'cancelled',
                    ],
                ],
            ]);

            Logger::info('Queue entry removed', [
                'entry_id' => $entryId,
                'removed_by' => $userId,
            ], $request->requestId);

            Response::success(['message' => 'Entry removed successfully']);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to remove entry', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to remove entry', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/batch-remove
     * 
     * Remove multiple entries from queue (staff only)
     */
    public function batchRemoveEntries(Request $request, int $id): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff members can batch remove entries', $request->requestId);
            return;
        }

        $data = $request->all();
        if (empty($data['entry_ids']) || !is_array($data['entry_ids'])) {
            Response::error('BAD_REQUEST', 'entry_ids array is required', 400, $request->requestId);
            return;
        }

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para remover pessoas desta fila'
            );

            $entryIds = array_values(array_unique(array_map('intval', $data['entry_ids'])));
            if (empty($entryIds)) {
                Response::error('BAD_REQUEST', 'No valid entry ids provided', 400, $request->requestId);
                return;
            }

            $activeIds = [];
            foreach ($entryIds as $candidateId) {
                $entry = QueueEntry::find($candidateId);
                if (!$entry) {
                    continue;
                }

                if ((int)$entry['queue_id'] !== $id) {
                    continue;
                }

                if (!in_array($entry['status'], ['waiting', 'called', 'serving'], true)) {
                    continue;
                }

                $this->cancelQueueEntry($entry, 'cancelled', 'staff', (int)$request->user['id']);
                $activeIds[] = (int)$entry['id'];
            }

            if (empty($activeIds)) {
                Response::error('INVALID_STATUS', 'No active entries were found to remove', 400, $request->requestId);
                return;
            }

            AuditService::logFromRequest($request, 'queue_batch_remove', 'queue', (string)$id, null, null, [
                'removed_count' => count($activeIds),
            ]);

            Response::success([
                'message' => count($activeIds) . ' entries removed successfully',
                'removed_count' => count($activeIds),
            ]);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to batch remove entries', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to batch remove entries', $request->requestId);
        }
    }

    /**
     * GET /api/v1/queues/:id/reports
     * 
     * Get queue reports and analytics (staff only)
     */
    public function reports(Request $request, int $id): void
    {
        $controller = new ReportsController();
        $controller->queueDrilldown($request, $id);
    }

    /**
     * GET /api/v1/queues/:id/professionals
     * 
     * List professionals linked to this queue's establishment
     */
    public function listProfessionals(Request $request, int $id): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para visualizar profissionais desta fila'
            );

            $establishment = Establishment::find((int)$queue['establishment_id']);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $queue['business_id'] = (int)($establishment['business_id'] ?? 0);
            $professionals = $this->membershipService->getQueueCandidateProfessionals($queue);

            Response::success([
                'professionals' => $professionals,
                'total' => count($professionals),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to list queue professionals', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve professionals', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/queues/:id/access-codes/:codeId
     * 
     * Update access code details (manager/admin)
     */
    public function updateCode(Request $request, int $id, int $codeId): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para editar códigos desta fila'
            );

            $code = QueueAccessCode::find($codeId);
            if (!$code || (int)$code['queue_id'] !== $id) {
                Response::notFound('Access code not found', $request->requestId);
                return;
            }

            $body = $request->getBody();
            $updateData = [];

            if (isset($body['expires_at'])) {
                $updateData['expires_at'] = $body['expires_at'] ?: null;
            }
            if (isset($body['max_uses'])) {
                $updateData['max_uses'] = $body['max_uses'] !== null && $body['max_uses'] !== '' ? (int)$body['max_uses'] : null;
            }
            if (isset($body['is_active'])) {
                $updateData['is_active'] = (bool)$body['is_active'] ? 1 : 0;
            }

            if (empty($updateData)) {
                Response::error('NO_FIELDS_TO_UPDATE', 'No valid fields provided. Available: expires_at, max_uses, is_active', 400, $request->requestId);
                return;
            }

            QueueAccessCode::update($codeId, $updateData);
            $updated = QueueAccessCode::find($codeId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $code[$field] ?? null, 'to' => $newValue];
            }

            AuditService::logFromRequest($request, 'update', 'queue_access_code', (string)$codeId, null, null, [
                'queue_id' => $id,
                'entity_name' => $code['code'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'access_code' => $updated,
                'message' => 'Access code updated successfully',
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to update access code', [
                'queue_id' => $id,
                'code_id' => $codeId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update access code', $request->requestId);
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

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para excluir códigos desta fila'
            );

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
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
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

    // ========================================================================
    // QUEUE PROFESSIONALS
    // ========================================================================

    /**
     * GET /api/v1/queues/:id/queue-professionals
     * 
     * List professionals assigned to this queue
     */
    public function listQueueProfessionals(Request $request, int $id): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para visualizar profissionais desta fila'
            );

            $professionals = QueueProfessional::findByQueue($id);

            Response::success([
                'professionals' => $professionals,
                'total' => count($professionals),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to list queue professionals', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve queue professionals', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/queue-professionals
     * 
     * Add a professional to this queue
     */
    public function addQueueProfessional(Request $request, int $id): void
    {
        $data = $request->all();
        $currentUserId = (int)$request->user['id'];
        $currentRole = $request->user['role'] ?? 'user';

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para gerenciar profissionais desta fila'
            );

            // Determine which user_id to add
            $userId = $currentUserId;
            if (isset($data['user_id']) && in_array($currentRole, ['admin', 'manager'])) {
                $userId = (int)$data['user_id'];
            } elseif (!in_array($currentRole, ['admin', 'manager', 'professional'])) {
                Response::forbidden('Only staff can be added as queue professionals', $request->requestId);
                return;
            }

            // Check if already assigned
            $existing = QueueProfessional::findByQueueAndUser($id, $userId);
            if ($existing) {
                Response::error('ALREADY_ASSIGNED', 'This professional is already assigned to this queue', 409, $request->requestId);
                return;
            }

            $establishment = Establishment::find((int)$queue['establishment_id']);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $businessId = (int)($establishment['business_id'] ?? 0);
            $belongsToEstablishment = $this->accessService->userBelongsToEstablishment($userId, (int)$queue['establishment_id']);
            $businessRole = $businessId > 0 ? \QueueMaster\Models\BusinessUser::getRole($businessId, $userId) : null;

            if (!$belongsToEstablishment) {
                $businessRole = $businessId > 0 ? \QueueMaster\Models\BusinessUser::getRole($businessId, $userId) : null;

                if ($businessRole !== \QueueMaster\Models\BusinessUser::ROLE_PROFESSIONAL) {
                    Response::error('INVALID_PROFESSIONAL', 'User does not belong to this establishment or business', 422, $request->requestId);
                    return;
                }

                $this->membershipService->ensureProfessionalRole($userId);
                $this->membershipService->ensureEstablishmentProfessional($businessId, (int)$queue['establishment_id'], $userId);
            } elseif ($businessId > 0 && $businessRole === null) {
                $this->membershipService->ensureProfessionalRole($userId);
                $this->membershipService->ensureBusinessProfessional($businessId, $userId);
            }

            $qpId = QueueProfessional::create([
                'queue_id' => $id,
                'user_id' => $userId,
                'is_active' => true,
            ]);

            $created = QueueProfessional::find($qpId);
            $user = User::find($userId);
            if ($user) {
                $created['user_name'] = $user['name'];
                $created['user_email'] = $user['email'];
            }

            AuditService::logFromRequest($request, 'add_queue_professional', 'queue', (string)$id, null, null, [
                'professional_user_id' => $userId,
            ]);

            Response::created([
                'professional' => $created,
                'message' => 'Professional added to queue',
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to add queue professional', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to add professional', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/queues/:id/queue-professionals/:profId
     * 
     * Update queue professional (toggle active/inactive)
     */
    public function updateQueueProfessional(Request $request, int $id, int $profId): void
    {
        $data = $request->all();

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para editar profissionais desta fila'
            );

            $qp = QueueProfessional::find($profId);
            if (!$qp || (int)$qp['queue_id'] !== $id) {
                Response::notFound('Queue professional not found', $request->requestId);
                return;
            }

            $updateData = [];
            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool)$data['is_active'];
            }

            if (empty($updateData)) {
                Response::error('NO_FIELDS', 'No valid fields provided', 400, $request->requestId);
                return;
            }

            QueueProfessional::update($profId, $updateData);
            $updated = QueueProfessional::find($profId);

            $user = User::find((int)$updated['user_id']);
            if (!empty($user)) {
                $updated['user_name'] = $user['name'];
                $updated['user_email'] = $user['email'];
            }

            Response::success([
                'professional' => $updated,
                'message' => 'Queue professional updated',
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to update queue professional', [
                'queue_id' => $id,
                'prof_id' => $profId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update professional', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/queues/:id/queue-professionals/:profId
     * 
     * Remove a professional from this queue
     */
    public function removeQueueProfessional(Request $request, int $id, int $profId): void
    {
        $currentUserId = (int)$request->user['id'];
        $currentRole = $request->user['role'] ?? 'user';

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para remover profissionais desta fila'
            );

            $qp = QueueProfessional::find($profId);
            if (!$qp || (int)$qp['queue_id'] !== $id) {
                Response::notFound('Queue professional not found', $request->requestId);
                return;
            }

            // Professionals can only remove themselves; managers/admins can remove anyone
            if (!in_array($currentRole, ['admin', 'manager']) && (int)$qp['user_id'] !== $currentUserId) {
                Response::forbidden('You can only remove yourself from the queue', $request->requestId);
                return;
            }

            QueueProfessional::delete($profId);

            AuditService::logFromRequest($request, 'remove_queue_professional', 'queue', (string)$id, null, null, [
                'professional_user_id' => $qp['user_id'],
            ]);

            Response::success(['message' => 'Professional removed from queue']);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to remove queue professional', [
                'queue_id' => $id,
                'prof_id' => $profId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to remove professional', $request->requestId);
        }
    }

    // ========================================================================
    // ENTRY POSITION & PRIORITY
    // ========================================================================

    /**
     * PUT /api/v1/queues/entries/:entryId/position
     * 
     * Move entry up or down in the visual queue order.
     * Swaps position (and priority) with the adjacent entry in visual order.
     */
    public function updateEntryPosition(Request $request, int $entryId): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff can reorder entries', $request->requestId);
            return;
        }

        $data = $request->all();
        $direction = $data['direction'] ?? null;

        if (!in_array($direction, ['up', 'down'])) {
            Response::validationError(['direction' => 'Must be "up" or "down"'], $request->requestId);
            return;
        }

        $db = Database::getInstance();

        try {
            $entry = QueueEntry::find($entryId);
            if (!$entry || ($entry['status'] ?? null) !== 'waiting') {
                Response::notFound('Entry not found or not in waiting status', $request->requestId);
                return;
            }

            $queue = Queue::find((int)$entry['queue_id']);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para reordenar esta fila'
            );

            // Get all waiting entries in visual order (priority DESC, position ASC)
            $entries = QueueEntry::getWaitingByQueue((int)$entry['queue_id']);

            $currentIndex = null;
            foreach ($entries as $i => $e) {
                if ((int)$e['id'] === (int)$entryId) {
                    $currentIndex = $i;
                    break;
                }
            }

            if ($currentIndex === null) {
                Response::notFound('Entry not found in queue', $request->requestId);
                return;
            }

            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if ($targetIndex < 0 || $targetIndex >= count($entries)) {
                Response::error('CANNOT_MOVE', 'Cannot move further in this direction', 400, $request->requestId);
                return;
            }

            $target = $entries[$targetIndex];

            // Swap position and priority values atomically
            $db->beginTransaction();
            QueueEntry::update((int)$entryId, [
                'position' => $target['position'],
                'priority' => $target['priority'],
            ]);
            QueueEntry::update((int)$target['id'], [
                'position' => $entry['position'],
                'priority' => $entry['priority'],
            ]);
            $db->commit();

            Response::success(['message' => 'Position updated']);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            Logger::error('Failed to update entry position', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update position', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/queues/entries/:entryId/priority
     * 
     * Change entry priority level (0=Normal, 1=Priority, 2=Very High Priority)
     */
    public function updateEntryPriority(Request $request, int $entryId): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff can change entry priority', $request->requestId);
            return;
        }

        $data = $request->all();
        $priority = $data['priority'] ?? null;

        if ($priority === null || !is_numeric($priority) || (int)$priority < 0 || (int)$priority > 2) {
            Response::validationError(['priority' => 'Must be 0, 1, or 2'], $request->requestId);
            return;
        }

        try {
            $entry = QueueEntry::find($entryId);
            if (!$entry || ($entry['status'] ?? null) !== 'waiting') {
                Response::notFound('Entry not found or not in waiting status', $request->requestId);
                return;
            }

            $queue = Queue::find((int)$entry['queue_id']);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para alterar prioridades desta fila'
            );

            QueueEntry::update($entryId, ['priority' => (int)$priority]);

            $updated = QueueEntry::find($entryId);

            Response::success([
                'entry' => $updated,
                'message' => 'Priority updated',
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to update entry priority', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to update priority', $request->requestId);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Queue Services (linking services to a specific queue)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/queues/:id/services
     *
     * List services linked to this queue
     */
    public function listQueueServices(Request $request, int $id): void
    {
        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para visualizar serviços desta fila'
            );

            $this->syncPrimaryQueueServiceLink($id, isset($queue['service_id']) ? (int)$queue['service_id'] : null);

            $services = QueueServiceModel::findByQueue($id);

            Response::success([
                'services' => $services,
                'total' => count($services),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to list queue services', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to list queue services', $request->requestId);
        }
    }

    /**
     * POST /api/v1/queues/:id/services
     *
     * Link an existing service to this queue.
     * Optionally create a brand-new service first (if 'create_new' flag is set).
     */
    public function addQueueService(Request $request, int $id): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff can manage queue services', $request->requestId);
            return;
        }

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para gerenciar serviços desta fila'
            );

            $data = $request->all();

            // Option A: batch link existing services by service_ids array
            if (!empty($data['service_ids']) && is_array($data['service_ids'])) {
                $linked = [];
                $skipped = [];
                foreach ($data['service_ids'] as $sid) {
                    $serviceId = (int)$sid;
                    $service = Service::find($serviceId);
                    if (!$service || (int)$service['establishment_id'] !== (int)$queue['establishment_id']) {
                        $skipped[] = $serviceId;
                        continue;
                    }
                    $existing = QueueServiceModel::findByQueueAndService($id, $serviceId);
                    if ($existing) {
                        $skipped[] = $serviceId;
                        continue;
                    }
                    $linkId = QueueServiceModel::create($id, $serviceId);
                    $linked[] = ['service_id' => $serviceId, 'queue_service_id' => $linkId];
                }

                Response::created([
                    'linked' => $linked,
                    'skipped' => $skipped,
                    'message' => count($linked) . ' service(s) linked to queue',
                ]);
                return;
            }

            // Option A-single: link a single existing service by service_id
            if (!empty($data['service_id'])) {
                $serviceId = (int)$data['service_id'];

                $service = Service::find($serviceId);
                if (!$service) {
                    Response::notFound('Service not found', $request->requestId);
                    return;
                }
                if ((int)$service['establishment_id'] !== (int)$queue['establishment_id']) {
                    Response::error('INVALID_SERVICE', 'Service does not belong to this establishment', 400, $request->requestId);
                    return;
                }

                $existing = QueueServiceModel::findByQueueAndService($id, $serviceId);
                if ($existing) {
                    Response::error('ALREADY_LINKED', 'This service is already linked to this queue', 409, $request->requestId);
                    return;
                }

                $linkId = QueueServiceModel::create($id, $serviceId);
                $this->syncPrimaryQueueServiceLink($id, (int)($queue['service_id'] ?? 0));

                Response::created([
                    'queue_service_id' => $linkId,
                    'message' => 'Service linked to queue',
                ]);
                return;
            }

            // Option B: create a new service and link it
            if (!empty($data['create_new'])) {
                $errors = Validator::make($data, [
                    'name' => 'required|min:2|max:150',
                    'duration_minutes' => 'required|integer',
                ]);
                if (!empty($errors)) {
                    Response::validationError($errors, $request->requestId);
                    return;
                }

                $newServiceId = Service::create([
                    'establishment_id' => (int)$queue['establishment_id'],
                    'name' => trim((string)$data['name']),
                    'description' => isset($data['description']) ? trim((string)$data['description']) : null,
                    'duration_minutes' => (int)$data['duration_minutes'],
                    'price' => isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null,
                    'icon' => isset($data['icon']) && $data['icon'] !== '' ? trim((string)$data['icon']) : null,
                    'image_url' => isset($data['image_url']) && $data['image_url'] !== '' ? trim((string)$data['image_url']) : null,
                    'is_active' => 1,
                ]);

                $linkId = QueueServiceModel::create($id, $newServiceId);

                Response::created([
                    'service_id' => $newServiceId,
                    'queue_service_id' => $linkId,
                    'message' => 'Service created and linked to queue',
                ]);
                return;
            }

            Response::error('MISSING_DATA', 'Provide service_ids array, service_id to link, or create_new with service data', 400, $request->requestId);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to add queue service', [
                'queue_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to add queue service', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/queues/:id/services/:serviceId
     *
     * Unlink a service from the queue (does NOT delete the service itself)
     */
    public function removeQueueService(Request $request, int $id, int $serviceId): void
    {
        $userRole = $request->user['role'];
        if (!in_array($userRole, ['professional', 'manager', 'admin'])) {
            Response::forbidden('Only staff can manage queue services', $request->requestId);
            return;
        }

        try {
            $queue = Queue::find($id);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Você não tem permissão para gerenciar serviços desta fila'
            );

            $link = QueueServiceModel::findByQueueAndService($id, $serviceId);
            if (!$link) {
                Response::notFound('Service is not linked to this queue', $request->requestId);
                return;
            }

            QueueServiceModel::delete((int)$link['id']);
            if ((int)($queue['service_id'] ?? 0) === $serviceId) {
                Queue::update($id, ['service_id' => null]);
            }

            Response::success([
                'message' => 'Service removed from queue',
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to remove queue service', [
                'queue_id' => $id,
                'service_id' => $serviceId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to remove queue service', $request->requestId);
        }
    }

    private function syncPrimaryQueueServiceLink(int $queueId, ?int $serviceId): void
    {
        if (!$serviceId) {
            return;
        }

        $existing = QueueServiceModel::findByQueueAndService($queueId, $serviceId);
        if (!$existing) {
            QueueServiceModel::create($queueId, $serviceId);
        }
    }

    private function resolveJoinContext(array $data, ?int $queueId, ?string $requestId): ?array
    {
        $normalizedAccessCode = isset($data['access_code'])
            ? strtoupper(trim((string)$data['access_code']))
            : null;

        if ($normalizedAccessCode === '') {
            $normalizedAccessCode = null;
        }

        if ($queueId !== null) {
            if ($normalizedAccessCode === null) {
                return [
                    'queue_id' => $queueId,
                    'access_code' => null,
                ];
            }

            $code = QueueAccessCode::findByCode($normalizedAccessCode);
            if (!$code || !QueueAccessCode::isValid($code) || (int)$code['queue_id'] !== $queueId) {
                Response::error('INVALID_CODE', 'Invalid or expired access code', 400, $requestId);
                return null;
            }

            return [
                'queue_id' => $queueId,
                'access_code' => $normalizedAccessCode,
            ];
        }

        if ($normalizedAccessCode === null) {
            Response::validationError([
                'access_code' => ['Queue access code is required'],
            ], $requestId);
            return null;
        }

        $code = QueueAccessCode::findByCode($normalizedAccessCode);
        if (!$code || !QueueAccessCode::isValid($code)) {
            Response::error('INVALID_CODE', 'Invalid or expired access code', 400, $requestId);
            return null;
        }

        return [
            'queue_id' => (int)$code['queue_id'],
            'access_code' => $normalizedAccessCode,
        ];
    }

    private function findLatestActiveEntryForUser(int $queueId, int $userId): ?array
    {
        $entries = (new QueryBuilder())
            ->select('queue_entries')
            ->where('queue_id', '=', $queueId)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();

        foreach ($entries as $entry) {
            if (in_array($entry['status'] ?? null, ['waiting', 'called', 'serving'], true)) {
                return $entry;
            }
        }

        return null;
    }

    private function findLatestActiveEntryForAnyQueue(int $userId): ?array
    {
        $entries = (new QueryBuilder())
            ->select('queue_entries')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        foreach ($entries as $entry) {
            if (in_array($entry['status'] ?? null, ['waiting', 'called', 'serving'], true)) {
                return $entry;
            }
        }

        return null;
    }

    private function cancelQueueEntry(
        array $entry,
        string $eventType = 'cancelled',
        string $actorType = 'staff',
        ?int $actorUserId = null
    ): void
    {
        $db = Database::getInstance();
        $startedTransaction = false;

        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $startedTransaction = true;
        }

        $now = date('Y-m-d H:i:s');
        $calledAt = !empty($entry['called_at']) ? $entry['called_at'] : (in_array($entry['status'], ['called', 'serving'], true) ? $now : null);
        $servedAt = !empty($entry['served_at']) ? $entry['served_at'] : (($entry['status'] ?? null) === 'serving' ? $now : null);

        try {
            QueueEntry::update((int)$entry['id'], [
                'status' => 'cancelled',
                'completed_at' => $now,
                'called_at' => $calledAt,
                'served_at' => $servedAt,
            ]);

            $this->queueEntryEventService->record(
                (int)$entry['id'],
                (int)$entry['queue_id'],
                !empty($entry['user_id']) ? (int)$entry['user_id'] : null,
                $eventType,
                [
                    'previous_status' => $entry['status'] ?? null,
                ],
                $now,
                $actorType,
                $actorUserId
            );

            if ($startedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollback();
            }

            throw $e;
        }
    }

    private function recordQueueEntryStatusEvents(array $originalEntry, array $updatedEntry, int $actorUserId): void
    {
        $entryId = (int)$updatedEntry['id'];
        $queueId = (int)$updatedEntry['queue_id'];
        $userId = !empty($updatedEntry['user_id']) ? (int)$updatedEntry['user_id'] : null;
        $previousStatus = $originalEntry['status'] ?? null;
        $currentStatus = $updatedEntry['status'] ?? null;

        if ($currentStatus === 'called' && $previousStatus !== 'called' && !empty($updatedEntry['called_at'])) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'called',
                [
                    'previous_status' => $previousStatus,
                ],
                $updatedEntry['called_at'],
                'staff',
                $actorUserId
            );
        }

        if (
            $currentStatus === 'serving'
            && empty($originalEntry['called_at'])
            && !empty($updatedEntry['called_at'])
        ) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'called',
                [
                    'previous_status' => $previousStatus,
                    'implicit' => true,
                ],
                $updatedEntry['called_at'],
                'staff',
                $actorUserId
            );
        }

        if (
            in_array($currentStatus, ['serving', 'done'], true)
            && empty($originalEntry['served_at'])
            && !empty($updatedEntry['served_at'])
        ) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'serving_started',
                [
                    'previous_status' => $previousStatus,
                    'professional_id' => isset($updatedEntry['professional_id']) ? (int)$updatedEntry['professional_id'] : null,
                ],
                $updatedEntry['served_at'],
                'staff',
                $actorUserId
            );
        }

        if ($currentStatus === 'done' && $previousStatus !== 'done' && !empty($updatedEntry['completed_at'])) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'completed',
                [
                    'previous_status' => $previousStatus,
                    'professional_id' => isset($updatedEntry['professional_id']) ? (int)$updatedEntry['professional_id'] : null,
                ],
                $updatedEntry['completed_at'],
                'staff',
                $actorUserId
            );
        }

        if ($currentStatus === 'no_show' && $previousStatus !== 'no_show' && !empty($updatedEntry['completed_at'])) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'no_show',
                [
                    'previous_status' => $previousStatus,
                ],
                $updatedEntry['completed_at'],
                'staff',
                $actorUserId
            );
        }

        if ($currentStatus === 'cancelled' && $previousStatus !== 'cancelled' && !empty($updatedEntry['completed_at'])) {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'cancelled',
                [
                    'previous_status' => $previousStatus,
                ],
                $updatedEntry['completed_at'],
                'staff',
                $actorUserId
            );
        }

        if ($currentStatus === 'waiting' && $previousStatus !== 'waiting') {
            $this->queueEntryEventService->record(
                $entryId,
                $queueId,
                $userId,
                'requeued',
                [
                    'previous_status' => $previousStatus,
                    'position' => isset($updatedEntry['position']) ? (int)$updatedEntry['position'] : null,
                ],
                $updatedEntry['created_at'] ?? date('Y-m-d H:i:s'),
                'staff',
                $actorUserId
            );
        }
    }

    private function buildQueuePermissions(?array $user, array $queue): array
    {
        if (!$user) {
            return [
                'can_manage' => false,
                'can_view_reports' => false,
                'can_view_codes' => false,
                'can_view_people_details' => false,
                'can_view_completed_people' => false,
                'can_join_via_code' => true,
            ];
        }

        $canManage = $this->accessService->isStaff($user)
            && $this->accessService->canAccessQueue($user, $queue);

        return [
            'can_manage' => $canManage,
            'can_view_reports' => $canManage,
            'can_view_codes' => $canManage,
            'can_view_people_details' => $canManage,
            'can_view_completed_people' => $canManage,
            'can_join_via_code' => true,
        ];
    }

    private function buildClientJoinEntryPayload(array $entry): array
    {
        return [
            'public_id' => $entry['public_id'] ?? null,
            'queue_id' => isset($entry['queue_id']) ? (int)$entry['queue_id'] : null,
            'status' => $entry['status'] ?? 'waiting',
            'position' => isset($entry['position']) ? (int)$entry['position'] : null,
            'priority' => isset($entry['priority']) ? (int)$entry['priority'] : 0,
            'created_at' => $entry['created_at'] ?? null,
            'called_at' => $entry['called_at'] ?? null,
            'served_at' => $entry['served_at'] ?? null,
            'completed_at' => $entry['completed_at'] ?? null,
            'estimated_wait_minutes' => isset($entry['estimated_wait_minutes'])
                ? (int)$entry['estimated_wait_minutes']
                : null,
        ];
    }
}
