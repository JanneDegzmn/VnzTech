CREATE TABLE IF NOT EXISTS seller_notification_preferences (
    user_id INT UNSIGNED NOT NULL,
    submission_updates TINYINT(1) NOT NULL DEFAULT 0,
    offer_updates TINYINT(1) NOT NULL DEFAULT 0,
    transaction_updates TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
