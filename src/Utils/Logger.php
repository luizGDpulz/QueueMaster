<?php

namespace QueueMaster\Utils;

/**
 * Logger - Simple Structured Logger
 * 
 * Logs with request_id for tracing and ensures secrets are not logged.
 * In production, integrate with proper logging service (e.g., Monolog, Syslog).
 */
class Logger
{
    private static string $logPath = '';
    private static array $secretPatterns = [
        'password',
        'token',
        'secret',
        'api_key',
        'authorization',
        'jwt',
        'bearer',
    ];

    /**
     * Initialize logger with log path
     */
    public static function init(?string $path = null): void
    {
        if ($path === null) {
            $path = $_ENV['LOG_PATH'] ?? __DIR__ . '/../../logs';
        }
        
        self::$logPath = rtrim($path, '/');
        
        // Create log directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    /**
     * Log info message
     */
    public static function info(string $message, array $context = [], ?string $requestId = null): void
    {
        self::log('INFO', $message, $context, $requestId);
    }

    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = [], ?string $requestId = null): void
    {
        self::log('WARNING', $message, $context, $requestId);
    }

    /**
     * Log error message
     */
    public static function error(string $message, array $context = [], ?string $requestId = null): void
    {
        self::log('ERROR', $message, $context, $requestId);
    }

    /**
     * Log debug message (only in development)
     */
    public static function debug(string $message, array $context = [], ?string $requestId = null): void
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            self::log('DEBUG', $message, $context, $requestId);
        }
    }

    /**
     * Write log entry
     */
    private static function log(string $level, string $message, array $context, ?string $requestId): void
    {
        if (empty(self::$logPath)) {
            self::init();
        }

        // Sanitize context to remove secrets
        $sanitizedContext = self::sanitizeContext($context);

        $logEntry = [
            'timestamp' => date('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'message' => $message,
            'request_id' => $requestId,
            'context' => $sanitizedContext,
        ];

        $jsonLog = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Write to daily log file
        $logFile = self::$logPath . '/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $jsonLog, FILE_APPEND | LOCK_EX);

        // Also write to error_log for errors
        if ($level === 'ERROR') {
            error_log("[$level] $message | Request: $requestId");
        }
    }

    /**
     * Sanitize context to prevent logging secrets
     */
    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            // Check if key contains secret pattern
            $isSecret = false;
            foreach (self::$secretPatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $isSecret = true;
                    break;
                }
            }

            if ($isSecret) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log HTTP request
     */
    public static function logRequest(
        string $method,
        string $path,
        int $statusCode,
        float $duration,
        ?string $requestId = null
    ): void {
        self::info('HTTP Request', [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
        ], $requestId);
    }

    /**
     * Log security event (authentication, authorization, rate limit)
     */
    public static function logSecurity(
        string $event,
        array $details = [],
        ?string $requestId = null
    ): void {
        self::warning("Security: $event", $details, $requestId);
    }
}
