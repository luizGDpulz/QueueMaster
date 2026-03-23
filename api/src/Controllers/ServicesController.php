<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Database;
use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;
use QueueMaster\Models\Service;
use QueueMaster\Models\Establishment;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\ContextAccessService;

/**
 * ServicesController - Service Management Endpoints
 * 
 * Handles CRUD operations for services offered by establishments
 */
class ServicesController
{
    private ContextAccessService $accessService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
    }

    /**
     * GET /api/v1/services
     * 
     * List all services (optionally filter by establishment)
     */
    public function list(Request $request): void
    {
        try {
            $params = $request->getQuery();
            $conditions = [];
            $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($request->user);

            // Filter by establishment if provided
            if (isset($params['establishment_id'])) {
                $conditions['establishment_id'] = (int)$params['establishment_id'];

                $establishment = Establishment::find((int)$params['establishment_id']);
                if (!$establishment) {
                    Response::notFound('Establishment not found', $request->requestId);
                    return;
                }

                $this->accessService->requireEstablishmentAccess(
                    $request->user,
                    $establishment,
                    'Você não tem acesso aos serviços deste estabelecimento'
                );
            }

            $services = Service::all($conditions, 'name', 'ASC');

            if (!$this->accessService->isAdmin($request->user) && !isset($params['establishment_id'])) {
                $services = empty($accessibleEstablishmentIds)
                    ? []
                    : array_values(array_filter(
                        $services,
                        static fn(array $service): bool => in_array((int)$service['establishment_id'], $accessibleEstablishmentIds, true)
                    ));
            }

            // Enrich with establishment name
            foreach ($services as &$service) {
                if ($service['establishment_id']) {
                    $establishment = Establishment::find($service['establishment_id']);
                    $service['establishment_name'] = $establishment ? $establishment['name'] : null;
                }
            }

            Response::success([
                'services' => $services,
                'total' => count($services),
            ]);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to list services', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve services', $request->requestId);
        }
    }

    /**
     * GET /api/v1/services/{id}
     * 
     * Get single service by ID
     */
    public function get(Request $request, int $id): void
    {
        try {
            $service = Service::find($id);

            if (!$service) {
                Response::notFound('Service not found', $request->requestId);
                return;
            }

            // Add establishment info
            if ($service['establishment_id']) {
                $establishment = Establishment::find($service['establishment_id']);
                if ($establishment) {
                    $this->accessService->requireEstablishmentAccess(
                        $request->user,
                        $establishment,
                        'Você não tem acesso a este serviço'
                    );
                }
                $service['establishment'] = $establishment;
            }

            $service['usage'] = $this->buildUsageSummary($id);
            $service['linked_queue_count'] = $service['usage']['linked_queue_count'];
            $service['linked_queues'] = $service['usage']['linked_queues'];

            Response::success([
                'service' => $service,
            ]);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to get service', [
                'service_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve service', $request->requestId);
        }
    }

    /**
     * POST /api/v1/services
     * 
     * Create new service (manager/admin)
     */
    public function create(Request $request): void
    {
        $data = $request->all();
        $duration = $data['duration_minutes'] ?? ($data['duration'] ?? null);

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'name' => 'required|min:2|max:150',
            'description' => 'max:500',
        ]);

        if ($duration === null || !is_numeric($duration)) {
            $errors['duration'] = 'Duration is required and must be numeric';
        }

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

            $this->accessService->requireEstablishmentManagement(
                $request->user,
                $establishment,
                'Você não tem permissão para criar serviços neste estabelecimento'
            );

            $serviceData = [
                'establishment_id' => (int)$data['establishment_id'],
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'duration_minutes' => (int)$duration,
            ];

            if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
                $serviceData['price'] = (float)$data['price'];
            }

            if (isset($data['sort_order'])) {
                $serviceData['sort_order'] = (int)$data['sort_order'];
            }

            if (isset($data['icon'])) {
                $serviceData['icon'] = trim($data['icon']);
            }

            if (isset($data['image_url'])) {
                $serviceData['image_url'] = trim($data['image_url']);
            }

            $serviceId = Service::create($serviceData);
            $service = Service::find($serviceId);

            Logger::info('Service created', [
                'service_id' => $serviceId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'create', 'service', (string)$serviceId, (int)$data['establishment_id'], null, [
                'name' => $serviceData['name'] ?? null,
            ]);

            Response::created([
                'service' => $service,
                'message' => 'Service created successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to create service', [
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create service', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/services/{id}
     * 
     * Update service (manager/admin)
     */
    public function update(Request $request, int $id): void
    {
        try {
            $service = Service::find($id);
            if (!$service) {
                Response::notFound('Service not found', $request->requestId);
                return;
            }

            $this->accessService->requireServiceManagement(
                $request->user,
                $service,
                'Você não tem permissão para editar este serviço'
            );

            $data = $request->all();
            $updateData = [];

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateData['name'] = trim($data['name']);
            }

            if (isset($data['description'])) {
                $updateData['description'] = trim($data['description']);
            }

            if (isset($data['duration'])) {
                $updateData['duration_minutes'] = (int)$data['duration'];
            } elseif (isset($data['duration_minutes'])) {
                $updateData['duration_minutes'] = (int)$data['duration_minutes'];
            }

            if (isset($data['price'])) {
                $updateData['price'] = $data['price'] !== '' ? (float)$data['price'] : null;
            }

            if (isset($data['sort_order'])) {
                $updateData['sort_order'] = (int)$data['sort_order'];
            }

            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool)$data['is_active'] ? 1 : 0;
            }

            if (isset($data['icon'])) {
                $updateData['icon'] = $data['icon'] !== '' ? trim($data['icon']) : null;
            }

            if (isset($data['image_url'])) {
                $updateData['image_url'] = $data['image_url'] !== '' ? trim($data['image_url']) : null;
            }

            if (isset($data['establishment_id'])) {
                $newEstablishmentId = (int)$data['establishment_id'];
                if ($newEstablishmentId !== (int)$service['establishment_id']) {
                    Response::error(
                        'ESTABLISHMENT_IMMUTABLE',
                        'O estabelecimento do serviço não pode ser alterado por esta rota',
                        422,
                        $request->requestId
                    );
                    return;
                }
            }

            if (empty($updateData)) {
                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, description, duration, duration_minutes, price, sort_order, is_active, icon, image_url',
                    400,
                    $request->requestId
                );
                return;
            }

            Service::update($id, $updateData);
            $updatedService = Service::find($id);

            Logger::info('Service updated', [
                'service_id' => $id,
                'updated_by' => $request->user['id'],
            ], $request->requestId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $service[$field] ?? null, 'to' => $newValue];
            }

            AuditService::logFromRequest($request, 'update', 'service', (string)$id, $service['establishment_id'] ?? null, null, [
                'entity_name' => $service['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'service' => $updatedService,
                'message' => 'Service updated successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to update service', [
                'service_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update service', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/services/{id}
     * 
     * Delete service (manager/admin)
     */
    public function delete(Request $request, int $id): void
    {
        try {
            $service = Service::find($id);
            if (!$service) {
                Response::notFound('Service not found', $request->requestId);
                return;
            }

            $this->accessService->requireServiceManagement(
                $request->user,
                $service,
                'Você não tem permissão para excluir este serviço'
            );

            $usage = $this->buildUsageSummary($id);

            Service::delete($id);

            Logger::info('Service deleted', [
                'service_id' => $id,
                'deleted_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'delete', 'service', (string)$id, $service['establishment_id'] ?? null, null, [
                'name' => $service['name'] ?? null,
                'linked_queue_count' => $usage['linked_queue_count'],
            ]);

            Response::success([
                'message' => 'Service deleted successfully',
                'usage' => $usage,
            ]);

        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to delete service', [
                'service_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete service', $request->requestId);
        }
    }

    private function buildUsageSummary(int $serviceId): array
    {
        $db = Database::getInstance();
        $linkedQueues = $db->query(
            "
            SELECT q.id, q.name, q.status, e.id AS establishment_id, e.name AS establishment_name
            FROM queue_services qs
            JOIN queues q ON q.id = qs.queue_id
            JOIN establishments e ON e.id = q.establishment_id
            WHERE qs.service_id = ?
            ORDER BY e.name ASC, q.name ASC
            ",
            [$serviceId]
        );

        return [
            'linked_queue_count' => count($linkedQueues),
            'linked_queues' => $linkedQueues,
        ];
    }
}
