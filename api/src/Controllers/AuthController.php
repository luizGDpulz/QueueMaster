<?php

namespace QueueMaster\Controllers;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Database;
use QueueMaster\Middleware\AuthMiddleware;
use QueueMaster\Middleware\TokenMiddleware;
use QueueMaster\Utils\Validator;
use QueueMaster\Utils\Logger;
use QueueMaster\Models\User;
use QueueMaster\Models\RefreshToken;
use QueueMaster\Services\AuditService;

/**
 * AuthController - Authentication Endpoints
 * 
 * Handles Google OAuth authentication, token refresh, and profile retrieval.
 * Uses JWT RS256 for access tokens and rotating refresh tokens.
 * 
 * Authentication Flow:
 * 1. Frontend gets Google ID token via Google Identity Services
 * 2. Frontend sends ID token to POST /auth/google
 * 3. Backend validates token with Google, creates/finds user
 * 4. Backend returns JWT access token + refresh token
 */
class AuthController
{
    /**
     * POST /api/v1/auth/google
     * 
     * Authenticate with Google OAuth
     * Creates new user if doesn't exist, or logs in existing user
     */
    public function google(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'id_token' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $idToken = $data['id_token'];

        try {
            // Validate Google ID token
            $googleUser = $this->verifyGoogleToken($idToken);

            if (!$googleUser) {
                Logger::logSecurity('Invalid Google token', [
                    'ip' => $request->getIp(),
                ], $request->requestId);

                Response::unauthorized('Invalid Google token', $request->requestId);
                return;
            }

            // Check if email is verified
            if (!($googleUser['email_verified'] ?? false)) {
                Logger::logSecurity('Google email not verified', [
                    'email' => $googleUser['email'] ?? 'unknown',
                    'ip' => $request->getIp(),
                ], $request->requestId);

                Response::forbidden('Email not verified by Google', $request->requestId);
                return;
            }

            // Find or create user
            $user = User::findOrCreateFromGoogle($googleUser);
            $isNewUser = isset($user['_is_new']) && $user['_is_new'];
            unset($user['_is_new']);

            // Update last login timestamp
            User::updateLastLogin((int)$user['id']);

            // Generate tokens
            $accessToken = AuthMiddleware::generateAccessToken($user);
            $refreshToken = TokenMiddleware::generateRefreshToken((int)$user['id']);

            Logger::info('User authenticated via Google', [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'is_new_user' => $isNewUser,
            ], $request->requestId);

            // Set tokens as httpOnly cookies
            self::setAccessTokenCookie($accessToken);
            self::setRefreshTokenCookie($refreshToken);

            AuditService::logFromRequest($request, 'login', 'user', (string)$user['id'], null, null, [
                'is_new_user' => $isNewUser,
                'email' => $user['email'] ?? null,
            ]);

            Response::success([
                'user' => $user,
                'is_new_user' => $isNewUser,
            ]);

        }
        catch (\Exception $e) {
            Logger::error('Google authentication failed: ' . $e->getMessage(), [
                'ip' => $request->getIp(),
            ], $request->requestId);

            Response::serverError('Authentication failed', $request->requestId);
        }
    }

