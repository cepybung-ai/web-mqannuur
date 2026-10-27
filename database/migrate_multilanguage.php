<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../common.php';

$columns = [
    'lang' => "VARCHAR(10) NOT NULL DEFAULT 'id'",
    'translation_group' => 'VARCHAR(100) NULL'
];

try {
    $pdo = pbDbConnection();
    $existingColumns = $pdo->query('SHOW COLUMNS FROM pages')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $definition) {
        if (in_array($name, $existingColumns, true)) {
            fwrite(STDOUT, "Kolom {$name} sudah ada.\n");
            continue;
        }
        $pdo->exec("ALTER TABLE pages ADD COLUMN `{$name}` {$definition}");
        fwrite(STDOUT, "Kolom {$name} berhasil ditambahkan.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migrasi multi-bahasa gagal: ' . $exception->getMessage() . "\n");
    exit(1);
}
