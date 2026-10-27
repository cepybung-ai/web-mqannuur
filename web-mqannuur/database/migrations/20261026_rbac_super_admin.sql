-- RBAC: normalisasi peran lama dan terapkan peran super_admin/editor (idempotent).
UPDATE `users`
SET `role` = 'super_admin'
WHERE LOWER(`role`) IN ('admin', 'operator', 'super_admin');

UPDATE `users`
SET `role` = 'editor'
WHERE LOWER(`role`) NOT IN ('super_admin', 'editor');

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin', 'editor') NOT NULL DEFAULT 'editor';
