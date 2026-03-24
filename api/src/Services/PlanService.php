<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\Business;
use QueueMaster\Models\Plan;
use QueueMaster\Models\User;
use QueueMaster\Models\UserPlanSubscription;

class PlanService
{
    private Database $db;
    private UserRoleService $userRoleService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->userRoleService = new UserRoleService();
    }

    public function getSubscriptionsForUser(int $userId): array
    {
        $rows = $this->db->query(
            "
            SELECT ups.*, p.name AS plan_name
            FROM user_plan_subscriptions ups
            JOIN plans p
              ON p.id = ups.plan_id
            WHERE ups.user_id = ?
            ORDER BY
              CASE ups.status
                WHEN 'active' THEN 0
                WHEN 'past_due' THEN 1
                ELSE 2
              END ASC,
              COALESCE(ups.starts_at, '1970-01-01 00:00:00') DESC,
              ups.id DESC
            ",
            [$userId]
        );

        foreach ($rows as &$row) {
            $row['plan'] = Plan::find((int)$row['plan_id']);
        }
        unset($row);

        return $rows;
    }

    public function getCurrentSubscriptionForUser(int $userId): ?array
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        foreach ($subscriptions as $subscription) {
            if (in_array($subscription['status'], ['active', 'past_due'], true)) {
                return $subscription;
            }
        }

        return null;
    }

    public function getCurrentPlanForUser(int $userId): ?array
    {
        $subscription = $this->getCurrentSubscriptionForUser($userId);
        if (!$subscription) {
            return null;
        }

        return $subscription['plan'] ?? Plan::find((int)$subscription['plan_id']);
    }

    public function getDefaultPlan(): ?array
    {
        $rows = Plan::all(['name' => 'Free', 'is_active' => 1], 'id', 'ASC', 1);
        return $rows[0] ?? null;
    }

    public function userCanHoldPlan(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        if (($user['role'] ?? null) === 'admin') {
            return false;
        }

        if ((bool)($user['manager_access_granted'] ?? false)) {
            return true;
        }

        return $this->userRoleService->userOwnsBusiness($userId);
    }

    public function getHolderUserIdForBusiness(int $businessId): ?int
    {
        $business = Business::find($businessId);
        if (!$business) {
            return null;
        }

        $ownerUserId = (int)($business['owner_user_id'] ?? 0);
        return $ownerUserId > 0 ? $ownerUserId : null;
    }

    public function getCurrentPlanForBusiness(int $businessId): ?array
    {
        $holderUserId = $this->getHolderUserIdForBusiness($businessId);
        if ($holderUserId === null) {
            return null;
        }

        $holder = User::find($holderUserId);
        if (($holder['role'] ?? null) === 'admin') {
            return null;
        }

        return $this->getCurrentPlanForUser($holderUserId);
    }

    public function getUsageSnapshotForUser(int $userId): array
    {
        $businessCount = (int)($this->db->query(
            "SELECT COUNT(*) AS total FROM businesses WHERE owner_user_id = ?",
            [$userId]
        )[0]['total'] ?? 0);

        $businessUsageRows = $this->db->query(
            "
            SELECT
                b.id,
                b.name,
                COUNT(DISTINCT e.id) AS establishment_count,
                COUNT(DISTINCT CASE
                    WHEN bu.role = 'manager' THEN CONCAT('user:', bu.user_id)
                    WHEN eu.role = 'manager' THEN CONCAT('user:', eu.user_id)
                    ELSE NULL
                END) AS manager_count
            FROM businesses b
            LEFT JOIN establishments e
              ON e.business_id = b.id
            LEFT JOIN business_users bu
              ON bu.business_id = b.id
            LEFT JOIN establishment_users eu
              ON eu.establishment_id = e.id
            WHERE b.owner_user_id = ?
            GROUP BY b.id, b.name
            ORDER BY b.name ASC
            ",
            [$userId]
        );

        $establishmentUsageRows = $this->db->query(
            "
            SELECT
                e.id,
                e.name,
                e.business_id,
                b.name AS business_name,
                COUNT(DISTINCT professional_usage.member_key) AS professional_count
            FROM establishments e
            JOIN businesses b
              ON b.id = e.business_id
            LEFT JOIN (
                SELECT
                    eu.establishment_id,
                    CONCAT('user:', eu.user_id) AS member_key
                FROM establishment_users eu
                WHERE eu.role = 'professional'

                UNION

                SELECT
                    pe.establishment_id,
                    CONCAT('user:', pe.user_id) AS member_key
                FROM professional_establishments pe
                WHERE pe.is_active = 1

                UNION

                SELECT
                    p.establishment_id,
                    CASE
                        WHEN p.user_id IS NOT NULL THEN CONCAT('user:', p.user_id)
                        ELSE CONCAT('professional:', p.id)
                    END AS member_key
                FROM professionals p
                WHERE p.is_active = 1
            ) AS professional_usage
              ON professional_usage.establishment_id = e.id
            WHERE b.owner_user_id = ?
            GROUP BY e.id, e.name, e.business_id, b.name
            ORDER BY b.name ASC, e.name ASC
            ",
            [$userId]
        );

        $maxEstablishmentsPerBusiness = 0;
        $maxManagersPerBusiness = 0;
        foreach ($businessUsageRows as &$businessUsage) {
            $businessUsage['establishment_count'] = (int)$businessUsage['establishment_count'];
            $businessUsage['manager_count'] = (int)$businessUsage['manager_count'];
            $maxEstablishmentsPerBusiness = max($maxEstablishmentsPerBusiness, $businessUsage['establishment_count']);
            $maxManagersPerBusiness = max($maxManagersPerBusiness, $businessUsage['manager_count']);
        }
        unset($businessUsage);

        $maxProfessionalsPerEstablishment = 0;
        foreach ($establishmentUsageRows as &$establishmentUsage) {
            $establishmentUsage['professional_count'] = (int)$establishmentUsage['professional_count'];
            $maxProfessionalsPerEstablishment = max($maxProfessionalsPerEstablishment, $establishmentUsage['professional_count']);
        }
        unset($establishmentUsage);

        return [
            'business_count' => $businessCount,
            'max_establishments_per_business_used' => $maxEstablishmentsPerBusiness,
            'max_managers_per_business_used' => $maxManagersPerBusiness,
            'max_professionals_per_establishment_used' => $maxProfessionalsPerEstablishment,
            'businesses' => $businessUsageRows,
            'establishments' => $establishmentUsageRows,
        ];
    }

    public function getUsageViolationsForPlan(int $userId, array $plan): array
    {
        $usage = $this->getUsageSnapshotForUser($userId);

        return $this->getUsageViolationsFromSnapshot($usage, $plan);
    }

    public function getUsageViolationsFromSnapshot(array $usage, array $plan): array
    {
        $violations = [];

        if (!$this->fitsWithinLimit($usage['business_count'] ?? 0, $plan['max_businesses'] ?? null)) {
            $violations[] = [
                'field' => 'max_businesses',
                'message' => 'O plano não suporta a quantidade atual de negócios do gerente.',
                'current_usage' => (int)($usage['business_count'] ?? 0),
                'candidate_limit' => $plan['max_businesses'],
            ];
        }

        if (!$this->fitsWithinLimit($usage['max_establishments_per_business_used'] ?? 0, $plan['max_establishments_per_business'] ?? null)) {
            $violations[] = [
                'field' => 'max_establishments_per_business',
                'message' => 'O plano não suporta a quantidade atual de estabelecimentos por negócio.',
                'current_usage' => (int)($usage['max_establishments_per_business_used'] ?? 0),
                'candidate_limit' => $plan['max_establishments_per_business'],
            ];
        }

        if (!$this->fitsWithinLimit($usage['max_managers_per_business_used'] ?? 0, $plan['max_managers'] ?? null)) {
            $violations[] = [
                'field' => 'max_managers',
                'message' => 'O plano não suporta a quantidade atual de gerentes por negócio.',
                'current_usage' => (int)($usage['max_managers_per_business_used'] ?? 0),
                'candidate_limit' => $plan['max_managers'],
            ];
        }

        if (!$this->fitsWithinLimit($usage['max_professionals_per_establishment_used'] ?? 0, $plan['max_professionals_per_establishment'] ?? null)) {
            $violations[] = [
                'field' => 'max_professionals_per_establishment',
                'message' => 'O plano não suporta a quantidade atual de profissionais por estabelecimento.',
                'current_usage' => (int)($usage['max_professionals_per_establishment_used'] ?? 0),
                'candidate_limit' => $plan['max_professionals_per_establishment'],
            ];
        }

        return $violations;
    }

    public function getDowngradeViolations(array $currentPlan, array $candidatePlan): array
    {
        $violations = [];

        foreach ([
            'max_businesses',
            'max_establishments_per_business',
            'max_managers',
            'max_professionals_per_establishment',
        ] as $field) {
            $currentLimit = $this->normalizeLimit($currentPlan[$field] ?? null);
            $candidateLimit = $this->normalizeLimit($candidatePlan[$field] ?? null);

            if ($currentLimit === null) {
                if ($candidateLimit !== null) {
                    $violations[] = [
                        'field' => $field,
                        'message' => 'Não é permitido mover para um plano menor no campo ' . $field . '.',
                        'current_limit' => null,
                        'candidate_limit' => $candidateLimit,
                    ];
                }
                continue;
            }

            if (($candidateLimit ?? -1) < $currentLimit) {
                $violations[] = [
                    'field' => $field,
                    'message' => 'Não é permitido mover para um plano menor no campo ' . $field . '.',
                    'current_limit' => $currentLimit,
                    'candidate_limit' => $candidateLimit,
                ];
            }
        }

        return $violations;
    }

    public function assignPlanToUser(int $userId, int $planId): array
    {
        $targetUser = User::find($userId);
        if (!$targetUser) {
            throw new \RuntimeException('Usuário não encontrado.');
        }

        if (($targetUser['role'] ?? null) === 'admin') {
            throw new \RuntimeException('Administradores não possuem limites de plano.');
        }

        if (!$this->userCanHoldPlan($userId)) {
            throw new \RuntimeException('Somente gerentes habilitados ou titulares de negócio podem possuir plano.');
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            throw new \RuntimeException('Plano não encontrado.');
        }

        if (!(bool)($plan['is_active'] ?? false)) {
            throw new \RuntimeException('Somente planos ativos podem ser atribuídos.');
        }

        $currentSubscription = $this->getCurrentSubscriptionForUser($userId);
        $currentPlan = $currentSubscription['plan'] ?? null;

        if ($currentPlan !== null) {
            $downgradeViolations = $this->getDowngradeViolations($currentPlan, $plan);
            if (!empty($downgradeViolations)) {
                throw new \RuntimeException('Não é permitido mover um gerente para um plano inferior ao atual.');
            }
        }

        $usageViolations = $this->getUsageViolationsForPlan($userId, $plan);
        if (!empty($usageViolations)) {
            throw new \RuntimeException('O plano selecionado não suporta o uso atual do gerente.');
        }

        $this->db->beginTransaction();

        try {
            if ($currentSubscription !== null) {
                $this->db->execute(
                    "
                    UPDATE user_plan_subscriptions
                    SET status = 'cancelled',
                        ends_at = COALESCE(ends_at, ?)
                    WHERE user_id = ?
                      AND status IN ('active', 'past_due')
                    ",
                    [date('Y-m-d H:i:s'), $userId]
                );
            }

            $subscriptionId = UserPlanSubscription::create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => 'active',
                'starts_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $subscription = UserPlanSubscription::find($subscriptionId);
            $subscription['plan'] = $plan;

            return $subscription;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }

    public function canDeletePlan(int $planId): array
    {
        $activeLinks = (int)($this->db->query(
            "
            SELECT COUNT(*) AS total
            FROM user_plan_subscriptions
            WHERE plan_id = ?
              AND status IN ('active', 'past_due')
            ",
            [$planId]
        )[0]['total'] ?? 0);

        if ($activeLinks > 0) {
            return [
                'allowed' => false,
                'message' => 'Não é possível excluir um plano com gerentes vinculados.',
                'active_links' => $activeLinks,
            ];
        }

        $historicalLinks = (int)($this->db->query(
            "
            SELECT COUNT(*) AS total
            FROM user_plan_subscriptions
            WHERE plan_id = ?
            ",
            [$planId]
        )[0]['total'] ?? 0);

        return [
            'allowed' => $historicalLinks === 0,
            'message' => $historicalLinks === 0
                ? null
                : 'Este plano já foi usado anteriormente. Desative-o em vez de excluir.',
            'active_links' => 0,
            'historical_links' => $historicalLinks,
        ];
    }

    public function getLinkedUsersForPlan(int $planId, bool $activeOnly = false): array
    {
        $sql = "
            SELECT DISTINCT
                u.id,
                u.name,
                u.email,
                ups.status
            FROM user_plan_subscriptions ups
            JOIN users u
              ON u.id = ups.user_id
            WHERE ups.plan_id = ?
        ";

        if ($activeOnly) {
            $sql .= " AND ups.status IN ('active', 'past_due')";
        }

        $sql .= ' ORDER BY u.name ASC';

        return $this->db->query($sql, [$planId]);
    }

    public function getPlanStats(int $planId): array
    {
        $activeUsers = $this->getLinkedUsersForPlan($planId, true);
        return [
            'active_manager_count' => count($activeUsers),
        ];
    }

    public function fitsWithinLimit(int $currentUsage, mixed $limit): bool
    {
        $normalizedLimit = $this->normalizeLimit($limit);
        if ($normalizedLimit === null) {
            return true;
        }

        return $currentUsage <= $normalizedLimit;
    }

    public function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int)$value;
        return $limit > 0 ? $limit : null;
    }
}
