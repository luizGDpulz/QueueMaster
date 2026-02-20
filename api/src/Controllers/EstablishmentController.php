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

/**
 * EstablishmentController - Establishment Management Endpoints
 * 
 * Handles establishment listing, details, and related resources (services, professionals)
 */
class EstablishmentController
{
    /**
     * GET /api/v1/establishments
     * 
     * List all establishments
     */
    public function list(Request $request): void
    {
        try {
            // Get all establishments using Model
            $establishments = Establishment::all([], 'name', 'ASC');

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

            Response::success([
                'establishment' => $establishment,
            ]);

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

            // Get services using Model relationship
            $services = Establishment::getServices($id);

            Response::success([
                'services' => $services,
                'total' => count($services),
            ]);

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

            // Get professionals using Model relationship
            $professionals = Establishment::getProfessionals($id);

            Response::success([
                'professionals' => $professionals,
                'total' => count($professionals),
            ]);

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

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

            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole !== 'admin' && !\QueueMaster\Models\BusinessUser::exists($businessId, $userId)) {
                Response::forbidden('You do not have access to this business', $request->requestId);
                return;
            }

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $establishment = Establishment::find($id);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            // Check access: admin can update any, manager must belong to the business
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole !== 'admin' && $establishment['business_id']) {
                if (!\QueueMaster\Models\BusinessUser::exists((int)$establishment['business_id'], $userId)) {
                    Response::forbidden('You do not have access to this establishment', $request->requestId);
                    return;
                }
            }

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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $establishment = Establishment::find($id);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            // Check access: admin can delete any, manager must belong to the business
            $userRole = $request->user['role'] ?? 'client';
            $userId = (int)$request->user['id'];

            if ($userRole !== 'admin' && $establishment['business_id']) {
                if (!\QueueMaster\Models\BusinessUser::exists((int)$establishment['business_id'], $userId)) {
                    Response::forbidden('You do not have access to this establishment', $request->requestId);
                    return;
                }
            }
            elseif ($userRole !== 'admin') {
                Response::forbidden('Insufficient permissions', $request->requestId);
                return;
            }

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
        catch (\Exception $e) {
            Logger::error('Failed to delete establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete establishment', $request->requestId);
        }
    }
}
