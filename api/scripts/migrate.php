<?php
/**
 * Migration Runner Script
 *
 * Usage:
 *   php scripts/migrate.php up     - Run all migrations in order
 *   php scripts/migrate.php down   - Roll back all migrations in reverse order
 *
 * Environment variables (from .env or environment):
 *   DB_HOST     - Database host (default: localhost)
 *   DB_PORT     - Database port (default: 3306)
 *   DB_USER     - Database username (default: root)
 *   DB_PASS     - Database password (default: empty)
 *   DB_NAME     - Database name (default: queue_master)
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        if (!getenv($key) && !isset($_ENV[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'queue_master';

function applyDatabaseName(string $sql, string $dbName): string
{
    $escapedName = str_replace('`', '``', $dbName);

    return str_replace('`queue_master`', "`{$escapedName}`", $sql);
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['up', 'down', 'help'], true)) {
    echo "Error: Invalid command '{$command}'\n\n";
    $command = 'help';
}

if ($command === 'help') {
    echo "Migration Runner\n";
    echo "================\n\n";
    echo "Usage:\n";
    echo "  php scripts/migrate.php up     - Run all migrations in order\n";
    echo "  php scripts/migrate.php down   - Roll back all migrations in reverse order\n\n";
    echo "Environment variables (from .env or environment):\n";
    echo "  DB_HOST     - Database host (default: localhost)\n";
    echo "  DB_PORT     - Database port (default: 3306)\n";
    echo "  DB_USER     - Database username (default: root)\n";
    echo "  DB_PASS     - Database password (default: empty)\n";
    echo "  DB_NAME     - Database name (default: queue_master)\n\n";
    exit(0);
}

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] Connected to MySQL server at {$dbHost}:{$dbPort}\n";
} catch (PDOException $e) {
    echo "[ERROR] Failed to connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

$migrationsDir = __DIR__ . '/../migrations';

if ($command === 'up') {
    echo "\nRunning migrations UP...\n";
    echo "========================\n\n";

    $files = glob($migrationsDir . '/*_up.sql');
    sort($files);

    if (empty($files)) {
        echo "No migration files found.\n";
        exit(0);
    }

    foreach ($files as $file) {
        $filename = basename($file);
        echo "Applying: {$filename} ... ";

        try {
            $sql = file_get_contents($file);
            $sql = applyDatabaseName($sql, $dbName);
            $pdo->exec($sql);
            echo "Done\n";
        } catch (PDOException $e) {
            echo "Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "\n[OK] All migrations applied successfully.\n";
} else {
    echo "\nRunning migrations DOWN...\n";
    echo "==========================\n\n";

    $files = glob($migrationsDir . '/*_down.sql');
    rsort($files);

    if (empty($files)) {
        echo "No rollback files found.\n";
        exit(0);
    }

    foreach ($files as $file) {
        $filename = basename($file);
        echo "Rolling back: {$filename} ... ";

        try {
            $sql = file_get_contents($file);
            $sql = applyDatabaseName($sql, $dbName);
            $pdo->exec($sql);
            echo "Done\n";
        } catch (PDOException $e) {
            echo "Failed\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "\n[OK] All migrations rolled back successfully.\n";
}

echo "\nDatabase: {$dbName}\n";
echo "Host: {$dbHost}:{$dbPort}\n";
