CREATE TABLE IF NOT EXISTS admin_password_reset_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_password_reset_attempts_lookup (
        login_hash,
        ip_address,
        attempted_at
    ),
    KEY idx_admin_password_reset_attempts_expiry (attempted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
