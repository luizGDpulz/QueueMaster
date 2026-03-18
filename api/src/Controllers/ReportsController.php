<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Queue;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Services\QueueReportsService;
use QueueMaster\Utils\Logger;

class ReportsController
{
    private ContextAccessService $accessService;
    private QueueReportsService $reportsService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
        $this->reportsService = new QueueReportsService($this->accessService);
    }

    public function queueReports(Request $request): void
    {
        try {
            if (!$this->accessService->canViewReports($request->user)) {
                Response::forbidden('Voce nao tem acesso aos relatorios', $request->requestId);
                return;
            }

            $payload = $this->reportsService->build($request->user, $request->getQuery() ?? []);
            Response::success($payload);
        } catch (\Exception $e) {
            Logger::error('Failed to build queue reports', [
                'error' => $e->getMessage(),
                'user_id' => $request->user['id'] ?? null,
            ], $request->requestId);

            Response::serverError('Failed to retrieve reports', $request->requestId);
        }
    }

    public function queueReportFilters(Request $request): void
    {
        try {
            if (!$this->accessService->canViewReports($request->user)) {
                Response::forbidden('Voce nao tem acesso aos relatorios', $request->requestId);
                return;
            }

            Response::success([
                'filters' => $this->reportsService->getFilterMetadata($request->user),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to load queue report filters', [
                'error' => $e->getMessage(),
                'user_id' => $request->user['id'] ?? null,
            ], $request->requestId);

            Response::serverError('Failed to retrieve report filters', $request->requestId);
        }
    }

    public function queueDrilldown(Request $request, int $queueId): void
    {
        try {
            $queue = Queue::find($queueId);
            if (!$queue) {
                Response::notFound('Queue not found', $request->requestId);
                return;
            }

            if (!$this->accessService->canViewReports($request->user)) {
                Response::forbidden('Voce nao tem acesso aos relatorios desta fila', $request->requestId);
                return;
            }

            $this->accessService->requireQueueAccess(
                $request->user,
                $queue,
                'Voce nao tem acesso aos relatorios desta fila'
            );

            $payload = $this->reportsService->build($request->user, $request->getQuery() ?? [], $queueId);
            Response::success($payload);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to build queue drilldown report', [
                'error' => $e->getMessage(),
                'queue_id' => $queueId,
                'user_id' => $request->user['id'] ?? null,
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue report', $request->requestId);
        }
    }
}
