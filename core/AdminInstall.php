<?php

/**
 * Ensures administration module tables/columns exist (idempotent).
 */
class AdminInstall
{
    private static bool $done = false;

    public static function hasCanAccessAdminColumn(mysqli $conn): bool
    {
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'can_access_admin'");
        if (!$col) {
            return false;
        }
        $ok = $col->num_rows > 0;
        $col->free();
        return $ok;
    }

    public static function ensureSchema(mysqli $conn): void
    {
        if (self::$done && self::hasCanAccessAdminColumn($conn)) {
            return;
        }

        if (!self::hasCanAccessAdminColumn($conn)) {
            @$conn->query(
                'ALTER TABLE users ADD COLUMN can_access_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active'
            );
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS user_field_privileges (
                user_id INT NOT NULL,
                field_group VARCHAR(64) NOT NULL,
                access_level ENUM('inherit','none','view','edit') NOT NULL DEFAULT 'inherit',
                PRIMARY KEY (user_id, field_group),
                CONSTRAINT fk_ufp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS form_template_strings (
                template_key VARCHAR(190) NOT NULL PRIMARY KEY,
                default_text TEXT NOT NULL,
                custom_text TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                CONSTRAINT fk_fts_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS system_branding (
                id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
                logo_path VARCHAR(255) NOT NULL DEFAULT '/assets/img/rhu-magallanes-logo.png',
                site_title VARCHAR(150) NOT NULL DEFAULT 'RHU Magallanes',
                site_subtitle VARCHAR(150) NOT NULL DEFAULT 'Agusan del Norte',
                facility_name VARCHAR(200) NOT NULL DEFAULT 'Rural Health Unit — Magallanes',
                updated_at DATETIME DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                CONSTRAINT fk_branding_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $conn->query('INSERT IGNORE INTO system_branding (id) VALUES (1)');

        $conn->query(
            "CREATE TABLE IF NOT EXISTS access_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                event ENUM('login','failed','logout') NOT NULL,
                user_id INT DEFAULT NULL,
                username VARCHAR(80) NOT NULL DEFAULT '',
                role_slug VARCHAR(64) DEFAULT NULL,
                role_name VARCHAR(80) DEFAULT NULL,
                reason VARCHAR(40) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_access_created (created_at),
                INDEX idx_access_event (event),
                INDEX idx_access_role (role_slug),
                CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                action VARCHAR(80) NOT NULL,
                target_type VARCHAR(40) DEFAULT NULL,
                target_id VARCHAR(64) DEFAULT NULL,
                details JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
                INDEX idx_audit_created (created_at),
                INDEX idx_audit_action (action)
            ) ENGINE=InnoDB"
        );

        if (self::hasCanAccessAdminColumn($conn)) {
            self::ensureSystemAdministratorRole($conn);
            self::ensureDemoAdminAccount($conn);
        }

        self::$done = true;

        require_once __DIR__ . '/RolePermissions.php';
        RolePermissions::ensureSchema($conn);
    }

    /** Ensures roles row for dedicated admin accounts (not Records / IT / clinical). */
    private static function ensureSystemAdministratorRole(mysqli $conn): ?int
    {
        $conn->query(
            "INSERT INTO roles (slug, name, description)
             VALUES ('system_administrator', 'System Administrator', 'User management, form templates, branding, and audit — no patient encoding workflow')
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)"
        );
        $res = $conn->query("SELECT id FROM roles WHERE slug = 'system_administrator' LIMIT 1");
        if (!$res || !($row = $res->fetch_assoc())) {
            if ($res) {
                $res->free();
            }
            return null;
        }
        $id = (int) $row['id'];
        $res->free();
        return $id;
    }

    /**
     * Demo admin account — separate from IT workflow (encoding / print / validation).
     */
    private static function ensureDemoAdminAccount(mysqli $conn): void
    {
        $conn->query(
            "UPDATE users u
             JOIN roles r ON r.id = u.role_id
             SET u.can_access_admin = 0
             WHERE r.slug = 'it_department' AND u.username = 'it'"
        );

        $check = $conn->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        if (!$check) {
            return;
        }
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
        $roleId = self::ensureSystemAdministratorRole($conn);
        if ($roleId === null) {
            return;
        }

        if ($exists) {
            $stmt = $conn->prepare(
                'UPDATE users SET role_id = ?, can_access_admin = 1, is_active = 1 WHERE username = ? LIMIT 1'
            );
            if ($stmt) {
                $uname = 'admin';
                $stmt->bind_param('is', $roleId, $uname);
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $stmt = $conn->prepare(
            'INSERT INTO users (role_id, username, password_hash, full_name, email, can_access_admin, is_active)
             VALUES (?, ?, ?, ?, ?, 1, 1)'
        );
        if (!$stmt) {
            return;
        }
        $username = 'admin';
        $fullName = 'System Administrator';
        $email = 'admin@rhu.local';
        $stmt->bind_param('issss', $roleId, $username, $hash, $fullName, $email);
        $stmt->execute();
        $stmt->close();
    }
}
