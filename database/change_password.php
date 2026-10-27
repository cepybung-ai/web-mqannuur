<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../common.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? '';

if ($password === '') {
    fwrite(STDERR, "Pemakaian: php database/change_password.php <username> <password-baru>\n");
    exit(1);
}

try {
    $pdo = pbDbConnection();
    $statement = $pdo->prepare('UPDATE users SET password = ? WHERE username = ?');
    $statement->execute([password_hash($password, PASSWORD_DEFAULT), $username]);
    if ($statement->rowCount() === 0) {
        fwrite(STDERR, "User {$username} tidak ditemukan.\n");
        exit(1);
    }
    fwrite(STDOUT, "Password {$username} berhasil diperbarui.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Gagal memperbarui password: ' . $exception->getMessage() . "\n");
    exit(1);
}
