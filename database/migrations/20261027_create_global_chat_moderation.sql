CREATE TABLE IF NOT EXISTS global_chat_moderation_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_text VARCHAR(191) NOT NULL,
    match_type ENUM('word', 'phrase') NOT NULL,
    category VARCHAR(80) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    action ENUM('warn', 'block', 'review') NOT NULL DEFAULT 'review',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by_admin_id INT UNSIGNED NULL,
    updated_by_admin_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_global_chat_moderation_rule_text (rule_text),
    KEY idx_global_chat_moderation_rules_enabled (enabled, category),
    CONSTRAINT fk_global_chat_moderation_rule_created_by
        FOREIGN KEY (created_by_admin_id)
        REFERENCES admins (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_global_chat_moderation_rule_updated_by
        FOREIGN KEY (updated_by_admin_id)
        REFERENCES admins (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS global_chat_moderation_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NULL,
    matched_rule VARCHAR(191) NOT NULL,
    category VARCHAR(80) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    action ENUM('warn', 'block', 'review') NOT NULL,
    status ENUM('pending', 'reviewed', 'dismissed', 'restricted') NOT NULL DEFAULT 'pending',
    message_snapshot TEXT NOT NULL,
    sender_type ENUM('seller', 'admin', 'guest') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    reviewer_admin_id INT UNSIGNED NULL,
    decision_reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_global_chat_moderation_cases_status_created (status, created_at),
    KEY idx_global_chat_moderation_cases_message (message_id),
    CONSTRAINT fk_global_chat_moderation_case_message
        FOREIGN KEY (message_id)
        REFERENCES messages (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_global_chat_moderation_case_rule
        FOREIGN KEY (rule_id)
        REFERENCES global_chat_moderation_rules (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_global_chat_moderation_case_reviewer
        FOREIGN KEY (reviewer_admin_id)
        REFERENCES admins (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO global_chat_moderation_rules
    (rule_text, match_type, category, severity, action, enabled)
VALUES
    ('putangina', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('putang ina', 'phrase', 'Profanity and harassment', 'high', 'block', 1),
    ('tangina', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('tang ina', 'phrase', 'Profanity and harassment', 'high', 'block', 1),
    ('gago', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('bobo', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('tanga', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('ulol', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('fuck', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('fuckyou', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('fucking', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('shit', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('asshole', 'word', 'Profanity and harassment', 'high', 'block', 1),
    ('bastard', 'word', 'Profanity and harassment', 'medium', 'block', 1),
    ('scammer', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('estafa', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('magnanakaw', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('manloloko', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('sinungaling', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('fraud', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('modus', 'word', 'Potential business accusations', 'medium', 'review', 1),
    ('wag kayong bumili', 'phrase', 'Potential business accusations', 'medium', 'review', 1),
    ('huwag kayong bumili', 'phrase', 'Potential business accusations', 'medium', 'review', 1),
    ('scam sila', 'phrase', 'Potential business accusations', 'medium', 'review', 1),
    ('scam ang vnz tech', 'phrase', 'Potential business accusations', 'medium', 'review', 1),
    ('send otp', 'phrase', 'Credential theft and suspicious requests', 'high', 'review', 1),
    ('bigay mo password', 'phrase', 'Credential theft and suspicious requests', 'critical', 'block', 1),
    ('send your password', 'phrase', 'Credential theft and suspicious requests', 'critical', 'block', 1),
    ('send gcash pin', 'phrase', 'Credential theft and suspicious requests', 'critical', 'block', 1),
    ('send bank pin', 'phrase', 'Credential theft and suspicious requests', 'critical', 'block', 1),
    ('verify your account here', 'phrase', 'Credential theft and suspicious requests', 'high', 'review', 1),
    ('pm mo ako sa ibang page', 'phrase', 'Spam and customer diversion', 'medium', 'review', 1),
    ('contact this other buyer', 'phrase', 'Spam and customer diversion', 'medium', 'review', 1),
    ('sell it to my shop instead', 'phrase', 'Spam and customer diversion', 'medium', 'review', 1);
