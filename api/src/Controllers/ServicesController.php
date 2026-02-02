<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;
use QueueMaster\Models\Service;
use QueueMaster\Models\Establishment;

/**
 * ServicesController - Service Management Endpoints
 * 
 * Handles CRUD operations for services offered by establishments
 */
class ServicesController
{
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

            // Filter by establishment if provided
            if (isset($params['establishment_id'])) {
                $conditions['establishment_id'] = (int)$params['establishment_id'];
            }

            $services = Service::all($conditions, 'name', 'ASC');

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
                $service['establishment'] = $establishment;
            }

            Response::success([
                'service' => $service,
            ]);

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
     * Create new service (admin only)
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
            'duration' => 'required|integer',
            'description' => 'max:500',
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

            $serviceId = Service::create([
                'establishment_id' => (int)$data['establishment_id'],
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'duration' => (int)$data['duration'],
            ]);

            $service = Service::find($serviceId);

            Logger::info('Service created', [
                'service_id' => $serviceId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            Response::created([
                'service' => $service,
                'message' => 'Service created successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
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
     * Update service (admin only)
     */
    public function update(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $service = Service::find($id);
            if (!$service) {
                Response::notFound('Service not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateData['name'] = trim($data['name']);
            }

            if (isset($data['description'])) {
                $updateData['description'] = trim($data['description']);
            }

            if (isset($data['duration'])) {
                $updateData['duration'] = (int)$data['duration'];
            }

            if (isset($data['establishment_id'])) {
                // Verify new establishment exists
                $establishment = Establishment::find((int)$data['establishment_id']);
                if (!$establishment) {
                    Response::notFound('Establishment not found', $request->requestId);
                    return;
                }
                $updateData['establishment_id'] = (int)$data['establishment_id'];
            }

            if (empty($updateData)) {
                Logger::warning('Update attempt with no fields', [
                    'service_id' => $id,
                    'user_id' => $request->user['id'],
                    'received_data' => $data,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, description, duration, establishment_id',
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

            Response::success([
                'service' => $updatedService,
                'message' => 'Service updated successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
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
     * Delete service (admin only)
     */
    public function delete(Request $request, int $id): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $service = Service::find($id);
            if (!$service) {
                Response::notFound('Service not found', $request->requestId);
                return;
            }

            Service::delete($id);

            Logger::info('Service deleted', [
                'service_id' => $id,
                'deleted_by' => $request->user['id'],
            ], $request->requestId);

            Response::success(['message' => 'Service deleted successfully']);

        } catch (\Exception $e) {
            Logger::error('Failed to delete service', [
                'service_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete service', $request->requestId);
        }
    }
}
