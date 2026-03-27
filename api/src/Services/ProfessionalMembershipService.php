<?php

namespace QueueMaster\Services;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;
use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\EstablishmentUser;
use QueueMaster\Models\Professional;
use QueueMaster\Models\ProfessionalEstablishment;
use QueueMaster\Models\User;

class ProfessionalMembershipService
{
    private UserRoleService $userRoleService;

    public function __construct()
    {
        $this->userRoleService = new UserRoleService();
    }

    public function ensureProfessionalRole(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $currentRole = $user['role'] ?? 'client';
        if (in_array($currentRole, ['professional', 'manager', 'admin'], true)) {
            return;
        }

        $this->userRoleService->syncUserRole($userId);
    }

    public function ensureBusinessProfessional(int $businessId, int $userId): void
    {
        $business = Business::find($businessId);
        if (!$business) {
            throw new \RuntimeException('Business not found');
        }

        if (!BusinessUser::exists($businessId, $userId)) {
            BusinessUser::addUser($businessId, $userId, BusinessUser::ROLE_PROFESSIONAL);
            $this->userRoleService->syncUserRole($userId);
            return;
        }

        $currentRole = BusinessUser::getRole($businessId, $userId);
        if ($currentRole === null) {
            throw new \RuntimeException('Unable to resolve business role');
        }
    }

    public function ensureEstablishmentProfessional(int $businessId, int $establishmentId, int $userId): void
    {
        $establishment = Establishment::find($establishmentId);
        if (!$establishment) {
            throw new \RuntimeException('Establishment not found');
        }

        if ((int)($establishment['business_id'] ?? 0) !== $businessId) {
            throw new \RuntimeException('Establishment does not belong to this business');
        }

        $this->ensureBusinessProfessional($businessId, $userId);

        if (!EstablishmentUser::exists($establishmentId, $userId)) {
            EstablishmentUser::addStaff($establishmentId, $userId, EstablishmentUser::ROLE_PROFESSIONAL);
        } elseif (EstablishmentUser::getRole($establishmentId, $userId) === null) {
            throw new \RuntimeException('Unable to resolve establishment role');
        }

        if (!ProfessionalEstablishment::exists($userId, $establishmentId)) {
            ProfessionalEstablishment::link($userId, $establishmentId);
        } else {
            ProfessionalEstablishment::setActive($userId, $establishmentId, true);
        }

        $this->userRoleService->syncUserRole($userId);

        $professionalRecord = $this->findProfessionalRecord($establishmentId, $userId);
        if (!$professionalRecord) {
            $quotaCheck = QuotaService::canAddProfessional($establishmentId);
            if (!($quotaCheck['allowed'] ?? false)) {
                throw new \RuntimeException($quotaCheck['message'] ?? 'Professional quota exceeded');
            }

            $user = User::find($userId);
            if (!$user) {
                throw new \RuntimeException('User not found');
            }

            Professional::create([
                'establishment_id' => $establishmentId,
                'user_id' => $userId,
                'name' => $user['name'] ?? $user['email'],
                'email' => $user['email'] ?? null,
                'phone' => $user['phone'] ?? null,
                'avatar_url' => $user['avatar_url'] ?? null,
                'specialty' => null,
                'is_active' => true,
            ]);
            return;
        }

        $updateData = ['is_active' => true];
        if (empty($professionalRecord['name']) || strpos((string)$professionalRecord['name'], '#') !== false) {
            $user = User::find($userId);
            $updateData['name'] = $user['name'] ?? $professionalRecord['name'];
        }
        Professional::update((int)$professionalRecord['id'], $updateData);
    }

    public function getBusinessManagerRecipients(int $businessId): array
    {
        $qb = new QueryBuilder();
        $rows = $qb->select('business_users bu', [
            'bu.user_id',
            'bu.role',
        ])
            ->where('bu.business_id', '=', $businessId)
            ->whereIn('bu.role', [BusinessUser::ROLE_OWNER, BusinessUser::ROLE_MANAGER])
            ->get();

        return array_map(
            static fn(array $row): int => (int)$row['user_id'],
            $rows
        );
    }

