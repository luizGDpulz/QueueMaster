-- 0001_schema_up.sql
-- QueueMaster unified database schema
-- Run with: php scripts/migrate.php up

SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE IF NOT EXISTS `queue_master`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `queue_master`;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL COMMENT 'Optional local password hash for admin/manual users',
  google_id VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth sub (unique ID)',
  avatar_url VARCHAR(500) NULL COMMENT 'Google profile picture URL',
  avatar_base64 MEDIUMTEXT NULL COMMENT 'Avatar image stored as base64 data URI',
  email_verified BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email verified by provider',
  phone VARCHAR(20) NULL COMMENT 'Contact phone number',
  address_line_1 VARCHAR(255) NULL COMMENT 'Primary address line',
  address_line_2 VARCHAR(255) NULL COMMENT 'Secondary address line',
  role ENUM('client','attendant','professional','manager','admin') NOT NULL DEFAULT 'client',
  manager_access_granted BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Administrative grant for manager access',
  manager_access_granted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When manager access was granted',
  is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Account active status',
  login_blocked_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When login was blocked internally',
  login_block_reason VARCHAR(500) NULL COMMENT 'Administrative reason for access block',
  login_blocked_by_user_id BIGINT UNSIGNED NULL COMMENT 'Admin who blocked login access',
  last_login_at TIMESTAMP NULL COMMENT 'Last successful login',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_login_blocked_by_user
    FOREIGN KEY (login_blocked_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_users_email (email),
  INDEX idx_users_google_id (google_id),
  INDEX idx_users_role (role),
  INDEX idx_users_active_blocked (is_active, login_blocked_at),
  INDEX idx_users_login_blocked_by (login_blocked_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS businesses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who owns this business',
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NULL UNIQUE COMMENT 'URL-friendly identifier',
  description TEXT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_businesses_owner_user
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_businesses_owner (owner_user_id),
  INDEX idx_businesses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager','professional') NOT NULL DEFAULT 'manager',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_business_users_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_business_users_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_business_user (business_id, user_id),
  INDEX idx_business_users_role (business_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS establishments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NULL COMMENT 'User who owns/manages this establishment',
  business_id BIGINT UNSIGNED NULL,
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
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_establishments_owner
    FOREIGN KEY (owner_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_establishments_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_establishments_owner (owner_id),
  INDEX idx_establishments_slug (slug),
  INDEX idx_establishments_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  icon VARCHAR(100) NULL,
  image_url VARCHAR(500) NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  price DECIMAL(10,2) NULL COMMENT 'Service price (optional)',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_services_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_services_establishment (establishment_id),
  INDEX idx_services_active (establishment_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_professionals_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_professionals_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_professionals_establishment (establishment_id),
  INDEX idx_professionals_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  professional_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_professional_services_professional
    FOREIGN KEY (professional_id) REFERENCES professionals(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_professional_services_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_professional_service (professional_id, service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_establishments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL COMMENT 'Professional user',
  establishment_id BIGINT UNSIGNED NOT NULL COMMENT 'Establishment they work at',
  is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Active link',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_professional_establishments_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_professional_establishments_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_professional_establishment (user_id, establishment_id),
  INDEX idx_pe_establishment (establishment_id),
  INDEX idx_pe_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  status ENUM('open','closed','paused') NOT NULL DEFAULT 'open',
  max_capacity INT NULL COMMENT 'Maximum entries allowed (NULL = unlimited)',
  avg_wait_minutes INT NULL COMMENT 'Average wait time in minutes',
  called_highlight_after_minutes INT NOT NULL DEFAULT 5 COMMENT 'Minutes before a called entry is highlighted as delayed (0 disables)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_queues_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queues_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queues_establishment (establishment_id),
  INDEX idx_queues_status (establishment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_professionals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_professionals_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_professionals_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_queue_professional (queue_id, user_id),
  INDEX idx_queue_professionals_active (queue_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL COMMENT 'Registered user (NULL = anonymous)',
  guest_name VARCHAR(150) NULL COMMENT 'Name for anonymous entries',
  guest_phone VARCHAR(20) NULL COMMENT 'Phone for anonymous entries',
  position INT NOT NULL,
  ticket_number VARCHAR(20) NULL COMMENT 'Display ticket (example: A001)',
  status ENUM('waiting','called','serving','done','no_show','cancelled') NOT NULL DEFAULT 'waiting',
  priority INT NOT NULL DEFAULT 0 COMMENT 'Higher = more priority',
  notes TEXT NULL COMMENT 'Special notes/requests',
  professional_id BIGINT UNSIGNED NULL COMMENT 'Professional user currently serving the entry',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  called_at TIMESTAMP NULL DEFAULT NULL,
  served_at TIMESTAMP NULL DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_queue_entries_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entries_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entries_professional
    FOREIGN KEY (professional_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queue_entries_queue_status (queue_id, status, position),
  INDEX idx_queue_entries_user (user_id),
  INDEX idx_queue_entries_public_id (public_id),
  INDEX idx_queue_entries_created (queue_id, created_at),
  INDEX idx_qe_queue_created_status (queue_id, created_at, status),
  INDEX idx_qe_queue_completed (queue_id, completed_at),
  INDEX idx_qe_professional_completed (professional_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_entry_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_entry_id BIGINT UNSIGNED NOT NULL,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('system','client','staff') NOT NULL DEFAULT 'system',
  event_type VARCHAR(50) NOT NULL,
  payload JSON NULL,
  occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_entry_events_entry
    FOREIGN KEY (queue_entry_id) REFERENCES queue_entries(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_actor_user
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queue_entry_events_entry_time (queue_entry_id, occurred_at, id),
  INDEX idx_queue_entry_events_user_time (user_id, occurred_at, id),
  INDEX idx_queue_entry_events_queue_time (queue_id, occurred_at, id),
  INDEX idx_queue_entry_events_type_time (event_type, occurred_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_access_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique join code',
  expires_at DATETIME NULL COMMENT 'Expiration time (NULL = never)',
  max_uses INT NULL COMMENT 'Maximum uses (NULL = unlimited)',
  uses INT NOT NULL DEFAULT 0 COMMENT 'Current use count',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_access_codes_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_queue_access_codes_queue (queue_id),
  INDEX idx_queue_access_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_services_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_services_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_queue_service (queue_id, service_id),
  INDEX idx_qs_queue (queue_id),
  INDEX idx_qs_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at TIMESTAMP NULL COMMENT 'When user confirmed',
  checkin_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_appointments_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointments_professional
    FOREIGN KEY (professional_id) REFERENCES professionals(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointments_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointments_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_appointments_start (start_at),
  INDEX idx_appointments_professional (professional_id, start_at),
  INDEX idx_appointments_user (user_id, start_at),
  INDEX idx_appointments_establishment (establishment_id, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NULL,
  client_user_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  responded_by_user_id BIGINT UNSIGNED NULL,
  direction ENUM('client_to_establishment','staff_to_client') NOT NULL,
  requester_role ENUM('client','professional','manager','admin') NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  proposed_start_at DATETIME NOT NULL,
  proposed_end_at DATETIME NOT NULL,
  notes TEXT NULL,
  decision_note TEXT NULL,
  responded_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_requests_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_professional
    FOREIGN KEY (professional_id) REFERENCES professionals(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_client
    FOREIGN KEY (client_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_requested_by
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_responded_by
    FOREIGN KEY (responded_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_appointment_requests_status (status, proposed_start_at),
  INDEX idx_appointment_requests_establishment (establishment_id, status, proposed_start_at),
  INDEX idx_appointment_requests_client (client_user_id, status, proposed_start_at),
  INDEX idx_appointment_requests_professional (professional_id, status, proposed_start_at),
  INDEX idx_appointment_requests_requested_by (requested_by_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  type VARCHAR(50) NULL COMMENT 'Notification type',
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  data JSON NULL COMMENT 'Additional payload data',
  channel ENUM('in_app','push','email','sms') NOT NULL DEFAULT 'in_app',
  read_at TIMESTAMP NULL DEFAULT NULL,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_notifications_user (user_id, read_at),
  INDEX idx_notifications_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  push_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_preferences_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  device_info VARCHAR(255) NULL COMMENT 'Device/browser info',
  ip_address VARCHAR(45) NULL COMMENT 'IP used to create token',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_refresh_tokens_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_refresh_tokens_user (user_id),
  INDEX idx_refresh_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS establishment_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager','professional') NOT NULL DEFAULT 'professional',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_establishment_users_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_establishment_users_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_establishment_user (establishment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  max_businesses INT NULL COMMENT 'Max businesses per owner (NULL = unlimited)',
  max_establishments_per_business INT NULL COMMENT 'Max establishments per business (NULL = unlimited)',
  max_managers INT NULL COMMENT 'Max managers per business (NULL = unlimited)',
  max_professionals_per_establishment INT NULL COMMENT 'Max professionals per establishment (NULL = unlimited)',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (
  name,
  max_businesses,
  max_establishments_per_business,
  max_managers,
  max_professionals_per_establishment
) VALUES
  ('Free', 1, 1, 2, 5),
  ('Basic', 3, 5, 10, 20),
  ('Premium', NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE
  max_businesses = VALUES(max_businesses),
  max_establishments_per_business = VALUES(max_establishments_per_business),
  max_managers = VALUES(max_managers),
  max_professionals_per_establishment = VALUES(max_professionals_per_establishment),
  is_active = TRUE;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL COMMENT 'Action performed',
  entity VARCHAR(100) NULL COMMENT 'Entity type',
  entity_id VARCHAR(100) NULL COMMENT 'Entity identifier',
  establishment_id BIGINT UNSIGNED NULL COMMENT 'Related establishment for filtering',
  business_id BIGINT UNSIGNED NULL COMMENT 'Related business for filtering',
  payload JSON NULL COMMENT 'Additional action data',
  ip VARCHAR(45) NULL COMMENT 'Client IP address',
  user_agent VARCHAR(500) NULL COMMENT 'Client User-Agent string',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_audit_logs_user (user_id),
  INDEX idx_audit_logs_entity (entity, entity_id),
  INDEX idx_audit_logs_establishment (establishment_id),
  INDEX idx_audit_logs_business (business_id),
  INDEX idx_audit_logs_action (action),
  INDEX idx_audit_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL COMMENT 'The business involved',
  establishment_id BIGINT UNSIGNED NULL COMMENT 'Optional establishment scope',
  from_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who initiated the invitation/request',
  to_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who receives the invitation/request',
  direction ENUM('business_to_professional','professional_to_business') NOT NULL COMMENT 'Who initiated',
  role VARCHAR(50) NULL DEFAULT 'professional' COMMENT 'Requested role for the target user',
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  message TEXT NULL COMMENT 'Optional message with the invitation',
  responded_at TIMESTAMP NULL COMMENT 'When the recipient responded',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_business_invitations_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_business_invitations_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_business_invitations_from_user
    FOREIGN KEY (from_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_business_invitations_to_user
    FOREIGN KEY (to_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_bi_business (business_id),
  INDEX idx_bi_establishment (establishment_id),
  INDEX idx_bi_from (from_user_id),
  INDEX idx_bi_to (to_user_id),
  INDEX idx_bi_status (status),
  INDEX idx_bi_business_status (business_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_role_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  requested_role ENUM('manager') NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  message TEXT NULL,
  payload JSON NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_role_requests_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_role_requests_reviewed_by
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_user_role_requests_user (user_id),
  INDEX idx_user_role_requests_status (status),
  INDEX idx_user_role_requests_role_status (requested_role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_plan_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','past_due','cancelled') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_plan_subscriptions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_plan_subscriptions_plan
    FOREIGN KEY (plan_id) REFERENCES plans(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_user_plan_subscriptions_user (user_id),
  INDEX idx_user_plan_subscriptions_status (status),
  INDEX idx_user_plan_subscriptions_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
