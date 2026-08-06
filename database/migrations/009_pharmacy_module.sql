-- Pharmacy module: role, demo user, status enum expansion
-- Applied automatically by Pharmacy::ensureSchema(); this file is for reference / manual runs.
-- Demo login: pharmacy / password

INSERT INTO roles (slug, name, description)
SELECT 'pharmacy', 'Pharmacy', 'Dispenses medicines from doctor prescriptions'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'pharmacy');

INSERT INTO users (role_id, username, password_hash, full_name, email)
SELECT r.id, 'pharmacy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RHU Pharmacist', 'pharmacy@rhu.local'
FROM roles r
WHERE r.slug = 'pharmacy'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'pharmacy');