    public function getQueueCandidateProfessionals(array $queue): array
    {
        $queueId = (int)($queue['id'] ?? 0);
        $businessId = (int)($queue['business_id'] ?? 0);
        $establishmentId = (int)($queue['establishment_id'] ?? 0);

        if ($queueId <= 0 || $businessId <= 0 || $establishmentId <= 0) {
            return [];
        }

        $assignedUserIds = array_map(
            static fn(array $item): int => (int)$item['user_id'],
            (new QueryBuilder())
                ->select('queue_professionals qp', ['qp.user_id'])
                ->where('qp.queue_id', '=', $queueId)
                ->get()
        );

        $db = Database::getInstance();
        $candidates = [];

        $establishmentCandidates = $db->query(
            "
            SELECT DISTINCT
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                u.avatar_url AS user_avatar,
                u.role AS user_role,
                bu.role AS business_role,
                eu.role AS establishment_role,
                CASE
                    WHEN eu.id IS NOT NULL OR pe.id IS NOT NULL OR p.id IS NOT NULL THEN 1
                    ELSE 0
                END AS is_establishment_linked
            FROM users u
            LEFT JOIN business_users bu
              ON bu.user_id = u.id
             AND bu.business_id = ?
            LEFT JOIN establishment_users eu
              ON eu.user_id = u.id
             AND eu.establishment_id = ?
            LEFT JOIN professional_establishments pe
              ON pe.user_id = u.id
             AND pe.establishment_id = ?
             AND pe.is_active = 1
            LEFT JOIN professionals p
              ON p.user_id = u.id
             AND p.establishment_id = ?
             AND p.is_active = 1
            WHERE u.is_active = 1
              AND (
                    eu.id IS NOT NULL
                 OR pe.id IS NOT NULL
                 OR p.id IS NOT NULL
              )
            ",
            [$businessId, $establishmentId, $establishmentId, $establishmentId]
        );

        $candidates = array_merge($candidates, $establishmentCandidates);

        if ($businessId > 0) {
            $businessCandidates = $db->query(
                "
                SELECT DISTINCT
                    u.id AS user_id,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.avatar_url AS user_avatar,
                    u.role AS user_role,
                    bu.role AS business_role,
                    NULL AS establishment_role,
                    0 AS is_establishment_linked
                FROM users u
                JOIN business_users bu
                  ON bu.user_id = u.id
                WHERE u.is_active = 1
                  AND bu.business_id = ?
                  AND bu.role = ?
                ",
                [$businessId, BusinessUser::ROLE_PROFESSIONAL]
            );

            $candidates = array_merge($candidates, $businessCandidates);
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            $userId = (int)$candidate['user_id'];
            if ($userId <= 0 || in_array($userId, $assignedUserIds, true)) {
                continue;
            }

            $establishmentRoleRow = $candidate['establishment_role'] ?? null;
            $isLinkedToEstablishment = (bool)($candidate['is_establishment_linked'] ?? false);
            $isBusinessLinked = $businessId > 0 && !empty($candidate['business_role']);
            $professionalRecord = $this->findProfessionalRecord($establishmentId, $userId);

            if ($professionalRecord) {
                $isLinkedToEstablishment = true;
                if ($establishmentRoleRow === null) {
                    $establishmentRoleRow = EstablishmentUser::ROLE_PROFESSIONAL;
                }
            }

            $normalized[$userId] = [
                'id' => $userId,
                'user_id' => $userId,
                'user_name' => $candidate['user_name'] ?? ('Usuário #' . $userId),
                'user_email' => $candidate['user_email'] ?? null,
                'user_avatar' => $candidate['user_avatar'] ?? null,
                'user_role' => $candidate['user_role'] ?? null,
                'business_role' => $candidate['business_role'] ?? BusinessUser::ROLE_PROFESSIONAL,
                'establishment_role' => $establishmentRoleRow,
                'is_business_linked' => $isBusinessLinked,
                'is_establishment_linked' => $isLinkedToEstablishment,
                'will_link_to_business' => $businessId > 0 && !$isBusinessLinked,
                'will_link_to_establishment' => !$isLinkedToEstablishment,
                'professional_record_id' => $professionalRecord['id'] ?? null,
            ];
        }

        uasort($normalized, static function (array $a, array $b): int {
            $gapA = ((int)!empty($a['will_link_to_business'])) + ((int)!empty($a['will_link_to_establishment']));
            $gapB = ((int)!empty($b['will_link_to_business'])) + ((int)!empty($b['will_link_to_establishment']));

            if ($gapA !== $gapB) {
                return $gapA <=> $gapB;
            }

            return strcasecmp((string)$a['user_name'], (string)$b['user_name']);
        });

        return array_values($normalized);
    }

    private function findProfessionalRecord(int $establishmentId, int $userId): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select('professionals')
            ->where('establishment_id', '=', $establishmentId)
            ->where('user_id', '=', $userId)
            ->first();
    }
}
