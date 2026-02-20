-- 0003_avatar_base64_down.sql
-- Rollback: Remove avatar_base64 column

USE `queue_master`;

ALTER TABLE users DROP COLUMN avatar_base64;
