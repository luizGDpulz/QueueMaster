<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessInvitation;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Establishment;
use QueueMaster\Models\Notification;
use QueueMaster\Models\User;
use QueueMaster\Services\AuditService;
use QueueMaster\Services\ContextAccessService;
use QueueMaster\Services\ProfessionalMembershipService;
use QueueMaster\Services\UserRoleService;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

class InvitationsController
{
    private ContextAccessService $accessService;
    private ProfessionalMembershipService $membershipService;
    private UserRoleService $userRoleService;

    public function __construct()
    {
        $this->accessService = new ContextAccessService();
        $this->membershipService = new ProfessionalMembershipService();
        $this->userRoleService = new UserRoleService();
    }

    /**
     * POST /api/v1/businesses/{id}/invitations
     * Manager/admin invites a professional to join the business.
     */
    public function invite(Request $request, int $businessId): void
    {
        $data = $request->all();
        $actorId = (int)$request->user['id'];

        $errors = Validator::make($data, [
            'email' => 'required|email',
            'establishment_id' => 'integer',
            'message' => 'max:2000',
            'role' => 'in:professional,manager',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $businessId,
                'Você não tem permissão para convidar profissionais neste negócio'
            );

            $inviteRole = $data['role'] ?? 'professional';
            $establishment = $this->resolveEstablishmentForInvitation($businessId, $data['establishment_id'] ?? null, $request->requestId);
            if (($establishment !== null) && $inviteRole !== BusinessUser::ROLE_PROFESSIONAL) {
                Response::validationError(['role' => 'Somente convites de profissional podem ser vinculados a um estabelecimento'], $request->requestId);
                return;
            }

            $targetUser = User::findByEmail(trim((string)$data['email']));
            if (!$targetUser) {
                Response::notFound('Target user not found', $request->requestId);
                return;
            }

            if (($targetUser['role'] ?? 'client') === 'admin') {
                Response::error('INVALID_ROLE', 'Administrators cannot be invited through this flow', 422, $request->requestId);
                return;
            }

            $targetUserId = (int)$targetUser['id'];
            $existingRole = BusinessUser::getRole($businessId, $targetUserId);
            if ($inviteRole === BusinessUser::ROLE_MANAGER && in_array($existingRole, [BusinessUser::ROLE_OWNER, BusinessUser::ROLE_MANAGER], true)) {
                Response::error('ALREADY_LINKED', 'User already manages this business', 409, $request->requestId);
                return;
            }

            if ($inviteRole === BusinessUser::ROLE_PROFESSIONAL && $existingRole === BusinessUser::ROLE_PROFESSIONAL && $establishment !== null
                && $this->accessService->userBelongsToEstablishment($targetUserId, (int)$establishment['id'])) {
                Response::error('ALREADY_LINKED', 'User is already linked to this establishment', 409, $request->requestId);
                return;
            }

            $invitationId = BusinessInvitation::create([
                'business_id' => $businessId,
                'establishment_id' => $establishment['id'] ?? null,
                'from_user_id' => $actorId,
                'to_user_id' => $targetUserId,
                'direction' => BusinessInvitation::DIRECTION_BUSINESS_TO_PROFESSIONAL,
                'role' => $inviteRole,
                'status' => 'pending',
                'message' => $data['message'] ?? null,
            ]);

            Notification::create([
                'user_id' => $targetUserId,
                'type' => 'business_invitation',
                'title' => 'Convite profissional',
                'body' => $this->buildInvitationBody($request->user['name'] ?? 'Um gestor', $business['name'] ?? 'o negócio', $establishment['name'] ?? null, true),
                'data' => [
                    'invitation_id' => $invitationId,
                    'business_id' => $businessId,
                    'business_name' => $business['name'] ?? null,
                    'establishment_id' => $establishment['id'] ?? null,
                    'establishment_name' => $establishment['name'] ?? null,
                    'deep_link' => '/app/settings?tab=roles&panel=professional',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'invite_professional', 'business', (string)$businessId, $establishment['id'] ?? null, $businessId, [
                'target_user_id' => $targetUserId,
                'target_email' => $targetUser['email'] ?? null,
                'role' => $inviteRole,
                'establishment_id' => $establishment['id'] ?? null,
                'invitation_id' => $invitationId,
            ]);

            Response::created([
                'invitation_id' => $invitationId,
                'message' => 'Invitation sent successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to send invitation', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to send invitation', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/{id}/invitation-target
     * Preview a target user before sending invitation.
     */
    public function previewTarget(Request $request, int $businessId): void
    {
        $email = trim((string)($request->getQuery()['email'] ?? ''));
        if ($email === '') {
            Response::validationError(['email' => 'Email is required'], $request->requestId);
            return;
        }

        try {
            $this->accessService->requireBusinessManagement(
                $request->user,
                $businessId,
                'Você não tem permissão para pesquisar profissionais neste negócio'
            );

            $user = User::findByEmail($email);
            if (!$user) {
                Response::notFound('Target user not found', $request->requestId);
                return;
            }

            Response::success([
                'user' => User::getSafeData($user),
                'already_linked' => BusinessUser::exists($businessId, (int)$user['id']),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to preview invitation target', [
                'business_id' => $businessId,
                'email' => $email,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to search invitation target', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses/{id}/join-request
     * User requests to join a business/establishment as professional.
     */
    public function joinRequest(Request $request, int $businessId): void
    {
        $data = $request->all();
        $actorId = (int)$request->user['id'];

        $errors = Validator::make($data, [
            'establishment_id' => 'required|integer',
            'specialty' => 'required|max:120',
            'experience_summary' => 'required|max:2000',
            'portfolio_url' => 'max:255',
            'github_url' => 'max:255',
            'linkedin_url' => 'max:255',
            'notes' => 'max:2000',
        ]);

        $portfolioUrl = $this->normalizeOptionalUrl($data['portfolio_url'] ?? null, 'portfolio_url', $errors);
        $githubUrl = $this->normalizeOptionalUrl($data['github_url'] ?? null, 'github_url', $errors);
        $linkedinUrl = $this->normalizeOptionalUrl($data['linkedin_url'] ?? null, 'linkedin_url', $errors);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $establishment = $this->resolveEstablishmentForInvitation($businessId, $data['establishment_id'], $request->requestId);
            if ($establishment === null) {
                Response::validationError(['establishment_id' => 'Establishment is required'], $request->requestId);
                return;
            }

            if (BusinessUser::exists($businessId, $actorId) && $this->accessService->userBelongsToEstablishment($actorId, (int)$establishment['id'])) {
                Response::error('ALREADY_LINKED', 'You are already linked to this establishment', 409, $request->requestId);
                return;
            }

            $managerRecipients = $this->membershipService->getBusinessManagerRecipients($businessId);
            if (empty($managerRecipients)) {
                $managerRecipients = [(int)$business['owner_user_id']];
            }

            $invitationId = BusinessInvitation::create([
                'business_id' => $businessId,
                'establishment_id' => (int)$establishment['id'],
                'from_user_id' => $actorId,
                'to_user_id' => (int)$managerRecipients[0],
                'direction' => BusinessInvitation::DIRECTION_PROFESSIONAL_TO_BUSINESS,
                'status' => 'pending',
                'role' => BusinessUser::ROLE_PROFESSIONAL,
                'message' => $this->buildProfessionalRequestMessage([
                    'specialty' => $data['specialty'] ?? null,
                    'experience_summary' => $data['experience_summary'] ?? null,
                    'portfolio_url' => $portfolioUrl,
                    'github_url' => $githubUrl,
                    'linkedin_url' => $linkedinUrl,
                    'notes' => $data['notes'] ?? null,
                ]),
            ]);

            Notification::create([
                'user_id' => $actorId,
                'type' => 'professional_request_created',
                'title' => 'Solicitação profissional criada',
                'body' => 'Sua solicitação para atuar em ' . ($business['name'] ?? 'o negócio') . ' / ' . ($establishment['name'] ?? 'estabelecimento') . ' foi enviada para análise.',
                'data' => [
                    'invitation_id' => $invitationId,
                    'business_id' => $businessId,
                    'business_name' => $business['name'] ?? null,
                    'establishment_id' => (int)$establishment['id'],
                    'establishment_name' => $establishment['name'] ?? null,
                    'specialty' => trim((string)($data['specialty'] ?? '')),
                    'portfolio_url' => $portfolioUrl,
                    'github_url' => $githubUrl,
                    'linkedin_url' => $linkedinUrl,
                    'deep_link' => '/app/settings?tab=roles',
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::logFromRequest($request, 'request_professional_link', 'business', (string)$businessId, (int)$establishment['id'], $businessId, [
                'requester_user_id' => $actorId,
                'establishment_id' => (int)$establishment['id'],
                'invitation_id' => $invitationId,
                'specialty' => trim((string)($data['specialty'] ?? '')),
                'portfolio_url' => $portfolioUrl,
                'github_url' => $githubUrl,
                'linkedin_url' => $linkedinUrl,
            ]);

            Response::created([
                'invitation_id' => $invitationId,
                'notification_type' => 'professional_request_created',
                'message' => 'Join request sent successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to send join request', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to send join request', $request->requestId);
        }
    }

    public function list(Request $request): void
    {
        $userId = (int)$request->user['id'];

        try {
            $received = BusinessInvitation::getReceivedByUser($userId);
            $sent = BusinessInvitation::getSentByUser($userId);

            Response::success([
                'received' => $received,
                'sent' => $sent,
                'received_count' => count($received),
                'sent_count' => count($sent),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list invitations', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve invitations', $request->requestId);
        }
    }

    public function listForBusiness(Request $request, int $businessId): void
    {
        try {
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $this->accessService->requireBusinessManagement(
                $request->user,
                $businessId,
                'Você não tem permissão para visualizar convites deste negócio'
            );

            $params = $request->getQuery();
            $status = $params['status'] ?? null;
            $invitations = BusinessInvitation::getByBusiness($businessId, $status);

            Response::success([
                'invitations' => $invitations,
                'total' => count($invitations),
            ]);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to list business invitations', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve invitations', $request->requestId);
        }
    }

    public function accept(Request $request, int $id): void
    {
        $actorId = (int)$request->user['id'];

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if (($invitation['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            if (!$this->canRespondToInvitation($request->user, $invitation)) {
                Response::forbidden('You cannot accept this invitation', $request->requestId);
                return;
            }

            $business = Business::find((int)$invitation['business_id']);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            $targetProfessionalId = ($invitation['direction'] === BusinessInvitation::DIRECTION_BUSINESS_TO_PROFESSIONAL)
                ? (int)$invitation['to_user_id']
                : (int)$invitation['from_user_id'];

            $role = $invitation['role'] ?? BusinessUser::ROLE_PROFESSIONAL;
            if ($role === BusinessUser::ROLE_MANAGER) {
                if (!BusinessUser::exists((int)$invitation['business_id'], $targetProfessionalId)) {
                    BusinessUser::addUser((int)$invitation['business_id'], $targetProfessionalId, BusinessUser::ROLE_MANAGER);
                }

                $this->userRoleService->syncUserRole($targetProfessionalId);
            } else {
                $this->membershipService->ensureProfessionalRole($targetProfessionalId);
                $this->membershipService->ensureBusinessProfessional((int)$invitation['business_id'], $targetProfessionalId);

                if (!empty($invitation['establishment_id'])) {
                    $this->membershipService->ensureEstablishmentProfessional(
                        (int)$invitation['business_id'],
                        (int)$invitation['establishment_id'],
                        $targetProfessionalId
                    );
                }

                $this->userRoleService->syncUserRole($targetProfessionalId);
            }

            BusinessInvitation::accept($id);

            $this->notifyInvitationDecision(
                $invitation,
                $actorId,
                true,
                $business['name'] ?? 'o negócio',
                $invitation['establishment_id'] ?? null,
            );

            AuditService::logFromRequest($request, 'accept_invitation', 'business_invitation', (string)$id, $invitation['establishment_id'] ?? null, $invitation['business_id'] ?? null, [
                'target_user_id' => $targetProfessionalId,
                'direction' => $invitation['direction'] ?? null,
                'role' => $role,
            ]);

            Response::success(['message' => 'Invitation accepted']);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to accept invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to accept invitation', $request->requestId);
        }
    }

    public function reject(Request $request, int $id): void
    {
        $actorId = (int)$request->user['id'];
        $data = $request->all();

        $errors = Validator::make($data, [
            'decision_note' => 'max:2000',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if (($invitation['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            if (!$this->canRespondToInvitation($request->user, $invitation)) {
                Response::forbidden('You cannot reject this invitation', $request->requestId);
                return;
            }

            BusinessInvitation::reject($id);
            $decisionNote = $this->resolveDecisionNote($data['decision_note'] ?? null);
            $business = Business::find((int)$invitation['business_id']);
            $this->notifyInvitationDecision(
                $invitation,
                $actorId,
                false,
                $business['name'] ?? 'o negócio',
                $invitation['establishment_id'] ?? null,
                $decisionNote
            );

            AuditService::logFromRequest($request, 'reject_invitation', 'business_invitation', (string)$id, $invitation['establishment_id'] ?? null, $invitation['business_id'] ?? null, [
                'direction' => $invitation['direction'] ?? null,
                'decision_note' => $decisionNote,
            ]);

            Response::success(['message' => 'Invitation rejected']);
        } catch (\RuntimeException $e) {
            Response::forbidden($e->getMessage(), $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to reject invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to reject invitation', $request->requestId);
        }
    }

    public function cancelInvitation(Request $request, int $id): void
    {
        $actorId = (int)$request->user['id'];

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if (($invitation['status'] ?? null) !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            $canCancel = (int)$invitation['from_user_id'] === $actorId;
            if (!$canCancel && ($invitation['direction'] ?? null) === BusinessInvitation::DIRECTION_BUSINESS_TO_PROFESSIONAL) {
                $canCancel = $this->accessService->canManageBusiness($request->user, (int)$invitation['business_id']);
            }

            if (!$canCancel) {
                Response::forbidden('Only the sender can cancel this invitation', $request->requestId);
                return;
            }

            BusinessInvitation::cancel($id);

            AuditService::logFromRequest($request, 'cancel_invitation', 'business_invitation', (string)$id, $invitation['establishment_id'] ?? null, $invitation['business_id'] ?? null, null);

            Response::success(['message' => 'Invitation cancelled']);
        } catch (\Exception $e) {
            Logger::error('Failed to cancel invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to cancel invitation', $request->requestId);
        }
    }

    private function resolveEstablishmentForInvitation(int $businessId, mixed $establishmentId, ?string $requestId): ?array
    {
        if ($establishmentId === null || $establishmentId === '') {
            return null;
        }

        $establishment = Establishment::find((int)$establishmentId);
        if (!$establishment) {
            throw new \RuntimeException('Establishment not found');
        }

        if ((int)($establishment['business_id'] ?? 0) !== $businessId) {
            throw new \RuntimeException('Establishment does not belong to this business');
        }

        return $establishment;
    }

    private function canRespondToInvitation(array $user, array $invitation): bool
    {
        $direction = $invitation['direction'] ?? null;
        if ($direction === BusinessInvitation::DIRECTION_BUSINESS_TO_PROFESSIONAL) {
            return (int)($invitation['to_user_id'] ?? 0) === (int)($user['id'] ?? 0);
        }

        if ($direction === BusinessInvitation::DIRECTION_PROFESSIONAL_TO_BUSINESS) {
            return $this->accessService->canManageBusiness($user, (int)($invitation['business_id'] ?? 0));
        }

        return false;
    }

    private function notifyInvitationDecision(array $invitation, int $actorId, bool $accepted, string $businessName, mixed $establishmentId, ?string $decisionNote = null): void
    {
        $actor = User::find($actorId);
        $actorName = $actor['name'] ?? 'Um usuário';
        $targetUserId = ($invitation['direction'] ?? null) === BusinessInvitation::DIRECTION_BUSINESS_TO_PROFESSIONAL
            ? (int)$invitation['to_user_id']
            : (int)$invitation['from_user_id'];

        $establishmentName = null;
        if (!empty($establishmentId)) {
            $establishment = Establishment::find((int)$establishmentId);
            $establishmentName = $establishment['name'] ?? null;
        }

        $decisionNote = $accepted ? null : $this->resolveDecisionNote($decisionNote);

        Notification::create([
            'user_id' => $targetUserId,
            'type' => $accepted ? 'invitation_accepted' : 'invitation_rejected',
            'title' => $accepted ? 'Solicitação aceita' : 'Solicitação recusada',
            'body' => $accepted
                ? $actorName . ' aprovou o vínculo com ' . $businessName . ($establishmentName ? ' em ' . $establishmentName : '')
                : $actorName . ' recusou o vínculo com ' . $businessName . ($establishmentName ? ' em ' . $establishmentName : ''),
            'data' => [
                'invitation_id' => (int)$invitation['id'],
                'business_id' => (int)$invitation['business_id'],
                'business_name' => $businessName,
                'establishment_id' => $establishmentId ? (int)$establishmentId : null,
                'establishment_name' => $establishmentName,
                'decision_note' => $decisionNote,
                'deep_link' => '/app/businesses/' . (int)$invitation['business_id'] . '?tab=professionals',
            ],
            'channel' => 'in_app',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function buildInvitationBody(string $actorName, string $businessName, ?string $establishmentName, bool $isInvite): string
    {
        if ($isInvite) {
            return $actorName . ' convidou você para atuar em ' . $businessName . ($establishmentName ? ' / ' . $establishmentName : '');
        }

        return $actorName . ' solicitou vínculo profissional em ' . $businessName . ($establishmentName ? ' / ' . $establishmentName : '');
    }

    private function buildProfessionalRequestMessage(array $data): string
    {
        $sections = [];

        $specialty = trim((string)($data['specialty'] ?? ''));
        if ($specialty !== '') {
            $sections[] = 'Área de atuação: ' . $specialty;
        }

        $experienceSummary = trim((string)($data['experience_summary'] ?? ''));
        if ($experienceSummary !== '') {
            $sections[] = 'Resumo profissional: ' . $experienceSummary;
        }

        $portfolioUrl = trim((string)($data['portfolio_url'] ?? ''));
        if ($portfolioUrl !== '') {
            $sections[] = 'Portfólio: ' . $portfolioUrl;
        }

        $githubUrl = trim((string)($data['github_url'] ?? ''));
        if ($githubUrl !== '') {
            $sections[] = 'GitHub: ' . $githubUrl;
        }

        $linkedinUrl = trim((string)($data['linkedin_url'] ?? ''));
        if ($linkedinUrl !== '') {
            $sections[] = 'LinkedIn: ' . $linkedinUrl;
        }

        $notes = trim((string)($data['notes'] ?? ''));
        if ($notes !== '') {
            $sections[] = 'Observações: ' . $notes;
        }

        return implode("\n\n", $sections);
    }

    private function normalizeOptionalUrl(mixed $value, string $field, array &$errors): ?string
    {
        $url = trim((string)($value ?? ''));
        if ($url === '') {
            return null;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[$field] = 'Informe uma URL válida';
            return null;
        }

        return $url;
    }

    private function resolveDecisionNote(mixed $value): string
    {
        $note = trim((string)($value ?? ''));
        if ($note !== '') {
            return $note;
        }

        return 'No momento esta solicitacao nao foi aprovada. Voce pode revisar seus dados e tentar novamente quando quiser.';
    }
}
