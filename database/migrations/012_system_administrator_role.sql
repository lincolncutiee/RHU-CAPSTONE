-- Dedicated admin role (not Records Office, IT, or clinical workflow)

INSERT INTO roles (slug, name, description)
VALUES (
    'system_administrator',
    'System Administrator',
    'User management, form templates, branding, and audit — no patient encoding workflow'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

UPDATE users u
JOIN roles r ON r.slug = 'system_administrator'
SET u.role_id = r.id, u.can_access_admin = 1, u.is_active = 1
WHERE u.username = 'admin';
