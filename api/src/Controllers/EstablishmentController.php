<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\EstablishmentUser;
use QueueMaster\Models\Service;
use QueueMaster\Models\Professional;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\ContextAccessService;

/**
 * EstablishmentController - Establishment Management Endpoints
 * 
 * Handles establishment listing, details, and related resources (services, professionals)
 */
class EstablishmentController
{
    private ContextAccessService $accessService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
    }

    /**
     * GET /api/v1/establishments
     * 
     * List all establishments
     */
    public function list(Request $request): void
    {
        try {
            $establishments = $this->accessService->getAccessibleEstablishments($request->user);

            Response::success([
                'establishments' => $establishments,
                'total' => count($establishments),
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to list establishments', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve establishments', $request->requestId);
        }
    }

    /**
     * GET /api/v1/establishments/search
     *
     * Search active establishments for discovery.
     */
    public function search(Request $request): void
    {
        try {
            $query = trim((string)$request->getQuery('q', ''));
            $limit = min(max((int)$request->getQuery('limit', 20), 1), 50);

            $params = [];
            $searchSql = '';
            if ($query !== '') {
                $searchSql = ' AND (e.name LIKE ? OR e.address LIKE ? OR b.name LIKE ?)';
                $like = '%' . $query . '%';
                $params = [$like, $like, $like];
            }

            $db = Database::getInstance();
            $establishments = $db->query(
                "
                SELECT
                    e.id,
                    e.business_id,
                    e.name,
                    e.slug,
                    e.description,
                    e.address,
                    e.phone,
                    e.email,
                    e.timezone,
                    e.opens_at,
                    e.closes_at,
                    e.is_active,
                    b.name AS business_name
                FROM establishments e
                INNER JOIN businesses b ON b.id = e.business_id
                WHERE e.is_active = 1
                  AND b.is_active = 1
                  $searchSql
                ORDER BY b.name ASC, e.name ASC
                LIMIT $limit
                ",
                $params
            );

            Response::success([
                'establishments' => array_map(static function (array $establishment): array {
                    return [
                        'id' => (int)$establishment['id'],
                        'business_id' => (int)$establishment['business_id'],
                        'business_name' => $establishment['business_name'] ?? null,
                        'name' => $establishment['name'],
                        'slug' => $establishment['slug'] ?? null,
                        'description' => $establishment['description'] ?? null,
                        'address' => $establishment['address'] ?? null,
                        'phone' => $establishment['phone'] ?? null,
                        'email' => $establishment['email'] ?? null,
                        'timezone' => $establishment['timezone'] ?? 'America/Sao_Paulo',
                        'opens_at' => $establishment['opens_at'] ?? null,
                        'closes_at' => $establishment['closes_at'] ?? null,
                        'is_active' => (bool)($establishment['is_active'] ?? false),
                    ];
                }, $establishments),
                'total' => count($establishments),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to search establishments', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to search establishments', $request->requestId);
        }
    }

    /**
     * GET /api/v1/establishments/:id
     * 
     * Get single establishment by ID
     */
    public function get(Request $request, int $id): void
    {
        try {
            // Find establishment using Model
            $establishment = Establishment::find($id);

            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $this->accessService->requireEstablishmentAccess(
                $request->user,
                $establishment,
                'Voce nao tem acesso a este estabelecimento'
            );

            Response::success([
                'establishment' => $establishment,
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve establishment', $request->requestId);
        }
    }

    /**
     * GET /api/v1/establishments/:id/discover
     *
     * Public authenticated read-only establishment detail.
     */
    public function discover(Request $request, int $id): void
    {
        try {
            $establishment = Establishment::find($id);

            if (!$establishment || !($establishment['is_active'] ?? false)) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $business = \QueueMaster\Models\Business::find((int)$establishment['business_id']);
            if (!$business || !($business['is_active'] ?? false)) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($request->user);
            $services = array_values(array_filter(
                Service::getActiveByEstablishment($id),
                static fn(array $service): bool => (bool)($service['is_active'] ?? false)
            ));
            $queues = array_values(array_filter(
                \QueueMaster\Models\Queue::getByEstablishment($id),
                static fn(array $queue): bool => in_array(($queue['status'] ?? 'closed'), ['open', 'paused', 'closed'], true)
            ));

            Response::success([
                'establishment' => [
                    'id' => (int)$establishment['id'],
                    'business_id' => (int)$establishment['business_id'],
                    'business_name' => $business['name'] ?? null,
                    'name' => $establishment['name'],
                    'slug' => $establishment['slug'] ?? null,
                    'description' => $establishment['description'] ?? null,
                    'address' => $establishment['address'] ?? null,
                    'phone' => $establishment['phone'] ?? null,
                    'email' => $establishment['email'] ?? null,
                    'timezone' => $establishment['timezone'] ?? 'America/Sao_Paulo',
                    'opens_at' => $establishment['opens_at'] ?? null,
                    'closes_at' => $establishment['closes_at'] ?? null,
                    'is_active' => (bool)($establishment['is_active'] ?? false),
                    'is_linked' => in_array($id, $accessibleEstablishmentIds, true),
                ],
                'services' => array_map(static function (array $service): array {
                    return [
                        'id' => (int)$service['id'],
                        'name' => $service['name'],
                        'description' => $service['description'] ?? null,
                        'duration_minutes' => (int)($service['duration_minutes'] ?? 0),
                        'price' => $service['price'] ?? null,
                    ];
                }, $services),
                'queues' => array_map(static function (array $queue): array {
                    return [
                        'id' => (int)$queue['id'],
                        'service_id' => !empty($queue['service_id']) ? (int)$queue['service_id'] : null,
                        'name' => $queue['name'],
                        'description' => $queue['description'] ?? null,
                        'status' => $queue['status'] ?? 'closed',
                        'max_capacity' => !empty($queue['max_capacity']) ? (int)$queue['max_capacity'] : null,
                    ];
                }, $queues),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to discover establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve establishment', $request->requestId);
        }
    }

    /**
     * GET /api/v1/establishments/:id/services
     * 
     * Get services for establishment
     */
    public function getServices(Request $request, int $id): void
    {
        try {
            // Verify establishment exists
            $establishment = Establishment::find($id);

            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $this->accessService->requireEstablishmentAccess(
                $request->user,
                $establishment,
                'Voce nao tem acesso aos servicos deste estabelecimento'
            );

            // Get services using Model relationship
            $services = Establishment::getServices($id);

            Response::success([
                'services' => $services,
                'total' => count($services),
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get establishment services', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve services', $request->requestId);
        }
    }

    /**
     * GET /api/v1/establishments/:id/professionals
     * 
     * Get professionals for establishment
     */
    public function getProfessionals(Request $request, int $id): void
    {
        try {
            // Verify establishment exists
            $establishment = Establishment::find($id);

            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $this->accessService->requireEstablishmentAccess(
                $request->user,
                $establishment,
                'Voce nao tem acesso aos profissionais deste estabelecimento'
            );

            // Get professionals using Model relationship
            $professionals = Establishment::getProfessionals($id);

            Response::success([
                'professionals' => $professionals,
                'total' => count($professionals),
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to get establishment professionals', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve professionals', $request->requestId);
        }
    }

    /**
     * POST /api/v1/establishments
     * 
     * Create establishment (manager/admin)
     * Requires business_id - establishments belong to a business, not directly to a user
     */
    public function create(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:255',
            'business_id' => 'required|integer',
            'slug' => 'max:100',
            'description' => 'max:5000',
            'address' => 'max:255',
            'phone' => 'max:20',
            'email' => 'email|max:150',
            'logo_url' => 'max:500',
            'timezone' => 'max:50',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            // Verify business exists and user has access
            $businessId = (int)$data['business_id'];
            $business = \QueueMaster\Models\Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $businessId,
                'Voce nao tem permissao para criar estabelecimentos neste negocio'
            );

            $establishmentData = [
                'name' => trim($data['name']),
                'business_id' => $businessId,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
            ];

            // Optional fields
            $optionalFields = ['slug', 'description', 'address', 'phone', 'email', 'logo_url', 'opens_at', 'closes_at'];
            foreach ($optionalFields as $field) {
                if (isset($data[$field])) {
                    $establishmentData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            $establishmentId = Establishment::create($establishmentData);
            $establishment = Establishment::find($establishmentId);

            Logger::info('Establishment created', [
                'establishment_id' => $establishmentId,
                'business_id' => $businessId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'create', 'establishment', (string)$establishmentId, null, $businessId, [
                'name' => $establishmentData['name'] ?? null,
                'business_id' => $businessId,
                'address' => $establishmentData['address'] ?? null,
                'phone' => $establishmentData['phone'] ?? null,
                'timezone' => $establishmentData['timezone'] ?? null,
            ]);

            Response::created([
                'establishment' => $establishment,
                'message' => 'Establishment created successfully',
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create establishment', [
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create establishment', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/establishments/{id}
     * 
     * Update establishment (manager/admin)
     */
    public function update(Request $request, int $id): void
    {
        try {
            $establishment = Establishment::find($id);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $this->accessService->requireEstablishmentManagement(
                $request->user,
                $establishment,
                'Voce nao tem permissao para editar este estabelecimento'
            );

            $data = $request->all();
            $updateData = [];

            // All updatable fields
            $stringFields = ['name', 'slug', 'description', 'address', 'phone', 'email', 'logo_url', 'timezone'];
            foreach ($stringFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'name' && empty(trim($data[$field]))) {
                        continue; // Skip empty name
                    }
                    $updateData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            // Time fields
            if (isset($data['opens_at'])) {
                $updateData['opens_at'] = $data['opens_at'];
            }
            if (isset($data['closes_at'])) {
                $updateData['closes_at'] = $data['closes_at'];
            }

            // Boolean field
            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool)$data['is_active'] ? 1 : 0;
            }

            if (empty($updateData)) {
                Logger::warning('Update attempt with no fields', [
                    'establishment_id' => $id,
                    'user_id' => $request->user['id'],
                    'received_data' => $data,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, slug, description, address, phone, email, logo_url, timezone, opens_at, closes_at, is_active',
                    400,
                    $request->requestId
                );
                return;
            }

            Establishment::update($id, $updateData);
            $updatedEstablishment = Establishment::find($id);

            Logger::info('Establishment updated', [
                'establishment_id' => $id,
                'updated_by' => $request->user['id'],
            ], $request->requestId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $establishment[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'establishment', (string)$id, $id, $establishment['business_id'] ?? null, [
                'entity_name' => $establishment['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'establishment' => $updatedEstablishment,
                'message' => 'Establishment updated successfully',
            ]);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update establishment', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/establishments/{id}
     * 
     * Delete establishment (manager who belongs to business, or admin)
     */
    public function delete(Request $request, int $id): void
    {
        try {
            $establishment = Establishment::find($id);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            $this->accessService->requireEstablishmentManagement(
                $request->user,
                $establishment,
                'Voce nao tem permissao para excluir este estabelecimento'
            );

            Establishment::delete($id);

            Logger::info('Establishment deleted', [
                'establishment_id' => $id,
                'deleted_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'delete', 'establishment', (string)$id, $id, $establishment['business_id'] ?? null, [
                'name' => $establishment['name'] ?? null,
                'business_id' => $establishment['business_id'] ?? null,
                'address' => $establishment['address'] ?? null,
            ]);

            Response::success(['message' => 'Establishment deleted successfully']);

        }
        catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to delete establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete establishment', $request->requestId);
        }
    }
}
