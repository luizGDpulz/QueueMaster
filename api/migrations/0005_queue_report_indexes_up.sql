-- 0005_queue_report_indexes_up.sql
-- Performance indexes for queue reports and operational filters

USE `queue_master`;

ALTER TABLE queue_entries
  ADD INDEX idx_qe_queue_created_status (queue_id, created_at, status),
  ADD INDEX idx_qe_queue_completed (queue_id, completed_at),
  ADD INDEX idx_qe_professional_completed (professional_id, completed_at);
