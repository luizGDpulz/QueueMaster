<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Notification;
use QueueMaster\Models\Professional;
use QueueMaster\Models\User;
use QueueMaster\Services\AppointmentService;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Services\AuditService;

/**
 * AppointmentsController - Appointment Management Endpoints
 * 
 * Handles appointment operations: create, list, get, check-in, cancel, available slots
 */
class AppointmentsController
{
    private ContextAccessService $accessService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
    }

    /**
     * POST /api/v1/appointments
     * 
     * Create appointment
     */
    public function create(Request $request): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];
        $userRole = (string)($request->user['role'] ?? 'client');

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'professional_id' => 'required|integer',
            'service_id' => 'required|integer',
            'start_at' => 'required',
            'client_user_id' => 'integer',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $establishmentId = (int)$data['establishment_id'];
            $establishment = Establishment::find($establishmentId);
            if (!$establishment) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            if ($userRole !== 'client') {
                $this->accessService->requireEstablishmentAccess(
                    $request->user,
                    $establishment,
                    'Voce nao tem acesso a este estabelecimento'
                );
            }

            $professional = Professional::find((int)$data['professional_id']);
            if (!$professional) {
                Response::notFound('Professional not found', $request->requestId);
                return;
            }

            if ($userRole === 'professional' && (int)($professional['user_id'] ?? 0) !== $userId) {
                Response::forbidden('O profissional so pode se alocar a si mesmo', $request->requestId);
                return;
            }

            $clientUserId = $userRole === 'client'
                ? $userId
                : (int)($data['client_user_id'] ?? $userId);

            if (!User::find($clientUserId)) {
                Response::notFound('Client user not found', $request->requestId);
                return;
            }

            $data['user_id'] = $clientUserId;

            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->create($data);
            $this->notifyAppointmentCreated($appointment, $userId);

            Logger::info('Appointment created', [
                'appointment_id' => $appointment['id'],
                'user_id' => $clientUserId,
                'created_by' => $userId,
            ], $request->requestId);

            AuditService::logFromRequest($request, 'create', 'appointment', (string)$appointment['id'], $data['establishment_id'] ?? null, null, [
                'service_id' => $data['service_id'] ?? null,
                'professional_id' => $data['professional_id'] ?? null,
                'start_at' => $data['start_at'] ?? null,
                'establishment_id' => $data['establishment_id'] ?? null,
                'client_user_id' => $clientUserId,
            ]);

            Response::created([
                'appointment' => $appointment,
                'message' => 'Appointment created successfully',
            ]);

        }
        catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound($e->getMessage(), $request->requestId);
            }
            else {
                Response::forbidden($e->getMessage(), $request->requestId);
            }
        }
        catch (\Exception $e) {
            Logger::error('Failed to create appointment', [
                'user_id' => $userId,
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'conflict') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                Response::conflict('Time slot is already booked', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound($e->getMessage(), $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Invalid') !== false) {
                Response::error('INVALID_DATA', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to create appointment', $request->requestId);
            }
        }
    }

    /**
     * GET /api/v1/appointments
     * 
     * List appointments (filter by user_id, professional_id, date)
     */
    public function list(Request $request): void
    {
        $params = $request->getQuery();
        $userId = (int)$request->user['id'];
        $userRole = (string)($request->user['role'] ?? 'client');

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;

        // Build filters
        $filters = [];

        // Clients can only see their own appointments
        if ($userRole === 'client') {
            $filters['user_id'] = $userId;
        }
        elseif ($this->accessService->isAdmin($request->user)) {
            if (isset($params['user_id'])) {
                $filters['user_id'] = (int)$params['user_id'];
            }
            if (isset($params['professional_id'])) {
                $filters['professional_id'] = (int)$params['professional_id'];
            }
            if (isset($params['establishment_id'])) {
                $filters['establishment_id'] = (int)$params['establishment_id'];
            }
        }
        else {
            $accessibleEstablishmentIds = $this->accessService->getAccessibleEstablishmentIds($request->user);
            if (empty($accessibleEstablishmentIds)) {
                Response::success([], [
                    'pagination' => [
                        'total' => 0,
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => 1,
                    ],
                ]);
                return;
            }

            if (isset($params['establishment_id'])) {
                $establishmentId = (int)$params['establishment_id'];
                if (!in_array($establishmentId, $accessibleEstablishmentIds, true)) {
                    Response::forbidden('Voce nao tem acesso a este estabelecimento', $request->requestId);
                    return;
                }
                $filters['establishment_id'] = $establishmentId;
            } else {
                $filters['establishment_ids'] = $accessibleEstablishmentIds;
            }

            if ($userRole === 'professional') {
                $filters['professional_user_id'] = $userId;
            } elseif (isset($params['user_id'])) {
                $filters['user_id'] = (int)$params['user_id'];
            }

            if (isset($params['professional_id'])) {
                $filters['professional_id'] = (int)$params['professional_id'];
            }
        }

        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }

        if (isset($params['bucket'])) {
            $filters['bucket'] = $params['bucket'];
        }

        if (isset($params['date'])) {
            $filters['date'] = $params['date'];
        }

        try {
            $appointmentService = new AppointmentService();
            $result = $appointmentService->list($filters, $page, $perPage);

            Response::success($result['appointments'], [
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to list appointments', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointments', $request->requestId);
        }
    }

    /**
     * GET /api/v1/appointments/:id
     * 
     * Get single appointment by ID
     */
    public function get(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);

            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            if (!$this->canAccessAppointment($request->user, $appointment)) {
                Response::forbidden('You cannot view this appointment', $request->requestId);
                return;
            }

            Response::success([
                'appointment' => $appointment,
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointment', $request->requestId);
        }
    }

    /**
     * POST /api/v1/appointments/:id/check-in
     * 
     * Check-in to appointment
     */
    public function checkIn(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->checkIn($id, $userId);

            Logger::info('Appointment checked-in', [
                'appointment_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            AuditService::logFromRequest($request, 'check_in', 'appointment', (string)$id, null, null, null);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Successfully checked-in',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to check-in appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot check-in to this appointment', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Cannot check-in') !== false || strpos($e->getMessage(), 'early') !== false || strpos($e->getMessage(), 'passed') !== false) {
                Response::error('CHECK_IN_FAILED', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to check-in', $request->requestId);
            }
        }
    }

    /**
     * PUT /api/v1/appointments/{id}
     * 
     * Update appointment
     */
    public function update(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);

            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            if (!$this->canAccessAppointment($request->user, $appointment)) {
                Response::forbidden('You cannot update this appointment', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            if ($userRole === 'client' && (isset($data['professional_id']) || isset($data['service_id']))) {
                Response::forbidden('Clients cannot change professional or service', $request->requestId);
                return;
            }

            // Allow updating start_at, professional_id, service_id
            if (isset($data['start_at'])) {
                $updateData['start_at'] = $data['start_at'];
            }

            if (isset($data['professional_id'])) {
                if ($userRole === 'professional') {
                    $targetProfessional = Professional::find((int)$data['professional_id']);
                    if (!$targetProfessional || (int)($targetProfessional['user_id'] ?? 0) !== $userId) {
                        Response::forbidden('O profissional so pode se alocar a si mesmo', $request->requestId);
                        return;
                    }
                }
                $updateData['professional_id'] = (int)$data['professional_id'];
            }

            if (isset($data['service_id'])) {
                $updateData['service_id'] = (int)$data['service_id'];
            }

            if (isset($data['status'])) {
                if ($userRole === 'client') {
                    Response::forbidden('Clients cannot update appointment status directly', $request->requestId);
                    return;
                }

                $updateData['status'] = (string)$data['status'];
            }

            if (empty($updateData)) {
                Logger::warning('Update attempt with no fields', [
                    'appointment_id' => $id,
                    'user_id' => $userId,
                    'received_data' => $data,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: start_at, professional_id, service_id, status',
                    400,
                    $request->requestId
                );
                return;
            }

            $updatedAppointment = $appointmentService->update($id, $updateData, $userId);

            Logger::info('Appointment updated', [
                'appointment_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $appointment[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'appointment', (string)$id, null, null, [
                'changes' => $changes,
            ]);

            Response::success([
                'appointment' => $updatedAppointment,
                'message' => 'Appointment updated successfully',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to update appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'conflict') !== false) {
                Response::conflict('Time slot conflict', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Cannot update') !== false || strpos($e->getMessage(), 'Invalid') !== false) {
                Response::error('UPDATE_FAILED', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to update appointment', $request->requestId);
            }
        }
    }

    /**
     * POST /api/v1/appointments/:id/cancel
     * 
     * Cancel appointment
     */
    public function cancel(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];
        $userRole = (string)($request->user['role'] ?? 'client');

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);
            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            if (!$this->canAccessAppointment($request->user, $appointment)) {
                Response::forbidden('You cannot cancel this appointment', $request->requestId);
                return;
            }

            $appointmentService->cancel($id, $userId, $userRole !== 'client');

            Logger::info('Appointment cancelled', [
                'appointment_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            AuditService::logFromRequest($request, 'cancel', 'appointment', (string)$id, null, null, null);

            Response::success([
                'message' => 'Appointment cancelled successfully',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to cancel appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot cancel this appointment', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Cannot cancel') !== false) {
                Response::error('CANCEL_FAILED', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to cancel appointment', $request->requestId);
            }
        }
    }

    /**
     * POST /api/v1/appointments/{id}/complete
     * 
     * Mark appointment as complete (professional/manager/admin)
     */
    public function complete(Request $request, int $id): void
    {
        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);
            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            if (!$this->canManageAppointment($request->user, $appointment)) {
                Response::forbidden('You cannot complete this appointment', $request->requestId);
                return;
            }

            $appointment = $appointmentService->complete($id);

            Logger::info('Appointment completed', [
                'appointment_id' => $id,
                'completed_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'complete', 'appointment', (string)$id, null, null, null);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Appointment marked as complete',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to complete appointment', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            }
            elseif (strpos($e->getMessage(), 'Cannot complete') !== false) {
                Response::error('COMPLETE_FAILED', $e->getMessage(), 400, $request->requestId);
            }
            else {
                Response::serverError('Failed to complete appointment', $request->requestId);
            }
        }
    }

    /**
     * POST /api/v1/appointments/{id}/no-show
     * 
     * Mark appointment as no-show (professional/manager/admin)
     */
    public function noShow(Request $request, int $id): void
    {
        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);
            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            if (!$this->canManageAppointment($request->user, $appointment)) {
                Response::forbidden('You cannot mark this appointment as no-show', $request->requestId);
                return;
            }

            $appointment = $appointmentService->noShow($id);

            Logger::info('Appointment marked as no-show', [
                'appointment_id' => $id,
                'marked_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'no_show', 'appointment', (string)$id, null, null, null);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Appointment marked as no-show',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to mark appointment as no-show', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            }
            else {
                Response::serverError('Failed to mark as no-show', $request->requestId);
            }
        }
    }

    /**
     * GET /api/v1/appointments/available-slots
     * 
     * Get available time slots for professional/service/date
     */
    public function availableSlots(Request $request): void
    {
        $params = $request->getQuery();

        // Validate input
        $errors = Validator::make($params, [
            'professional_id' => 'required|integer',
            'service_id' => 'required|integer',
            'date' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $professionalId = (int)$params['professional_id'];
        $serviceId = (int)$params['service_id'];
        $date = $params['date'];

        // Validate date format
        $dateTimestamp = strtotime($date);
        if ($dateTimestamp === false) {
            Response::error('INVALID_DATE', 'Invalid date format', 400, $request->requestId);
            return;
        }

        $formattedDate = date('Y-m-d', $dateTimestamp);

        try {
            $appointmentService = new AppointmentService();
            $slots = $appointmentService->getAvailableSlots($professionalId, $serviceId, $formattedDate);

            Response::success([
                'slots' => $slots,
                'total' => count($slots),
                'date' => $formattedDate,
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get available slots', [
                'professional_id' => $professionalId,
                'service_id' => $serviceId,
                'date' => $date,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound($e->getMessage(), $request->requestId);
            }
            else {
                Response::serverError('Failed to retrieve available slots', $request->requestId);
            }
        }
    }

    private function canAccessAppointment(array $actor, array $appointment): bool
    {
        if ($this->accessService->isAdmin($actor)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorRole = (string)($actor['role'] ?? 'client');

        if ($actorRole === 'client') {
            return (int)($appointment['user_id'] ?? 0) === $actorId;
        }

        if ($actorRole === 'professional') {
            return (int)($appointment['professional_user_id'] ?? 0) === $actorId;
        }

        $establishment = $this->resolveAppointmentEstablishment($appointment);
        return $establishment
            ? $this->accessService->canAccessEstablishment($actor, $establishment)
            : false;
    }

    private function canManageAppointment(array $actor, array $appointment): bool
    {
        if ($this->accessService->isAdmin($actor)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorRole = (string)($actor['role'] ?? 'client');

        if ($actorRole === 'professional') {
            return (int)($appointment['professional_user_id'] ?? 0) === $actorId;
        }

        if ($actorRole !== 'manager') {
            return false;
        }

        $establishment = $this->resolveAppointmentEstablishment($appointment);
        return $establishment
            ? $this->accessService->canAccessEstablishment($actor, $establishment)
            : false;
    }

    private function resolveAppointmentEstablishment(array $appointment): ?array
    {
        $establishmentId = (int)($appointment['establishment_id'] ?? 0);
        if ($establishmentId <= 0) {
            return null;
        }

        return Establishment::find($establishmentId);
    }

    private function notifyAppointmentCreated(array $appointment, int $actorId): void
    {
        $appointmentId = (int)($appointment['id'] ?? 0);
        if ($appointmentId <= 0) {
            return;
        }

        $serviceName = (string)($appointment['service_name'] ?? 'o servico');
        $establishmentName = (string)($appointment['establishment_name'] ?? 'o estabelecimento');
        $deepLink = '/app/appointments/' . $appointmentId;

        Notification::create([
            'user_id' => (int)($appointment['user_id'] ?? 0),
            'type' => 'appointment_created',
            'title' => 'Agendamento confirmado',
            'body' => 'Seu agendamento para ' . $serviceName . ' em ' . $establishmentName . ' foi confirmado.',
            'data' => [
                'appointment_id' => $appointmentId,
                'deep_link' => $deepLink,
            ],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        $professionalUserId = (int)($appointment['professional_user_id'] ?? 0);
        if ($professionalUserId > 0 && $professionalUserId !== $actorId) {
            Notification::create([
                'user_id' => $professionalUserId,
                'type' => 'appointment_created',
                'title' => 'Novo agendamento confirmado',
                'body' => ($appointment['user_name'] ?? 'Um cliente') . ' foi agendado para ' . $serviceName . '.',
                'data' => [
                    'appointment_id' => $appointmentId,
                    'deep_link' => $deepLink,
                ],
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
