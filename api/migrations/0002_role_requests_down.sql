USE `queue_master`;

DROP TABLE IF EXISTS user_role_requests;

ALTER TABLE users
  DROP COLUMN IF EXISTS manager_access_granted_at,
  DROP COLUMN IF EXISTS manager_access_granted;
