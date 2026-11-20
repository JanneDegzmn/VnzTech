CREATE TABLE IF NOT EXISTS site_announcements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'draft',
 expires_at DATETIME NULL,
 published_at DATETIME NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by INT UNSIGNED NOT NULL,
 updated_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX site_announcements_visible (status, published_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS site_maintenance (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 version INT UNSIGNED NOT NULL DEFAULT 0,
 state_json TEXT NOT NULL,
 updated_by INT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO site_maintenance (id, version, state_json) VALUES (1, 0, '{"version":0,"active":false,"reason":"","started_at":null,"duration_minutes":0,"estimated_end_at":null,"updated_by":null}');
INSERT INTO permissions (name, description)
SELECT 'manage_announcements','Publish and archive site-wide announcements'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_announcements');
INSERT INTO permissions (name, description)
SELECT 'manage_maintenance','Enable, update and disable site maintenance'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_maintenance');
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Super Admin' AND r.is_active=1 AND p.name IN ('manage_announcements','manage_maintenance')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
