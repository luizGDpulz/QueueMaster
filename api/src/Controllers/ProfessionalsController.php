<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;
use QueueMaster\Models\Professional;
use QueueMaster\Models\Establishment;
use QueueMaster\Services\AuditService;

/**
 * ProfessionalsController - Professional Management Endpoints
 * 
 * Handles CRUD operations for professionals (staff) in establishments
 */
class ProfessionalsController
{
    /**
     * GET /api/v1/professionals
     * 
     * List all professionals (optionally filter by establishment)
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

            $professionals = Professional::all($conditions, 'name', 'ASC');

            // Enrich with establishment name
            foreach ($professionals as &$professional) {
                if ($professional['establishment_id']) {
                    $establishment = Establishment::find($professional['establishment_id']);
                    $professional['establishment_name'] = $establishment ? $establishment['name'] : null;
                }
            }

            Response::success([
                'professionals' => $professionals,
                'total' => count($professionals),
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to list professionals', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve professionals', $request->requestId);
        }
    }

    /**
     * GET /api/v1/professionals/{id}
     * 
     * Get single professional by ID
     */
    public function get(Request $request, int $id): void
    {
        try {
            $professional = Professional::find($id);

            if (!$professional) {
                Response::notFound('Professional not found', $request->requestId);
                return;
            }

            // Add establishment info
            if ($professional['establishment_id']) {
                $establishment = Establishment::find($professional['establishment_id']);
                $professional['establishment'] = $establishment;
            }

            Response::success([
                'professional' => $professional,
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get professional', [
                'professional_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve professional', $request->requestId);
        }
    }

    /**
     * POST /api/v1/professionals
     * 
     * Create new professional (manager/admin)
     */
    public function create(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'name' => 'required|min:2|max:150',
            'specialization' => 'max:100',
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

            // Check SaaS quota
            $quotaCheck = \QueueMaster\Services\QuotaService::canAddProfessional((int)$data['establishment_id']);
            if (!$quotaCheck['allowed']) {
                Response::error($quotaCheck['error'], $quotaCheck['message'], 403, $request->requestId);
                return;
            }

            $professionalId = Professional::create([
                'establishment_id' => (int)$data['establishment_id'],
                'name' => trim($data['name']),
                'specialization' => isset($data['specialization']) ? trim($data['specialization']) : null,
            ]);

            $professional = Professional::find($professionalId);

            Logger::info('Professional created', [
                'professional_id' => $professionalId,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'create', 'professional', (string)$professionalId, (int)$data['establishment_id'], null, [
                'name' => trim($data['name']),
                'specialization' => $data['specialization'] ?? null,
                'establishment_id' => (int)$data['establishment_id'],
            ]);

            Response::created([
                'professional' => $professional,
                'message' => 'Professional created successfully',
            ]);

        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create professional', [
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create professional', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/professionals/{id}
     * 
     * Update professional (manager/admin)
     */
    public function update(Request $request, int $id): void
    {
        try {
            $professional = Professional::find($id);
            if (!$professional) {
                Response::notFound('Professional not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateData['name'] = trim($data['name']);
            }

            if (isset($data['specialization'])) {
                $updateData['specialization'] = trim($data['specialization']);
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
                    'professional_id' => $id,
                    'user_id' => $request->user['id'],
                    'received_data' => $data,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, specialization, establishment_id',
                    400,
                    $request->requestId
                );
                return;
            }

            Professional::update($id, $updateData);
            $updatedProfessional = Professional::find($id);

            Logger::info('Professional updated', [
                'professional_id' => $id,
                'updated_by' => $request->user['id'],
            ], $request->requestId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $professional[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'professional', (string)$id, $professional['establishment_id'] ?? null, null, [
                'entity_name' => $professional['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'professional' => $updatedProfessional,
                'message' => 'Professional updated successfully',
            ]);

        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to update professional', [
                'professional_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to update professional', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/professionals/{id}
     * 
     * Delete professional (manager/admin)
     */
    public function delete(Request $request, int $id): void
    {
        try {
            $professional = Professional::find($id);
            if (!$professional) {
                Response::notFound('Professional not found', $request->requestId);
                return;
            }

            Professional::delete($id);

            Logger::info('Professional deleted', [
                'professional_id' => $id,
                'deleted_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'delete', 'professional', (string)$id, $professional['establishment_id'] ?? null, null, [
                'name' => $professional['name'] ?? null,
                'specialization' => $professional['specialization'] ?? null,
                'establishment_id' => $professional['establishment_id'] ?? null,
            ]);

            Response::success(['message' => 'Professional deleted successfully']);

        }
        catch (\Exception $e) {
            Logger::error('Failed to delete professional', [
                'professional_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete professional', $request->requestId);
        }
    }

    /**
     * GET /api/v1/professionals/{id}/appointments
     * 
     * Get appointments for professional
     */
    public function getAppointments(Request $request, int $id): void
    {
        try {
            $professional = Professional::find($id);
            if (!$professional) {
                Response::notFound('Professional not found', $request->requestId);
                return;
            }

            $params = $request->getQuery();
            $conditions = ['professional_id' => $id];

            // Add optional filters
            if (isset($params['status'])) {
                $conditions['status'] = $params['status'];
            }

            if (isset($params['date'])) {
                $conditions['date'] = $params['date'];
            }

            $appointments = Professional::getAppointments($id);

            // Filter by status if provided
            if (isset($conditions['status'])) {
                $appointments = array_filter($appointments, function ($apt) use ($conditions) {
                    return $apt['status'] === $conditions['status'];
                });
            }

            Response::success([
                'appointments' => array_values($appointments),
                'total' => count($appointments),
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get professional appointments', [
                'professional_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointments', $request->requestId);
        }
    }
}
