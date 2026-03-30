-- 0002_queue_history_alignment_up.sql
-- Aligns older databases with the current queue history schema.

USE `queue_master`;

ALTER TABLE queues
  ADD COLUMN IF NOT EXISTS called_highlight_after_minutes INT NOT NULL DEFAULT 5
  COMMENT 'Minutes before a called entry is highlighted as delayed (0 disables)'
  AFTER avg_wait_minutes;

ALTER TABLE queue_entries
  ADD COLUMN IF NOT EXISTS public_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL
  AFTER id;

UPDATE queue_entries
SET public_id = UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26))
WHERE public_id IS NULL OR public_id = '';

SET @queue_entries_public_id_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'queue_entries'
    AND column_name = 'public_id'
    AND non_unique = 0
);

SET @queue_entries_public_id_index_sql := IF(
  @queue_entries_public_id_index_exists = 0,
  'ALTER TABLE queue_entries ADD UNIQUE KEY uq_queue_entries_public_id (public_id)',
  'SELECT 1'
);
PREPARE stmt FROM @queue_entries_public_id_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE queue_entries
  MODIFY COLUMN public_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

CREATE TABLE IF NOT EXISTS queue_entry_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_entry_id BIGINT UNSIGNED NOT NULL,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('system','client','staff') NOT NULL DEFAULT 'system',
  event_type VARCHAR(50) NOT NULL,
  payload JSON NULL,
  occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_entry_events_entry
    FOREIGN KEY (queue_entry_id) REFERENCES queue_entries(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_queue
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_queue_entry_events_actor_user
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_queue_entry_events_entry_time (queue_entry_id, occurred_at, id),
  INDEX idx_queue_entry_events_user_time (user_id, occurred_at, id),
  INDEX idx_queue_entry_events_queue_time (queue_id, occurred_at, id),
  INDEX idx_queue_entry_events_type_time (event_type, occurred_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
