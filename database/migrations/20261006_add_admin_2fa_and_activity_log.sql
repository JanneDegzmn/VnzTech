ALTER TABLE admins
    ADD COLUMN totp_secret VARCHAR(64) NULL,
    ADD COLUMN totp_enabled_at DATETIME NULL,
    ADD COLUMN totp_last_used_counter BIGINT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_activity_logs_created_at (created_at),
    KEY idx_admin_activity_logs_admin_created (admin_id, created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_totp_recovery_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_totp_recovery_code_hash (code_hash),
    KEY idx_admin_totp_recovery_codes_admin (admin_id, used_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
