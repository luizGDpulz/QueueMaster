-- 0002_business_hierarchy_up.sql
-- QueueMaster Multi-Tenant Business Hierarchy
-- Adds: businesses, business_users, queue_access_codes, plans, business_subscriptions, audit_logs
-- Alters: establishments (add business_id), users (expand role enum)
--
-- Run with: php scripts/migrate.php up

USE `queue_master`;

-- ============================================================================
-- EXPAND USER ROLES: add 'manager' and 'professional'
-- NOTE: This migrates existing 'attendant' users to 'professional'.
-- The down migration will revert 'professional' back to 'attendant'.
-- Both roles are treated as interchangeable by RoleMiddleware for backward compat.
-- ============================================================================
ALTER TABLE users MODIFY COLUMN role ENUM('client','attendant','professional','manager','admin') NOT NULL DEFAULT 'client';

-- Migrate existing 'attendant' rows to 'professional'
UPDATE users SET role = 'professional' WHERE role = 'attendant';

-- ============================================================================
-- BUSINESSES (Brand / Company)
-- ============================================================================
CREATE TABLE IF NOT EXISTS businesses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who owns this business',
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NULL UNIQUE COMMENT 'URL-friendly identifier',
  description TEXT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_businesses_owner (owner_user_id),
  INDEX idx_businesses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- BUSINESS_USERS (link business <-> users with roles)
-- ============================================================================
CREATE TABLE IF NOT EXISTS business_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager') NOT NULL DEFAULT 'manager',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_business_user (business_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ADD business_id TO ESTABLISHMENTS
-- ============================================================================
ALTER TABLE establishments ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER owner_id;
ALTER TABLE establishments
  ADD FOREIGN KEY fk_establishments_business (business_id) REFERENCES businesses(id) ON DELETE SET NULL ON UPDATE CASCADE;
CREATE INDEX idx_establishments_business ON establishments (business_id);

-- ============================================================================
-- QUEUE ACCESS CODES (join by code / QR)
-- ============================================================================
CREATE TABLE IF NOT EXISTS queue_access_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique join code',
  expires_at DATETIME NULL COMMENT 'Expiration time (null = never)',
  max_uses INT NULL COMMENT 'Maximum uses (null = unlimited)',
  uses INT NOT NULL DEFAULT 0 COMMENT 'Current use count',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
  INDEX idx_queue_access_codes_queue (queue_id),
  INDEX idx_queue_access_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PLANS (SaaS plan definitions)
-- ============================================================================
CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  max_businesses INT NULL COMMENT 'Max businesses per owner (null = unlimited)',
  max_establishments_per_business INT NULL COMMENT 'Max establishments per business (null = unlimited)',
  max_managers INT NULL COMMENT 'Max managers per business (null = unlimited)',
  max_professionals_per_establishment INT NULL COMMENT 'Max professionals per establishment (null = unlimited)',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- BUSINESS SUBSCRIPTIONS
-- ============================================================================
CREATE TABLE IF NOT EXISTS business_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','past_due','cancelled') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
  INDEX idx_business_subscriptions_business (business_id),
  INDEX idx_business_subscriptions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- AUDIT LOGS
-- ============================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL COMMENT 'Action performed (e.g. create, update, delete)',
  entity VARCHAR(100) NULL COMMENT 'Entity type (e.g. business, establishment)',
  entity_id VARCHAR(100) NULL COMMENT 'Entity ID',
  establishment_id BIGINT UNSIGNED NULL COMMENT 'Related establishment for filtering',
  business_id BIGINT UNSIGNED NULL COMMENT 'Related business for filtering',
  payload JSON NULL COMMENT 'Additional action data',
  ip VARCHAR(45) NULL COMMENT 'Client IP address',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_logs_user (user_id),
  INDEX idx_audit_logs_entity (entity, entity_id),
  INDEX idx_audit_logs_establishment (establishment_id),
  INDEX idx_audit_logs_business (business_id),
  INDEX idx_audit_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- INSERT DEFAULT PLAN (Free tier)
-- ============================================================================
INSERT INTO plans (name, max_businesses, max_establishments_per_business, max_managers, max_professionals_per_establishment) VALUES
  ('Free', 1, 1, 2, 5),
  ('Basic', 3, 5, 10, 20),
  ('Premium', NULL, NULL, NULL, NULL);

-- ============================================================================
-- UPDATE establishment_users ROLE ENUM to use 'professional' instead of 'attendant'
-- ============================================================================
-- First add 'professional' to the enum while keeping 'attendant'
ALTER TABLE establishment_users MODIFY COLUMN role ENUM('owner','manager','attendant','professional') NOT NULL DEFAULT 'professional';

-- Migrate existing 'attendant' rows to 'professional' in establishment_users
UPDATE establishment_users SET role = 'professional' WHERE role = 'attendant';

-- Now remove 'attendant' from the enum
ALTER TABLE establishment_users MODIFY COLUMN role ENUM('owner','manager','professional') NOT NULL DEFAULT 'professional';
