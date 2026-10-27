<?php
declare(strict_types=1);

/** Export current database tables and upload media as a downloadable ZIP. */
function pbExportFullSite(): void
{
    if (!class_exists(ZipArchive::class)) {
        pbJsonResponse(['success' => false, 'message' => 'PHP ZipExtension (ZipArchive) belum aktif di server.'], 503);
    }
    $pdo = pbDbConnection();
    $tableNames = ['pages', 'templates', 'posts', 'form_submissions', 'users'];
    $backup = ['format' => 'web-mqannuur-full-backup', 'version' => 1, 'created_at' => date(DATE_ATOM), 'tables' => []];
    foreach ($tableNames as $tableName) {
        $backup['tables'][$tableName] = $pdo->query('SELECT * FROM ' . $tableName)->fetchAll();
    }
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $zipPath = tempnam(sys_get_temp_dir(), 'mq-site-backup-');
    if ($zipPath === false) throw new RuntimeException('File sementara backup tidak dapat dibuat.');
    try {
        $archive = new ZipArchive();
        if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip ZIP tidak dapat dibuat.');
        }
        if (!$archive->addFromString('data.json', $json)) {
            $archive->close();
            throw new RuntimeException('Data database tidak dapat dimasukkan ke arsip.');
        }
        $directory = pbUploadsDirectory();
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        foreach (glob($directory . '/*') ?: [] as $file) {
            if (!is_file($file) || is_link($file)) continue;
            if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowed, true)) continue;
            if (!$archive->addFile($file, 'uploads/' . basename($file))) {
                $archive->close();
                throw new RuntimeException('File media gagal dimasukkan ke arsip.');
            }
        }
        if (!$archive->close()) throw new RuntimeException('Arsip ZIP tidak dapat diselesaikan.');
        $filename = 'full-backup-site-' . date('Y-m-d-His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)filesize($zipPath));
        header('X-Content-Type-Options: nosniff');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    } catch (Throwable $exception) {
        if (isset($archive) && $archive instanceof ZipArchive) {
            @$archive->close();
        }
        @unlink($zipPath);
        throw $exception;
    }
}

