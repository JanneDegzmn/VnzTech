CREATE TABLE remember_login_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_type ENUM('admin', 'seller') NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_login_selector (selector),
    KEY idx_remember_login_actor (actor_type, actor_id),
    KEY idx_remember_login_expiry (expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
