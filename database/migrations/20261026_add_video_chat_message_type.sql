ALTER TABLE messages
    MODIFY COLUMN message_type
        ENUM('text', 'image', 'file', 'voice', 'video', 'system')
        NOT NULL DEFAULT 'text';
