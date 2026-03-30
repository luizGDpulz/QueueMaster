<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Queue;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Services\QueueEntryHistoryReadService;
use QueueMaster\Utils\Logger;

/**
 * QueueEntriesController
 *
 * Read-only endpoints for queue participation history and timeline.
 */
class QueueEntriesController
{
    private ContextAccessService $accessService;
    private QueueEntryHistoryReadService $historyReadService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
        $this->historyReadService = new QueueEntryHistoryReadService();
    }

    public function current(Request $request): void
    {
        $userId = (int)($request->user['id'] ?? 0);

        try {
            $entry = $this->historyReadService->findLatestActiveEntryByUser($userId);
            if (!$entry) {
                Response::error('NO_ACTIVE_QUEUE', 'No active queue entry found', 404, $request->requestId);
                return;
            }

            Response::success([
                'entry' => $this->historyReadService->serializeEntry($entry),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get current queue entry', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve current queue entry', $request->requestId);
        }
    }

    public function history(Request $request): void
    {
        $userId = (int)($request->user['id'] ?? 0);
        $page = max((int)$request->getQuery('page', 1), 1);
        $perPage = min(max((int)$request->getQuery('per_page', 20), 1), 100);
        $status = trim((string)$request->getQuery('status', ''));
        $state = trim((string)$request->getQuery('state', ''));

        try {
            $history = $this->historyReadService->listHistoryByUser(
                $userId,
                $page,
                $perPage,
                $status,
                $state
            );

            Response::success($history['items'], [
                'pagination' => $history['pagination'],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list queue entry history', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entry history', $request->requestId);
        }
    }

    public function get(Request $request, string $publicId): void
    {
        try {
            $entry = $this->historyReadService->findEntryByPublicId($publicId);
            if (!$entry) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $this->authorizeEntryAccess($request->user, $entry);

            Response::success([
                'entry' => $this->historyReadService->serializeEntry($entry),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to get queue entry', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entry', $request->requestId);
        }
    }

    public function events(Request $request, string $publicId): void
    {
        try {
            $entry = $this->historyReadService->findEntryByPublicId($publicId);
            if (!$entry) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $this->authorizeEntryAccess($request->user, $entry);

            Response::success([
                'entry' => $this->historyReadService->serializeEntry($entry),
                'events' => $this->historyReadService->listEventsByEntryId((int)$entry['id']),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to get queue entry events', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entry events', $request->requestId);
        }
    }

    public function staffGet(Request $request, int $queueId, string $publicId): void
    {
        try {
            $queue = Queue::find($queueId);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'VocÃª nÃ£o tem acesso a esta fila'
            );

            $entry = $this->historyReadService->findEntryByPublicId($publicId);
            if (!$entry || (int)$entry['queue_id'] !== $queueId) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            Response::success([
                'entry' => $this->historyReadService->serializeEntry($entry),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to get queue entry for staff', [
                'queue_id' => $queueId,
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entry', $request->requestId);
        }
    }

    public function staffEvents(Request $request, int $queueId, string $publicId): void
    {
        try {
            $queue = Queue::find($queueId);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'VocÃª nÃ£o tem acesso a esta fila'
            );

            $entry = $this->historyReadService->findEntryByPublicId($publicId);
            if (!$entry || (int)$entry['queue_id'] !== $queueId) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            Response::success([
                'entry' => $this->historyReadService->serializeEntry($entry),
                'events' => $this->historyReadService->listEventsByEntryId((int)$entry['id']),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to get queue entry events for staff', [
                'queue_id' => $queueId,
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entry events', $request->requestId);
        }
    }

    private function authorizeEntryAccess(array $user, array $entry): void
    {
        $currentUserId = (int)($user['id'] ?? 0);
        $entryUserId = !empty($entry['user_id']) ? (int)$entry['user_id'] : null;

        if ($entryUserId !== null && $entryUserId === $currentUserId) {
            return;
        }

        if ($this->accessService->isStaff($user)) {
            $this->accessService->requireQueueAccess(
                $user,
                [
                    'id' => (int)$entry['queue_id'],
                    'establishment_id' => (int)$entry['establishment_id'],
                ],
                'VocÃª nÃ£o tem acesso a esta entrada de fila'
            );
            return;
        }

        throw new \RuntimeException('VocÃª nÃ£o tem acesso a esta entrada de fila');
    }
}
