<?php
/**
 * Cleanup Script - Tokens & Logs
 * 
 * 1. Removes expired/old-revoked refresh tokens from database
 * 2. Deletes old application log files (default: older than 30 days)
 * 
 * Run this periodically via cron job or Windows Task Scheduler.
 * 
 * Usage:
 *   php scripts/cleanup_tokens.php
 * 
 * Cron example (daily at 3:00 AM):
 *   0 3 * * * /usr/bin/php /var/www/api/scripts/cleanup_tokens.php >> /var/log/cleanup.log 2>&1
 * 
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\api\scripts\cleanup_tokens.php
 */

// Change to script directory for proper path resolution
chdir(__DIR__ . '/..');

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use QueueMaster\Core\Database;
use QueueMaster\Middleware\TokenMiddleware;

echo "=== Cleanup Script ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// =========================================================================
// 1. Token Cleanup
// =========================================================================
echo "--- Token Cleanup ---\n";

try {
    $db = Database::getInstance();
    
    // Count tokens before cleanup
    $beforeCount = $db->query("SELECT COUNT(*) as count FROM refresh_tokens")[0]['count'] ?? 0;
    $expiredCount = $db->query("SELECT COUNT(*) as count FROM refresh_tokens WHERE expires_at < NOW()")[0]['count'] ?? 0;
    $revokedCount = $db->query("SELECT COUNT(*) as count FROM refresh_tokens WHERE revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")[0]['count'] ?? 0;
    
    echo "Tokens before cleanup: $beforeCount\n";
    echo "Expired tokens: $expiredCount\n";
    echo "Old revoked tokens (>7 days): $revokedCount\n\n";
    
    // Delete expired tokens
    $deletedExpired = $db->execute("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
    echo "Deleted expired tokens: $deletedExpired\n";
    
    // Delete old revoked tokens (keep 7 days for audit trail)
    $deletedRevoked = $db->execute("DELETE FROM refresh_tokens WHERE revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    echo "Deleted old revoked tokens: $deletedRevoked\n";
    
    // Count tokens after cleanup
    $afterCount = $db->query("SELECT COUNT(*) as count FROM refresh_tokens")[0]['count'] ?? 0;
    echo "Tokens after cleanup: $afterCount\n";
    
    $totalDeleted = $deletedExpired + $deletedRevoked;
    echo "Total tokens deleted: $totalDeleted\n\n";

} catch (\Exception $e) {
    echo "ERROR (tokens): " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

// =========================================================================
// 2. Log File Cleanup
// =========================================================================
echo "--- Log Cleanup ---\n";

$logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
$logRetentionDays = (int)($_ENV['LOG_RETENTION_DAYS'] ?? 30);

if (!is_dir($logPath)) {
    echo "Log directory not found: $logPath\n";
} else {
    $cutoffDate = new DateTime("-{$logRetentionDays} days");
    $deletedLogs = 0;
    $freedBytes = 0;

    $files = glob($logPath . '/app-*.log');
    echo "Total log files: " . count($files) . "\n";
    echo "Retention: {$logRetentionDays} days (deleting before " . $cutoffDate->format('Y-m-d') . ")\n\n";

    foreach ($files as $file) {
        // Extract date from filename (app-YYYY-MM-DD.log)
        if (preg_match('/app-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $matches)) {
            try {
                $fileDate = new DateTime($matches[1]);
                if ($fileDate < $cutoffDate) {
                    $size = filesize($file);
                    if (unlink($file)) {
                        $deletedLogs++;
                        $freedBytes += $size;
                        echo "  Deleted: " . basename($file) . " (" . round($size / 1024, 1) . " KB)\n";
                    }
                }
            } catch (\Exception $e) {
                // Skip files with invalid date format
            }
        }
    }

    if ($deletedLogs === 0) {
        echo "  No old log files to delete.\n";
    } else {
        echo "\nDeleted $deletedLogs log files (" . round($freedBytes / 1024, 1) . " KB freed)\n";
    }
}

echo "\n=== Cleanup Complete ===\n";
echo "Finished: " . date('Y-m-d H:i:s') . "\n";

exit(0);
