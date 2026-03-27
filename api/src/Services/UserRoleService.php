<?php

namespace QueueMaster\Services;

use QueueMaster\Core\Database;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\User;
use QueueMaster\Models\UserRoleAccess;
use QueueMaster\Models\UserPlanSubscription;

class UserRoleService
{
    public function syncUserRole(int $userId): ?string
    {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        if (($user['role'] ?? null) === 'admin') {
            return 'admin';
        }

        $effectiveRole = $this->resolveEffectiveRole($userId);
        if (($user['role'] ?? null) !== $effectiveRole) {
            User::update($userId, ['role' => $effectiveRole]);
        }

        return $effectiveRole;
    }

    public function resolveEffectiveRole(int $userId): string
    {
        $user = User::find($userId);
        if (!$user) {
            return 'client';
        }

        if (($user['role'] ?? null) === 'admin') {
            return 'admin';
        }

        if ((bool)($user['manager_access_granted'] ?? false)) {
            return 'manager';
        }

        $metrics = UserRoleAccess::getMetrics($userId);
        if (!empty($metrics['has_management_signals'])) {
            return 'manager';
        }

        return !empty($metrics['has_professional_signals']) ? 'professional' : 'client';
    }

    public function getRoleSummary(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return [
                'effective_role' => 'client',
                'has_professional_access' => false,
                'has_management_access' => false,
                'has_contextual_management_access' => false,
                'has_manager_access_grant' => false,
                'can_manage_own_businesses' => false,
                'has_active_plan' => false,
                'owns_business' => false,
                'business_count' => 0,
                'owned_establishment_count' => 0,
                'professional_link_count' => 0,
                'manager_link_count' => 0,
                'can_revert_to_client' => false,
                'revert_blockers' => [],
                'active_roles' => [],
            ];
        }

        if (($user['role'] ?? null) === 'admin') {
            return [
                'effective_role' => 'admin',
                'has_professional_access' => false,
                'has_management_access' => true,
                'has_contextual_management_access' => true,
                'has_manager_access_grant' => true,
                'can_manage_own_businesses' => true,
                'has_active_plan' => false,
                'owns_business' => true,
                'business_count' => 0,
                'owned_establishment_count' => 0,
                'professional_link_count' => 0,
                'manager_link_count' => 0,
                'can_revert_to_client' => false,
                'revert_blockers' => ['Administrador nao pode voltar para cliente por esse fluxo.'],
                'active_roles' => ['admin'],
            ];
        }

        $metrics = UserRoleAccess::getMetrics($userId);
        $businessCount = (int)($metrics['business_count'] ?? 0);
        $ownedEstablishmentCount = (int)($metrics['owned_establishment_count'] ?? 0);
        $managerLinkCount = (int)($metrics['manager_link_count'] ?? 0);
        $professionalLinkCount = (int)($metrics['professional_link_count'] ?? 0);

        $hasManagerAccessGrant = (bool)($user['manager_access_granted'] ?? false);
        $hasContextualManagementAccess = !empty($metrics['has_management_signals']);
        $hasManagementAccess = $hasManagerAccessGrant || $hasContextualManagementAccess;
        $canManageOwnBusinesses = $hasManagerAccessGrant || $businessCount > 0;
        $hasProfessionalAccess = !empty($metrics['has_professional_signals']);
        $hasActivePlan = $this->hasActivePlanSubscription($userId);

        $revertBlockers = [];
        if ($hasActivePlan) {
            $revertBlockers[] = 'Cancele o plano ativo antes de voltar para cliente.';
        }
        if ($businessCount > 0) {
            $revertBlockers[] = 'Remova ou transfira seus negocios antes de voltar para cliente.';
        }
        if ($ownedEstablishmentCount > 0) {
            $revertBlockers[] = 'Remova ou transfira seus estabelecimentos antes de voltar para cliente.';
        }
        if ($managerLinkCount > 0) {
            $revertBlockers[] = 'Saia dos vinculos de gestao antes de voltar para cliente.';
        }
        if ($professionalLinkCount > 0) {
            $revertBlockers[] = 'Remova seus vinculos profissionais antes de voltar para cliente.';
        }

        $activeRoles = [];
        if ($hasManagementAccess) {
            $activeRoles[] = 'manager';
        }
        if ($hasProfessionalAccess) {
            $activeRoles[] = 'professional';
        }
        if (empty($activeRoles)) {
            $activeRoles[] = 'client';
        }

        return [
            'effective_role' => $this->resolveEffectiveRole($userId),
            'has_professional_access' => $hasProfessionalAccess,
            'has_management_access' => $hasManagementAccess,
            'has_contextual_management_access' => $hasContextualManagementAccess,
            'has_manager_access_grant' => $hasManagerAccessGrant,
            'can_manage_own_businesses' => $canManageOwnBusinesses,
            'has_active_plan' => $hasActivePlan,
            'owns_business' => $businessCount > 0,
            'business_count' => $businessCount,
            'owned_establishment_count' => $ownedEstablishmentCount,
            'professional_link_count' => $professionalLinkCount,
            'manager_link_count' => $managerLinkCount,
            'can_revert_to_client' => empty($revertBlockers) && ($hasManagementAccess || $hasProfessionalAccess || ($user['role'] ?? 'client') !== 'client'),
            'revert_blockers' => $revertBlockers,
            'active_roles' => $activeRoles,
        ];
    }

    public function userOwnsBusiness(int $userId): bool
    {
        return UserRoleAccess::countOwnedBusinesses($userId) > 0;
    }

    public function userHasOwnershipSignals(int $userId): bool
    {
        return UserRoleAccess::hasOwnershipSignals($userId);
    }

    public function getProfessionalBusinessIdsForPromotion(int $userId): array
    {
        return UserRoleAccess::getProfessionalBusinessIds($userId);
    }

    public function isAlreadyManagerInBusiness(int $userId, int $businessId): bool
    {
        return UserRoleAccess::isManagerInBusiness($userId, $businessId);
    }

    public function applyContextualRoleTransition(int $userId, string $newRole): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            if ($newRole === 'manager') {
                UserRoleAccess::updateContextualRole($userId, BusinessUser::ROLE_PROFESSIONAL, BusinessUser::ROLE_MANAGER);
            } else {
                UserRoleAccess::updateContextualRole($userId, BusinessUser::ROLE_MANAGER, BusinessUser::ROLE_PROFESSIONAL);
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            throw $exception;
        }
    }

    public function hasActivePlanSubscription(int $userId): bool
    {
        foreach (UserPlanSubscription::all(['user_id' => $userId], 'id', 'DESC') as $subscription) {
            if (in_array($subscription['status'] ?? null, ['active', 'past_due'], true)) {
                return true;
            }
        }

        return false;
    }
}
