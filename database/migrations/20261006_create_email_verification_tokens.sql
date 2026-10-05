CREATE TABLE IF NOT EXISTS email_verification_tokens (
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NULL,
    expires_at DATETIME NULL,
    last_requested_at DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_email_verification_token_hash (token_hash),
    CONSTRAINT fk_email_verification_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
