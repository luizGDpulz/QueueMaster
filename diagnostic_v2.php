<?php
require 'api/vendor/autoload.php';

use Dotenv\Dotenv;
use QueueMaster\Core\Database;

// Mock environment for local check
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3307'; // Docker exposed port
$_ENV['DB_NAME'] = 'queue_master';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "--- DATABASE DIAGNOSTIC (Port 3307) ---\n";

    // Check tables
    $tables = $db->query("SHOW TABLES");
    echo "Tables found: " . count($tables) . "\n";

    // Check Users
    $users = $db->query("SELECT id, email, role FROM users");
    echo "Total Users: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "- ID: {$u['id']}, Email: {$u['email']}, Role: {$u['role']}\n";
    }

    // Check Businesses
    $bizs = $db->query("SELECT id, name FROM businesses");
    echo "Total Businesses: " . count($bizs) . "\n";


}
catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
