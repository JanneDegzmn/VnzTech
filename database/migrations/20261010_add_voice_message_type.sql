ALTER TABLE messages
    MODIFY COLUMN message_type
        ENUM('text', 'image', 'file', 'voice', 'system')
        NOT NULL DEFAULT 'text';
