<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Services\AppointmentRequestService;
use QueueMaster\Services\AuditService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

class AppointmentRequestsController
{
    public function list(Request $request): void
    {
        $params = $request->getQuery();
        $filters = [];

        foreach (['status', 'direction', 'date'] as $field) {
            if (!empty($params[$field])) {
                $filters[$field] = $params[$field];
            }
        }

        if (!empty($params['establishment_id'])) {
            $filters['establishment_id'] = (int)$params['establishment_id'];
        }

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;

        try {
            $service = new AppointmentRequestService();
            $result = $service->list($request->user, $filters, $page, $perPage);

            Response::success($result['requests'], [
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
                'summary' => [
                    'pending_count' => $result['pending_count'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->respondToRuntimeException($e, $request->requestId, 'Failed to retrieve appointment requests');
        } catch (\Exception $e) {
            Logger::error('Failed to list appointment requests', [
                'user_id' => $request->user['id'] ?? null,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointment requests', $request->requestId);
        }
    }

    public function create(Request $request): void
    {
        $data = $request->all();
        $actorRole = (string)($request->user['role'] ?? 'client');

        $rules = [
            'establishment_id' => 'required|integer',
            'service_id' => 'required|integer',
            'start_at' => 'required',
            'notes' => 'max:2000',
        ];

        if ($actorRole !== 'client') {
            $rules['client_user_id'] = 'required|integer';
        }

        $errors = Validator::make($data, $rules);
        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $service = new AppointmentRequestService();
            $appointmentRequest = $service->create($data, $request->user);

            AuditService::logFromRequest(
                $request,
                'create',
                'appointment_request',
                (string)($appointmentRequest['id'] ?? 0),
                $appointmentRequest['establishment_id'] ?? null,
                null,
                [
                    'service_id' => $appointmentRequest['service_id'] ?? null,
                    'professional_id' => $appointmentRequest['professional_id'] ?? null,
                    'client_user_id' => $appointmentRequest['client_user_id'] ?? null,
                    'direction' => $appointmentRequest['direction'] ?? null,
                    'proposed_start_at' => $appointmentRequest['proposed_start_at'] ?? null,
                ]
            );

            Response::created([
                'request' => $appointmentRequest,
                'message' => 'Appointment request created successfully',
            ]);
        } catch (\RuntimeException $e) {
            $this->respondToRuntimeException($e, $request->requestId, 'Failed to create appointment request');
        } catch (\InvalidArgumentException $e) {
            Response::error('INVALID_DATA', $e->getMessage(), 400, $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to create appointment request', [
                'user_id' => $request->user['id'] ?? null,
                'payload' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create appointment request', $request->requestId);
        }
    }

    public function accept(Request $request, int $id): void
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'professional_id' => 'integer',
            'decision_note' => 'max:2000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $service = new AppointmentRequestService();
            $result = $service->accept($id, $request->user, $data);

            AuditService::logFromRequest(
                $request,
                'accept',
                'appointment_request',
                (string)$id,
                $result['request']['establishment_id'] ?? null,
                null,
                [
                    'appointment_id' => $result['appointment']['id'] ?? null,
                    'professional_id' => $result['request']['professional_id'] ?? null,
                ]
            );

            Response::success([
                'request' => $result['request'],
                'appointment' => $result['appointment'],
                'message' => 'Appointment request accepted successfully',
            ]);
        } catch (\RuntimeException $e) {
            $this->respondToRuntimeException($e, $request->requestId, 'Failed to accept appointment request');
        } catch (\Exception $e) {
            Logger::error('Failed to accept appointment request', [
                'request_id' => $id,
                'user_id' => $request->user['id'] ?? null,
                'payload' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to accept appointment request', $request->requestId);
        }
    }

    public function reject(Request $request, int $id): void
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'decision_note' => 'max:2000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $service = new AppointmentRequestService();
            $appointmentRequest = $service->reject($id, $request->user, $data['decision_note'] ?? null);

            AuditService::logFromRequest(
                $request,
                'reject',
                'appointment_request',
                (string)$id,
                $appointmentRequest['establishment_id'] ?? null,
                null,
                [
                    'decision_note' => $appointmentRequest['decision_note'] ?? null,
                ]
            );

            Response::success([
                'request' => $appointmentRequest,
                'message' => 'Appointment request rejected successfully',
            ]);
        } catch (\RuntimeException $e) {
            $this->respondToRuntimeException($e, $request->requestId, 'Failed to reject appointment request');
        } catch (\Exception $e) {
            Logger::error('Failed to reject appointment request', [
                'request_id' => $id,
                'user_id' => $request->user['id'] ?? null,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to reject appointment request', $request->requestId);
        }
    }

    public function cancel(Request $request, int $id): void
    {
        try {
            $service = new AppointmentRequestService();
            $appointmentRequest = $service->cancel($id, $request->user);

            AuditService::logFromRequest(
                $request,
                'cancel',
                'appointment_request',
                (string)$id,
                $appointmentRequest['establishment_id'] ?? null,
                null,
                null
            );

            Response::success([
                'request' => $appointmentRequest,
                'message' => 'Appointment request cancelled successfully',
            ]);
        } catch (\RuntimeException $e) {
            $this->respondToRuntimeException($e, $request->requestId, 'Failed to cancel appointment request');
        } catch (\Exception $e) {
            Logger::error('Failed to cancel appointment request', [
                'request_id' => $id,
                'user_id' => $request->user['id'] ?? null,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to cancel appointment request', $request->requestId);
        }
    }

    private function respondToRuntimeException(\RuntimeException $exception, ?string $requestId, string $fallbackMessage): void
    {
        $message = $exception->getMessage();
        $normalized = strtolower($message);

        if (str_contains($normalized, 'not found')) {
            Response::notFound($message, $requestId);
            return;
        }

        if (str_contains($normalized, 'already exists') || str_contains($normalized, 'conflict')) {
            Response::conflict($message, $requestId);
            return;
        }

        if (str_contains($normalized, 'pending') || str_contains($normalized, 'status')) {
            Response::error('INVALID_STATUS', $message, 409, $requestId);
            return;
        }

        if (
            str_contains($normalized, 'unauthorized')
            || str_contains($normalized, 'forbidden')
            || str_contains($normalized, 'voce nao')
            || str_contains($normalized, 'não')
            || str_contains($normalized, 'only ')
            || str_contains($normalized, 'so pode')
        ) {
            Response::forbidden($message, $requestId);
            return;
        }

        Logger::error($fallbackMessage, [
            'error' => $message,
        ], $requestId);

        Response::serverError($fallbackMessage, $requestId);
    }
}
