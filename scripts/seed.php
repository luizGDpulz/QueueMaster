<?php
/**
 * Seed Runner Script
 * 
 * Usage:
 *   php scripts/seed.php sample    - Run sample/test seed data (seed_sample_data.sql)
 *   php scripts/seed.php up        - Run all production seed files
 *   php scripts/seed.php down      - Clean/rollback all seed data
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
$envPath = __DIR__ . '/../.env';
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
if (!in_array($command, ['up', 'down', 'sample', 'help'])) {
    echo "Error: Invalid command '$command'\n\n";
    $command = 'help';
}

// Show help
if ($command === 'help') {
    echo "Seed Runner\n";
    echo "===========\n\n";
    echo "Usage:\n";
    echo "  php scripts/seed.php sample    - Run sample/test seed data (seed_sample_data.sql)\n";
    echo "  php scripts/seed.php up        - Run all production seed files (*_seed.sql)\n";
    echo "  php scripts/seed.php down      - Clean/rollback all seed data (*_seed_down.sql)\n\n";
    echo "Environment variables (from .env or environment):\n";
    echo "  DB_HOST     - Database host (default: localhost)\n";
    echo "  DB_PORT     - Database port (default: 3306)\n";
    echo "  DB_USER     - Database username (default: root)\n";
    echo "  DB_PASS     - Database password (default: empty)\n";
    echo "  DB_NAME     - Database name (default: queue_system)\n\n";
    echo "File naming conventions:\n";
    echo "  Production seeds: seeds/NNNN_description_seed.sql (e.g., 0001_initial_data_seed.sql)\n";
    echo "  Seed cleanups:    seeds/NNNN_description_seed_down.sql\n";
    echo "  Sample data:      seeds/seed_sample_data.sql\n\n";
    exit(0);
}

// Connect to MySQL server with database
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to database '$dbName' at $dbHost:$dbPort\n";
} catch (PDOException $e) {
    echo "✗ Failed to connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Get seeds directory
$seedsDir = __DIR__ . '/../seeds';

// Run sample seeds
if ($command === 'sample') {
    echo "\nRunning SAMPLE seed data...\n";
    echo "===========================\n\n";
    
    $sampleFile = $seedsDir . '/seed_sample_data.sql';
    
    if (!file_exists($sampleFile)) {
        echo "✗ Sample seed file not found: seed_sample_data.sql\n";
        exit(1);
    }
    
    $filename = basename($sampleFile);
    echo "Loading: $filename ... ";
    
    try {
        $sql = file_get_contents($sampleFile);
        
        // Execute the entire SQL file at once to preserve session variables
        // MySQL session variables (@var) only persist within the same execution context
        $pdo->exec($sql);
        
        echo "✓ Done\n";
        echo "\n✓ Sample data loaded successfully!\n";
    } catch (PDOException $e) {
        echo "✗ Failed\n";
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
    
// Run production seeds
} else if ($command === 'up') {
    echo "\nRunning production seeds UP...\n";
    echo "==============================\n\n";
    
    // Find all *_seed.sql files (excluding seed_sample_data.sql and *_seed_down.sql)
    $files = glob($seedsDir . '/*_seed.sql');
    
    // Filter out *_seed_down.sql files
    $files = array_filter($files, function($file) {
        return !preg_match('/_seed_down\.sql$/', $file);
    });
    
    sort($files);
    
    if (empty($files)) {
        echo "No production seed files found.\n";
        echo "Tip: Create seed files with naming pattern: NNNN_description_seed.sql\n";
        exit(0);
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Applying: $filename ... ";
        
        try {
            $sql = file_get_contents($file);
            
            // Execute the entire SQL file at once to preserve session variables
            $pdo->exec($sql);
            
            echo "✓ Done\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n✓ All production seeds applied successfully!\n";
    
// Clean/rollback seeds
} else if ($command === 'down') {
    echo "\nCleaning/rolling back seeds DOWN...\n";
    echo "===================================\n\n";
    
    // Find all *_seed_down.sql files
    $files = glob($seedsDir . '/*_seed_down.sql');
    rsort($files); // Reverse order for rollback
    
    if (empty($files)) {
        echo "No seed cleanup files found.\n";
        echo "Tip: Create cleanup files with naming pattern: NNNN_description_seed_down.sql\n";
        echo "\nAlternatively, you can truncate tables manually:\n";
        echo "  TRUNCATE TABLE appointments;\n";
        echo "  TRUNCATE TABLE queue_entries;\n";
        echo "  TRUNCATE TABLE queues;\n";
        echo "  TRUNCATE TABLE professionals;\n";
        echo "  TRUNCATE TABLE services;\n";
        echo "  TRUNCATE TABLE establishments;\n";
        echo "  DELETE FROM users WHERE role != 'admin';\n";
        exit(0);
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Cleaning: $filename ... ";
        
        try {
            $sql = file_get_contents($file);
            
            // Execute the entire SQL file at once
            $pdo->exec($sql);
            
            echo "✓ Done\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n✓ All seed data cleaned successfully!\n";
}

echo "\nDatabase: $dbName\n";
echo "Host: $dbHost:$dbPort\n";
