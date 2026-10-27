<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/common.php';

$pdo = getDbConnection();
$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    fwrite(STDOUT, "Seeder dilewati: tabel users sudah berisi akun.\n");
    exit(0);
}

$statement = $pdo->prepare(
    'INSERT INTO users (username, password, name, role) VALUES (:username, :password, :name, :role)'
);
$statement->execute([
    'username' => 'admin',
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'name' => 'Super Admin',
    'role' => 'super_admin',
]);
fwrite(STDOUT, "Akun awal dibuat: username admin. Segera ganti password admin123 setelah login.\n");
