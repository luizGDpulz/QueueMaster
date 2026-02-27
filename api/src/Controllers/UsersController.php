<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Models\User;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Services\AuditService;

/**
 * UsersController - User Management Endpoints (CRUD)
 * 
 * Handles user operations: list, show, create, update, delete
 * Admin only access for most operations
 */
class UsersController
{
    /**
     * GET /api/v1/users
     * 
     * List users with role-based visibility:
     * - Admin: sees all users (managers, professionals, clients)
     * - Manager: sees managers in same business, professionals in their establishments, and clients
     * - Professional: sees clients only
     * Supports filtering by role
     */
    public function list(Request $request): void
    {
        try {
            $params = $request->getQuery();
            $currentUserRole = $request->user['role'] ?? 'client';
            $currentUserId = (int)$request->user['id'];

            // Build conditions
            $conditions = [];
            if (!empty($params['role'])) {
                $validRoles = ['client', 'professional', 'manager', 'admin'];
                if (in_array($params['role'], $validRoles)) {
                    $conditions['role'] = $params['role'];
                }
            }

            $users = [];

            if ($currentUserRole === 'admin') {
                // Admin sees all users
                $users = User::all($conditions, 'created_at', 'DESC');
            }
            elseif ($currentUserRole === 'manager') {
                // Manager sees: other managers in same business, professionals in their establishments, and clients
                $allUsers = User::all($conditions, 'created_at', 'DESC');

                // Get user's businesses
                $businessLinks = \QueueMaster\Models\BusinessUser::getBusinessesForUser($currentUserId);
                $businessIds = array_column($businessLinks, 'business_id');

                // Get all managers in the same businesses
                $businessManagerIds = [];
                foreach ($businessIds as $bId) {
                    $businessUsers = \QueueMaster\Models\BusinessUser::getUsers((int)$bId);
                    foreach ($businessUsers as $bu) {
                        $businessManagerIds[] = (int)$bu['id'];
                    }
                }

                // Get professionals linked to establishments in the manager's businesses
                $professionalUserIds = [];
                foreach ($businessIds as $bId) {
                    $establishments = \QueueMaster\Models\Business::getEstablishments((int)$bId);
                    foreach ($establishments as $est) {
                        $professionals = \QueueMaster\Models\Professional::getByEstablishment((int)$est['id']);
                        foreach ($professionals as $prof) {
                            if (!empty($prof['user_id'])) {
                                $professionalUserIds[] = (int)$prof['user_id'];
                            }
                        }
                    }
                }

                $allowedIds = array_unique(array_merge($businessManagerIds, $professionalUserIds));

                foreach ($allUsers as $u) {
                    $uRole = $u['role'] ?? 'client';
                    if ($uRole === 'client') {
                        $users[] = $u;
                    }
                    elseif (in_array((int)$u['id'], $allowedIds)) {
                        $users[] = $u;
                    }
                }
            }
            elseif ($currentUserRole === 'professional') {
                // Professional sees clients only
                $clientConditions = array_merge($conditions, ['role' => 'client']);
                $users = User::all($clientConditions, 'created_at', 'DESC');
            }
            else {
                // Client: no access to user list
                Response::forbidden('Insufficient permissions', $request->requestId);
                return;
            }

            // Get pagination params
            $page = max(1, (int)($params['page'] ?? 1));
            $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));
            $total = count($users);

            // Paginate
            $offset = ($page - 1) * $perPage;
            $paginatedUsers = array_slice($users, $offset, $perPage);

            // Remove sensitive data
            $paginatedUsers = array_map(function ($user) {
                return User::getSafeData($user);
            }, $paginatedUsers);

