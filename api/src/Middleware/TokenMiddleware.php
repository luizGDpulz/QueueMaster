<?php

namespace QueueMaster\Middleware;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * TokenMiddleware - Refresh Token Management
 * 
 * Handles refresh token rotation for enhanced security.
 * Refresh tokens are stored hashed in database and rotated on each use.
 */
class TokenMiddleware
{
    /**
     * Generate secure refresh token
     */
    public static function generateRefreshToken(int $userId): string
    {
        $db = Database::getInstance();
        $ttl = (int) ($_ENV['REFRESH_TOKEN_TTL'] ?? 2592000); // 30 days default

        // Generate cryptographically secure random token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        // Store hashed token in database
        $sql = "INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)";
        $db->execute($sql, [$userId, $tokenHash, $expiresAt]);

        return $token;
    }

    /**
     * Validate and rotate refresh token
     * 
     * @return array|null User data if valid, null otherwise
     */
    public static function validateAndRotateRefreshToken(string $token): ?array
    {
        $db = Database::getInstance();
        $tokenHash = hash('sha256', $token);

        try {
            $db->beginTransaction();

            // Find refresh token with user data
            $sql = "
                SELECT rt.id as token_id, rt.user_id, rt.expires_at, rt.revoked_at,
                       u.id, u.name, u.email, u.role, u.created_at
                FROM refresh_tokens rt
                JOIN users u ON u.id = rt.user_id
                WHERE rt.token_hash = ?
                LIMIT 1
            ";
            $results = $db->query($sql, [$tokenHash]);

            if (empty($results)) {
                $db->rollback();
                return null;
            }

            $result = $results[0];

            // Check if token is revoked
            if ($result['revoked_at'] !== null) {
                $db->rollback();
                Logger::logSecurity('Attempted use of revoked refresh token', [
                    'user_id' => $result['user_id'],
                ]);
                return null;
            }

            // Check if token is expired
            if (strtotime($result['expires_at']) < time()) {
                $db->rollback();
                return null;
            }

            // Revoke old token (rotation)
            $revokeSql = "UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = ?";
            $db->execute($revokeSql, [$result['token_id']]);

            $db->commit();

            // Return user data (without token_id)
            return [
                'id' => $result['id'],
                'name' => $result['name'],
                'email' => $result['email'],
                'role' => $result['role'],
                'created_at' => $result['created_at'],
            ];

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            Logger::error('Refresh token validation failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Revoke all refresh tokens for a user (logout from all devices)
     */
    public static function revokeAllUserTokens(int $userId): void
    {
        $db = Database::getInstance();
        $sql = "UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL";
        $db->execute($sql, [$userId]);
    }

    /**
     * Cleanup expired refresh tokens (should be run periodically)
     */
    public static function cleanupExpiredTokens(): int
    {
        $db = Database::getInstance();
        $sql = "DELETE FROM refresh_tokens WHERE expires_at < NOW()";
        return $db->execute($sql);
    }
}
