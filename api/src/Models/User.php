<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * User Model - Generated from 'users' table
 * 
 * Represents a user in the system (client, professional, manager, or admin).
 * Handles user authentication and profile management.
 */
class User
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    /**
     * Find record by primary key
     * 
     * @param int $id Primary key value
     * @return array|null Record data or null
     */
    public static function find(int $id): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->first();
    }

    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return array|null Record data or null
     */
    public static function findByEmail(string $email): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('email', '=', strtolower(trim($email)))
            ->first();
    }

    /**
     * Find user by Google ID
     * 
     * @param string $googleId Google OAuth sub (unique ID)
     * @return array|null Record data or null
     */
    public static function findByGoogleId(string $googleId): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('google_id', '=', $googleId)
            ->first();
    }

    /**
     * Create or update user from Google OAuth data
     * 
     * @param array $googleData Data from Google token (sub, email, name, picture, email_verified)
     * @return array User data with '_is_new' flag
     */
    public static function findOrCreateFromGoogle(array $googleData): array
    {
        $googleId = $googleData['sub'];
        $email = strtolower(trim($googleData['email']));
        $name = $googleData['name'] ?? $googleData['email'];
        $avatarUrl = $googleData['picture'] ?? null;
        $emailVerified = $googleData['email_verified'] ?? false;

        // Download Google avatar and convert to base64
        $avatarBase64 = null;
        if ($avatarUrl) {
            $avatarBase64 = self::downloadAvatarAsBase64($avatarUrl);
        }

        // First, try to find by Google ID
        $user = self::findByGoogleId($googleId);

        if ($user) {
            // Update user info from Google (name, avatar might have changed)
            $profileUpdate = [
                'name' => $name,
                'avatar_url' => $avatarUrl,
                'email_verified' => $emailVerified,
            ];
            if ($avatarBase64) {
                $profileUpdate['avatar_base64'] = $avatarBase64;
            }
            self::updateGoogleProfile($user['id'], $profileUpdate);

            $user = self::find($user['id']);
            $safeUser = self::getSafeData($user);
            $safeUser['_is_new'] = false;
            return $safeUser;
        }

        // Check if user exists by email (linking existing account)
        $user = self::findByEmail($email);

        if ($user) {
            // Link Google account to existing user
            $linkUpdate = [
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl,
                'email_verified' => $emailVerified,
            ];
            if ($avatarBase64) {
                $linkUpdate['avatar_base64'] = $avatarBase64;
            }
            self::updateGoogleProfile($user['id'], $linkUpdate);

            $user = self::find($user['id']);
            $safeUser = self::getSafeData($user);
            $safeUser['_is_new'] = false;
            return $safeUser;
        }

        // Create new user
        $createData = [
            'name' => $name,
            'email' => $email,
            'google_id' => $googleId,
            'avatar_url' => $avatarUrl,
            'email_verified' => $emailVerified,
            'role' => self::getDefaultRole($email),
        ];
        if ($avatarBase64) {
            $createData['avatar_base64'] = $avatarBase64;
        }
        $userId = self::createFromGoogle($createData);

        $user = self::find($userId);
        $safeUser = self::getSafeData($user);
        $safeUser['_is_new'] = true;
        return $safeUser;
    }

    /**
     * Create new user from Google OAuth (no password required)
     * 
     * @param array $data User data
     * @return int New user ID
     */
    public static function createFromGoogle(array $data): int
    {
        // Normalize email
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Update user's Google profile data
     * 
     * @param int $id User ID
     * @param array $data Google profile data to update
     * @return int Affected rows
     */
    public static function updateGoogleProfile(int $id, array $data): int
    {
        $allowedFields = ['google_id', 'name', 'avatar_url', 'avatar_base64', 'email_verified'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return 0;
        }

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($updateData);
    }

    /**
     * Get default role for new user
     * Checks SUPER_ADMIN_EMAIL env for first admin setup
     * 
     * @param string $email User email
     * @return string Role
     */
    public static function getDefaultRole(string $email): string
    {
        $superAdminEmail = $_ENV['SUPER_ADMIN_EMAIL'] ?? null;

        if ($superAdminEmail && strtolower(trim($email)) === strtolower(trim($superAdminEmail))) {
            return 'admin';
        }

        return 'client';
    }

    /**
     * Get all records
     * 
     * @param array $conditions Optional WHERE conditions ['column' => 'value']
     * @param string $orderBy Optional ORDER BY column
     * @param string $direction Sort direction (ASC|DESC)
     * @param int|null $limit Optional LIMIT
     * @return array Records array
     */
    public static function all(
        array $conditions = [],
        string $orderBy = '',
        string $direction = 'ASC',
        ?int $limit = null
        ): array
    {
        $qb = new QueryBuilder();
        $qb->select(self::$table);

        foreach ($conditions as $column => $value) {
            $qb->where($column, '=', $value);
        }

        if (!empty($orderBy)) {
            $qb->orderBy($orderBy, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb->get();
    }

    /**
     * Create new record
     * 
     * @param array $data Column => value pairs
     * @return int Inserted record ID
     */
    public static function create(array $data): int
    {
        $errors = self::validate($data);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        // Normalize email
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        $qb = new QueryBuilder();
        $qb->select(self::$table);
        return $qb->insert($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id Primary key value
     * @param array $data Column => value pairs to update
     * @return int Number of affected rows
     */
    public static function update(int $id, array $data): int
    {
        // Normalize email if present
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Don't allow updating password_hash directly (use changePassword method)
        unset($data['password_hash']);

        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }

        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update($data);
    }

    /**
     * Delete record
     * 
     * @param int $id Primary key value
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->delete();
    }

    /**
     * Update user's last login timestamp
     * 
     * @param int $id User ID
     * @return int Affected rows
     */
    public static function updateLastLogin(int $id): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where(self::$primaryKey, '=', $id)
            ->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Get user's queue entries (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of queue entries
     */
    public static function getQueueEntries(int $userId, ?string $status = null): array
    {
        return QueueEntry::getByUser($userId, $status);
    }

    /**
     * Get user's appointments (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @return array Array of appointments
     */
    public static function getAppointments(int $userId, ?string $status = null): array
    {
        return Appointment::getByUser($userId, $status);
    }

    /**
     * Get user's notifications (hasMany relationship)
     * 
     * @param int $userId User ID
     * @param bool $unreadOnly Get only unread notifications
     * @return array Array of notifications
     */
    public static function getNotifications(int $userId, bool $unreadOnly = false): array
    {
        return Notification::getByUser($userId, $unreadOnly);
    }

    /**
     * Get users by role
     * 
     * @param string $role User role (client|professional|manager|admin)
     * @return array Array of users
     */
    public static function getByRole(string $role): array
    {
        return self::all(['role' => $role], 'name', 'ASC');
    }

    /**
     * Validate data before create/update
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Validate name
        if (!$isUpdate && empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        elseif (isset($data['name'])) {
            if (strlen($data['name']) < 2) {
                $errors['name'] = 'Name must be at least 2 characters';
            }
            if (strlen($data['name']) > 150) {
                $errors['name'] = 'Name must not exceed 150 characters';
            }
        }

        // Validate email
        if (!$isUpdate && empty($data['email'])) {
            $errors['email'] = 'Email is required';
        }
        elseif (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            if (strlen($data['email']) > 150) {
                $errors['email'] = 'Email must not exceed 150 characters';
            }
        }

        // Validate google_id format if present (optional for manual creation)
        if (isset($data['google_id']) && strlen($data['google_id']) > 255) {
            $errors['google_id'] = 'Google ID must not exceed 255 characters';
        }

        // Validate role
        if (isset($data['role'])) {
            $validRoles = ['client', 'attendant', 'professional', 'manager', 'admin'];
            if (!in_array($data['role'], $validRoles)) {
                $errors['role'] = 'Invalid role value';
            }
        }

        return $errors;
    }

    /**
     * Get safe user data (excludes sensitive fields)
     * 
     * @param array $user User data
     * @return array Safe user data
     */
    public static function getSafeData(array $user): array
    {
        // Check before unsetting
        $hasAvatar = !empty($user['avatar_base64'] ?? null) || !empty($user['avatar_url'] ?? null);

        unset($user['google_id']); // Don't expose Google ID to frontend
        unset($user['avatar_base64']); // Too large for JSON — use GET /users/{id}/avatar

        $user['has_avatar'] = $hasAvatar;

        return $user;
    }

    /**
     * Get user's avatar as base64 data URI
     * Falls back to avatar_url if base64 not stored yet
     * 
     * @param int $id User ID
     * @return string|null Base64 data URI or external URL
     */
    public static function getAvatarBase64(int $id): ?string
    {
        $db = Database::getInstance();
        $result = $db->query(
            "SELECT avatar_base64, avatar_url FROM " . self::$table . " WHERE id = ? LIMIT 1",
        [$id]
        );

        if (empty($result)) {
            return null;
        }

        // Prefer stored base64
        if (!empty($result[0]['avatar_base64'])) {
            return $result[0]['avatar_base64'];
        }

        return $result[0]['avatar_url'] ?? null;
    }

    /**
     * Download an image URL and convert to base64 data URI
     * Used to cache Google profile pictures locally
     * 
     * @param string $url Image URL
     * @return string|null Base64 data URI or null on failure
     */
    public static function downloadAvatarAsBase64(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'header' => "Accept: image/*\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);

            if ($imageData === false || empty($imageData)) {
                return null;
            }

            // Detect MIME type from response headers or content
            $mime = 'image/jpeg'; // default
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (stripos($header, 'Content-Type:') === 0) {
                        $mime = trim(explode(':', $header, 2)[1]);
                        // Strip charset if present
                        $mime = explode(';', $mime)[0];
                        $mime = trim($mime);
                        break;
                    }
                }
            }

            return 'data:' . $mime . ';base64,' . base64_encode($imageData);
        }
        catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify a user's current password
     * 
     * @param int $id User ID
     * @param string $password Plain text password to verify
     * @return bool True if password matches
     */
    public static function verifyPassword(int $id, string $password): bool
    {
        $db = Database::getInstance();
        $result = $db->query(
            "SELECT password_hash FROM " . self::$table . " WHERE id = ? LIMIT 1",
        [$id]
        );

        if (empty($result) || empty($result[0]['password_hash'])) {
            return false;
        }

        return password_verify($password, $result[0]['password_hash']);
    }

    /**
     * Change user's password
     * 
     * Hashes the new password (Argon2id preferred, Bcrypt fallback)
     * and revokes all refresh tokens for the user.
     * 
     * @param int $id User ID
     * @param string $newPassword New plain text password
     * @return void
     */
    public static function changePassword(int $id, string $newPassword): void
    {
        if (defined('PASSWORD_ARGON2ID')) {
            $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        }
        else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        $db = Database::getInstance();
        $db->execute(
            "UPDATE " . self::$table . " SET password_hash = ? WHERE id = ?",
        [$hash, $id]
        );

        // Revoke all refresh tokens (force re-login on all devices)
        RefreshToken::revokeAllForUser($id);
    }

/**
 * Table columns:
 * - id: bigint NOT NULL [PRI]
 * - name: varchar(150) NOT NULL
 * - email: varchar(150) NOT NULL [UNIQUE]
 * - google_id: varchar(255) NULL [UNIQUE] - Google OAuth sub
 * - avatar_url: varchar(500) NULL - Google profile picture URL
 * - avatar_base64: MEDIUMTEXT NULL - Avatar cached as base64 data URI
 * - email_verified: boolean NOT NULL DEFAULT FALSE
 * - phone: varchar(20) NULL - Contact phone
 * - role: enum('client','attendant','professional','manager','admin') NOT NULL DEFAULT 'client'
 * - is_active: boolean NOT NULL DEFAULT TRUE
 * - last_login_at: timestamp NULL
 * - created_at: timestamp NOT NULL
 * - updated_at: timestamp NULL
 */
}
