CREATE TABLE IF NOT EXISTS appointment_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NULL,
  client_user_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  responded_by_user_id BIGINT UNSIGNED NULL,
  direction ENUM('client_to_establishment','staff_to_client') NOT NULL,
  requester_role ENUM('client','professional','manager','admin') NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  proposed_start_at DATETIME NOT NULL,
  proposed_end_at DATETIME NOT NULL,
  notes TEXT NULL,
  decision_note TEXT NULL,
  responded_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_requests_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_professional
    FOREIGN KEY (professional_id) REFERENCES professionals(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_client
    FOREIGN KEY (client_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_requested_by
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appointment_requests_responded_by
    FOREIGN KEY (responded_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_appointment_requests_status (status, proposed_start_at),
  INDEX idx_appointment_requests_establishment (establishment_id, status, proposed_start_at),
  INDEX idx_appointment_requests_client (client_user_id, status, proposed_start_at),
  INDEX idx_appointment_requests_professional (professional_id, status, proposed_start_at),
  INDEX idx_appointment_requests_requested_by (requested_by_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
