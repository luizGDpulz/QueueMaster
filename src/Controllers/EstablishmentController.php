<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Service;
use QueueMaster\Models\Professional;

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

        } catch (\Exception $e) {
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

        } catch (\Exception $e) {
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

        } catch (\Exception $e) {
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

        } catch (\Exception $e) {
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
     * Create establishment (admin only)
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
            'address' => 'max:255',
            'timezone' => 'max:50',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $establishmentId = Establishment::create([
                'name' => trim($data['name']),
                'address' => isset($data['address']) ? trim($data['address']) : null,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
            ]);

            $establishment = Establishment::find($establishmentId);

            Logger::info('Establishment created', [
                'establishment_id' => $establishmentId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            Response::created([
                'establishment' => $establishment,
                'message' => 'Establishment created successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        } catch (\Exception $e) {
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
     * Update establishment (admin only)
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

            $data = $request->all();
            $updateData = [];

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateData['name'] = trim($data['name']);
            }

            if (isset($data['address'])) {
                $updateData['address'] = trim($data['address']);
            }

            if (isset($data['timezone'])) {
                $updateData['timezone'] = $data['timezone'];
            }

            if (empty($updateData)) {
                Response::badRequest('No fields to update', $request->requestId);
                return;
            }

            Establishment::update($id, $updateData);
            $updatedEstablishment = Establishment::find($id);

            Logger::info('Establishment updated', [
                'establishment_id' => $id,
                'updated_by' => $request->user['id'],
            ], $request->requestId);

            Response::success([
                'establishment' => $updatedEstablishment,
                'message' => 'Establishment updated successfully',
            ]);

        } catch (\Exception $e) {
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
     * Delete establishment (admin only)
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

            Establishment::delete($id);

            Logger::info('Establishment deleted', [
                'establishment_id' => $id,
                'deleted_by' => $request->user['id'],
            ], $request->requestId);

            Response::success(['message' => 'Establishment deleted successfully']);

        } catch (\Exception $e) {
            Logger::error('Failed to delete establishment', [
                'establishment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete establishment', $request->requestId);
        }
    }
}
