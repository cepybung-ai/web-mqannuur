<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

function pbBackupDirectory(): string
{
    static $directory = null;
    if (is_string($directory)) {
        return $directory;
    }

    $configured = pbConfigValue('backup_dir', 'WEB_MQ_BACKUP_DIR', '');
    $directory = $configured !== ''
        ? rtrim($configured, '/\\')
        : dirname(__DIR__, 2) . '/web-mqannuur-backups';

    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder backup tidak dapat dibuat: ' . $directory);
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Folder backup tidak dapat ditulis oleh server: ' . $directory);
    }

    $htaccess = $directory . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    $index = $directory . '/index.html';
    if (!is_file($index)) {
        @file_put_contents($index, '');
    }

    return $directory;
}

function pbBackupFileName(): string
{
    return 'web-mqannuur-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
}

function pbBackupSafeFile(string $name): ?string
{
    $name = basename(str_replace('\\', '/', $name));
    if (preg_match('/^web-mqannuur-\d{8}-\d{6}-[a-f0-9]{6}\.zip$/', $name) !== 1) {
        return null;
    }
    $path = pbBackupDirectory() . '/' . $name;
    if (!is_file($path)) {
        return null;
    }
    $realPath = realpath($path);
    $realDirectory = realpath(pbBackupDirectory());
    if ($realPath === false || $realDirectory === false || !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $realPath;
}

function pbBackupFiles(): array
{
    $files = [];
    foreach (glob(pbBackupDirectory() . '/web-mqannuur-*.zip') ?: [] as $path) {
        $name = basename($path);
        if (pbBackupSafeFile($name) === null) {
            continue;
        }
        $files[] = [
            'name' => $name,
            'path' => $path,
            'size' => (int)(filesize($path) ?: 0),
            'modified_at' => date('Y-m-d H:i:s', (int)(filemtime($path) ?: time())),
            'modified_ts' => (int)(filemtime($path) ?: 0),
        ];
    }
    usort($files, static fn(array $a, array $b): int => $b['modified_ts'] <=> $a['modified_ts']);
    return $files;
}

function pbBackupPrune(int $retention = 10): int
{
    $retention = max(1, min(50, $retention));
    $deleted = 0;
    foreach (array_slice(pbBackupFiles(), $retention) as $file) {
        if (@unlink((string)$file['path'])) {
            $deleted++;
        }
    }
    return $deleted;
}

function pbBackupSqlDump(PDO $pdo, string $path): array
{
    $handle = @fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('File SQL sementara tidak dapat dibuat.');
    }

    $write = static function (string $data) use ($handle): void {
        if (fwrite($handle, $data) === false) {
            throw new RuntimeException('File SQL backup gagal ditulis.');
        }
    };

    try {
        $write("-- Web MQ An-Nuur database backup\n");
        $write('-- Dibuat: ' . date(DATE_ATOM) . "\n\n");
        $write("SET NAMES utf8mb4;\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\n");
        $write("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        $tableNames = [];
        $rowCount = 0;
        foreach ($tables as $tableRow) {
            $table = (string)($tableRow[0] ?? '');
            if ($table === '') {
                continue;
            }
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
            $createSql = (string)($createRow[1] ?? '');
            if ($createSql === '') {
                continue;
            }

            $tableNames[] = $table;
            $write('-- --------------------------------------------------------' . "\n");
            $write('-- Tabel ' . $table . "\n");
            $write('-- --------------------------------------------------------' . "\n");
            $write('DROP TABLE IF EXISTS ' . $quotedTable . ";\n");
            $write($createSql . ";\n\n");

            $columns = [];
            foreach ($pdo->query('SHOW COLUMNS FROM ' . $quotedTable)->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $field = (string)($column['Field'] ?? '');
                if ($field !== '') {
                    $columns[] = '`' . str_replace('`', '``', $field) . '`';
                }
            }
            if ($columns === []) {
                continue;
            }

            $statement = $pdo->query('SELECT * FROM ' . $quotedTable);
            $buffer = [];
            $bufferBytes = 0;
            $flush = static function () use (&$buffer, &$bufferBytes, $write, $quotedTable, $columns): void {
                if ($buffer === []) {
                    return;
                }
                $write('INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ") VALUES\n");
                $write(implode(",\n", $buffer) . ";\n\n");
                $buffer = [];
                $bufferBytes = 0;
            };

            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $values = [];
                foreach ($columns as $quotedColumn) {
                    $column = trim($quotedColumn, '`');
                    $value = $row[$column] ?? null;
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string)$value;
                    } else {
                        $values[] = $pdo->quote((string)$value);
                    }
                }
                $tuple = '(' . implode(', ', $values) . ')';
                $buffer[] = $tuple;
                $bufferBytes += strlen($tuple);
                $rowCount++;
                if (count($buffer) >= 100 || $bufferBytes >= 2_000_000) {
                    $flush();
                }
            }
            $flush();
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        $write('-- Selesai. Total baris data: ' . $rowCount . "\n");

        return ['tables' => $tableNames, 'rows' => $rowCount];
    } finally {
        fclose($handle);
    }
}

