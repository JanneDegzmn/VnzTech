-- Additive V4 provenance. Existing manual approvals/history remain unchanged.
CREATE TABLE IF NOT EXISTS identity_auto_decisions (
 attempt_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 policy_version VARCHAR(48) NOT NULL,
 evidence_hash CHAR(64) NOT NULL,
 consent_version VARCHAR(24) NOT NULL,
 crop_hash CHAR(64) NULL,
 crop_policy VARCHAR(24) NULL,
 local_engine VARCHAR(32) NULL,
 provider_engine VARCHAR(32) NULL,
 local_pass TINYINT NOT NULL DEFAULT 0,
 provider_pass TINYINT NOT NULL DEFAULT 0,
 local_distance DOUBLE NULL,
 local_threshold DOUBLE NULL,
 provider_confidence DOUBLE NULL,
 provider_threshold DOUBLE NULL,
 processing_nonce_hash CHAR(64) NOT NULL,
 lease_expires_at DATETIME NULL,
 outcome_code VARCHAR(40) NOT NULL DEFAULT 'processing',
 completed_at DATETIME NULL,
 CONSTRAINT identity_auto_attempt_fk FOREIGN KEY (attempt_id) REFERENCES identity_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
