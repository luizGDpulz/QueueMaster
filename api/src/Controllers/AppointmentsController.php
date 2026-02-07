<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Services\AppointmentService;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;

/**
 * AppointmentsController - Appointment Management Endpoints
 * 
 * Handles appointment operations: create, list, get, check-in, cancel, available slots
 */
class AppointmentsController
{
    /**
     * POST /api/v1/appointments
     * 
     * Create appointment
     */
    public function create(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $data = $request->all();
        $userId = (int)$request->user['id'];

        // Validate input
        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'professional_id' => 'required|integer',
            'service_id' => 'required|integer',
            'start_at' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        // Add user_id to data
        $data['user_id'] = $userId;

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->create($data);

            Logger::info('Appointment created', [
                'appointment_id' => $appointment['id'],
                'user_id' => $userId,
            ], $request->requestId);

            Response::created([
                'appointment' => $appointment,
                'message' => 'Appointment created successfully',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to create appointment', [
                'user_id' => $userId,
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'conflict') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                Response::conflict('Time slot is already booked', $request->requestId);
            } elseif (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound($e->getMessage(), $request->requestId);
            } elseif (strpos($e->getMessage(), 'Invalid') !== false) {
                Response::error('INVALID_DATA', $e->getMessage(), 400, $request->requestId);
            } else {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $params = $request->getQuery();
        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'];

        // Build filters
        $filters = [];

        // Clients can only see their own appointments
        if ($userRole === 'client') {
            $filters['user_id'] = $userId;
        } else {
            // Attendants and admins can filter by user_id or professional_id
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

        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }

        if (isset($params['date'])) {
            $filters['date'] = $params['date'];
        }

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;

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

        } catch (\Exception $e) {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);

            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            // Clients can only view their own appointments
            if ($userRole === 'client' && $appointment['user_id'] != $userId) {
                Response::forbidden('You cannot view this appointment', $request->requestId);
                return;
            }

            Response::success([
                'appointment' => $appointment,
            ]);

        } catch (\Exception $e) {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->checkIn($id, $userId);

            Logger::info('Appointment checked-in', [
                'appointment_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Successfully checked-in',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to check-in appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot check-in to this appointment', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Cannot check-in') !== false || strpos($e->getMessage(), 'early') !== false || strpos($e->getMessage(), 'passed') !== false) {
                Response::error('CHECK_IN_FAILED', $e->getMessage(), 400, $request->requestId);
            } else {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'];

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->getAppointment($id);

            if (!$appointment) {
                Response::notFound('Appointment not found', $request->requestId);
                return;
            }

            // Clients can only update their own appointments
            if ($userRole === 'client' && $appointment['user_id'] != $userId) {
                Response::forbidden('You cannot update this appointment', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            // Allow updating start_at, professional_id, service_id
            if (isset($data['start_at'])) {
                $updateData['start_at'] = $data['start_at'];
            }

            if (isset($data['professional_id'])) {
                $updateData['professional_id'] = (int)$data['professional_id'];
            }

            if (isset($data['service_id'])) {
                $updateData['service_id'] = (int)$data['service_id'];
            }

            if (empty($updateData)) {
                Logger::warning('Update attempt with no fields', [
                    'appointment_id' => $id,
                    'user_id' => $userId,
                    'received_data' => $data,
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: start_at, professional_id, service_id',
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

            Response::success([
                'appointment' => $updatedAppointment,
                'message' => 'Appointment updated successfully',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to update appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'conflict') !== false) {
                Response::conflict('Time slot conflict', $request->requestId);
            } else {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        try {
            $appointmentService = new AppointmentService();
            $appointmentService->cancel($id, $userId);

            Logger::info('Appointment cancelled', [
                'appointment_id' => $id,
                'user_id' => $userId,
            ], $request->requestId);

            Response::success([
                'message' => 'Appointment cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to cancel appointment', [
                'appointment_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Unauthorized') !== false) {
                Response::forbidden('You cannot cancel this appointment', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Cannot cancel') !== false) {
                Response::error('CANCEL_FAILED', $e->getMessage(), 400, $request->requestId);
            } else {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->complete($id);

            Logger::info('Appointment completed', [
                'appointment_id' => $id,
                'completed_by' => $request->user['id'],
            ], $request->requestId);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Appointment marked as complete',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to complete appointment', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            } elseif (strpos($e->getMessage(), 'Cannot complete') !== false) {
                Response::error('COMPLETE_FAILED', $e->getMessage(), 400, $request->requestId);
            } else {
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
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $appointmentService = new AppointmentService();
            $appointment = $appointmentService->noShow($id);

            Logger::info('Appointment marked as no-show', [
                'appointment_id' => $id,
                'marked_by' => $request->user['id'],
            ], $request->requestId);

            Response::success([
                'appointment' => $appointment,
                'message' => 'Appointment marked as no-show',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to mark appointment as no-show', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound('Appointment not found', $request->requestId);
            } else {
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

        } catch (\Exception $e) {
            Logger::error('Failed to get available slots', [
                'professional_id' => $professionalId,
                'service_id' => $serviceId,
                'date' => $date,
                'error' => $e->getMessage(),
            ], $request->requestId);

            if (strpos($e->getMessage(), 'not found') !== false) {
                Response::notFound($e->getMessage(), $request->requestId);
            } else {
                Response::serverError('Failed to retrieve available slots', $request->requestId);
            }
        }
    }
}
