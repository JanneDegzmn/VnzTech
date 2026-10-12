ALTER TABLE conversations
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER transaction_id,
    ADD UNIQUE KEY uq_conversations_submission (submission_id),
    ADD CONSTRAINT fk_conversations_submission
        FOREIGN KEY (submission_id)
        REFERENCES submissions (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE conversation_participants
    MODIFY actor_type ENUM('seller', 'admin', 'guest') NOT NULL;

ALTER TABLE messages
    MODIFY sender_type ENUM('seller', 'admin', 'guest') NOT NULL;

ALTER TABLE message_reactions
    MODIFY actor_type ENUM('seller', 'admin', 'guest') NOT NULL;

ALTER TABLE message_deliveries
    MODIFY recipient_type ENUM('seller', 'admin', 'guest') NOT NULL;

ALTER TABLE chat_presence_sessions
    MODIFY actor_type ENUM('seller', 'admin', 'guest') NOT NULL;

CREATE TABLE guest_chat_identities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guest_chat_submission (submission_id),
    UNIQUE KEY uq_guest_chat_token_hash (token_hash),
    CONSTRAINT fk_guest_chat_submission
        FOREIGN KEY (submission_id)
        REFERENCES submissions (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
