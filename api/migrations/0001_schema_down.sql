-- 0001_schema_down.sql
-- QueueMaster Database Schema - Rollback
-- Drops all tables in reverse order of creation

USE `queue_master`;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS establishment_users;
DROP TABLE IF EXISTS idempotency_keys;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS queue_entries;
DROP TABLE IF EXISTS queues;
DROP TABLE IF EXISTS professional_services;
DROP TABLE IF EXISTS professionals;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS establishments;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS=1;

-- Optionally drop the database entirely:
-- DROP DATABASE IF EXISTS queue_master;
