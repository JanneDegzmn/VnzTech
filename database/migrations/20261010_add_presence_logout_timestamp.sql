ALTER TABLE chat_presence_sessions
    ADD COLUMN ended_at DATETIME NULL AFTER heartbeat_at;
