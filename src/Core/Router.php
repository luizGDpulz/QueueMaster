<?php

namespace QueueMaster\Core;

/**
 * Router - Simple Router with Middleware Support
 * 
 * Supports:
 * - Method + Path routing with parameters (e.g., /users/{id})
 * - Middleware (global and route-specific)
 * - Route groups
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    /**
     * Add a route
     * 
     * @param string $method HTTP method
     * @param string $path Route path (can include {param})
     * @param callable $handler Route handler
     * @param array $middleware Route-specific middleware
     */
    public function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $path = $this->prefix . $path;
        
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $this->compilePattern($path),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * Add GET route
     */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add POST route
     */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add PUT route
     */
    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add DELETE route
     */
    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add PATCH route
     */
    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add global middleware (runs before all routes)
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Create a route group with prefix and middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        
        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        
        $callback($this);
        
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Compile route pattern to regex
     */
    private function compilePattern(string $path): string
    {
        // Replace {param} with named capture groups FIRST (before escaping)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // Then escape forward slashes for regex
        $pattern = preg_replace('/\//', '\/', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    /**
     * Dispatch request to matching route
     */
    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Find matching route
        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $matchedRoute = $route;
                
                // Extract named parameters
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                
                break;
            }
        }

        if (!$matchedRoute) {
            Response::notFound('Route not found', $request->requestId);
            return;
        }

        // Set route parameters in request
        $request->setParams($params);

        // Execute middleware chain
        try {
            $this->executeMiddleware($request, array_merge($this->middleware, $matchedRoute['middleware']), $matchedRoute['handler']);
        } catch (\Exception $e) {
            // Log error securely
            error_log("Error in route {$method} {$path}: " . $e->getMessage());
            
            // Return generic error (don't expose internals in production)
            if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
                Response::serverError($e->getMessage(), $request->requestId);
            } else {
                Response::serverError('An error occurred', $request->requestId);
            }
        }
    }

    /**
     * Execute middleware chain and handler
     */
    private function executeMiddleware(Request $request, array $middleware, callable $handler): void
    {
        if (empty($middleware)) {
            // No more middleware, execute handler
            $handler($request);
            return;
        }

        // Get next middleware
        $currentMiddleware = array_shift($middleware);

        // Create next function
        $next = function (Request $request) use ($middleware, $handler) {
            $this->executeMiddleware($request, $middleware, $handler);
        };

        // Execute middleware
        $currentMiddleware($request, $next);
    }

    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes(): array
    {
        return array_map(function ($route) {
            return [
                'method' => $route['method'],
                'path' => $route['path'],
            ];
        }, $this->routes);
    }
}
