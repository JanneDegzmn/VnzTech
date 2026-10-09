CREATE TABLE IF NOT EXISTS chat_call_sessions (
    call_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    caller_type ENUM('seller', 'admin') NOT NULL,
    caller_id INT UNSIGNED NOT NULL,
    callee_type ENUM('seller', 'admin') NOT NULL,
    callee_id INT UNSIGNED NOT NULL,
    call_mode ENUM('audio', 'video') NOT NULL,
    status ENUM(
        'ringing',
        'accepted',
        'connected',
        'completed',
        'missed',
        'declined',
        'cancelled',
        'busy',
        'failed'
    ) NOT NULL,
    invited_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    accepted_at DATETIME(6) NULL,
    caller_connected_at DATETIME(6) NULL,
    callee_connected_at DATETIME(6) NULL,
    connected_at DATETIME(6) NULL,
    ended_at DATETIME(6) NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    summary_message_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (call_id),
    UNIQUE KEY uq_chat_call_summary_message (summary_message_id),
    KEY idx_chat_call_conversation (conversation_id, invited_at),
    CONSTRAINT fk_chat_call_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES conversations (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_chat_call_summary_message
        FOREIGN KEY (summary_message_id)
        REFERENCES messages (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
