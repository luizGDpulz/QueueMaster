USE `queue_master`;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS address_line_1 VARCHAR(255) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS address_line_2 VARCHAR(255) NULL AFTER address_line_1;

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

INSERT INTO user_plan_subscriptions (
  user_id,
  plan_id,
  status,
  starts_at,
  ends_at,
  created_at,
  updated_at
)
SELECT
  b.owner_user_id,
  bs.plan_id,
  bs.status,
  bs.starts_at,
  bs.ends_at,
  bs.created_at,
  bs.updated_at
FROM business_subscriptions bs
JOIN businesses b
  ON b.id = bs.business_id
WHERE b.owner_user_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM user_plan_subscriptions ups
    WHERE ups.user_id = b.owner_user_id
      AND ups.plan_id = bs.plan_id
      AND ups.status = bs.status
      AND ups.starts_at = bs.starts_at
      AND (
        (ups.ends_at IS NULL AND bs.ends_at IS NULL)
        OR ups.ends_at = bs.ends_at
      )
  );
