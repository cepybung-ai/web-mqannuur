-- Akun Super Admin awal untuk instalasi hosting tanpa akses CLI.
-- Password awal: admin123 — wajib diganti setelah login pertama.
INSERT IGNORE INTO `users` (`username`, `password`, `name`, `role`) VALUES
('admin', '$2y$12$YhuV5bZ2VC9CIVrYZBzI.u/JV6Po0JzER1jlYND9UUt4QzgFBSv7u', 'Super Admin', 'super_admin');
