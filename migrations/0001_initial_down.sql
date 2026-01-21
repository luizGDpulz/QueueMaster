-- 0001_initial_down.sql
-- Note: This rollback drops the entire database as it reverses the initial setup
-- Use with caution - this will delete all data
SET FOREIGN_KEY_CHECKS=0;
USE `queue_master`;

DROP TABLE IF EXISTS idempotency_keys;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS queue_entries;
DROP TABLE IF EXISTS queues;
DROP TABLE IF EXISTS professionals;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS establishments;
DROP TABLE IF EXISTS users;

DROP DATABASE IF EXISTS `queue_master`;
SET FOREIGN_KEY_CHECKS=1;
