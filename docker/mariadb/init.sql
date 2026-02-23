-- QueueMaster Database Initialization
-- This runs automatically on first container start

CREATE DATABASE IF NOT EXISTS `queue_master`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

-- Grant privileges to the app user (created via MYSQL_USER env var)
-- MariaDB image already creates the user, this ensures DB-level grants
GRANT ALL PRIVILEGES ON `queue_master`.* TO 'queuemaster'@'%';
FLUSH PRIVILEGES;
