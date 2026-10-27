-- Pengaturan template aktif (idempotent).
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('active_theme', 'default');
