<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../common.php';

$columnDefinitions = [
    'meta_title' => 'VARCHAR(255) NULL',
    'meta_description' => 'TEXT NULL',
    'meta_keywords' => 'TEXT NULL',
    'og_image' => 'VARCHAR(500) NULL'
];

try {
    $pdo = pbDbConnection();
    $existingColumns = $pdo->query('SHOW COLUMNS FROM pages')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columnDefinitions as $column => $definition) {
        if (in_array($column, $existingColumns, true)) {
            fwrite(STDOUT, "Kolom {$column} sudah ada.\n");
            continue;
        }
        // Names and definitions come from this fixed allowlist, not from user input.
        $pdo->exec("ALTER TABLE pages ADD COLUMN `{$column}` {$definition}");
        fwrite(STDOUT, "Kolom {$column} berhasil ditambahkan.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Migrasi SEO gagal: " . $exception->getMessage() . "\n");
    exit(1);
}
