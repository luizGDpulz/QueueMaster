-- 0002_queue_history_alignment_down.sql
-- Rolls back the queue history alignment additions.

USE `queue_master`;

DROP TABLE IF EXISTS queue_entry_events;

SET @queue_entries_public_id_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'queue_entries'
    AND index_name = 'uq_queue_entries_public_id'
);

SET @queue_entries_public_id_drop_index_sql := IF(
  @queue_entries_public_id_index_exists > 0,
  'ALTER TABLE queue_entries DROP INDEX uq_queue_entries_public_id',
  'SELECT 1'
);
PREPARE stmt FROM @queue_entries_public_id_drop_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE queue_entries
  DROP COLUMN IF EXISTS public_id;

ALTER TABLE queues
  DROP COLUMN IF EXISTS called_highlight_after_minutes;
