-- Submission-linked encrypted selfie; no facial descriptors, ID or public photo path.
-- UTC times. Terminal timestamp is latched inside accepted/declined status transactions.
CREATE TABLE IF NOT EXISTS submission_security_faces (
 submission_id INT UNSIGNED NOT NULL PRIMARY KEY,
 file_name CHAR(52) NOT NULL,
 photo_hash CHAR(64) NOT NULL,
 nonce_hash CHAR(64) NOT NULL,
 context VARCHAR(10) NOT NULL,
 user_id INT UNSIGNED NULL,
 policy_version VARCHAR(40) NOT NULL,
 detector_version VARCHAR(40) NOT NULL,
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 terminal_at DATETIME NULL,
 purge_after DATETIME NULL,
 purged_at DATETIME NULL,
 UNIQUE KEY submission_face_nonce (nonce_hash),
 UNIQUE KEY submission_face_file (file_name),
 INDEX submission_face_purge (purged_at,purge_after),
 CONSTRAINT submission_face_submission_fk FOREIGN KEY (submission_id) REFERENCES submissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
