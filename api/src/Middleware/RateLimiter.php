<?php

namespace QueueMaster\Middleware;

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Utils\Logger;

/**
 * RateLimiter - Request Rate Limiting Middleware
 * 
 * Implements token bucket algorithm for rate limiting.
 * Uses Redis when available, falls back to in-memory for single-server setups.
 * 
 * Production note: For multi-server deployments, Redis is required.
 * Memory fallback is NOT shared across processes/servers.
 */
class RateLimiter
{
    private static ?object $redis = null;
    private static array $memoryStore = [];
    private static bool $redisAvailable = false;

    private int $maxRequests;
    private int $windowSeconds;

    /**
     * @param int $maxRequests Maximum requests allowed in window
     * @param int $windowSeconds Time window in seconds
     */
    public function __construct(?int $maxRequests = null, ?int $windowSeconds = null)
    {
        $this->maxRequests = $maxRequests ?? (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 60);
        $this->windowSeconds = $windowSeconds ?? (int)($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 60);

        // Initialize Redis connection once
        if (self::$redis === null) {
            $this->initializeRedis();
        }
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis(): void
    {
        $redisEnabled = filter_var($_ENV['REDIS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$redisEnabled) {
            return;
        }

        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'database' => (int)($_ENV['REDIS_DB'] ?? 0),
            ]);

            // Test connection
            $redis->ping();
            
            self::$redis = $redis;
            self::$redisAvailable = true;
            
            Logger::debug('Redis rate limiter initialized');
        } catch (\Exception $e) {
            Logger::warning('Redis unavailable, using memory fallback for rate limiting', [
                'error' => $e->getMessage(),
            ]);
            self::$redisAvailable = false;
        }
    }

    /**
     * Handle rate limiting
     */
    public function __invoke(Request $request, callable $next): void
    {
        $enabled = filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            $next($request);
            return;
        }

        // Generate rate limit key based on IP and optional user ID
        $identifier = $request->getIp();
        if ($request->user) {
            $identifier .= ':user:' . $request->user['id'];
        }

        $key = 'ratelimit:' . hash('sha256', $identifier);

        // Check rate limit
        $result = $this->checkLimit($key);

        // Set rate limit headers
        Response::setHeaders([
            'X-RateLimit-Limit' => (string)$this->maxRequests,
            'X-RateLimit-Remaining' => (string)max(0, $result['remaining']),
            'X-RateLimit-Reset' => (string)$result['reset'],
        ]);

        if (!$result['allowed']) {
            Logger::logSecurity('Rate limit exceeded', [
                'ip' => $request->getIp(),
                'user_id' => $request->user['id'] ?? null,
                'path' => $request->getPath(),
            ], $request->requestId);

            $retryAfter = $result['reset'] - time();
            Response::rateLimitExceeded($request->requestId, $retryAfter);
            return;
        }

        // Continue to next middleware/handler
        $next($request);
    }

    /**
     * Check rate limit using Redis or memory
     * 
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => timestamp]
     */
    private function checkLimit(string $key): array
    {
        if (self::$redisAvailable) {
            return $this->checkLimitRedis($key);
        } else {
            return $this->checkLimitMemory($key);
        }
    }

    /**
     * Check rate limit using Redis (production)
     */
    private function checkLimitRedis(string $key): array
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Remove old entries outside window
        self::$redis->zremrangebyscore($key, 0, $windowStart);

        // Count requests in current window
        $count = self::$redis->zcard($key);

        $allowed = $count < $this->maxRequests;
        
        if ($allowed) {
            // Add current request with score = timestamp
            self::$redis->zadd($key, $now, uniqid('', true));
            // Set expiry on key
            self::$redis->expire($key, $this->windowSeconds);
        }

        // Calculate reset time
        $oldestEntry = self::$redis->zrange($key, 0, 0, 'WITHSCORES');
        $reset = $now + $this->windowSeconds;
        if (!empty($oldestEntry)) {
            $oldestTime = (int)array_values($oldestEntry)[0];
            $reset = $oldestTime + $this->windowSeconds;
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $this->maxRequests - $count - ($allowed ? 1 : 0)),
            'reset' => $reset,
        ];
    }

    /**
     * Check rate limit using memory (dev/single-server fallback)
     * 
     * WARNING: Not shared across processes. For production, use Redis.
     */
    private function checkLimitMemory(string $key): array
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Initialize if not exists
        if (!isset(self::$memoryStore[$key])) {
            self::$memoryStore[$key] = [];
        }

        // Remove old entries
        self::$memoryStore[$key] = array_filter(
            self::$memoryStore[$key],
            fn($timestamp) => $timestamp > $windowStart
        );

        $count = count(self::$memoryStore[$key]);
        $allowed = $count < $this->maxRequests;

        if ($allowed) {
            self::$memoryStore[$key][] = $now;
        }

        // Calculate reset time
        $reset = $now + $this->windowSeconds;
        if (!empty(self::$memoryStore[$key])) {
            $oldestTime = min(self::$memoryStore[$key]);
            $reset = $oldestTime + $this->windowSeconds;
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $this->maxRequests - $count - ($allowed ? 1 : 0)),
            'reset' => $reset,
        ];
    }

    /**
     * Static factory for convenience
     */
    public static function create(?int $maxRequests = null, ?int $windowSeconds = null): self
    {
        return new self($maxRequests, $windowSeconds);
    }
}
