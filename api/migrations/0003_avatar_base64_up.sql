-- 0003_avatar_base64_up.sql
-- QueueMaster - Store user avatars as base64 in the database
-- Avoids depending on external Google URLs (prevents 429 rate-limiting)
--
-- Run with: php scripts/migrate.php up

USE `queue_master`;

-- Add avatar_base64 column to users table (MEDIUMTEXT supports ~16MB)
ALTER TABLE users
  ADD COLUMN avatar_base64 MEDIUMTEXT NULL COMMENT 'Avatar image stored as base64 data URI' AFTER avatar_url;
