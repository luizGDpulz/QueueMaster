-- 0004_invitation_role_down.sql
-- Remove role column from business_invitations

USE `queue_master`;

ALTER TABLE business_invitations
  DROP COLUMN role;
