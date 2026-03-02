<?php

namespace QueueMaster\Services;

use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\BusinessSubscription;
use QueueMaster\Models\Plan;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Professional;
use QueueMaster\Utils\Logger;

/**
 * QuotaService - SaaS Plan Limit Enforcement
 * 
 * Checks business subscription limits before creating resources.
 * Returns ok or exceeded status with descriptive messages.
 */
class QuotaService
{
    /**
     * Check if a business can create more establishments
     */
    public static function canCreateEstablishment(int $businessId): array
    {

        $plan = self::getActivePlan($businessId);
        if (!$plan) {
            return ['allowed' => true]; // No plan = no limits
        }

        if ($plan['max_establishments_per_business'] === null) {
            return ['allowed' => true]; // Unlimited
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

        // Count existing businesses owned by user
        $links = BusinessUser::getBusinessesForUser($ownerUserId);
        $ownedCount = 0;
        foreach ($links as $link) {
            if ($link['role'] === 'owner') {
                $ownedCount++;
            }
        }

        // Check the most permissive plan among owned businesses
        $maxAllowed = 1; // Default free tier
        foreach ($links as $link) {
            if ($link['role'] !== 'owner')
                continue;
            $plan = self::getActivePlan($link['business_id']);
            if ($plan && $plan['max_businesses'] !== null) {
                $maxAllowed = max($maxAllowed, $plan['max_businesses']);
            }
            elseif ($plan && $plan['max_businesses'] === null) {
                return ['allowed' => true]; // Unlimited plan
            }
        }

        // First business is always allowed
        if ($ownedCount === 0) {
            return ['allowed' => true];
        }

        if ($ownedCount >= $maxAllowed) {
            return [
                'allowed' => false,
                'error' => 'quota_exceeded',
                'message' => 'Plan limit: max_businesses_reached (' . $maxAllowed . ')',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check if a business can add more managers
     */
    public static function canAddManager(int $businessId): array
    {

        $plan = self::getActivePlan($businessId);
        if (!$plan) {
            return ['allowed' => true];
        }

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
            return ['allowed' => true]; // No business linked = no plan limits
        }

        $plan = self::getActivePlan($establishment['business_id']);
        if (!$plan) {
            return ['allowed' => true];
        }

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

    /**
     * Get the active plan for a business
     */
    private static function getActivePlan(int $businessId): ?array
    {
        $subscription = BusinessSubscription::getActiveForBusiness($businessId);
        if (!$subscription) {
            return null;
        }

        return Plan::find($subscription['plan_id']);
    }
}
