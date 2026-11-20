-- Additive identity workflow. UTC timestamps; no raw ID/face descriptors in database.
CREATE TABLE IF NOT EXISTS identity_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 context VARCHAR(10) NOT NULL,
 user_id INT UNSIGNED NULL,
 draft_id CHAR(48) NOT NULL,
 capability_hash CHAR(64) NULL,
 email VARCHAR(190) NOT NULL,
 status VARCHAR(24) NOT NULL,
 id_file VARCHAR(52) NOT NULL,
 selfie_file VARCHAR(52) NOT NULL,
 evidence_hash CHAR(64) NOT NULL,
 rate_limit_hash CHAR(64) NULL,
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 expires_at DATETIME NULL,
 grant_expires_at DATETIME NULL,
 submission_id INT UNSIGNED NULL,
 reserved_user_id INT UNSIGNED NULL,
 reservation_expires_at DATETIME NULL,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_reason VARCHAR(500) NULL,
 purge_after DATETIME NULL,
 purged_at DATETIME NULL,
 INDEX identity_draft (draft_id),
 INDEX identity_user (user_id),
 INDEX identity_status_expiry (status,expires_at),
 INDEX identity_rate (rate_limit_hash,created_at),
 INDEX identity_purge (purged_at,purge_after),
 UNIQUE KEY identity_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_bindings (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 attempt_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY identity_bound_attempt (attempt_id),
 CONSTRAINT identity_binding_attempt FOREIGN KEY (attempt_id) REFERENCES identity_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_audit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 attempt_id BIGINT UNSIGNED NOT NULL,
 event VARCHAR(40) NOT NULL,
 admin_id INT UNSIGNED NULL,
 user_id INT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 INDEX identity_audit_attempt (attempt_id),
 CONSTRAINT identity_audit_attempt_fk FOREIGN KEY (attempt_id) REFERENCES identity_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO permissions (name,description)
SELECT 'identity_review','Review private ID/selfie evidence and approve, reject or revoke identity verification'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='identity_review');

INSERT INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Super Admin' AND r.is_active=1 AND p.name='identity_review'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
