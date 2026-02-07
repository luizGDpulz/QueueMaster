<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;

/**
 * ProfessionalService Model - Pivot table for professionals <-> services
 * 
 * Manages the many-to-many relationship between professionals and services.
 * A professional can offer multiple services, and a service can be offered by multiple professionals.
 */
class ProfessionalService
{
    protected static string $table = 'professional_services';
    protected static string $primaryKey = 'id';

    /**
     * Link a professional to a service
     * 
     * @param int $professionalId Professional ID
     * @param int $serviceId Service ID
     * @return int Inserted record ID
     */
    public static function link(int $professionalId, int $serviceId): int
    {
        // Check if already linked
        if (self::exists($professionalId, $serviceId)) {
            throw new \InvalidArgumentException('Professional is already linked to this service');
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert([
            'professional_id' => $professionalId,
            'service_id' => $serviceId,
        ]);
    }

    /**
     * Unlink a professional from a service
     * 
     * @param int $professionalId Professional ID
     * @param int $serviceId Service ID
     * @return int Affected rows
     */
    public static function unlink(int $professionalId, int $serviceId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('professional_id', '=', $professionalId)
            ->where('service_id', '=', $serviceId)
            ->delete();
    }

    /**
     * Check if a professional-service link exists
     * 
     * @param int $professionalId Professional ID
     * @param int $serviceId Service ID
     * @return bool
     */
    public static function exists(int $professionalId, int $serviceId): bool
    {
        $qb = new QueryBuilder();
        $result = $qb->select(self::$table)
            ->where('professional_id', '=', $professionalId)
            ->where('service_id', '=', $serviceId)
            ->first();
        
        return $result !== null;
    }

    /**
     * Get all services for a professional
     * 
     * @param int $professionalId Professional ID
     * @return array Array of services
     */
    public static function getServicesForProfessional(int $professionalId): array
    {
        $qb = new QueryBuilder();
        $links = $qb->select(self::$table)
            ->where('professional_id', '=', $professionalId)
            ->get();

        $services = [];
        foreach ($links as $link) {
            $service = Service::find($link['service_id']);
            if ($service) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * Get all professionals for a service
     * 
     * @param int $serviceId Service ID
     * @return array Array of professionals
     */
    public static function getProfessionalsForService(int $serviceId): array
    {
        $qb = new QueryBuilder();
        $links = $qb->select(self::$table)
            ->where('service_id', '=', $serviceId)
            ->get();

        $professionals = [];
        foreach ($links as $link) {
            $professional = Professional::find($link['professional_id']);
            if ($professional) {
                $professionals[] = $professional;
            }
        }

        return $professionals;
    }

    /**
     * Sync services for a professional (replace all links)
     * 
     * @param int $professionalId Professional ID
     * @param array $serviceIds Array of service IDs
     * @return void
     */
    public static function syncServicesForProfessional(int $professionalId, array $serviceIds): void
    {
        // Remove all existing links
        $qb = new QueryBuilder();
        $qb->select(self::$table)
            ->where('professional_id', '=', $professionalId)
            ->delete();

        // Add new links
        foreach ($serviceIds as $serviceId) {
            self::link($professionalId, (int)$serviceId);
        }
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - professional_id: bigint NOT NULL [FK -> professionals]
     * - service_id: bigint NOT NULL [FK -> services]
     * - created_at: timestamp NOT NULL
     * - UNIQUE(professional_id, service_id)
     */
}
