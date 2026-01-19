<?php
/**
 * CLI Model Generator Script
 * 
 * Generates model classes from database tables using ModelBuilder.
 * 
 * Usage:
 *   php scripts/cli-model-generator.php <table_name>
 * 
 * Example:
 *   php scripts/cli-model-generator.php users
 *   php scripts/cli-model-generator.php queue_entries
 * 
 * Environment variables (from .env or environment):
 *   DB_HOST     - Database host
 *   DB_PORT     - Database port
 *   DB_USER     - Database username
 *   DB_PASS     - Database password
 *   DB_NAME     - Database name
 */

// Load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    echo "Error: Composer autoloader not found. Please run 'composer install' first.\n";
    exit(1);
}

require $autoloadPath;

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
try {
    $dotenv->load();
} catch (\Exception $e) {
    echo "Warning: Could not load .env file: " . $e->getMessage() . "\n";
    echo "Make sure environment variables are set.\n\n";
}

// Get table name from command line
if ($argc < 2) {
    echo "CLI Model Generator\n";
    echo "===================\n\n";
    echo "Usage:\n";
    echo "  php scripts/cli-model-generator.php <table_name>\n\n";
    echo "Example:\n";
    echo "  php scripts/cli-model-generator.php users\n";
    echo "  php scripts/cli-model-generator.php queue_entries\n\n";
    echo "Environment variables (from .env):\n";
    echo "  DB_HOST     - Database host\n";
    echo "  DB_PORT     - Database port\n";
    echo "  DB_USER     - Database username\n";
    echo "  DB_PASS     - Database password\n";
    echo "  DB_NAME     - Database name\n\n";
    exit(1);
}

$tableName = $argv[1];

// Validate table name (basic sanitization)
if (!preg_match('/^[a-z0-9_]+$/i', $tableName)) {
    echo "Error: Invalid table name. Use only letters, numbers, and underscores.\n";
    exit(1);
}

echo "Generating model for table: $tableName\n";
echo str_repeat('-', 50) . "\n\n";

try {
    // Initialize ModelBuilder
    $builder = new QueueMaster\Builders\ModelBuilder();
    
    // Generate model
    $success = $builder->generate($tableName);
    
    if ($success) {
        echo "✓ Model generated successfully!\n";
        echo "  Location: src/Models/\n";
        echo "  Class: " . ucfirst($tableName) . "\n\n";
        echo "You can now use the model in your code:\n";
        echo "  use QueueMaster\\Models\\" . ucfirst($tableName) . ";\n\n";
        exit(0);
    } else {
        echo "✗ Model generation failed. Check logs for details.\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}
