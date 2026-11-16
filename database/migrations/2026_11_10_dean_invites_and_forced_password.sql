-- ICOTAS upgrade: Dean invite registration and forced password change
-- Target: MySQL 5.7+/MariaDB 10.3+ (the schema already requires InnoDB + utf8mb4).
--
-- Run this once against an existing installation BEFORE deploying the matching
-- application files. Back up the database first. Fresh installations that
-- import database/schema.sql do not need this file.
--
-- This script is intentionally not idempotent for the ALTER statement: MySQL
-- versions differ on ADD COLUMN IF NOT EXISTS support, so re-running it should
-- fail loudly instead of silently leaving a half-upgraded schema.

ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at;

CREATE TABLE IF NOT EXISTS registration_invites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  code_hint VARCHAR(24) NOT NULL,
  note VARCHAR(190) NULL,
  max_uses INT UNSIGNED NOT NULL DEFAULT 1,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_registration_invites_hash (code_hash),
  KEY idx_registration_invites_role (role_id),
  KEY idx_registration_invites_expiry (expires_at),
  CONSTRAINT fk_registration_invites_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_registration_invites_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES
  ('allow_dean_signup', '1', 'evaluation')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- Optional: manually re-enable forced change for existing temporary accounts.
-- UPDATE users SET must_change_password = 1 WHERE id IN (...);
