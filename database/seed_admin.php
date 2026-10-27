<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../common.php';

try {
    $pdo = pbDbConnection();
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount > 0) {
        fwrite(STDOUT, "Tabel users sudah berisi akun. Seed admin dilewati.\n");
        exit(0);
    }

    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $statement = $pdo->prepare(
        'INSERT INTO users (username, password, name) VALUES (:username, :password, :name)'
    );
    $statement->execute([
        'username' => 'admin',
        'password' => $passwordHash,
        'name' => 'Administrator'
    ]);
    fwrite(STDOUT, "Akun admin dibuat. Username: admin | Password awal: admin123\nGanti password awal setelah login pertama.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Seed admin gagal: " . $exception->getMessage() . "\n");
    exit(1);
}
