ALTER TABLE admins
    ADD COLUMN totp_login_required TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN totp_reminder_dismissed TINYINT(1) NOT NULL DEFAULT 0;
