-- Basis data dan tabel awal untuk Web MQ An-Nuur Page Builder.
CREATE DATABASE IF NOT EXISTS `web_mqannuur`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `web_mqannuur`;

CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content_json` LONGTEXT NOT NULL,
    `content_css` LONGTEXT NOT NULL,
    `content_html` LONGTEXT NOT NULL,
    `meta_title` VARCHAR(255) NULL,
    `meta_description` TEXT NULL,
    `meta_keywords` TEXT NULL,
    `og_image` VARCHAR(500) NULL,
    `lang` VARCHAR(10) NOT NULL DEFAULT 'id',
    `translation_group` VARCHAR(100) NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_revisions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `page_slug` VARCHAR(100) NOT NULL,
    `content_json` LONGTEXT NOT NULL,
    `content_css` LONGTEXT NULL,
    `content_html` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_revisions_slug` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `templates` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `content_json` LONGTEXT NOT NULL,
    `content_css` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `page_slug` VARCHAR(100) NOT NULL,
    `form_data` JSON NOT NULL,
    `sender_ip` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_form_submissions_ip_created` (`sender_ip`, `created_at`),
    KEY `idx_form_submissions_page_created` (`page_slug`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `attempt_key` CHAR(64) NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `locked_until` DATETIME NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_key`),
    KEY `idx_login_attempts_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jalankan database/seed_admin.php melalui PHP CLI sekali untuk membuat akun admin awal.

CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL,
    `excerpt` TEXT NULL,
    `featured_image` VARCHAR(500) NULL,
    `category` VARCHAR(100) DEFAULT 'Umum',
    `status` ENUM('draft', 'published') DEFAULT 'published',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_posts_slug` (`slug`),
    KEY `idx_posts_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `posts` (`title`, `slug`, `content`, `excerpt`, `featured_image`, `category`, `status`) VALUES
('Membangun Kebiasaan Membaca Al-Qur’an', 'membangun-kebiasaan-membaca-al-quran', '<p>Membaca Al-Qur’an secara rutin dapat dimulai dengan waktu yang singkat dan jadwal yang konsisten. Pilih waktu yang nyaman, siapkan mushaf, lalu jaga kebiasaan itu setiap hari.</p><h2>Mulai dari target kecil</h2><p>Target yang realistis membantu kita terus melangkah. Tambahkan durasi atau jumlah bacaan secara bertahap ketika kebiasaan sudah terbentuk.</p>', 'Langkah sederhana untuk membangun kebiasaan membaca Al-Qur’an secara rutin dan konsisten.', 'https://placehold.co/900x560/e8f4ef/176b45?text=Al-Quran', 'Pendidikan', 'published'),
('Belajar Bersama dalam Lingkungan yang Mendukung', 'belajar-bersama-lingkungan-mendukung', '<p>Lingkungan belajar yang baik memberi ruang untuk bertanya, mencoba, dan bertumbuh. Dukungan guru dan teman membantu proses belajar terasa lebih terarah.</p><h2>Kolaborasi dalam belajar</h2><p>Diskusi dan saling berbagi pemahaman membuat peserta didik belajar menghargai sudut pandang yang berbeda serta menguatkan materi yang dipelajari.</p>', 'Peran lingkungan, guru, dan teman dalam mendukung proses belajar yang positif.', 'https://placehold.co/900x560/dbeafe/1d4ed8?text=Belajar', 'Kegiatan', 'published'),
('Menanamkan Akhlak Mulia dalam Keseharian', 'menanamkan-akhlak-mulia-keseharian', '<p>Akhlak mulia tumbuh melalui latihan dalam keseharian: berkata jujur, menghormati orang lain, dan bertanggung jawab atas amanah.</p><h2>Teladan dan kebiasaan</h2><p>Teladan yang konsisten dan kebiasaan kecil yang dilakukan bersama dapat membantu nilai-nilai baik menjadi bagian dari kehidupan sehari-hari.</p>', 'Kebiasaan sederhana yang membantu menumbuhkan akhlak mulia dalam kehidupan sehari-hari.', 'https://placehold.co/900x560/fef3c7/92400e?text=Akhlak', 'Karakter', 'published');
