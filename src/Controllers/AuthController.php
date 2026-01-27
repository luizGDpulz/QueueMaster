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
 * Handles user registration, login, token refresh, and profile retrieval.
 * Uses JWT RS256 for access tokens and rotating refresh tokens.
 */
class AuthController
{
    /**
     * POST /api/v1/auth/register
     * 
     * Register a new user account
     */
    public function register(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'name' => 'required|min:2|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|max:100',
            'role' => 'in:client,attendant,admin', // Optional, defaults to client
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $name = trim($data['name']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        $role = $data['role'] ?? 'client';

        // Hash password using Argon2id (or bcrypt fallback)
        if (defined('PASSWORD_ARGON2ID')) {
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        }

        try {
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

            // Generate tokens
            $accessToken = AuthMiddleware::generateAccessToken($user);
            $refreshToken = TokenMiddleware::generateRefreshToken($userId);

            Logger::info('User registered', [
                'user_id' => $userId,
                'email' => $email,
                'role' => $role,
            ], $request->requestId);

            Response::created([
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            ]);

        } catch (\Exception $e) {
            Logger::error('Registration failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Registration failed', $request->requestId);
        }
    }

    /**
     * POST /api/v1/auth/login
     * 
     * Login with email and password
     */
    public function login(Request $request): void
    {
        $data = $request->all();

        // Validate input
        $errors = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            Response::validationError($errors, $request->requestId);
            return;
        }

        $email = strtolower(trim($data['email']));
        $password = $data['password'];

        try {
            // Find user by email using Model
            $user = User::findByEmail($email);

            if (!$user) {
                Logger::logSecurity('Login failed - user not found', [
                    'email' => $email,
                    'ip' => $request->getIp(),
                ], $request->requestId);

                // Generic error to prevent user enumeration
                Response::unauthorized('Invalid credentials', $request->requestId);
                return;
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                Logger::logSecurity('Login failed - invalid password', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $request->getIp(),
                ], $request->requestId);

                Response::unauthorized('Invalid credentials', $request->requestId);
                return;
            }

            // Remove password_hash from response
            $user = User::getSafeData($user);

            // Generate tokens
            $accessToken = AuthMiddleware::generateAccessToken($user);
            $refreshToken = TokenMiddleware::generateRefreshToken((int)$user['id']);

            Logger::info('User logged in', [
                'user_id' => $user['id'],
                'email' => $email,
            ], $request->requestId);

            Response::success([
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            ]);

        } catch (\Exception $e) {
            Logger::error('Login failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ], $request->requestId);

            Response::serverError('Login failed', $request->requestId);
        }
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
