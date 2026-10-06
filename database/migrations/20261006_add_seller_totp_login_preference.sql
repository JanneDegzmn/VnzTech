ALTER TABLE users
    ADD COLUMN totp_login_required TINYINT(1) NOT NULL DEFAULT 0;
