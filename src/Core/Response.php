<?php

namespace QueueMaster\Core;

/**
 * Response - JSON Response Helper
 * 
 * Provides consistent JSON envelope for all API responses:
 * Success: { success: true, data: {...}, meta: {...} }
 * Error: { success: false, error: { code: "...", message: "...", request_id: "..." } }
 */
class Response
{
    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param array $meta Optional metadata (pagination, etc.)
     * @param int $statusCode HTTP status code
     */
    public static function success(mixed $data = null, array $meta = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'data' => $data,
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send error response
     * 
     * @param string $code Machine-readable error code
     * @param string $message Human-readable error message
     * @param int $statusCode HTTP status code
     * @param string|null $requestId Request ID for logging/tracing
     * @param array $details Additional error details
     */
    public static function error(
        string $code,
        string $message,
        int $statusCode = 400,
        ?string $requestId = null,
        array $details = []
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        
        if ($requestId) {
            $error['request_id'] = $requestId;
        }
        
        if (!empty($details)) {
            $error['details'] = $details;
        }
        
        $response = [
            'success' => false,
            'error' => $error,
        ];
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send validation error response
     */
    public static function validationError(array $errors, ?string $requestId = null): void
    {
        self::error(
            'VALIDATION_ERROR',
            'The request data is invalid',
            422,
            $requestId,
            ['validation_errors' => $errors]
        );
    }

    /**
     * Send unauthorized error
     */
    public static function unauthorized(string $message = 'Unauthorized', ?string $requestId = null): void
    {
        self::error('UNAUTHORIZED', $message, 401, $requestId);
    }

    /**
     * Send forbidden error
     */
    public static function forbidden(string $message = 'Forbidden', ?string $requestId = null): void
    {
        self::error('FORBIDDEN', $message, 403, $requestId);
    }

    /**
     * Send not found error
     */
    public static function notFound(string $message = 'Resource not found', ?string $requestId = null): void
    {
        self::error('NOT_FOUND', $message, 404, $requestId);
    }

    /**
     * Send method not allowed error
     */
    public static function methodNotAllowed(string $message = 'Method not allowed', ?string $requestId = null): void
    {
        self::error('METHOD_NOT_ALLOWED', $message, 405, $requestId);
    }

    /**
     * Send rate limit exceeded error
     */
    public static function rateLimitExceeded(?string $requestId = null, ?int $retryAfter = null): void
    {
        if ($retryAfter) {
            header("Retry-After: $retryAfter");
        }
        self::error('RATE_LIMIT_EXCEEDED', 'Too many requests', 429, $requestId);
    }

    /**
     * Send internal server error
     */
    public static function serverError(
        string $message = 'Internal server error',
        ?string $requestId = null
    ): void {
        self::error('INTERNAL_ERROR', $message, 500, $requestId);
    }

    /**
     * Send conflict error (e.g., duplicate resource)
     */
    public static function conflict(string $message = 'Resource conflict', ?string $requestId = null): void
    {
        self::error('CONFLICT', $message, 409, $requestId);
    }

    /**
     * Send created response (201)
     */
    public static function created(mixed $data = null, array $meta = []): void
    {
        self::success($data, $meta, 201);
    }

    /**
     * Send no content response (204)
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Set custom header
     */
    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    /**
     * Set multiple headers
     */
    public static function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            self::setHeader($name, $value);
        }
    }

    /**
     * Set CORS headers
     * 
     * @param array|string $allowedOrigins Allowed origins (array or '*')
     * @param array $allowedMethods Allowed HTTP methods
     * @param array $allowedHeaders Allowed headers
     */
    public static function setCorsHeaders(
        array|string $allowedOrigins = '*',
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'Idempotency-Key']
    ): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (is_array($allowedOrigins)) {
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
            }
        } else {
            header("Access-Control-Allow-Origin: $allowedOrigins");
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }

    /**
     * Handle OPTIONS preflight request
     */
    public static function handlePreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
