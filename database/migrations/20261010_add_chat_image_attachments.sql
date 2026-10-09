ALTER TABLE messages
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER content;
