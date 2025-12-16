<?php
/**
 * Migration Runner Script
 * 
 * Usage:
 *   php api/scripts/migrate.php up     - Run all pending migrations
 *   php api/scripts/migrate.php down   - Rollback the last migration
 * 
 * Environment variables (from .env or environment):
 *   DB_HOST     - Database host (default: localhost)
 *   DB_PORT     - Database port (default: 3306)
 *   DB_USER     - Database username (default: root)
 *   DB_PASS     - Database password (default: empty)
 *   DB_NAME     - Database name (default: queue_system)
 */

// Load environment variables from .env file if it exists
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Only set if not already set (prioritize existing env vars)
            if (!getenv($key) && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Load .env file
$envPath = __DIR__ . '/../../.env';
loadEnv($envPath);

// Get database configuration from environment
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'queue_system';

// Get command
$command = $argv[1] ?? 'help';

// Validate command
if (!in_array($command, ['up', 'down', 'help'])) {
    echo "Error: Invalid command '$command'\n\n";
    $command = 'help';
}

// Show help
if ($command === 'help') {
    echo "Migration Runner\n";
    echo "================\n\n";
    echo "Usage:\n";
    echo "  php api/scripts/migrate.php up     - Run all pending migrations\n";
    echo "  php api/scripts/migrate.php down   - Rollback the last migration\n\n";
    echo "Environment variables (from .env or environment):\n";
    echo "  DB_HOST     - Database host (default: localhost)\n";
    echo "  DB_PORT     - Database port (default: 3306)\n";
    echo "  DB_USER     - Database username (default: root)\n";
    echo "  DB_PASS     - Database password (default: empty)\n";
    echo "  DB_NAME     - Database name (default: queue_system)\n\n";
    exit(0);
}

// Connect to MySQL server (not to a specific database yet)
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to MySQL server at $dbHost:$dbPort\n";
} catch (PDOException $e) {
    echo "✗ Failed to connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// Get migrations directory
$migrationsDir = __DIR__ . '/../migrations';

// Run migrations
if ($command === 'up') {
    echo "\nRunning migrations UP...\n";
    echo "========================\n\n";
    
    // Find all *_up.sql files
    $files = glob($migrationsDir . '/*_up.sql');
    sort($files);
    
    if (empty($files)) {
        echo "No migration files found.\n";
        exit(0);
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Applying: $filename ... ";
        
        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            echo "✓ Done\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n✓ All migrations applied successfully!\n";
    
} else if ($command === 'down') {
    echo "\nRunning migrations DOWN...\n";
    echo "==========================\n\n";
    
    // Find all *_down.sql files
    $files = glob($migrationsDir . '/*_down.sql');
    rsort($files); // Reverse order for rollback
    
    if (empty($files)) {
        echo "No rollback files found.\n";
        exit(0);
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Rolling back: $filename ... ";
        
        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            echo "✓ Done\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n✓ All migrations rolled back successfully!\n";
}

echo "\nDatabase: $dbName\n";
echo "Host: $dbHost:$dbPort\n";
