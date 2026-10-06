ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(64) NULL,
    ADD COLUMN totp_enabled_at DATETIME NULL,
    ADD COLUMN totp_last_used_counter BIGINT UNSIGNED NULL,
    ADD COLUMN totp_reminder_dismissed TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS seller_totp_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_seller_totp_login_attempts_limit (user_id, ip_hash, attempted_at),
    KEY idx_seller_totp_login_attempts_expiry (attempted_at),
    CONSTRAINT fk_seller_totp_login_attempts_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
