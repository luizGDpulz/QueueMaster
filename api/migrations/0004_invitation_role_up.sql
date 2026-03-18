-- 0004_invitation_role_up.sql
-- Add role column to business_invitations to store intended role for the invitee

USE `queue_master`;

ALTER TABLE business_invitations
  ADD COLUMN role VARCHAR(50) NULL DEFAULT 'professional' AFTER direction;