/** Validate a backup archive and replace the five application tables and media files. */
function pbRestoreFullSite(array $upload): array
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipExtension (ZipArchive) belum aktif di server.');
    $uploadPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
    $uploadName = is_string($upload['name'] ?? null) ? $upload['name'] : '';
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    $uploadSize = (int)($upload['size'] ?? 0);
    if ($uploadError !== UPLOAD_ERR_OK || strtolower(pathinfo($uploadName, PATHINFO_EXTENSION)) !== 'zip'
        || $uploadSize <= 0 || $uploadSize > 100 * 1024 * 1024 || !is_uploaded_file($uploadPath)) {
        throw new InvalidArgumentException('File harus berupa ZIP yang valid dan maksimal 100 MB.');
    }

    $workDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mq-site-restore-' . bin2hex(random_bytes(12));
    if (!mkdir($workDirectory, 0700, true)) throw new RuntimeException('Folder sementara restore tidak dapat dibuat.');
    $mediaFiles = [];
    try {
        $archive = new ZipArchive();
        if ($archive->open($uploadPath) !== true) throw new InvalidArgumentException('Arsip ZIP rusak atau tidak dapat dibuka.');
        try {
            if ($archive->numFiles < 1 || $archive->numFiles > 5000) throw new InvalidArgumentException('Jumlah file ZIP tidak valid.');
            $expandedSize = 0;
            $dataPath = null;
            $mediaDirectory = $workDirectory . DIRECTORY_SEPARATOR . 'uploads';
            mkdir($mediaDirectory, 0700, true);
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);
                $entry = $archive->statIndex($index);
                if (!is_string($entryName) || !is_array($entry) || str_contains($entryName, chr(0))
                    || str_contains($entryName, chr(92)) || str_starts_with($entryName, '/')
                    || preg_match('~(^|/)\.\.(/|$)~', $entryName)) {
                    throw new InvalidArgumentException('Arsip berisi path file yang tidak aman.');
                }
                if (method_exists($archive, 'getExternalAttributesIndex')) {
                    $os = 0;
                    $attributes = 0;
                    if ($archive->getExternalAttributesIndex($index, $os, $attributes)
                        && (($attributes >> 16) & 0170000) === 0120000) {
                        throw new InvalidArgumentException('Arsip tidak boleh berisi symbolic link.');
                    }
                }
                $size = (int)($entry['size'] ?? 0);
                $expandedSize += $size;
                if ($size < 0 || $expandedSize > 500 * 1024 * 1024) throw new InvalidArgumentException('Ukuran ekstraksi ZIP terlalu besar.');
                if ($entryName === 'uploads/') continue;
                if ($entryName === 'data.json') {
                    if ($dataPath !== null || $size > 100 * 1024 * 1024) throw new InvalidArgumentException('data.json tidak valid atau terlalu besar.');
                    $dataPath = $workDirectory . DIRECTORY_SEPARATOR . 'data.json';
                    pbCopyZipEntry($archive, $entryName, $dataPath);
                    continue;
                }
                if (!preg_match('~^uploads/([a-zA-Z0-9_.-]+\.(?:png|jpe?g|gif|webp|svg))$~i', $entryName, $match)
                    || str_contains($match[1], '..') || $size > 20 * 1024 * 1024) {
                    throw new InvalidArgumentException('ZIP hanya boleh berisi data.json dan gambar di folder uploads/.');
                }
                $name = $match[1];
                if (isset($mediaFiles[$name])) throw new InvalidArgumentException('Nama media duplikat di dalam arsip.');
                $target = $mediaDirectory . DIRECTORY_SEPARATOR . $name;
                pbCopyZipEntry($archive, $entryName, $target);
                pbValidateBackupMedia($target, $name);
                $mediaFiles[$name] = $target;
            }
        } finally {
            $archive->close();
        }
        if ($dataPath === null || !is_file($dataPath)) throw new InvalidArgumentException('data.json tidak ditemukan di dalam ZIP.');
        $json = file_get_contents($dataPath);
        if ($json === false) throw new RuntimeException('data.json tidak dapat dibaca.');
        try {
            $backup = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Struktur data.json tidak valid.');
        }

        $tables = ['pages', 'templates', 'posts', 'form_submissions', 'users'];
        if (!is_array($backup) || ($backup['format'] ?? '') !== 'web-mqannuur-full-backup'
            || !is_array($backup['tables'] ?? null)) {
            throw new InvalidArgumentException('File ini bukan full backup Web MQ An-Nuur yang dikenali.');
        }
        foreach ($tables as $table) {
            if (!isset($backup['tables'][$table]) || !is_array($backup['tables'][$table])) {
                throw new InvalidArgumentException('Data tabel ' . $table . ' tidak ditemukan atau tidak valid.');
            }
        }
        if ($backup['tables']['users'] === []) {
            throw new InvalidArgumentException('Backup users kosong; restore dibatalkan agar akses admin tidak hilang.');
        }
        $specs = [
            'pages' => [
                'columns' => ['id', 'slug', 'title', 'content_json', 'content_css', 'content_html', 'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'lang', 'translation_group', 'updated_at'],
                'required' => ['slug', 'title', 'content_json', 'content_css', 'content_html']
            ],
            'templates' => [
                'columns' => ['id', 'name', 'description', 'content_json', 'content_css', 'created_at'],
                'required' => ['name', 'content_json']
            ],
            'posts' => [
                'columns' => ['id', 'title', 'slug', 'content', 'excerpt', 'featured_image', 'category', 'status', 'created_at'],
                'required' => ['title', 'slug']
            ],
            'form_submissions' => [
                'columns' => ['id', 'page_slug', 'form_data', 'sender_ip', 'created_at'],
                'required' => ['page_slug', 'form_data', 'sender_ip']
            ],
            'users' => [
                'columns' => ['id', 'username', 'password', 'name', 'created_at'],
                'required' => ['username', 'password']
            ]
        ];
        foreach ($specs as $table => $spec) {
            foreach ($backup['tables'][$table] as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('Record pada tabel ' . $table . ' tidak valid.');
                foreach ($spec['required'] as $column) {
                    if (!array_key_exists($column, $row) || $row[$column] === null) {
                        throw new InvalidArgumentException('Kolom wajib ' . $column . ' hilang pada tabel ' . $table . '.');
                    }
                }
                foreach ($row as $value) {
                    if (is_array($value) || is_object($value) || is_resource($value)) {
                        throw new InvalidArgumentException('Nilai record backup harus berupa data scalar.');
                    }
                }
            }
        }

        $pdo = pbDbConnection();
        $uploadsDirectory = pbUploadsDirectory();
        $rollbackDirectory = $workDirectory . DIRECTORY_SEPARATOR . 'rollback';
        mkdir($rollbackDirectory, 0700, true);
        $changedFiles = [];
        $pdo->beginTransaction();
        try {
            foreach (['form_submissions', 'posts', 'templates', 'pages', 'users'] as $table) {
                $delete = $pdo->prepare('DELETE FROM ' . $table);
                $delete->execute();
            }
            $statements = [];
            foreach ($specs as $table => $spec) {
                foreach ($backup['tables'][$table] as $row) {
                    $columns = array_values(array_intersect($spec['columns'], array_keys($row)));
                    if ($table === 'pages') {
                        if (!in_array('lang', $columns, true)) $row['lang'] = 'id';
                        if (!in_array('translation_group', $columns, true)) $row['translation_group'] = null;
                        $columns = array_values(array_intersect($spec['columns'], array_keys($row)));
                    }
                    $signature = $table . ':' . implode(',', $columns);
                    if (!isset($statements[$signature])) {
                        $names = $columns;
                        $parameters = array_map(static fn(string $column): string => ':' . $column, $columns);
                        $statements[$signature] = $pdo->prepare(
                            'INSERT INTO ' . $table . ' (' . implode(',', $names) . ') VALUES (' . implode(',', $parameters) . ')'
                        );
                    }
                    $values = [];
                    foreach ($columns as $column) $values[$column] = $row[$column] ?? null;
                    $statements[$signature]->execute($values);
                }
            }
            foreach ($mediaFiles as $name => $source) {
                $destination = $uploadsDirectory . DIRECTORY_SEPARATOR . $name;
                if (is_link($destination) || (file_exists($destination) && !is_file($destination))) {
                    throw new RuntimeException('Tujuan media restore tidak aman.');
                }
                $oldFile = null;
                if (is_file($destination)) {
                    $oldFile = $rollbackDirectory . DIRECTORY_SEPARATOR . $name;
                    if (!copy($destination, $oldFile)) throw new RuntimeException('Media lama gagal dicadangkan.');
                }
                $stagedFile = $uploadsDirectory . DIRECTORY_SEPARATOR . '.restore-' . bin2hex(random_bytes(8));
                if (!copy($source, $stagedFile)) throw new RuntimeException('Media backup gagal disalin.');
                if (is_file($destination) && !unlink($destination)) {
                    @unlink($stagedFile);
                    throw new RuntimeException('Media lama gagal diperbarui.');
                }
                if (!rename($stagedFile, $destination)) {
                    if ($oldFile !== null) @copy($oldFile, $destination);
                    @unlink($stagedFile);
                    throw new RuntimeException('Media backup gagal dipasang.');
                }
                $changedFiles[] = ['destination' => $destination, 'old' => $oldFile];
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach (array_reverse($changedFiles) as $file) {
                if ($file['old'] !== null) @copy($file['old'], $file['destination']);
                else @unlink($file['destination']);
            }
            throw $exception;
        }
        return ['status' => 'success', 'message' => 'Seluruh paket website berhasil di-restore!'];
    } finally {
        pbRemoveTemporaryTree($workDirectory);
    }
}

