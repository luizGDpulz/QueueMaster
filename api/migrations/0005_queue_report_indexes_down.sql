-- 0005_queue_report_indexes_down.sql

USE `queue_master`;

ALTER TABLE queue_entries
  DROP INDEX idx_qe_queue_created_status,
  DROP INDEX idx_qe_queue_completed,
  DROP INDEX idx_qe_professional_completed;
