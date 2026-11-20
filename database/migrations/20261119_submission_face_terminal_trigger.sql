-- Exact terminal transition time; notification/seen updates must not extend retention.
CREATE TRIGGER vnz_submission_face_terminal_v6
AFTER UPDATE ON submissions
FOR EACH ROW
UPDATE submission_security_faces
SET terminal_at = UTC_TIMESTAMP(),
    purge_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)
WHERE submission_id = NEW.id
  AND terminal_at IS NULL
  AND NEW.status IN ('accepted', 'declined')
  AND NOT (OLD.status <=> NEW.status);
