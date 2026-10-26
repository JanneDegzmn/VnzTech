INSERT IGNORE INTO global_chat_moderation_rules
    (rule_text, match_type, category, severity, action, enabled)
VALUES
    ('fuckyou', 'word', 'Profanity and harassment', 'high', 'block', 1);
