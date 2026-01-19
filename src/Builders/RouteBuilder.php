<?php

namespace QueueMaster\Builders;

use QueueMaster\Core\Router;
use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * RouteBuilder - Dynamic Route Loader
 * 
 * Loads routes from database or falls back to routes/api.php file.
 * Allows routes to be managed dynamically while supporting static fallback.
 * 
 * Priority:
 * 1. Try loading from routes table in database
 * 2. Fall back to routes/api.php if table doesn't exist or is empty
 * 
 * Route table structure (optional):
 * - id: Primary key
 * - method: HTTP method (GET, POST, PUT, DELETE, PATCH)
 * - path: Route path (e.g., /api/v1/users/{id})
 * - controller: Fully qualified controller class name
 * - action: Controller method name
 * - middleware: JSON array of middleware class names
 * - is_active: Boolean flag to enable/disable routes
 */
class RouteBuilder
{
    private Database $db;
    private bool $useDatabase = false;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
            $this->checkRoutesTable();
        } catch (\Exception $e) {
            Logger::warning('RouteBuilder: Database not available, using file-based routes', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if routes table exists and is usable
     * 
     * @return void
     */
    private function checkRoutesTable(): void
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM routes WHERE is_active = 1 LIMIT 1";
            $result = $this->db->query($sql);
            
            if (!empty($result)) {
                $this->useDatabase = true;
                Logger::debug('RouteBuilder: Using database routes table');
            }
        } catch (\Exception $e) {
            $this->useDatabase = false;
            Logger::debug('RouteBuilder: Routes table not available, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Load routes into router
     * 
     * @param Router $router Router instance
     * @return void
     */
    public function loadRoutes(Router $router): void
    {
        if ($this->useDatabase) {
            $this->loadDatabaseRoutes($router);
        } else {
            $this->loadFileRoutes($router);
        }
    }

    /**
     * Load routes from database
     * 
     * @param Router $router Router instance
     * @return void
     */
    private function loadDatabaseRoutes(Router $router): void
    {
        try {
            $sql = "SELECT * FROM routes WHERE is_active = 1 ORDER BY id ASC";
            $routes = $this->db->query($sql);

            foreach ($routes as $route) {
                $middleware = [];
                
                if (!empty($route['middleware'])) {
                    $middleware = json_decode($route['middleware'], true) ?? [];
                }

                $this->registerRoute(
                    $router,
                    $route['method'],
                    $route['path'],
                    $route['controller'],
                    $route['action'],
                    $middleware
                );
            }

            Logger::info('RouteBuilder: Loaded routes from database', [
                'count' => count($routes),
            ]);

        } catch (\Exception $e) {
            Logger::error('RouteBuilder: Failed to load database routes, falling back to file', [
                'error' => $e->getMessage(),
            ]);
            
            $this->loadFileRoutes($router);
        }
    }

    /**
     * Load routes from routes/api.php file
     * 
     * @param Router $router Router instance
     * @return void
     */
    private function loadFileRoutes(Router $router): void
    {
        $routesFile = __DIR__ . '/../../routes/api.php';

        if (!file_exists($routesFile)) {
            Logger::warning('RouteBuilder: Routes file not found', [
                'file' => $routesFile,
            ]);
            return;
        }

        try {
            require $routesFile;
            Logger::info('RouteBuilder: Loaded routes from file', [
                'file' => $routesFile,
            ]);
        } catch (\Exception $e) {
            Logger::error('RouteBuilder: Failed to load routes from file', [
                'file' => $routesFile,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Register a single route
     * 
     * @param Router $router Router instance
     * @param string $method HTTP method
     * @param string $path Route path
     * @param string $controller Fully qualified controller class name
     * @param string $action Controller method name
     * @param array $middleware Array of middleware instances or class names
     * @return void
     */
    public function registerRoute(
        Router $router,
        string $method,
        string $path,
        string $controller,
        string $action,
        array $middleware = []
    ): void {
        // Create handler closure that instantiates controller and calls action
        $handler = function ($request) use ($controller, $action) {
            if (!class_exists($controller)) {
                throw new \RuntimeException("Controller not found: $controller");
            }

            $controllerInstance = new $controller();

            if (!method_exists($controllerInstance, $action)) {
                throw new \RuntimeException("Method not found: $controller::$action");
            }

            $controllerInstance->$action($request);
        };

        // Resolve middleware (convert class names to instances if needed)
        $resolvedMiddleware = [];
        foreach ($middleware as $mw) {
            if (is_string($mw) && class_exists($mw)) {
                $resolvedMiddleware[] = new $mw();
            } elseif (is_callable($mw)) {
                $resolvedMiddleware[] = $mw;
            } else {
                $resolvedMiddleware[] = $mw;
            }
        }

        // Register route with router
        $router->addRoute(
            strtoupper($method),
            $path,
            $handler,
            $resolvedMiddleware
        );
    }
}
