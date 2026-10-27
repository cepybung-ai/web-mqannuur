CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `page_slug` VARCHAR(100) NOT NULL,
    `form_data` JSON NOT NULL,
    `sender_ip` VARCHAR(50) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_form_submissions_page_created` (`page_slug`, `created_at`),
    KEY `idx_form_submissions_ip_created` (`sender_ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
