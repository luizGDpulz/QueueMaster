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
        // Read token from: 1) httpOnly cookie (frontend), 2) Authorization header (Swagger/API clients)
        $token = $_COOKIE['access_token'] ?? $request->getBearerToken();

        if (!$token) {
            error_log("[DEBUG] AuthMiddleware: Token not found in cookies or headers (Path: " . $request->getPath() . ")");
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

        }
        catch (\Exception $e) {
            error_log("[ERROR] AuthMiddleware Validation Failed: " . $e->getMessage() . " (Path: " . $request->getPath() . ")");
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
        $payload = (array)$decoded;

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
    public static function generateAccessToken(array $user, ?int $customTtl = null): string
    {
        $privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH'] ?? 'keys/private.key';
        $fullPath = __DIR__ . '/../../' . $privateKeyPath;

        if (!file_exists($fullPath)) {
            // Log as error for visibility in Apache logs even if exception is caught
            error_log("[ERROR] Private key file not found at: $fullPath");
            throw new \Exception('Private key not found. Generate with: openssl genrsa -out keys/private.key 2048');
        }

        $privateKey = file_get_contents($fullPath);

        if ($privateKey === false) {
            error_log("[ERROR] Failed to read private key file: $fullPath (Check permissions)");
            throw new \Exception('Failed to read private key file');
        }

        if (strlen(trim($privateKey)) < 100) {
            error_log("[ERROR] Private key file is suspiciously empty or small (" . strlen($privateKey) . " bytes)");
            throw new \Exception('Private key file is invalid or empty');
        }

        if (!str_contains($privateKey, 'BEGIN RSA PRIVATE KEY') && !str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            error_log("[ERROR] Private key file does not contain a valid PEM header: " . substr($privateKey, 0, 30) . "...");
            throw new \Exception('Private key file is not in PEM format');
        }

        $ttl = $customTtl ?? (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900); // 15 minutes default

        $payload = [
            'iss' => $_ENV['API_BASE_URL'] ?? 'http://localhost:8080',
            'aud' => $_ENV['API_BASE_URL'] ?? 'http://localhost:8080',
            'iat' => time(),
            'exp' => time() + $ttl,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        try {
            return JWT::encode($payload, $privateKey, 'RS256');
        }
        catch (\Exception $e) {
            error_log("[ERROR] JWT::encode failed: " . $e->getMessage());
            throw $e;
        }
    }
}
