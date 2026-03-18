<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\EstablishmentUser;
use QueueMaster\Models\Professional;
use QueueMaster\Models\ProfessionalEstablishment;
use QueueMaster\Models\Queue;
use QueueMaster\Models\Service;

class ContextAccessService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function isAdmin(array $user): bool
    {
        return ($user['role'] ?? null) === 'admin';
    }

    public function isManager(array $user): bool
    {
        return ($user['role'] ?? null) === 'manager';
    }

    public function isProfessional(array $user): bool
    {
        return ($user['role'] ?? null) === 'professional';
    }

    public function isClient(array $user): bool
    {
        return ($user['role'] ?? null) === 'client';
    }

    public function isStaff(array $user): bool
    {
        return in_array($user['role'] ?? null, ['admin', 'manager', 'professional'], true);
    }

    public function canAccessBusiness(array $user, int $businessId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $businesses = $this->getAccessibleBusinessIds($user);
        return in_array($businessId, $businesses, true);
    }

    public function canAccessEstablishment(array $user, array $establishment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userId = (int)($user['id'] ?? 0);
        $establishmentId = (int)($establishment['id'] ?? 0);

        if ($userId <= 0 || $establishmentId <= 0) {
            return false;
        }

        if (!empty($establishment['owner_id']) && (int)$establishment['owner_id'] === $userId) {
            return true;
        }

        if (EstablishmentUser::exists($establishmentId, $userId)) {
            return true;
        }

        if (ProfessionalEstablishment::exists($userId, $establishmentId)) {
            return true;
        }

        $professionalLinks = Professional::all([
            'establishment_id' => $establishmentId,
            'user_id' => $userId,
        ]);
        if (!empty($professionalLinks)) {
            return true;
        }

        $businessId = (int)($establishment['business_id'] ?? 0);
        $businessRole = $businessId > 0 ? BusinessUser::getRole($businessId, $userId) : null;
        if (in_array($businessRole, [BusinessUser::ROLE_OWNER, BusinessUser::ROLE_MANAGER], true)) {
            return true;
        }

        return false;
    }

    public function canAccessQueue(array $user, array $queue): bool
    {
        $establishment = Establishment::find((int)($queue['establishment_id'] ?? 0));
        return $establishment ? $this->canAccessEstablishment($user, $establishment) : false;
    }

    public function canAccessService(array $user, array $service): bool
    {
        $establishment = Establishment::find((int)($service['establishment_id'] ?? 0));
        return $establishment ? $this->canAccessEstablishment($user, $establishment) : false;
    }

    public function requireBusinessAccess(array $user, int $businessId, string $message = 'Voce nao tem acesso a este negocio'): void
    {
        if (!$this->canAccessBusiness($user, $businessId)) {
            throw new \RuntimeException($message);
        }
    }

    public function requireEstablishmentAccess(array $user, array $establishment, string $message = 'Voce nao tem acesso a este estabelecimento'): void
    {
        if (!$this->canAccessEstablishment($user, $establishment)) {
            throw new \RuntimeException($message);
        }
    }

    public function requireQueueAccess(array $user, array $queue, string $message = 'Voce nao tem acesso a esta fila'): void
    {
        if (!$this->canAccessQueue($user, $queue)) {
            throw new \RuntimeException($message);
        }
    }

    public function requireServiceAccess(array $user, array $service, string $message = 'Voce nao tem acesso a este servico'): void
    {
        if (!$this->canAccessService($user, $service)) {
            throw new \RuntimeException($message);
        }
    }

    public function canManageBusiness(array $user, int $businessId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->isManager($user)) {
            return false;
        }

        return $this->canAccessBusiness($user, $businessId);
    }

    public function requireBusinessManagement(array $user, int $businessId, string $message = 'Voce nao tem permissao para gerenciar este negocio'): void
    {
        if (!$this->canManageBusiness($user, $businessId)) {
            throw new \RuntimeException($message);
        }
    }

    public function canManageEstablishment(array $user, array $establishment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->isManager($user)) {
            return false;
        }

        return $this->canAccessEstablishment($user, $establishment);
    }

    public function requireEstablishmentManagement(array $user, array $establishment, string $message = 'Voce nao tem permissao para gerenciar este estabelecimento'): void
    {
        if (!$this->canManageEstablishment($user, $establishment)) {
            throw new \RuntimeException($message);
        }
    }

    public function canManageService(array $user, array $service): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->isManager($user)) {
            return false;
        }

        return $this->canAccessService($user, $service);
    }

    public function requireServiceManagement(array $user, array $service, string $message = 'Voce nao tem permissao para gerenciar este servico'): void
    {
        if (!$this->canManageService($user, $service)) {
            throw new \RuntimeException($message);
        }
    }

    public function canViewReports(array $user): bool
    {
        return $this->isStaff($user);
    }

    public function getAccessibleBusinessIds(array $user): array
    {
        return array_map(
            static fn(array $business): int => (int)$business['id'],
            $this->getAccessibleBusinesses($user)
        );
    }

    public function getAccessibleBusinesses(array $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->db->query(
                "SELECT id, name, slug FROM businesses ORDER BY name ASC"
            );
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        return $this->db->query(
            "
            SELECT DISTINCT b.id, b.name, b.slug
            FROM businesses b
            LEFT JOIN business_users bu
              ON bu.business_id = b.id
             AND bu.user_id = ?
            LEFT JOIN establishments e
              ON e.business_id = b.id
            LEFT JOIN professional_establishments pe
              ON pe.establishment_id = e.id
             AND pe.user_id = ?
             AND pe.is_active = 1
            LEFT JOIN establishment_users eu
              ON eu.establishment_id = e.id
             AND eu.user_id = ?
            LEFT JOIN professionals p
              ON p.establishment_id = e.id
             AND p.user_id = ?
            WHERE b.owner_user_id = ?
               OR bu.id IS NOT NULL
               OR pe.id IS NOT NULL
               OR eu.id IS NOT NULL
               OR p.id IS NOT NULL
            ORDER BY b.name ASC
            ",
            [$userId, $userId, $userId, $userId, $userId]
        );
    }

    public function getAccessibleEstablishments(array $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->db->query(
                "
                SELECT e.id, e.business_id, e.name, e.slug, b.name AS business_name
                FROM establishments e
                LEFT JOIN businesses b ON b.id = e.business_id
                ORDER BY b.name ASC, e.name ASC
                "
            );
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        return $this->db->query(
            "
            SELECT DISTINCT e.id, e.business_id, e.name, e.slug, b.name AS business_name
            FROM establishments e
            LEFT JOIN businesses b
              ON b.id = e.business_id
            LEFT JOIN business_users bu
              ON bu.business_id = e.business_id
             AND bu.user_id = ?
            LEFT JOIN professional_establishments pe
              ON pe.establishment_id = e.id
             AND pe.user_id = ?
             AND pe.is_active = 1
            LEFT JOIN establishment_users eu
              ON eu.establishment_id = e.id
             AND eu.user_id = ?
            LEFT JOIN professionals p
              ON p.establishment_id = e.id
             AND p.user_id = ?
            WHERE e.owner_id = ?
               OR bu.role IN ('owner', 'manager')
               OR pe.id IS NOT NULL
               OR eu.id IS NOT NULL
               OR p.id IS NOT NULL
            ORDER BY b.name ASC, e.name ASC
            ",
            [$userId, $userId, $userId, $userId, $userId]
        );
    }

    public function getAccessibleEstablishmentIds(array $user): array
    {
        return array_map(
            static fn(array $establishment): int => (int)$establishment['id'],
            $this->getAccessibleEstablishments($user)
        );
    }

    public function getAccessibleQueues(array $user): array
    {
        $accessibleEstablishments = $this->getAccessibleEstablishmentIds($user);

        if ($this->isAdmin($user)) {
            return $this->db->query(
                "
                SELECT q.id, q.name, q.status, q.establishment_id, e.business_id,
                       e.name AS establishment_name, b.name AS business_name
                FROM queues q
                JOIN establishments e ON e.id = q.establishment_id
                LEFT JOIN businesses b ON b.id = e.business_id
                ORDER BY b.name ASC, e.name ASC, q.name ASC
                "
            );
        }

        if (empty($accessibleEstablishments)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($accessibleEstablishments), '?'));

        return $this->db->query(
            "
            SELECT q.id, q.name, q.status, q.establishment_id, e.business_id,
                   e.name AS establishment_name, b.name AS business_name
            FROM queues q
            JOIN establishments e ON e.id = q.establishment_id
            LEFT JOIN businesses b ON b.id = e.business_id
            WHERE q.establishment_id IN ($placeholders)
            ORDER BY b.name ASC, e.name ASC, q.name ASC
            ",
            $accessibleEstablishments
        );
    }

    public function getAccessibleReportProfessionals(array $user): array
    {
        $accessibleEstablishments = $this->getAccessibleEstablishmentIds($user);

        if (!$this->isAdmin($user) && empty($accessibleEstablishments)) {
            return [];
        }

        $whereSql = '';
        $params = [];

        if (!$this->isAdmin($user)) {
            $placeholders = implode(',', array_fill(0, count($accessibleEstablishments), '?'));
            $whereSql = "WHERE q.establishment_id IN ($placeholders)";
            $params = $accessibleEstablishments;
        }

        return $this->db->query(
            "
            SELECT DISTINCT u.id, u.name, u.email
            FROM queue_entries qe
            JOIN queues q ON q.id = qe.queue_id
            JOIN users u ON u.id = qe.professional_id
            $whereSql
            ORDER BY u.name ASC
            ",
            $params
        );
    }

    public function userBelongsToEstablishment(int $userId, int $establishmentId): bool
    {
        if ($userId <= 0 || $establishmentId <= 0) {
            return false;
        }

        $establishment = Establishment::find($establishmentId);
        if (!$establishment) {
            return false;
        }

        if (!empty($establishment['owner_id']) && (int)$establishment['owner_id'] === $userId) {
            return true;
        }

        if (EstablishmentUser::exists($establishmentId, $userId)) {
            return true;
        }

        if (ProfessionalEstablishment::exists($userId, $establishmentId)) {
            return true;
        }

        $professionalLinks = Professional::all([
            'establishment_id' => $establishmentId,
            'user_id' => $userId,
        ]);

        return !empty($professionalLinks);
    }

    public function serviceBelongsToEstablishment(int $serviceId, int $establishmentId): bool
    {
        $service = Service::find($serviceId);
        return $service && (int)$service['establishment_id'] === $establishmentId;
    }

    public function queueBelongsToEstablishment(int $queueId, int $establishmentId): bool
    {
        $queue = Queue::find($queueId);
        return $queue && (int)$queue['establishment_id'] === $establishmentId;
    }
}
