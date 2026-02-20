-- 0001_schema_up.sql
-- QueueMaster Database Schema
-- Hybrid Queue + Scheduling system with Google OAuth authentication
-- 
-- Run with: php scripts/migrate.php up

SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE IF NOT EXISTS `queue_master` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `queue_master`;
SET FOREIGN_KEY_CHECKS=1;

-- ============================================================================
-- USERS (Google OAuth only - no password)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  google_id VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth sub (unique ID)',
  avatar_url VARCHAR(500) NULL COMMENT 'Google profile picture URL',
  email_verified BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email verified by Google',
  phone VARCHAR(20) NULL COMMENT 'Contact phone number',
  role ENUM('client','attendant','admin') NOT NULL DEFAULT 'client',
  is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Account active status',
  last_login_at TIMESTAMP NULL COMMENT 'Last successful login',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_email (email),
  INDEX idx_users_google_id (google_id),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ESTABLISHMENTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS establishments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NULL COMMENT 'User who owns/manages this establishment',
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NULL UNIQUE COMMENT 'URL-friendly identifier',
  description TEXT NULL COMMENT 'Establishment description',
  address VARCHAR(255) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(150) NULL,
  logo_url VARCHAR(500) NULL COMMENT 'Establishment logo',
  timezone VARCHAR(50) NOT NULL DEFAULT 'America/Sao_Paulo',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  opens_at TIME NULL COMMENT 'Default opening time',
  closes_at TIME NULL COMMENT 'Default closing time',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_establishments_owner (owner_id),
  INDEX idx_establishments_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SERVICES
-- ============================================================================
CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  price DECIMAL(10,2) NULL COMMENT 'Service price (optional)',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_services_establishment (establishment_id),
  INDEX idx_services_active (establishment_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PROFESSIONALS
-- ============================================================================
CREATE TABLE IF NOT EXISTS professionals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL COMMENT 'Link to user account (optional)',
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(20) NULL,
  avatar_url VARCHAR(500) NULL,
  specialty VARCHAR(150) NULL COMMENT 'Professional specialty/role',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_professionals_establishment (establishment_id),
  INDEX idx_professionals_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PROFESSIONAL_SERVICES (many-to-many)
-- ============================================================================
CREATE TABLE IF NOT EXISTS professional_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  professional_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY idx_professional_service (professional_id, service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- QUEUES
-- ============================================================================
CREATE TABLE IF NOT EXISTS queues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  status ENUM('open','closed','paused') NOT NULL DEFAULT 'open',
  max_capacity INT NULL COMMENT 'Maximum entries allowed (null = unlimited)',
  avg_wait_minutes INT NULL COMMENT 'Average wait time in minutes',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queues_establishment (establishment_id),
  INDEX idx_queues_status (establishment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- QUEUE ENTRIES (walk-in)
-- ============================================================================
CREATE TABLE IF NOT EXISTS queue_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL COMMENT 'Registered user (null = anonymous)',
  guest_name VARCHAR(150) NULL COMMENT 'Name for anonymous entries',
  guest_phone VARCHAR(20) NULL COMMENT 'Phone for anonymous entries',
  position INT NOT NULL,
  ticket_number VARCHAR(20) NULL COMMENT 'Display ticket (e.g., A001)',
  status ENUM('waiting','called','serving','done','no_show','cancelled') NOT NULL DEFAULT 'waiting',
  priority INT NOT NULL DEFAULT 0 COMMENT 'Higher = more priority',
  notes TEXT NULL COMMENT 'Special notes/requests',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  called_at TIMESTAMP NULL DEFAULT NULL,
  served_at TIMESTAMP NULL DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queue_entries_queue_status (queue_id, status, position),
  INDEX idx_queue_entries_user (user_id),
  INDEX idx_queue_entries_created (queue_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- APPOINTMENTS (scheduled bookings)
-- ============================================================================
CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('booked','confirmed','checked_in','in_progress','completed','no_show','cancelled') NOT NULL DEFAULT 'booked',
  notes TEXT NULL COMMENT 'Appointment notes',
  cancellation_reason TEXT NULL COMMENT 'Reason if cancelled',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at TIMESTAMP NULL COMMENT 'When user confirmed',
  checkin_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_appointments_start (start_at),
  INDEX idx_appointments_professional (professional_id, start_at),
  INDEX idx_appointments_user (user_id, start_at),
  INDEX idx_appointments_establishment (establishment_id, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- NOTIFICATIONS
-- ============================================================================
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  type VARCHAR(50) NULL COMMENT 'Notification type (queue_called, appointment_reminder, etc)',
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  data JSON NULL COMMENT 'Additional payload data',
  channel ENUM('in_app','push','email','sms') NOT NULL DEFAULT 'in_app',
  read_at TIMESTAMP NULL DEFAULT NULL,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_notifications_user (user_id, read_at),
  INDEX idx_notifications_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- REFRESH TOKENS (JWT rotation)
-- ============================================================================
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  device_info VARCHAR(255) NULL COMMENT 'Device/browser info',
  ip_address VARCHAR(45) NULL COMMENT 'IP used to create token',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_refresh_tokens_user (user_id),
  INDEX idx_refresh_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- IDEMPOTENCY KEYS (request deduplication)
-- ============================================================================
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  key_hash VARCHAR(255) NOT NULL UNIQUE,
  request_method VARCHAR(10) NOT NULL,
  request_path VARCHAR(255) NOT NULL,
  response_body TEXT NULL,
  status_code INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  INDEX idx_idempotency_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ESTABLISHMENT_USERS (staff/team members)
-- ============================================================================
CREATE TABLE IF NOT EXISTS establishment_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager','professional') NOT NULL DEFAULT 'professional',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY idx_establishment_user (establishment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
