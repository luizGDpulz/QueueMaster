-- 0005_audit_logs_enhance_up.sql
-- Enhances audit_logs table with user_agent column and action index

USE `queue_master`;

-- Add user_agent column for richer audit trail
ALTER TABLE audit_logs
  ADD COLUMN user_agent VARCHAR(500) NULL COMMENT 'Client User-Agent string' AFTER ip;

-- Add index on action for filtering
ALTER TABLE audit_logs
  ADD INDEX idx_audit_logs_action (action);
