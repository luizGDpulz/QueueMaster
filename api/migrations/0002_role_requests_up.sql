USE `queue_master`;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS manager_access_granted BOOLEAN NOT NULL DEFAULT FALSE AFTER role,
  ADD COLUMN IF NOT EXISTS manager_access_granted_at TIMESTAMP NULL DEFAULT NULL AFTER manager_access_granted;

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
