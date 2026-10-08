ALTER TABLE messages
    ADD COLUMN reply_to_message_id BIGINT UNSIGNED NULL AFTER content,
    ADD COLUMN edited_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER edited_at,
    ADD KEY idx_messages_reply (reply_to_message_id);

ALTER TABLE messages
    ADD CONSTRAINT fk_messages_reply_to
        FOREIGN KEY (reply_to_message_id)
        REFERENCES messages (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS message_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    actor_type ENUM('seller', 'admin') NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    reaction VARCHAR(8) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_reactions_actor_reaction (
        message_id,
        actor_type,
        actor_id,
        reaction
    ),
    KEY idx_message_reactions_message (
        message_id,
        reaction
    ),
    CONSTRAINT fk_message_reactions_message
        FOREIGN KEY (message_id)
        REFERENCES messages (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
