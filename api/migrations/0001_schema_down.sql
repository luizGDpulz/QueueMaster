-- 0001_schema_down.sql
-- QueueMaster unified schema rollback

USE `queue_master`;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS business_invitations;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS business_subscriptions;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS establishment_users;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS fcm_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS queue_services;
DROP TABLE IF EXISTS queue_access_codes;
DROP TABLE IF EXISTS queue_entries;
DROP TABLE IF EXISTS queue_professionals;
DROP TABLE IF EXISTS queues;
DROP TABLE IF EXISTS professional_establishments;
DROP TABLE IF EXISTS professional_services;
DROP TABLE IF EXISTS professionals;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS establishments;
DROP TABLE IF EXISTS business_users;
DROP TABLE IF EXISTS businesses;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS=1;
