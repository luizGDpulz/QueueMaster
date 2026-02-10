<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\BusinessInvitation;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\Business;
use QueueMaster\Models\User;
use QueueMaster\Models\Notification;
use QueueMaster\Models\ProfessionalEstablishment;
use QueueMaster\Utils\Logger;
use QueueMaster\Utils\Validator;

/**
 * InvitationsController - Business Invitation Endpoints
 * 
 * Handles inviting professionals to businesses and vice-versa.
 * Creates notifications on invitation events.
 */
class InvitationsController
{
    /**
     * POST /api/v1/businesses/{id}/invitations
     * Manager invites a professional to join the business
     */
    public function invite(Request $request, int $businessId): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];

        $errors = Validator::make($data, [
            'to_user_id' => 'required|integer',
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

            // Check that current user is owner/manager of the business
            if (!BusinessUser::exists($businessId, $userId) && $request->user['role'] !== 'admin') {
                Response::forbidden('You are not a manager of this business', $request->requestId);
                return;
            }

            $toUserId = (int)$data['to_user_id'];
            $targetUser = User::find($toUserId);
            if (!$targetUser) {
                Response::notFound('Target user not found', $request->requestId);
                return;
            }

            // Target must be a professional
            if (!in_array($targetUser['role'], ['professional', 'attendant'])) {
                Response::error('INVALID_ROLE', 'Can only invite professionals', 400, $request->requestId);
                return;
            }

            $invitationId = BusinessInvitation::create([
                'business_id' => $businessId,
                'from_user_id' => $userId,
                'to_user_id' => $toUserId,
                'direction' => 'business_to_professional',
                'status' => 'pending',
                'message' => $data['message'] ?? null,
            ]);

            // Create notification for the professional
            Notification::create([
                'user_id' => $toUserId,
                'type' => 'business_invitation',
                'title' => 'Convite de negócio',
                'body' => $request->user['name'] . ' convidou você para trabalhar em ' . $business['name'],
                'data' => [
                    'invitation_id' => $invitationId,
                    'business_id' => $businessId,
                    'business_name' => $business['name'],
                    'from_user_name' => $request->user['name'],
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Business invitation sent', [
                'invitation_id' => $invitationId,
                'business_id' => $businessId,
                'from' => $userId,
                'to' => $toUserId,
            ], $request->requestId);

            Response::created([
                'invitation_id' => $invitationId,
                'message' => 'Invitation sent successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to send invitation', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to send invitation', $request->requestId);
        }
    }

    /**
     * POST /api/v1/businesses/{id}/join-request
     * Professional requests to join a business
     */
    public function joinRequest(Request $request, int $businessId): void
    {
        $data = $request->all();
        $userId = (int)$request->user['id'];
        $userRole = $request->user['role'] ?? 'client';

        // Only professionals can request to join
        if (!in_array($userRole, ['professional', 'attendant'])) {
            Response::forbidden('Only professionals can request to join a business', $request->requestId);
            return;
        }

        try {
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            // Get business owner to send notification
            $ownerId = (int)$business['owner_user_id'];

            $invitationId = BusinessInvitation::create([
                'business_id' => $businessId,
                'from_user_id' => $userId,
                'to_user_id' => $ownerId,
                'direction' => 'professional_to_business',
                'status' => 'pending',
                'message' => $data['message'] ?? null,
            ]);

            // Notify business owner
            Notification::create([
                'user_id' => $ownerId,
                'type' => 'join_request',
                'title' => 'Solicitação de vínculo',
                'body' => $request->user['name'] . ' quer se vincular ao negócio ' . $business['name'],
                'data' => [
                    'invitation_id' => $invitationId,
                    'business_id' => $businessId,
                    'from_user_name' => $request->user['name'],
                    'from_user_id' => $userId,
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Join request sent', [
                'invitation_id' => $invitationId,
                'business_id' => $businessId,
                'from' => $userId,
            ], $request->requestId);

            Response::created([
                'invitation_id' => $invitationId,
                'message' => 'Join request sent successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            Response::error('CONFLICT', $e->getMessage(), 409, $request->requestId);
        } catch (\Exception $e) {
            Logger::error('Failed to send join request', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to send join request', $request->requestId);
        }
    }

    /**
     * GET /api/v1/invitations
     * Get invitations for current user (received + sent)
     */
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
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve invitations', $request->requestId);
        }
    }

    /**
     * GET /api/v1/businesses/{id}/invitations
     * Get invitations for a business (managers)
     */
    public function listForBusiness(Request $request, int $businessId): void
    {
        $userId = (int)$request->user['id'];

        try {
            $business = Business::find($businessId);
            if (!$business) {
                Response::notFound('Business not found', $request->requestId);
                return;
            }

            if (!BusinessUser::exists($businessId, $userId) && $request->user['role'] !== 'admin') {
                Response::forbidden('Access denied', $request->requestId);
                return;
            }

            $params = $request->getQuery();
            $status = $params['status'] ?? null;
            $invitations = BusinessInvitation::getByBusiness($businessId, $status);

            Response::success([
                'invitations' => $invitations,
                'total' => count($invitations),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to list business invitations', [
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to retrieve invitations', $request->requestId);
        }
    }

    /**
     * POST /api/v1/invitations/{id}/accept
     * Accept an invitation
     */
    public function accept(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if ($invitation['status'] !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            // Only the recipient can accept
            if ((int)$invitation['to_user_id'] !== $userId) {
                Response::forbidden('Only the recipient can accept this invitation', $request->requestId);
                return;
            }

            BusinessInvitation::accept($id);

            // Get the professional user ID (the one being linked)
            $professionalUserId = $invitation['direction'] === 'business_to_professional'
                ? (int)$invitation['to_user_id']
                : (int)$invitation['from_user_id'];

            $businessId = (int)$invitation['business_id'];

            // Add professional to business_users if not already there
            if (!BusinessUser::exists($businessId, $professionalUserId)) {
                BusinessUser::addUser($businessId, $professionalUserId, 'manager');
            }

            // Notify the sender
            $business = Business::find($businessId);
            Notification::create([
                'user_id' => (int)$invitation['from_user_id'],
                'type' => 'invitation_accepted',
                'title' => 'Convite aceito',
                'body' => $request->user['name'] . ' aceitou o convite para ' . ($business['name'] ?? 'o negócio'),
                'data' => [
                    'invitation_id' => $id,
                    'business_id' => $businessId,
                    'accepted_by' => $userId,
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Invitation accepted', [
                'invitation_id' => $id,
                'accepted_by' => $userId,
            ], $request->requestId);

            Response::success(['message' => 'Invitation accepted']);

        } catch (\Exception $e) {
            Logger::error('Failed to accept invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to accept invitation', $request->requestId);
        }
    }

    /**
     * POST /api/v1/invitations/{id}/reject
     * Reject an invitation
     */
    public function reject(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if ($invitation['status'] !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            if ((int)$invitation['to_user_id'] !== $userId) {
                Response::forbidden('Only the recipient can reject this invitation', $request->requestId);
                return;
            }

            BusinessInvitation::reject($id);

            // Notify the sender
            Notification::create([
                'user_id' => (int)$invitation['from_user_id'],
                'type' => 'invitation_rejected',
                'title' => 'Convite recusado',
                'body' => $request->user['name'] . ' recusou o convite',
                'data' => [
                    'invitation_id' => $id,
                    'rejected_by' => $userId,
                ],
                'channel' => 'in_app',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Invitation rejected', [
                'invitation_id' => $id,
                'rejected_by' => $userId,
            ], $request->requestId);

            Response::success(['message' => 'Invitation rejected']);

        } catch (\Exception $e) {
            Logger::error('Failed to reject invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to reject invitation', $request->requestId);
        }
    }

    /**
     * POST /api/v1/invitations/{id}/cancel
     * Cancel an invitation (by sender)
     */
    public function cancelInvitation(Request $request, int $id): void
    {
        $userId = (int)$request->user['id'];

        try {
            $invitation = BusinessInvitation::find($id);
            if (!$invitation) {
                Response::notFound('Invitation not found', $request->requestId);
                return;
            }

            if ($invitation['status'] !== 'pending') {
                Response::error('INVALID_STATUS', 'Invitation is no longer pending', 400, $request->requestId);
                return;
            }

            if ((int)$invitation['from_user_id'] !== $userId) {
                Response::forbidden('Only the sender can cancel this invitation', $request->requestId);
                return;
            }

            BusinessInvitation::cancel($id);

            Logger::info('Invitation cancelled', [
                'invitation_id' => $id,
                'cancelled_by' => $userId,
            ], $request->requestId);

            Response::success(['message' => 'Invitation cancelled']);

        } catch (\Exception $e) {
            Logger::error('Failed to cancel invitation', [
                'invitation_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);
            Response::serverError('Failed to cancel invitation', $request->requestId);
        }
    }
}
