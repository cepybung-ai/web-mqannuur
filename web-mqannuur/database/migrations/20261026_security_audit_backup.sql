-- Audit log, proteksi brute-force login, dan backup terjadwal (idempotent).
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT NULL,
    `username` VARCHAR(50) NOT NULL DEFAULT '',
    `role` VARCHAR(30) NOT NULL DEFAULT '',
    `action` VARCHAR(50) NOT NULL DEFAULT '',
    `entity` VARCHAR(50) NOT NULL DEFAULT '',
    `entity_id` VARCHAR(64) NOT NULL DEFAULT '',
    `details` TEXT NULL,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_created` (`created_at`),
    KEY `idx_activity_logs_user_created` (`username`, `created_at`),
    KEY `idx_activity_logs_action_created` (`action`, `created_at`),
    KEY `idx_activity_logs_entity_created` (`entity`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `username` VARCHAR(50) NOT NULL DEFAULT '',
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_ip_time` (`ip`, `attempted_at`),
    KEY `idx_login_attempts_username_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('backup_schedule', 'disabled'),
    ('backup_retention', '10'),
    ('backup_token', ''),
    ('backup_last_run', ''),
    ('backup_last_status', ''),
    ('backup_last_file', '');