function pbBackupUploadFiles(): array
{
    $root = dirname(__DIR__) . '/uploads';
    if (!is_dir($root)) {
        return ['files' => [], 'count' => 0, 'bytes' => 0];
    }

    $root = (string)realpath($root);
    $files = [];
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
            continue;
        }
        $path = $file->getPathname();
        $realPath = realpath($path);
        if ($realPath === false || !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($realPath, strlen($root) + 1));
        if ($relative === '' || str_contains($relative, '..')) {
            continue;
        }
        $files['uploads/' . $relative] = $realPath;
        $bytes += (int)$file->getSize();
    }

    ksort($files, SORT_STRING);
    return ['files' => $files, 'count' => count($files), 'bytes' => $bytes];
}

function pbZipDosDateTime(int $timestamp): array
{
    $parts = getdate($timestamp);
    $year = max(1980, (int)$parts['year']);
    $time = (($parts['hours'] & 0x1F) << 11) | (($parts['minutes'] & 0x3F) << 5) | (($parts['seconds'] & 0x3F) >> 1);
    $date = ((($year - 1980) & 0x7F) << 9) | (($parts['mon'] & 0x0F) << 5) | ($parts['mday'] & 0x1F);
    return [$time, $date];
}

function pbZipWrite(string $zipPath, array $files, array $directories = []): void
{
    if (!function_exists('gzdeflate')) {
        throw new RuntimeException('Ekstensi zlib diperlukan untuk membuat ZIP.');
    }

    $handle = @fopen($zipPath, 'wb');
    if ($handle === false) {
        throw new RuntimeException('File ZIP tidak dapat dibuat.');
    }

    $central = [];
    $offset = 0;
    $write = static function (string $data) use ($handle, &$offset): void {
        $length = strlen($data);
        if ($length > 0 && fwrite($handle, $data) !== $length) {
            throw new RuntimeException('File ZIP gagal ditulis.');
        }
        $offset += $length;
    };

    $addEntry = static function (string $name, string $data, int $method, int $crc, int $compressed, int $uncompressed, int $timestamp) use ($write, &$central, &$offset): void {
        [$time, $date] = pbZipDosDateTime($timestamp);
        $name = ltrim(str_replace('\\', '/', $name), '/');
        if ($name === '' || str_contains($name, '..')) {
            throw new RuntimeException('Nama entri ZIP tidak valid.');
        }
        $entryOffset = $offset;
        $write(pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, $compressed, $uncompressed, strlen($name), 0) . $name);
        if ($data !== '') {
            $write($data);
        }
        $central[] = [
            'name' => $name,
            'method' => $method,
            'crc' => $crc,
            'compressed' => $compressed,
            'uncompressed' => $uncompressed,
            'time' => $time,
            'date' => $date,
            'offset' => $entryOffset,
        ];
    };

    try {
        foreach ($directories as $directoryName) {
            $directoryName = rtrim(str_replace('\\', '/', (string)$directoryName), '/') . '/';
            $addEntry($directoryName, '', 0, 0, 0, 0, time());
        }

        foreach ($files as $archiveName => $sourcePath) {
            $sourcePath = (string)$sourcePath;
            if (!is_file($sourcePath) || !is_readable($sourcePath)) {
                throw new RuntimeException('Berkas backup tidak dapat dibaca: ' . $archiveName);
            }
            $data = file_get_contents($sourcePath);
            if ($data === false) {
                throw new RuntimeException('Berkas backup tidak dapat dibaca: ' . $archiveName);
            }
            $uncompressed = strlen($data);
            $crc = crc32($data);
            $deflated = gzdeflate($data, 6);
            $useDeflate = is_string($deflated) && strlen($deflated) < $uncompressed;
            $payload = $useDeflate ? $deflated : $data;
            $method = $useDeflate ? 8 : 0;
            $compressed = strlen($payload);
            if ($uncompressed > 0xFFFFFFFF || $compressed > 0xFFFFFFFF) {
                throw new RuntimeException('Ukuran berkas melebihi batas ZIP standar 4 GB.');
            }
            $addEntry((string)$archiveName, $payload, $method, $crc, $compressed, $uncompressed, (int)(filemtime($sourcePath) ?: time()));
        }

        $centralOffset = $offset;
        foreach ($central as $entry) {
            $write(pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0x0800,
                $entry['method'],
                $entry['time'],
                $entry['date'],
                $entry['crc'],
                $entry['compressed'],
                $entry['uncompressed'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ) . $entry['name']);
        }
        $centralSize = $offset - $centralOffset;
        $count = count($central);
        $write(pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0));
    } finally {
        fclose($handle);
    }
}

