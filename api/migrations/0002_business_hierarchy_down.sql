-- 0002_business_hierarchy_down.sql
-- QueueMaster Multi-Tenant Business Hierarchy - Rollback
-- Reverts all changes from 0002_business_hierarchy_up.sql
--
-- Run with: php scripts/migrate.php down

USE `queue_master`;

SET FOREIGN_KEY_CHECKS=0;

-- Drop new tables (reverse order of creation)
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS business_subscriptions;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS queue_access_codes;
DROP TABLE IF EXISTS business_users;

-- Remove business_id from establishments
ALTER TABLE establishments DROP FOREIGN KEY fk_establishments_business;
DROP INDEX idx_establishments_business ON establishments;
ALTER TABLE establishments DROP COLUMN business_id;

-- Drop businesses table (after FK removal)
DROP TABLE IF EXISTS businesses;

-- Revert user roles: professional -> attendant, remove new enum values
UPDATE users SET role = 'attendant' WHERE role = 'professional';
UPDATE users SET role = 'client' WHERE role = 'manager';
ALTER TABLE users MODIFY COLUMN role ENUM('client','attendant','admin') NOT NULL DEFAULT 'client';

-- Revert establishment_users role enum
ALTER TABLE establishment_users MODIFY COLUMN role ENUM('owner','manager','attendant') NOT NULL DEFAULT 'attendant';

SET FOREIGN_KEY_CHECKS=1;
