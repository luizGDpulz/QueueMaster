USE `queue_master`;

ALTER TABLE business_users
  MODIFY COLUMN role ENUM('owner','manager','professional') NOT NULL DEFAULT 'manager';

ALTER TABLE business_invitations
  ADD COLUMN establishment_id BIGINT UNSIGNED NULL AFTER business_id,
  ADD CONSTRAINT fk_business_invitations_establishment
    FOREIGN KEY (establishment_id) REFERENCES establishments(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD INDEX idx_bi_establishment (establishment_id),
  ADD INDEX idx_bi_business_status (business_id, status);
