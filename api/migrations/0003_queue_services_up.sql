-- 0003_queue_services_up.sql
-- Junction table to link services to specific queues
-- A queue can have many services; a service can be in many queues.

USE `queue_master`;

CREATE TABLE IF NOT EXISTS queue_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY idx_queue_service (queue_id, service_id),
  INDEX idx_qs_queue (queue_id),
  INDEX idx_qs_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
