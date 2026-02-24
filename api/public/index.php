<?php
/**
 * QueueMaster API - Front Controller
 * 
 * This is the entry point for all API requests.
 * Routes are defined and dispatched from here.
 * 
 * Document Root: public/
 * 
 * Apache: Use .htaccess for URL rewriting
 * Dev Server: php -S 127.0.0.1:8080 -t public
 */

// Error reporting is set below based on APP_ENV

// Start output buffering to prevent premature output
ob_start();

// Set error reporting based on environment
$env = $_ENV['APP_ENV'] ?? 'production';
if ($env === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

use QueueMaster\Core\Request;
use QueueMaster\Core\Response;
use QueueMaster\Core\Router;
use QueueMaster\Utils\Logger;

// Load environment variables (from .env file if present, Docker provides them via compose)
$envDir = __DIR__ . '/..';
if (file_exists($envDir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envDir);
    $dotenv->load();
}

// Set timezone (default: America/Sao_Paulo for GMT-3)
$timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo';
date_default_timezone_set($timezone);

// Initialize logger
Logger::init();

// Track request start time
$requestStartTime = microtime(true);

// Create request object
$request = new Request();

// Check if this is a Swagger route (don't apply strict CSP)
$isSwaggerRoute = str_starts_with($request->getPath(), '/swagger');

// Set CORS headers
$allowedOrigins = $_ENV['CORS_ORIGINS'] ?? '*';
if ($allowedOrigins !== '*') {
    $allowedOrigins = explode(',', $allowedOrigins);
}
Response::setCorsHeaders($allowedOrigins, applyStrictCsp: !$isSwaggerRoute);

// Handle OPTIONS preflight
Response::handlePreflight();

// Create router
$router = new Router();

// Load routes
require __DIR__ . '/../routes/api.php';

// Dispatch request
try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    // Log error
    Logger::error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ], $request->requestId);
    
    // Return error response
    if ($env === 'development') {
        Response::serverError($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), $request->requestId);
    } else {
        Response::serverError('An unexpected error occurred', $request->requestId);
    }
}

// Log request
$duration = microtime(true) - $requestStartTime;
Logger::logRequest(
    $request->getMethod(),
    $request->getPath(),
    http_response_code(),
    $duration,
    $request->requestId
);

// Flush output buffer
ob_end_flush();