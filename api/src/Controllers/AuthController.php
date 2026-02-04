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

            Response::success([
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
                'is_new_user' => $isNewUser,
            ]);

        } catch (\Exception $e) {
            Logger::error('Google authentication failed', [
                'error' => $e->getMessage(),
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
            ]);
            return null;
        }

        // Verify audience (client ID)
        if (($payload['aud'] ?? '') !== $clientId) {
            Logger::logSecurity('Google token audience mismatch', [
                'expected' => $clientId,
                'received' => $payload['aud'] ?? 'none',
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

        // Validate input
        $errors = Validator::make($data, [
            'refresh_token' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $refreshToken = $data['refresh_token'];

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

        Logger::info('Token refreshed', [
            'user_id' => $user['id'],
        ], $request->requestId);

        Response::success([
            'user' => $user,
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
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

        Response::success([
            'user' => $request->user,
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

        Logger::info('User logged out', [
            'user_id' => $userId,
        ], $request->requestId);

        Response::success([
            'message' => 'Logged out successfully',
        ]);
    }
}
