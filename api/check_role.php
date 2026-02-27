<?php
require 'vendor/autoload.php';
\QueueMaster\Core\Database::getInstance()->connect();
$user = \QueueMaster\Models\User::findByEmail('luizg.pulz@gmail.com');
echo "CURRENT ROLE FOR luizg.pulz@gmail.com: " . ($user['role'] ?? 'NOT FOUND') . "\n";
