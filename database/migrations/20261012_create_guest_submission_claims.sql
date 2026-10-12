CREATE TABLE IF NOT EXISTS guest_submission_claims (
    submission_id BIGINT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    guest_identity_id INT UNSIGNED NOT NULL,
    proof_method ENUM('guest_chat_capability') NOT NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (submission_id),
    KEY idx_guest_submission_claims_seller (seller_id, claimed_at),
    CONSTRAINT fk_guest_submission_claims_submission
        FOREIGN KEY (submission_id)
        REFERENCES submissions (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_guest_submission_claims_seller
        FOREIGN KEY (seller_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_guest_submission_claims_identity
        FOREIGN KEY (guest_identity_id)
        REFERENCES guest_chat_identities (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
