CREATE TABLE IF NOT EXISTS seller_password_reset_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    token_hash CHAR(64) NULL,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seller_password_reset_token_hash (token_hash),
    KEY idx_seller_password_reset_rate_limit (
        email_hash,
        ip_hash,
        attempted_at
    ),
    KEY idx_seller_password_reset_expiry (expires_at),
    KEY idx_seller_password_reset_attempted_at (attempted_at),
    CONSTRAINT fk_seller_password_reset_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
