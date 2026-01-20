<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * DashboardController - Dashboard Endpoints for Attendants/Admins
 * 
 * Provides overview statistics and management actions for queue/appointment management
 */
class DashboardController
{
    /**
     * GET /api/v1/dashboard/queue-overview
     * 
     * Get queue statistics (requires attendant or admin role)
     */
    public function queueOverview(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'admin'])) {
            Response::forbidden('Only attendants and admins can view dashboard', $request->requestId);
            return;
        }

        try {
            $db = Database::getInstance();
            $params = $request->getQuery();

            $establishmentId = isset($params['establishment_id']) ? (int)$params['establishment_id'] : null;

            // Build queue overview statistics
            $whereClauses = [];
            $values = [];

            if ($establishmentId) {
                $whereClauses[] = "q.establishment_id = ?";
                $values[] = $establishmentId;
            }

            $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            // Get queue statistics
            $queueStatsSql = "
                SELECT 
                    q.id,
                    q.name,
                    q.status,
                    COUNT(CASE WHEN qe.status = 'waiting' THEN 1 END) as waiting_count,
                    COUNT(CASE WHEN qe.status = 'called' THEN 1 END) as called_count,
                    COUNT(CASE WHEN qe.status = 'served' THEN 1 END) as served_count
                FROM queues q
                LEFT JOIN queue_entries qe ON qe.queue_id = q.id
                $whereClause
                GROUP BY q.id, q.name, q.status
                ORDER BY q.name ASC
            ";
            $queueStats = $db->query($queueStatsSql, $values);

            // Get today's totals
            $todayTotalsSql = "
                SELECT 
                    COUNT(CASE WHEN qe.status = 'served' THEN 1 END) as served_today,
                    COUNT(CASE WHEN qe.status = 'no_show' THEN 1 END) as no_show_today,
                    COUNT(CASE WHEN qe.status = 'waiting' THEN 1 END) as currently_waiting
                FROM queue_entries qe
                INNER JOIN queues q ON q.id = qe.queue_id
                WHERE DATE(qe.created_at) = CURDATE()
                " . ($establishmentId ? "AND q.establishment_id = ?" : "");
            
            $todayValues = $establishmentId ? [$establishmentId] : [];
            $todayTotalsResult = $db->query($todayTotalsSql, $todayValues);
            $todayTotals = $todayTotalsResult[0];

            Response::success([
                'queues' => $queueStats,
                'totals' => [
                    'served_today' => (int)$todayTotals['served_today'],
                    'no_show_today' => (int)$todayTotals['no_show_today'],
                    'currently_waiting' => (int)$todayTotals['currently_waiting'],
                ],
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get queue overview', [
                'user_id' => $request->user['id'],
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue overview', $request->requestId);
        }
    }

    /**
     * GET /api/v1/dashboard/appointments
     * 
     * Get today's appointments (requires attendant or admin role)
     */
    public function appointmentsList(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'admin'])) {
            Response::forbidden('Only attendants and admins can view dashboard', $request->requestId);
            return;
        }

        try {
            $db = Database::getInstance();
            $params = $request->getQuery();

            $establishmentId = isset($params['establishment_id']) ? (int)$params['establishment_id'] : null;
            $professionalId = isset($params['professional_id']) ? (int)$params['professional_id'] : null;
            $date = isset($params['date']) ? $params['date'] : date('Y-m-d');

            // Build filters
            $whereClauses = ["DATE(a.start_at) = ?"];
            $values = [$date];

            if ($establishmentId) {
                $whereClauses[] = "a.establishment_id = ?";
                $values[] = $establishmentId;
            }

            if ($professionalId) {
                $whereClauses[] = "a.professional_id = ?";
                $values[] = $professionalId;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);

            // Get appointments
            $sql = "
                SELECT 
                    a.*,
                    u.name as user_name,
                    u.email as user_email,
                    p.name as professional_name,
                    s.name as service_name
                FROM appointments a
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN users p ON p.id = a.professional_id
                LEFT JOIN services s ON s.id = a.service_id
                $whereClause
                ORDER BY a.start_at ASC
            ";
            $appointments = $db->query($sql, $values);

            Response::success([
                'appointments' => $appointments,
                'total' => count($appointments),
                'date' => $date,
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get appointments list', [
                'user_id' => $request->user['id'],
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointments', $request->requestId);
        }
    }

    /**
     * POST /api/v1/dashboard/queue-entries/:entryId/mark-served
     * 
     * Mark queue entry as served (requires attendant or admin role)
     */
    public function markServed(Request $request, int $entryId): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'admin'])) {
            Response::forbidden('Only attendants and admins can mark as served', $request->requestId);
            return;
        }

        try {
            $db = Database::getInstance();

            // Verify entry exists and is in called status
            $entrySql = "SELECT id, status, queue_id, user_id FROM queue_entries WHERE id = ? LIMIT 1";
            $entries = $db->query($entrySql, [$entryId]);

            if (empty($entries)) {
                Response::notFound('Queue entry not found', $request->requestId);
                return;
            }

            $entry = $entries[0];

            if ($entry['status'] !== 'called') {
                Response::error(
                    'INVALID_STATUS',
                    'Can only mark called entries as served',
                    400,
                    $request->requestId
                );
                return;
            }

            // Update status
            $updateSql = "UPDATE queue_entries SET status = 'served', served_at = NOW() WHERE id = ?";
            $db->execute($updateSql, [$entryId]);

            Logger::info('Queue entry marked as served', [
                'entry_id' => $entryId,
                'marked_by' => $request->user['id'],
            ], $request->requestId);

            Response::success([
                'message' => 'Queue entry marked as served',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to mark entry as served', [
                'entry_id' => $entryId,
                'user_id' => $request->user['id'],
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to mark as served', $request->requestId);
        }
    }

    /**
     * POST /api/v1/dashboard/mark-no-show
     * 
     * Mark queue entry or appointment as no-show (requires attendant or admin role)
     */
    public function markNoShow(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userRole = $request->user['role'];
        if (!in_array($userRole, ['attendant', 'admin'])) {
            Response::forbidden('Only attendants and admins can mark as no-show', $request->requestId);
            return;
        }

        $data = $request->all();

        // Validate input - must provide either queue_entry_id or appointment_id
        if (!isset($data['queue_entry_id']) && !isset($data['appointment_id'])) {
            Response::error(
                'INVALID_INPUT',
                'Must provide either queue_entry_id or appointment_id',
                400,
                $request->requestId
            );
            return;
        }

        try {
            $db = Database::getInstance();

            if (isset($data['queue_entry_id'])) {
                $entryId = (int)$data['queue_entry_id'];

                // Verify entry exists
                $entrySql = "SELECT id, status FROM queue_entries WHERE id = ? LIMIT 1";
                $entries = $db->query($entrySql, [$entryId]);

                if (empty($entries)) {
                    Response::notFound('Queue entry not found', $request->requestId);
                    return;
                }

                // Update status
                $updateSql = "UPDATE queue_entries SET status = 'no_show' WHERE id = ?";
                $db->execute($updateSql, [$entryId]);

                Logger::info('Queue entry marked as no-show', [
                    'entry_id' => $entryId,
                    'marked_by' => $request->user['id'],
                ], $request->requestId);

            } elseif (isset($data['appointment_id'])) {
                $appointmentId = (int)$data['appointment_id'];

                // Verify appointment exists
                $appointmentSql = "SELECT id, status FROM appointments WHERE id = ? LIMIT 1";
                $appointments = $db->query($appointmentSql, [$appointmentId]);

                if (empty($appointments)) {
                    Response::notFound('Appointment not found', $request->requestId);
                    return;
                }

                // Update status
                $updateSql = "UPDATE appointments SET status = 'no_show' WHERE id = ?";
                $db->execute($updateSql, [$appointmentId]);

                Logger::info('Appointment marked as no-show', [
                    'appointment_id' => $appointmentId,
                    'marked_by' => $request->user['id'],
                ], $request->requestId);
            }

            Response::success([
                'message' => 'Marked as no-show',
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to mark as no-show', [
                'data' => $data,
                'user_id' => $request->user['id'],
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to mark as no-show', $request->requestId);
        }
    }
}
