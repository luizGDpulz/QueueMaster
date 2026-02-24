<?php
require 'api/vendor/autoload.php';

use Dotenv\Dotenv;
use QueueMaster\Builders\QueryBuilder;

if (file_exists('api/.env')) {
    $dotenv = Dotenv::createImmutable('api');
    $dotenv->load();
}

try {
    $qb = new QueryBuilder();

    echo "--- DATABASE DIAGNOSTIC ---\n";

    // Check Users
    $userCount = $qb->select('users')->count();
    echo "Total Users: $userCount\n";
    if ($userCount > 0) {
        $firstUser = $qb->select('users')->first();
        echo "First User: " . $firstUser['email'] . " (Role: " . $firstUser['role'] . ")\n";
    }

    // Check Businesses
    $bizCount = $qb->select('businesses')->count();
    echo "Total Businesses: $bizCount\n";

    // Check Config
    echo "SUPER_ADMIN_EMAIL: " . ($_ENV['SUPER_ADMIN_EMAIL'] ?? 'NOT SET') . "\n";


}
catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
