<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

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
            $db = Database::getInstance();
            
            $sql = "
                SELECT id, name, address, phone, email, created_at 
                FROM establishments 
                ORDER BY name ASC
            ";
            $establishments = $db->query($sql);

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
            $db = Database::getInstance();
            
            $sql = "
                SELECT id, name, address, phone, email, created_at 
                FROM establishments 
                WHERE id = ? 
                LIMIT 1
            ";
            $establishments = $db->query($sql, [$id]);

            if (empty($establishments)) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            Response::success([
                'establishment' => $establishments[0],
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
            $db = Database::getInstance();
            
            // Verify establishment exists
            $estSql = "SELECT id FROM establishments WHERE id = ? LIMIT 1";
            $establishments = $db->query($estSql, [$id]);

            if (empty($establishments)) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            // Fetch services
            $sql = "
                SELECT id, establishment_id, name, description, duration_minutes, created_at 
                FROM services 
                WHERE establishment_id = ? 
                ORDER BY name ASC
            ";
            $services = $db->query($sql, [$id]);

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
            $db = Database::getInstance();
            
            // Verify establishment exists
            $estSql = "SELECT id FROM establishments WHERE id = ? LIMIT 1";
            $establishments = $db->query($estSql, [$id]);

            if (empty($establishments)) {
                Response::notFound('Establishment not found', $request->requestId);
                return;
            }

            // Fetch professionals
            $sql = "
                SELECT u.id, u.name, u.email, u.created_at 
                FROM users u
                INNER JOIN establishment_professionals ep ON ep.user_id = u.id
                WHERE ep.establishment_id = ? 
                  AND u.role IN ('attendant', 'admin')
                ORDER BY u.name ASC
            ";
            $professionals = $db->query($sql, [$id]);

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
}
