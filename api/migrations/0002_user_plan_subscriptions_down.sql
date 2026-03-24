USE `queue_master`;

DROP TABLE IF EXISTS user_plan_subscriptions;

ALTER TABLE users
  DROP COLUMN IF EXISTS address_line_2,
  DROP COLUMN IF EXISTS address_line_1;
