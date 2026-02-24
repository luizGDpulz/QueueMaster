<?php
/**
 * QueueMaster - Main Entry Point
 * 
 * This is the unified entry point for both:
 * - API requests (/api/*) → Routed to PHP backend
 * - Web app requests (/*) → Served from web/dist (future)
 * 
 * Document Root: public/
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// ============================================================================
// API Routes (/api/*, /health, /docs, /swagger/*)
// ============================================================================

// Serve static files from api/public/ for swagger and docs (dev only)
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

if (!$isProduction && (str_starts_with($path, '/swagger/') || str_starts_with($path, '/docs/'))) {
    $staticFile = __DIR__ . '/../api/public' . $path;
    if (file_exists($staticFile) && is_file($staticFile)) {
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'yaml' => 'text/yaml',
            'yml' => 'text/yaml',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
        ];
        $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($staticFile);
        exit;
    }
}

if (
str_starts_with($path, '/api/') ||
str_starts_with($path, '/health') ||
(!$isProduction && (str_starts_with($path, '/docs') || str_starts_with($path, '/swagger')))
) {
    // Forward to API entry point
    require __DIR__ . '/../api/public/index.php';
    exit;
}

// ============================================================================
// Web App Routes (Future - Quasar SPA)
// ============================================================================

// Check if web app is built (dist folder exists)
$webDistPath = __DIR__ . '/../web/dist/spa';
$webIndexPath = $webDistPath . '/index.html';

if (is_dir($webDistPath) && file_exists($webIndexPath)) {
    // Try to serve static file from web/dist
    $staticFile = $webDistPath . $path;

    if (file_exists($staticFile) && is_file($staticFile)) {
        // Serve static asset (js, css, images, etc.)
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];
        $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($staticFile);
        exit;
    }

    // SPA fallback - serve index.html for all other routes
    // This allows Vue Router to handle /home, /users, etc.
    header('Content-Type: text/html');
    readfile($webIndexPath);
    exit;
}

// ============================================================================
// Web App Not Built Yet - Show Welcome Page
// ============================================================================

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QueueMaster</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #00d4ff, #7b2cbf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .subtitle {
            font-size: 1.2rem;
            color: #a0a0a0;
            margin-bottom: 2rem;
        }
        .links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .btn-primary {
            background: linear-gradient(90deg, #00d4ff, #0099cc);
            color: #fff;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .status {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(0,212,255,0.1);
            border-radius: 8px;
            border: 1px solid rgba(0,212,255,0.2);
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #00ff88;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        code {
            background: rgba(255,255,255,0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎫 QueueMaster</h1>
        <p class="subtitle">Sistema Híbrido de Gerenciamento de Filas e Agendamentos</p>
        
        <div class="links">
            <a href="/api/v1/status" class="btn-primary">📡 API Status</a>
            <a href="/swagger/" class="btn-secondary">📖 API Docs</a>
            <a href="/docs" class="btn-secondary">📚 Documentation</a>
        </div>
        
        <div class="status">
            <span class="status-dot"></span>
            <strong>API Online</strong> - Web App em desenvolvimento
            <br><br>
            <small>Para iniciar o web app: <code>cd web && npm install && quasar dev</code></small>
        </div>
    </div>
</body>
</html>