    /**
     * Verify Google ID token
     * 
     * Uses Google's tokeninfo endpoint for validation
     * In production, consider using Google's PHP client library
     * 
     * @param string $idToken Google ID token
     * @return array|null User data or null if invalid
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? null;

        if (!$clientId) {
            Logger::error('GOOGLE_CLIENT_ID not configured');
            return null;
        }

        // Validate token with Google
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            Logger::warning('Failed to connect to Google tokeninfo endpoint');
            return null;
        }

        $payload = json_decode($response, true);

        if (!$payload || isset($payload['error'])) {
            Logger::warning('Invalid Google token response', [
                'error' => $payload['error'] ?? 'Unknown error',
                'description' => $payload['error_description'] ?? 'No description',
                'response_snippet' => substr($response, 0, 100),
            ]);
            return null;
        }

        // Verify audience (client ID)
        if (($payload['aud'] ?? '') !== $clientId) {
            Logger::logSecurity('Google token audience mismatch', [
                'expected' => $clientId,
                'received' => $payload['aud'] ?? 'none',
                'azp' => $payload['azp'] ?? 'none',
            ]);
            return null;
        }

        // Verify token is not expired
        $exp = (int)($payload['exp'] ?? 0);
        if ($exp < time()) {
            Logger::warning('Google token expired');
            return null;
        }

        // Return normalized user data
        return [
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'email_verified' => ($payload['email_verified'] ?? 'false') === 'true',
            'name' => $payload['name'] ?? $payload['email'],
            'picture' => $payload['picture'] ?? null,
        ];
    }

    /**
     * POST /api/v1/auth/refresh
     * 
     * Refresh access token using refresh token
     * Implements token rotation for security
     */
    public function refresh(Request $request): void
    {
        $data = $request->all();

        // Read refresh token from httpOnly cookie first, fallback to body
        $refreshToken = $_COOKIE['refresh_token'] ?? ($data['refresh_token'] ?? null);

        if (!$refreshToken) {
            Response::validationError(['refresh_token' => ['Refresh token is required']], $request->requestId);
            return;
        }

        // Validate and rotate refresh token
        $user = TokenMiddleware::validateAndRotateRefreshToken($refreshToken);

        if (!$user) {
            Logger::logSecurity('Invalid refresh token', [
                'ip' => $request->getIp(),
            ], $request->requestId);

            Response::unauthorized('Invalid or expired refresh token', $request->requestId);
            return;
        }

        // Generate new tokens
        $newAccessToken = AuthMiddleware::generateAccessToken($user);
        $newRefreshToken = TokenMiddleware::generateRefreshToken((int)$user['id']);

        // Set new tokens as httpOnly cookies
        self::setAccessTokenCookie($newAccessToken);
        self::setRefreshTokenCookie($newRefreshToken);

        Logger::info('Token refreshed', [
            'user_id' => $user['id'],
        ], $request->requestId);

        Response::success([
            'user' => $user,
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * 
     * Get authenticated user profile
     * Requires authentication
     */
    public function me(Request $request): void
    {
        // User is already attached by AuthMiddleware
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        // Fetch full user record from DB (middleware only carries basic fields)
        $fullUser = User::find((int)$request->user['id']);

        if (!$fullUser) {
            Response::notFound('User not found', $request->requestId);
            return;
        }

        Response::success([
            'user' => User::getSafeData($fullUser),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * 
     * Logout and revoke all refresh tokens for user
     * Requires authentication
     */
    public function logout(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        $userId = (int)$request->user['id'];

        // Revoke all refresh tokens
        TokenMiddleware::revokeAllUserTokens($userId);

        // Clear httpOnly cookies
        self::clearAccessTokenCookie();
        self::clearRefreshTokenCookie();

        Logger::info('User logged out', [
            'user_id' => $userId,
        ], $request->requestId);

        AuditService::logFromRequest($request, 'logout', 'user', (string)$userId, null, null, null);

        Response::success([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * GET /api/v1/auth/dev-token
     * 
     * Generate a fresh access token for Swagger/dev use (admin only).
     * This is the ONLY endpoint that exposes a token in the response body.
     * Requires an explicit, conscious action by an authenticated admin.
     */
    public function devToken(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Authentication required', $request->requestId);
            return;
        }

        if ($request->user['role'] !== 'admin') {
            Response::forbidden('Admin access required', $request->requestId);
            return;
        }

        // Generate a short-lived token for Swagger use (5 minutes)
        $token = AuthMiddleware::generateAccessToken($request->user, 300);

        Logger::info('Dev token generated for Swagger', [
            'user_id' => $request->user['id'],
            'ip' => $request->getIp(),
        ], $request->requestId);

        Response::success([
            'token' => $token,
            'expires_in' => 300,
            'warning' => 'This token is for Swagger/development use only. Do not share.',
        ]);
    }

    // =========================================================================
    // Cookie Helpers
    // =========================================================================

    /**
     * Detect if the current connection is HTTPS.
     * Works correctly behind a reverse proxy (Nginx Proxy Manager).
     */
    private static function isSecureConnection(): bool
    {
        // 1. PHP detected HTTPS natively
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        // 2. Reverse proxy forwarded the original scheme (Apache sets this via X-Forwarded-Proto)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        // 3. Fallback: treat as secure in production unless explicitly set to development
        return ($_ENV['APP_ENV'] ?? 'production') !== 'development';
    }

    /**
     * Set access token as httpOnly cookie
     *
     * Security:
     * - httpOnly: JS cannot access (XSS-proof)
     * - Secure: only HTTPS (detected from reverse proxy headers)
     * - SameSite=Lax: CSRF protection
     */
    private static function setAccessTokenCookie(string $token): void
    {
        $ttl = (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900);

        setcookie('access_token', $token, [
            'expires' => time() + $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => self::isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Clear access token cookie
     */
    private static function clearAccessTokenCookie(): void
    {
        setcookie('access_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => self::isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Set refresh token as httpOnly cookie
     */
    private static function setRefreshTokenCookie(string $token): void
    {
        $ttl = (int)($_ENV['REFRESH_TOKEN_TTL'] ?? 2592000);

        setcookie('refresh_token', $token, [
            'expires' => time() + $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => self::isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Clear refresh token cookie
     */
    private static function clearRefreshTokenCookie(): void
    {
        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => self::isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
