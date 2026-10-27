-- Modul SEO Manager dan Analytics (idempotent).
CREATE TABLE IF NOT EXISTS `visits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visit_date` DATE NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `page_title` VARCHAR(255) NOT NULL DEFAULT '',
    `visitor_ip` VARCHAR(45) NOT NULL DEFAULT '',
    `visitor_hash` CHAR(64) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_visits_date` (`visit_date`),
    KEY `idx_visits_path_date` (`path`, `visit_date`),
    KEY `idx_visits_hash_date` (`visitor_hash`, `visit_date`),
    KEY `idx_visits_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('seo_meta_title', ''),
    ('seo_meta_description', ''),
    ('seo_og_image', ''),
    ('seo_site_url', '');