            Response::success([
                'users' => array_values($paginatedUsers),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int)ceil($total / $perPage),
                ],
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to list users', [
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve users', $request->requestId);
        }
    }

    /**
     * GET /api/v1/users/{id}
     * 
     * Get single user by ID
     * Users can view their own profile, admins can view any user,
     * managers/professionals can view clients
     */
    public function show(Request $request, int $id): void
    {
        $currentUserId = (int)$request->user['id'];
        $currentUserRole = $request->user['role'];

        // Check permissions: user can view themselves, admin can view anyone
        // managers and professionals can view clients
        if ($currentUserId !== $id && $currentUserRole !== 'admin') {
            if (!in_array($currentUserRole, ['manager', 'professional'])) {
                Response::forbidden('Access denied', $request->requestId);
                return;
            }
            // Manager/professional can only view clients
            $targetUser = User::find($id);
            if (!$targetUser || $targetUser['role'] !== 'client') {
                if ($currentUserRole === 'professional') {
                    Response::forbidden('Access denied', $request->requestId);
                    return;
                }
            // Managers can also view professionals/managers in their business (already verified in list)
            }
        }

        try {
            $user = User::find($id);

            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            // Remove password_hash
            $user = User::getSafeData($user);

            Response::success(['user' => $user]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve user', $request->requestId);
        }
    }

    /**
     * POST /api/v1/users
     * 
     * Create new user (admin only)
     */
    public function create(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|max:100',
            'role' => 'in:client,professional,manager,admin',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        try {
            $name = trim($data['name']);
            $email = strtolower(trim($data['email']));
            $password = $data['password'];
            $role = $data['role'] ?? 'client';

            // Hash password
            if (defined('PASSWORD_ARGON2ID')) {
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            }
            else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            // Create user using Model
            $userId = User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
            ]);

            // Fetch created user
            $user = User::find($userId);
            $user = User::getSafeData($user);

            Logger::info('User created by admin', [
                'user_id' => $userId,
                'email' => $email,
                'role' => $role,
                'created_by' => $request->user['id'],
            ], $request->requestId);

            AuditService::logFromRequest($request, 'create', 'user', (string)$userId, null, null, [
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ]);

            Response::created([
                'user' => $user,
                'message' => 'User created successfully',
            ]);

        }
        catch (\InvalidArgumentException $e) {
            Response::validationError(['general' => $e->getMessage()], $request->requestId);
        }
        catch (\Exception $e) {
            Logger::error('Failed to create user', [
                'data' => $data,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to create user', $request->requestId);
        }
    }

    /**
     * PUT /api/v1/users/{id}
     * 
     * Update user
     * Users can update their own profile (limited fields), admins can update any user
     */
    public function update(Request $request, int $id): void
    {
        $currentUserId = (int)$request->user['id'];
        $currentUserRole = $request->user['role'];

        // Check permissions: user can update themselves, admin can update anyone
        if ($currentUserId !== $id && $currentUserRole !== 'admin') {
            Response::forbidden('Access denied', $request->requestId);
            return;
        }

        try {
            // Check if user exists
            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            $data = $request->all();
            $updateData = [];

            // Name update
            if (isset($data['name']) && !empty(trim($data['name']))) {
                $errors = Validator::make(['name' => $data['name']], [
                    'name' => 'min:2|max:150',
                ]);

                if (!empty($errors)) {
                    Response::validationError($errors, $request->requestId);
                    return;
                }

                $updateData['name'] = trim($data['name']);
            }

            // Email update (with uniqueness check)
            if (isset($data['email'])) {
                $newEmail = strtolower(trim($data['email']));

                if ($newEmail !== $user['email']) {
                    $errors = Validator::make(['email' => $newEmail], [
                        'email' => 'required|email|unique:users,email',
                    ]);

                    if (!empty($errors)) {
                        Response::validationError($errors, $request->requestId);
                        return;
                    }

                    $updateData['email'] = $newEmail;
                }
            }

            // Role update (admin only)
            if (isset($data['role'])) {
                if ($currentUserRole !== 'admin') {
                    Response::forbidden('Only admins can change user roles', $request->requestId);
                    return;
                }

                $errors = Validator::make(['role' => $data['role']], [
                    'role' => 'in:client,professional,manager,admin',
                ]);

                if (!empty($errors)) {
                    Response::validationError($errors, $request->requestId);
                    return;
                }

                $updateData['role'] = $data['role'];
            }

            // Password update (separate method)
            if (isset($data['password'])) {
                Logger::info('Password change attempt', [
                    'user_id' => $id,
                    'by_user' => $currentUserId,
                    'has_current_password' => isset($data['current_password']),
                    'new_password_length' => strlen($data['password']),
                ], $request->requestId);

                $errors = Validator::make(['password' => $data['password']], [
                    'password' => 'min:8|max:100',
                ]);

                if (!empty($errors)) {
                    Response::validationError($errors, $request->requestId);
                    return;
                }

                // For password change, require current password if user is updating themselves
                if ($currentUserId === $id) {
                    // User is changing their OWN password - requires current password
                    if (empty($data['current_password'])) {
                        Logger::warning('Password change rejected: current password missing', [
                            'user_id' => $id,
                            'is_admin' => $currentUserRole === 'admin',
                        ], $request->requestId);

                        Response::error('CURRENT_PASSWORD_REQUIRED', 'Current password is required to change password', 400, $request->requestId);
                        return;
                    }

                    $isValidPassword = User::verifyPassword($id, $data['current_password']);

                    Logger::info('Current password verification', [
                        'user_id' => $id,
                        'is_valid' => $isValidPassword,
                        'current_password_length' => strlen($data['current_password']),
                    ], $request->requestId);

                    if (!$isValidPassword) {
                        Logger::warning('Password change rejected: invalid current password', [
                            'user_id' => $id,
                        ], $request->requestId);

                        Response::error('INVALID_CURRENT_PASSWORD', 'Current password is incorrect', 400, $request->requestId);
                        return;
                    }
                }
                elseif ($currentUserRole === 'admin') {
                    // Admin is changing ANOTHER user's password - no current password required
                    Logger::info('Admin changing another user password', [
                        'target_user_id' => $id,
                        'admin_id' => $currentUserId,
                    ], $request->requestId);
                }

                try {
                    User::changePassword($id, $data['password']);

                    Logger::info('Password changed successfully - tokens revoked', [
                        'user_id' => $id,
                        'changed_by' => $currentUserId,
                    ], $request->requestId);
                }
                catch (\Exception $e) {
                    Logger::error('Failed to change password', [
                        'user_id' => $id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ], $request->requestId);

                    Response::serverError('Failed to change password', $request->requestId);
                    return;
                }

                // If only password was updated, return success immediately
                if (empty($updateData)) {
                    $updatedUser = User::find($id);
                    $updatedUser = User::getSafeData($updatedUser);

                    Logger::info('User password updated', [
                        'user_id' => $id,
                        'updated_by' => $currentUserId,
                    ], $request->requestId);

                    Response::success([
                        'user' => $updatedUser,
                        'message' => 'Password updated successfully',
                    ]);
                    return;
                }
            }

            if (empty($updateData)) {
                Logger::warning('Update attempt with no fields', [
                    'user_id' => $id,
                    'updated_by' => $currentUserId,
                    'received_data' => array_diff_key($data, ['password' => '', 'current_password' => '']),
                ], $request->requestId);

                Response::error(
                    'NO_FIELDS_TO_UPDATE',
                    'No valid fields provided for update. Available fields: name, email, role (admin only), password (requires current_password)',
                    400,
                    $request->requestId
                );
                return;
            }

            // Update user
            User::update($id, $updateData);

            // Fetch updated user
            $updatedUser = User::find($id);
            $updatedUser = User::getSafeData($updatedUser);

            Logger::info('User updated', [
                'user_id' => $id,
                'updated_fields' => array_keys($updateData),
                'updated_by' => $currentUserId,
            ], $request->requestId);

            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $changes[$field] = ['from' => $user[$field] ?? null, 'to' => $newValue];
            }
            AuditService::logFromRequest($request, 'update', 'user', (string)$id, null, null, [
                'entity_name' => $user['name'] ?? null,
                'changes' => $changes,
            ]);

            Response::success([
                'user' => $updatedUser,
                'message' => 'User updated successfully',
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to update user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], $request->requestId);

            Response::serverError('Failed to update user', $request->requestId);
        }
    }

    /**
     * DELETE /api/v1/users/{id}
     * 
     * Delete user (admin only)
     * Users cannot delete themselves
     */
    public function delete(Request $request, int $id): void
    {
        $currentUserId = (int)$request->user['id'];

        // Prevent self-deletion
        if ($currentUserId === $id) {
            Response::error('CANNOT_DELETE_SELF', 'You cannot delete your own account', 400, $request->requestId);
            return;
        }

        try {
            // Check if user exists
            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            // Delete user
            User::delete($id);

            Logger::info('User deleted', [
                'user_id' => $id,
                'deleted_user_email' => $user['email'],
                'deleted_by' => $currentUserId,
            ], $request->requestId);

            AuditService::logFromRequest($request, 'delete', 'user', (string)$id, null, null, [
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? null,
            ]);

            Response::success(['message' => 'User deleted successfully']);

        }
        catch (\Exception $e) {
            Logger::error('Failed to delete user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to delete user', $request->requestId);
        }
    }

    /**
     * GET /api/v1/users/{id}/avatar
     * 
     * Get user's avatar image (served as base64 data URI or redirect to Google URL)
     * Returns the cached base64 image to avoid hitting Google's servers
     */
    public function getAvatar(Request $request, int $id): void
    {
        try {
            $avatar = User::getAvatarBase64($id);

            if (!$avatar) {
                // Return a default avatar (initials-based SVG)
                $user = User::find($id);
                $initials = 'U';
                if ($user) {
                    $parts = explode(' ', $user['name'] ?? 'U');
                    $initials = strtoupper(substr($parts[0], 0, 1));
                    if (count($parts) > 1) {
                        $initials .= strtoupper(substr(end($parts), 0, 1));
                    }
                }

                // Generate SVG avatar
                $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];
                $color = $colors[($id ?? 0) % count($colors)];
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="' . $color . '"/><text x="100" y="100" font-size="80" font-family="Arial,sans-serif" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($initials) . '</text></svg>';

                header('Content-Type: image/svg+xml');
                header('Cache-Control: public, max-age=3600');
                echo $svg;
                exit;
            }

            // If it's a data URI (base64), decode and serve as image
            if (str_starts_with($avatar, 'data:')) {
                // Parse data URI: data:image/jpeg;base64,/9j/4AAQ...
                preg_match('/^data:([^;]+);base64,(.+)$/', $avatar, $matches);
                if ($matches) {
                    $mime = $matches[1];
                    $data = base64_decode($matches[2]);
                    header('Content-Type: ' . $mime);
                    header('Content-Length: ' . strlen($data));
                    header('Cache-Control: public, max-age=86400');
                    echo $data;
                    exit;
                }
            }

            // Fallback: redirect to external URL
            header('Location: ' . $avatar);
            header('Cache-Control: public, max-age=3600');
            exit;

        }
        catch (\Exception $e) {
            Logger::error('Failed to get user avatar', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve avatar', $request->requestId);
        }
    }

    /**
     * GET /api/v1/users/{id}/queue-entries
     * 
     * Get user's queue entries
     */
    public function getQueueEntries(Request $request, int $id): void
    {
        $currentUserId = (int)$request->user['id'];
        $currentUserRole = $request->user['role'];

        // Check permissions
        if ($currentUserId !== $id && $currentUserRole !== 'admin') {
            Response::forbidden('Access denied', $request->requestId);
            return;
        }

        try {
            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            $params = $request->getQuery();
            $status = $params['status'] ?? null;

            $entries = User::getQueueEntries($id, $status);

            Response::success([
                'queue_entries' => $entries,
                'count' => count($entries),
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get user queue entries', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve queue entries', $request->requestId);
        }
    }

    /**
     * GET /api/v1/users/{id}/appointments
     * 
     * Get user's appointments
     */
    public function getAppointments(Request $request, int $id): void
    {
        $currentUserId = (int)$request->user['id'];
        $currentUserRole = $request->user['role'];

        // Check permissions
        if ($currentUserId !== $id && $currentUserRole !== 'admin') {
            Response::forbidden('Access denied', $request->requestId);
            return;
        }

        try {
            $user = User::find($id);
            if (!$user) {
                Response::notFound('User not found', $request->requestId);
                return;
            }

            $params = $request->getQuery();
            $status = $params['status'] ?? null;

            $appointments = User::getAppointments($id, $status);

            Response::success([
                'appointments' => $appointments,
                'count' => count($appointments),
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Failed to get user appointments', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Failed to retrieve appointments', $request->requestId);
        }
    }
}
