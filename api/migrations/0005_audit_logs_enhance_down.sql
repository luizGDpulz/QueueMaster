-- 0005_audit_logs_enhance_down.sql
-- Reverts audit_logs enhancements

USE `queue_master`;

ALTER TABLE audit_logs DROP INDEX idx_audit_logs_action;
ALTER TABLE audit_logs DROP COLUMN user_agent;
