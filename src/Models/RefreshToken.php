<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * RefreshToken Model - Generated from 'refresh_tokens' table
 * 
 * Represents a refresh token for JWT authentication.
 * Manages token lifecycle including expiration and revocation.
 */
class RefreshToken
{
    protected static string $table = 'refresh_tokens';
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
     * Find by token hash
     * 
     * @param string $tokenHash Token hash value
     * @return array|null Record data or null
     */
    public static function findByTokenHash(string $tokenHash): ?array
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('token_hash', '=', $tokenHash)
            ->first();
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
    ): array {
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
     * Revoke token
     * 
     * @param string $tokenHash Token hash value
     * @return int Number of affected rows
     */
    public static function revoke(string $tokenHash): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('token_hash', '=', $tokenHash)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Revoke all tokens for a user
     * 
     * @param int $userId User ID
     * @return int Number of affected rows
     */
    public static function revokeAllForUser(int $userId): int
    {
        $qb = new QueryBuilder();
        return $qb->select(self::$table)
            ->where('user_id', '=', $userId)
            ->where('revoked_at', 'IS', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Check if token is valid (not revoked and not expired)
     * 
     * @param string $tokenHash Token hash value
     * @return bool True if valid
     */
    public static function isValid(string $tokenHash): bool
    {
        $token = self::findByTokenHash($tokenHash);
        
        if (!$token) {
            return false;
        }

        // Check if revoked
        if ($token['revoked_at'] !== null) {
            return false;
        }

        // Check if expired
        $expiresAt = strtotime($token['expires_at']);
        if ($expiresAt < time()) {
            return false;
        }

        return true;
    }

    /**
     * Get user who owns this token (belongsTo relationship)
     * 
     * @param int $tokenId Token ID
     * @return array|null User data or null
     */
    public static function getUser(int $tokenId): ?array
    {
        $token = self::find($tokenId);
        
        if (!$token || !$token['user_id']) {
            return null;
        }

        return User::find($token['user_id']);
    }

    /**
     * Get tokens by user
     * 
     * @param int $userId User ID
     * @param bool $activeOnly Get only active (non-revoked, non-expired) tokens
     * @return array Array of tokens
     */
    public static function getByUser(int $userId, bool $activeOnly = false): array
    {
        $db = Database::getInstance();
        
        if ($activeOnly) {
            $sql = "SELECT * FROM " . self::$table . " 
                    WHERE user_id = ? 
                    AND revoked_at IS NULL 
                    AND expires_at > NOW() 
                    ORDER BY created_at DESC";
            return $db->query($sql, [$userId]);
        }
        
        return self::all(['user_id' => $userId], 'created_at', 'DESC');
    }

    /**
     * Clean up expired tokens
     * 
     * @return int Number of deleted rows
     */
    public static function cleanupExpired(): int
    {
        $db = Database::getInstance();
        $sql = "DELETE FROM " . self::$table . " WHERE expires_at < NOW()";
        return $db->execute($sql);
    }

    /**
     * Validate data before create/update
     * 
     * @param array $data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array $data): array
    {
        $errors = [];
        
        // Validate user_id
        if (empty($data['user_id'])) {
            $errors['user_id'] = 'User ID is required';
        } elseif (!is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            $errors['user_id'] = 'User ID must be a positive integer';
        }

        // Validate token_hash
        if (empty($data['token_hash'])) {
            $errors['token_hash'] = 'Token hash is required';
        } elseif (strlen($data['token_hash']) > 255) {
            $errors['token_hash'] = 'Token hash must not exceed 255 characters';
        }

        // Validate expires_at
        if (empty($data['expires_at'])) {
            $errors['expires_at'] = 'Expiration date is required';
        }

        return $errors;
    }

    /**
     * Table columns:
     * - id: bigint NOT NULL [PRI]
     * - user_id: bigint NOT NULL
     * - token_hash: varchar(255) NOT NULL [UNIQUE]
     * - expires_at: datetime NOT NULL
     * - created_at: timestamp NOT NULL
     * - revoked_at: timestamp NULL
     */
}
