USE `queue_master`;

ALTER TABLE business_invitations
  DROP FOREIGN KEY fk_business_invitations_establishment,
  DROP INDEX idx_bi_establishment,
  DROP INDEX idx_bi_business_status,
  DROP COLUMN establishment_id;

UPDATE business_users
SET role = 'manager'
WHERE role = 'professional';

ALTER TABLE business_users
  MODIFY COLUMN role ENUM('owner','manager') NOT NULL DEFAULT 'manager';
