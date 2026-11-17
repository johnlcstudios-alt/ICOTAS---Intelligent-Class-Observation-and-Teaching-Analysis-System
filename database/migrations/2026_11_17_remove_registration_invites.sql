-- ICOTAS upgrade: remove invitation-based Dean registration
-- Target: MySQL 5.7+/MariaDB 10.3+
--
-- Run this once against an existing installation BEFORE deploying the matching
-- application files. Back up the database first. Fresh installations that
-- import database/schema.sql do not need this file.
--
-- Administrator (Dean / Academic Head) accounts are now created directly by an
-- authorized administrator. No invitation codes are issued, stored, or
-- redeemed. The developer console remains unlinked and key-protected.

DROP TABLE IF EXISTS registration_invites;

DELETE FROM system_settings WHERE setting_key = 'allow_dean_signup';
