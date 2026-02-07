<?php
/**
 * Token Cleanup Script
 * 
 * Removes expired refresh tokens from database.
 * Run this periodically via cron job or Windows Task Scheduler.
 * 
 * Usage:
 *   php scripts/cleanup_tokens.php
 * 
 * Cron example (daily at 3:00 AM):
 *   0 3 * * * /usr/bin/php /var/www/api/scripts/cleanup_tokens.php >> /var/log/token_cleanup.log 2>&1
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

echo "=== Token Cleanup Script ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

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
    echo "\nTokens after cleanup: $afterCount\n";
    
    // Log summary
    $totalDeleted = $deletedExpired + $deletedRevoked;
    echo "\n=== Summary ===\n";
    echo "Total tokens deleted: $totalDeleted\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