/** Extract one ZIP file entry to a controlled path, never using extractTo(). */
function pbCopyZipEntry(ZipArchive $archive, string $entryName, string $destination): void
{
    $source = $archive->getStream($entryName);
    $target = fopen($destination, 'xb');
    if ($source === false || $target === false) {
        if (is_resource($source)) fclose($source);
        if (is_resource($target)) fclose($target);
        throw new RuntimeException('File dari arsip gagal diekstrak.');
    }
    $copied = stream_copy_to_stream($source, $target);
    fclose($source);
    fclose($target);
    if ($copied === false) throw new RuntimeException('File dari arsip gagal disalin.');
}

/** Check image MIME and sanitize SVG content before a restore installs it. */
function pbValidateBackupMedia(string $path, string $filename): void
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === 'svg') {
        $clean = pbSanitizeUploadedSvg($path);
        if (file_put_contents($path, $clean, LOCK_EX) === false) throw new RuntimeException('SVG backup gagal dibersihkan.');
        return;
    }
    if (!class_exists(finfo::class)) throw new RuntimeException('PHP Fileinfo diperlukan untuk memvalidasi media backup.');
    $expected = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (($finfo->file($path) ?: '') !== ($expected[$extension] ?? '') || @getimagesize($path) === false) {
        throw new InvalidArgumentException('Format media tidak cocok dengan isi file.');
    }
}
