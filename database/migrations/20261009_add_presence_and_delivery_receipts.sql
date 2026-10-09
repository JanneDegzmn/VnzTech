CREATE TABLE IF NOT EXISTS chat_presence_sessions (
    session_token_hash CHAR(64) NOT NULL,
    actor_type ENUM('seller', 'admin') NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    PRIMARY KEY (session_token_hash),
    KEY idx_chat_presence_actor_heartbeat (
        actor_type,
        actor_id,
        heartbeat_at
    ),
    KEY idx_chat_presence_heartbeat (heartbeat_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_deliveries (
    message_id BIGINT UNSIGNED NOT NULL,
    recipient_type ENUM('seller', 'admin') NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, recipient_type, recipient_id),
    KEY idx_message_deliveries_recipient (
        recipient_type,
        recipient_id,
        delivered_at
    ),
    CONSTRAINT fk_message_deliveries_message
        FOREIGN KEY (message_id)
        REFERENCES messages (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
