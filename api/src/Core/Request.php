<?php

namespace QueueMaster\Core;

/**
 * Request - Simple HTTP Request Helper
 * 
 * Provides easy access to request data (body, query params, headers, method)
 */
class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private mixed $body;
    private array $params = []; // Route parameters (e.g., {id})

    // Attached data from middleware (e.g., authenticated user)
    public ?array $user = null;
    public ?string $requestId = null;
    public ?string $businessUserRole = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $this->parsePath();
        $this->query = $_GET;
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
        $this->requestId = $this->generateRequestId();
    }

    /**
     * Parse request path from REQUEST_URI
     */
    private function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?: '/';
    }

    /**
     * Parse request headers
     */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerKey = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerKey)] = $value;
            }
        }

        // Also include CONTENT_TYPE and CONTENT_LENGTH
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Parse request body (JSON or form data)
     */
    private function parseBody(): mixed
    {
        $contentType = $this->getHeader('content-type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return $decoded ?? [];
        }

        // For POST form data
        if ($this->method === 'POST') {
            return $_POST;
        }

        return [];
    }

    /**
     * Generate unique request ID for logging/tracing
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Get request method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get query parameters
     */
    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Get request header
     */
    public function getHeader(string $key): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get request body
     */
    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        if (is_array($this->body)) {
            return $this->body[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Get all body data as array
     */
    public function all(): array
    {
        return is_array($this->body) ? $this->body : [];
    }

    /**
     * Get route parameter
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set route parameters (called by Router)
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get Authorization bearer token
     */
    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('authorization');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get idempotency key for request deduplication
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->getHeader('idempotency-key');
    }

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept') ?? '';
        return str_contains($accept, 'application/json');
    }

    /**
     * Get client IP address
     * 
     * Only trusts proxy headers (X-Forwarded-For, X-Real-IP) when the request
     * comes from a configured trusted proxy. Set TRUSTED_PROXIES env as a
     * comma-separated list of proxy IPs (e.g. "10.0.0.1,10.0.0.2").
     */
    public function getIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust proxy headers if REMOTE_ADDR is a known proxy
        $trustedProxies = array_filter(
            array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? ''))
        );

        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            // Trust X-Forwarded-For first, then X-Real-IP
            $proxyHeaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];

            foreach ($proxyHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    // X-Forwarded-For is comma-separated; take the leftmost (client) IP
                    if (str_contains($ip, ',')) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    // Validate IP format
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }
}
