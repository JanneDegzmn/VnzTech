ALTER TABLE guest_submission_claims
    MODIFY proof_method
        ENUM('guest_chat_capability', 'verified_email') NOT NULL;
