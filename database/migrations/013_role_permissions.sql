-- Configurable role permissions (Admin → Roles & permissions)

CREATE TABLE IF NOT EXISTS role_permissions (
    role_slug       VARCHAR(64)  NOT NULL,
    permission_key  VARCHAR(80)  NOT NULL,
    allowed         TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at      DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_slug, permission_key),
    INDEX idx_rp_role (role_slug)
) ENGINE=InnoDB;
