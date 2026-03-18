-- ============================================================
-- Rollback Migration 0002
-- ============================================================

ALTER TABLE services DROP COLUMN IF EXISTS image_url;
ALTER TABLE services DROP COLUMN IF EXISTS icon;

ALTER TABLE queue_entries DROP FOREIGN KEY IF EXISTS fk_qe_professional;
ALTER TABLE queue_entries DROP COLUMN IF EXISTS professional_id;

DROP TABLE IF EXISTS queue_professionals;
