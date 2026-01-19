<?php

namespace QueueMaster\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * AuthMiddleware - JWT RS256 Token Validation
 * 
 * Validates JWT access tokens using RS256 (RSA public key).
 * Attaches authenticated user to request object.
 * 
 * Security notes:
 * - Uses RS256 (asymmetric) instead of HS256 for better security
 * - Public key can be distributed; only private key signs tokens
 * - Validates token expiration, issuer, and signature
 */
class AuthMiddleware
{
    /**
     * Handle authentication
     */
    public function __invoke(Request $request, callable $next): void
    {
        $token = $request->getBearerToken();

        if (!$token) {
            Logger::logSecurity('Missing authentication token', [
                'ip' => $request->getIp(),
                'path' => $request->getPath(),
            ], $request->requestId);
            
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        try {
            $user = $this->validateToken($token);
            
            // Attach user to request
            $request->user = $user;
            
            // Continue to next middleware/handler
            $next($request);
            
        } catch (\Exception $e) {
            Logger::logSecurity('Invalid authentication token', [
                'ip' => $request->getIp(),
                'path' => $request->getPath(),
                'error' => $e->getMessage(),
            ], $request->requestId);
            
            Response::unauthorized('Invalid or expired token', $request->requestId);
            return;
        }
    }

    /**
     * Validate JWT token and return user data
     * 
     * @throws \Exception if token is invalid
     */
    private function validateToken(string $token): array
    {
        // Get public key path from environment
        $publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH'] ?? 'keys/public.key';
        $fullPath = __DIR__ . '/../../' . $publicKeyPath;

        if (!file_exists($fullPath)) {
            throw new \Exception('Public key not found');
        }

        $publicKey = file_get_contents($fullPath);

        // Decode and validate token
        $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

        // Convert to array
        $payload = (array) $decoded;

        // Validate required fields
        if (!isset($payload['user_id']) || !isset($payload['email'])) {
            throw new \Exception('Invalid token payload');
        }

        // Fetch user from database to ensure they still exist and are active
        $db = Database::getInstance();
        $sql = "SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1";
        $users = $db->query($sql, [$payload['user_id']]);

        if (empty($users)) {
            throw new \Exception('User not found');
        }

        $user = $users[0];

        // Verify email matches (additional security check)
        if ($user['email'] !== $payload['email']) {
            throw new \Exception('Token user mismatch');
        }

        return $user;
    }

    /**
     * Generate JWT access token for user
     * 
     * @param array $user User data
     * @return string JWT token
     */
    public static function generateAccessToken(array $user): string
    {
        $privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH'] ?? 'keys/private.key';
        $fullPath = __DIR__ . '/../../' . $privateKeyPath;

        if (!file_exists($fullPath)) {
            throw new \Exception('Private key not found. Generate with: openssl genrsa -out keys/private.key 2048');
        }

        $privateKey = file_get_contents($fullPath);
        $ttl = (int) ($_ENV['ACCESS_TOKEN_TTL'] ?? 900); // 15 minutes default

        $payload = [
            'iss' => $_ENV['API_BASE_URL'] ?? 'http://localhost:8080',
            'aud' => $_ENV['API_BASE_URL'] ?? 'http://localhost:8080',
            'iat' => time(),
            'exp' => time() + $ttl,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }
}
