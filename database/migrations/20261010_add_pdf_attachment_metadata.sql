ALTER TABLE messages
    ADD COLUMN attachment_name VARCHAR(190) NULL AFTER attachment_path,
    ADD COLUMN attachment_size BIGINT UNSIGNED NULL AFTER attachment_name;
