<?php

namespace QueueMaster\Services;

use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Professional;
use QueueMaster\Models\User;

/**
 * QuotaService - SaaS Plan Limit Enforcement
 * 
 * Checks business subscription limits before creating resources.
 * Returns ok or exceeded status with descriptive messages.
 */
class QuotaService
{
    private static function planService(): PlanService
    {
        return new PlanService();
    }

    /**
     * Check if a business can create more establishments
     */
    public static function canCreateEstablishment(int $businessId): array
    {
        $planResult = self::resolvePlanForBusiness($businessId);
        if (($planResult['allowed'] ?? false) === true) {
            return ['allowed' => true];
        }

        if (!isset($planResult['plan'])) {
            return $planResult;
        }

        $plan = $planResult['plan'];
        if ($plan['max_establishments_per_business'] === null) {
            return ['allowed' => true];
        }

        $currentCount = count(Establishment::all(['business_id' => $businessId]));

        if ($currentCount >= $plan['max_establishments_per_business']) {
            return [
                'allowed' => false,
                'error' => 'quota_exceeded',
                'message' => 'Plan limit: max_establishments_reached (' . $plan['max_establishments_per_business'] . ')',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check if a user can create more businesses
     */
    public static function canCreateBusiness(int $ownerUserId): array
    {
        $owner = User::find($ownerUserId);
        if (($owner['role'] ?? null) === 'admin') {
            return ['allowed' => true];
        }

        $plan = self::getApplicablePlanForUser($ownerUserId);
        if (!$plan) {
            return self::planRequiredResponse();
        }

        $ownedCount = count(array_filter(
            BusinessUser::getBusinessesForUser($ownerUserId),
            static fn(array $link): bool => ($link['role'] ?? null) === BusinessUser::ROLE_OWNER
        ));

        if ($plan['max_businesses'] === null) {
            return ['allowed' => true];
        }

        if ($ownedCount >= (int)$plan['max_businesses']) {
            return [
                'allowed' => false,
                'error' => 'quota_exceeded',
                'message' => 'Plan limit: max_businesses_reached (' . $plan['max_businesses'] . ')',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check if a business can add more managers
     */
    public static function canAddManager(int $businessId): array
    {
        $planResult = self::resolvePlanForBusiness($businessId);
        if (($planResult['allowed'] ?? false) === true) {
            return ['allowed' => true];
        }

        if (!isset($planResult['plan'])) {
            return $planResult;
        }

        $plan = $planResult['plan'];
        if ($plan['max_managers'] === null) {
            return ['allowed' => true];
        }

        $currentCount = BusinessUser::countManagers($businessId);

        if ($currentCount >= $plan['max_managers']) {
            return [
                'allowed' => false,
                'error' => 'quota_exceeded',
                'message' => 'Plan limit: max_managers_reached (' . $plan['max_managers'] . ')',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check if an establishment can add more professionals
     */
    public static function canAddProfessional(int $establishmentId): array
    {
        $establishment = Establishment::find($establishmentId);
        if (!$establishment || !$establishment['business_id']) {
            return ['allowed' => true];
        }

        $planResult = self::resolvePlanForBusiness((int)$establishment['business_id']);
        if (($planResult['allowed'] ?? false) === true) {
            return ['allowed' => true];
        }

        if (!isset($planResult['plan'])) {
            return $planResult;
        }

        $plan = $planResult['plan'];
        if ($plan['max_professionals_per_establishment'] === null) {
            return ['allowed' => true];
        }

        $currentCount = count(Professional::getByEstablishment($establishmentId));

        if ($currentCount >= $plan['max_professionals_per_establishment']) {
            return [
                'allowed' => false,
                'error' => 'quota_exceeded',
                'message' => 'Plan limit: max_professionals_reached (' . $plan['max_professionals_per_establishment'] . ')',
            ];
        }

        return ['allowed' => true];
    }

    private static function getApplicablePlanForUser(int $userId): ?array
    {
        return self::planService()->getCurrentPlanForUser($userId)
            ?? self::planService()->getDefaultPlan();
    }

    private static function resolvePlanForBusiness(int $businessId): array
    {
        $holderUserId = self::planService()->getHolderUserIdForBusiness($businessId);
        if ($holderUserId === null) {
            return [
                'allowed' => false,
                'error' => 'plan_holder_missing',
                'message' => 'Não foi possível identificar o titular do plano deste negócio.',
            ];
        }

        $holder = User::find($holderUserId);
        if (($holder['role'] ?? null) === 'admin') {
            return ['allowed' => true];
        }

        $plan = self::getApplicablePlanForUser($holderUserId);
        if (!$plan) {
            return self::planRequiredResponse();
        }

        return [
            'allowed' => false,
            'plan' => $plan,
        ];
    }

    private static function planRequiredResponse(): array
    {
        return [
            'allowed' => false,
            'error' => 'plan_required',
            'message' => 'É necessário ter um plano ativo para continuar.',
        ];
    }
}
