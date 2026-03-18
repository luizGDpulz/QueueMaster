-- ============================================================
-- Migration 0002: Queue Professionals + Service Images + Entry Professional
-- ============================================================

-- Queue professionals: tracks which professionals are assigned to work a queue
CREATE TABLE IF NOT EXISTS queue_professionals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_queue_user (queue_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Track which professional is serving each entry
ALTER TABLE queue_entries ADD COLUMN professional_id BIGINT UNSIGNED NULL AFTER notes;
ALTER TABLE queue_entries ADD CONSTRAINT fk_qe_professional FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add icon and image_url columns to services
ALTER TABLE services ADD COLUMN icon VARCHAR(100) NULL AFTER description;
ALTER TABLE services ADD COLUMN image_url VARCHAR(500) NULL AFTER icon;
