-- 0004_professional_establishments_invitations_down.sql
-- Rollback: Remove professional_establishments and business_invitations tables

USE `queue_master`;

DROP TABLE IF EXISTS business_invitations;
DROP TABLE IF EXISTS professional_establishments;
