-- Administration module: can_access_admin flag (separate from IT Department role), privileges, templates, audit

ALTER TABLE users
    ADD COLUMN can_access_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

CREATE TABLE IF NOT EXISTS user_field_privileges (
    user_id       INT          NOT NULL,
    field_group   VARCHAR(64)  NOT NULL,
    access_level  ENUM('inherit','none','view','edit') NOT NULL DEFAULT 'inherit',
    PRIMARY KEY (user_id, field_group),
    CONSTRAINT fk_ufp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS form_template_strings (
    template_key  VARCHAR(190) NOT NULL PRIMARY KEY,
    default_text  TEXT         NOT NULL,
    custom_text   TEXT         DEFAULT NULL,
    updated_at    DATETIME     DEFAULT NULL,
    updated_by    INT          DEFAULT NULL,
    CONSTRAINT fk_fts_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_branding (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    logo_path     VARCHAR(255) NOT NULL DEFAULT '/assets/img/rhu-magallanes-logo.png',
    site_title    VARCHAR(150) NOT NULL DEFAULT 'RHU Magallanes',
    site_subtitle VARCHAR(150) NOT NULL DEFAULT 'Agusan del Norte',
    facility_name VARCHAR(200) NOT NULL DEFAULT 'Rural Health Unit — Magallanes',
    updated_at    DATETIME     DEFAULT NULL,
    updated_by    INT          DEFAULT NULL,
    CONSTRAINT fk_branding_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO system_branding (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT          NOT NULL,
    action        VARCHAR(80)  NOT NULL,
    target_type   VARCHAR(40)  DEFAULT NULL,
    target_id     VARCHAR(64)  DEFAULT NULL,
    details       JSON         DEFAULT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB;

-- IT demo account is workflow-only; admin access uses a separate account (see AdminInstall::ensureDemoAdminAccount).
UPDATE users u
JOIN roles r ON r.id = u.role_id
SET u.can_access_admin = 0
WHERE r.slug = 'it_department' AND u.username = 'it';
