-- 0004_professional_establishments_invitations_up.sql
-- QueueMaster - Professional N:N Establishments + Business Invitations
--
-- Changes:
--   1. professional_establishments (N:N pivot) — a professional can work at
--      multiple establishments across multiple businesses.
--   2. business_invitations — managers invite professionals to join a business,
--      or professionals request to join. Both parties can initiate.
--
-- Run with: php scripts/migrate.php up

USE `queue_master`;

-- ============================================================================
-- PROFESSIONAL_ESTABLISHMENTS (N:N pivot)
-- A professional user can be linked to many establishments.
-- An establishment can have many professional users.
-- ============================================================================
CREATE TABLE IF NOT EXISTS professional_establishments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL COMMENT 'The professional user',
  establishment_id BIGINT UNSIGNED NOT NULL COMMENT 'The establishment they work at',
  is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Active link',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY idx_prof_estab (user_id, establishment_id),
  INDEX idx_pe_establishment (establishment_id),
  INDEX idx_pe_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- BUSINESS_INVITATIONS
-- Handles both directions:
--   - Manager invites a professional to join → direction = 'business_to_professional'
--   - Professional requests to join → direction = 'professional_to_business'
-- ============================================================================
CREATE TABLE IF NOT EXISTS business_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL COMMENT 'The business involved',
  from_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who initiated the invitation/request',
  to_user_id BIGINT UNSIGNED NOT NULL COMMENT 'User who receives the invitation/request',
  direction ENUM('business_to_professional','professional_to_business') NOT NULL COMMENT 'Who initiated',
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  message TEXT NULL COMMENT 'Optional message with the invitation',
  responded_at TIMESTAMP NULL COMMENT 'When the recipient responded',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_bi_business (business_id),
  INDEX idx_bi_from (from_user_id),
  INDEX idx_bi_to (to_user_id),
  INDEX idx_bi_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
