CREATE TABLE IF NOT EXISTS trade_request_conversations (
    trade_request_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (trade_request_id),
    UNIQUE KEY uq_trade_request_conversations_conversation (conversation_id),
    CONSTRAINT fk_trade_request_conversations_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES conversations (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