function pbBackupRunNow(string $reason = 'manual', ?string $actorUsername = null, ?string $actorRole = null): array
{
    @set_time_limit(0);
    $startedAt = microtime(true);
    $directory = pbBackupDirectory();
    $lockDirectory = $directory . '/.backup.lock.d';
    if (!@mkdir($lockDirectory, 0775)) {
        $lockedAt = @filemtime($lockDirectory);
        if ($lockedAt === false || time() - $lockedAt < 3600) {
            throw new RuntimeException('Backup lain sedang berjalan. Coba lagi beberapa saat.');
        }
        @rmdir($lockDirectory);
        if (!@mkdir($lockDirectory, 0775)) {
            throw new RuntimeException('Lock backup tidak dapat dibuat.');
        }
    }

    $sqlPath = '';
    $manifestPath = '';
    $zipTmpPath = '';
    try {
        $pdo = getDbConnection();
        $fileName = pbBackupFileName();
        $zipPath = $directory . '/' . $fileName;
        $zipTmpPath = $directory . '/.tmp-' . bin2hex(random_bytes(8)) . '.zip';
        $sqlPath = $directory . '/.tmp-' . bin2hex(random_bytes(8)) . '.sql';
        $manifestPath = $directory . '/.tmp-' . bin2hex(random_bytes(8)) . '.json';

        $dump = pbBackupSqlDump($pdo, $sqlPath);
        $uploads = pbBackupUploadFiles();
        $sqlSize = (int)(filesize($sqlPath) ?: 0);
        $manifest = [
            'format' => 'web-mqannuur-full-backup',
            'version' => 1,
            'created_at' => date(DATE_ATOM),
            'site_url' => pbSiteBaseUrl(),
            'database' => pbConfigValue('db_name', 'WEB_MQ_DB_NAME', 'web_mqannuur'),
            'tables' => $dump['tables'],
            'table_count' => count($dump['tables']),
            'rows' => $dump['rows'],
            'sql_bytes' => $sqlSize,
            'uploads_files' => $uploads['count'],
            'uploads_bytes' => $uploads['bytes'],
            'reason' => $reason,
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        if (file_put_contents($manifestPath, $manifestJson) === false) {
            throw new RuntimeException('Manifest backup tidak dapat ditulis.');
        }

        $entries = [
            'database.sql' => $sqlPath,
            'manifest.json' => $manifestPath,
        ] + $uploads['files'];
        pbZipWrite($zipTmpPath, $entries, ['uploads/']);
        if (!@rename($zipTmpPath, $zipPath)) {
            throw new RuntimeException('File ZIP backup tidak dapat disimpan.');
        }
        $zipTmpPath = '';
        @chmod($zipPath, 0640);
        $zipSize = (int)(filesize($zipPath) ?: 0);

        try {
            pbSetSetting('backup_last_run', date('Y-m-d H:i:s'));
            pbSetSetting('backup_last_status', 'success');
            pbSetSetting('backup_last_file', $fileName);
        } catch (Throwable $settingsException) {
            error_log('[Page Builder] Backup settings update error: ' . $settingsException->getMessage());
        }

        $retention = (int)pbSetting('backup_retention', '10');
        $pruned = pbBackupPrune($retention);
        $duration = round(microtime(true) - $startedAt, 2);
        logActivity(
            'backup_create',
            'backup',
            $fileName,
            'ZIP penuh ' . pbFormatBytes($zipSize) . '; SQL ' . pbFormatBytes($sqlSize) . '; uploads ' . $uploads['count'] . ' berkas (' . pbFormatBytes($uploads['bytes']) . '); sumber: ' . $reason,
            $actorUsername,
            $actorRole
        );

        return [
            'ok' => true,
            'due' => true,
            'file' => $fileName,
            'path' => $zipPath,
            'size' => $zipSize,
            'sql_bytes' => $sqlSize,
            'uploads_files' => $uploads['count'],
            'uploads_bytes' => $uploads['bytes'],
            'tables' => count($dump['tables']),
            'rows' => $dump['rows'],
            'pruned' => $pruned,
            'duration' => $duration,
        ];
    } catch (Throwable $exception) {
        try {
            pbSetSetting('backup_last_run', date('Y-m-d H:i:s'));
            pbSetSetting('backup_last_status', 'error: ' . substr($exception->getMessage(), 0, 180));
        } catch (Throwable $ignored) {
        }
        logActivity('backup_failed', 'backup', null, 'Backup gagal: ' . substr($exception->getMessage(), 0, 500), $actorUsername, $actorRole);
        throw $exception;
    } finally {
        foreach ([$sqlPath, $manifestPath, $zipTmpPath] as $temporaryPath) {
            if ($temporaryPath !== '' && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
        @rmdir($lockDirectory);
    }
}

function pbBackupScheduleSeconds(string $schedule): int
{
    return match ($schedule) {
        'daily' => 86400,
        'weekly' => 604800,
        'monthly' => 2592000,
        default => 0,
    };
}

function pbBackupIsDue(): bool
{
    $schedule = strtolower(trim(pbSetting('backup_schedule', 'disabled')));
    $seconds = pbBackupScheduleSeconds($schedule);
    if ($seconds < 1) {
        return false;
    }
    $lastRun = trim(pbSetting('backup_last_run', ''));
    $lastTimestamp = $lastRun !== '' ? strtotime($lastRun) : false;
    if ($lastTimestamp === false) {
        return true;
    }
    return time() >= $lastTimestamp + $seconds;
}

function pbBackupRunScheduledIfDue(): array
{
    $schedule = strtolower(trim(pbSetting('backup_schedule', 'disabled')));
    if (!pbBackupIsDue()) {
        return [
            'ok' => true,
            'due' => false,
            'schedule' => $schedule,
            'last_run' => pbSetting('backup_last_run', ''),
            'message' => 'Belum waktunya backup terjadwal.',
        ];
    }
    $result = pbBackupRunNow('scheduled', 'system', 'system');
    $result['schedule'] = $schedule;
    return $result;
}
