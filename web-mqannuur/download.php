<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$downloadId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($downloadId === false || $downloadId < 1) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

try {
    $statement = getDbConnection()->prepare('SELECT id, title, file_path, file_name, external_url FROM downloads WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $downloadId]);
    $download = $statement->fetch();
    if (!$download) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }

    getDbConnection()->prepare('UPDATE downloads SET download_count = download_count + 1 WHERE id = :id')->execute(['id' => $downloadId]);

    $external = trim((string)($download['external_url'] ?? ''));
    if ($external !== '') {
        $safe = pbSafeUrl($external);
        if ($safe !== null && preg_match('~^https?://~i', $safe)) {
            header('Location: ' . $safe, true, 302);
            exit;
        }
    }

    $filePath = trim((string)($download['file_path'] ?? ''));
    $uploadsRoot = realpath(__DIR__ . '/uploads');
    $absolute = $filePath !== '' ? realpath(__DIR__ . '/' . $filePath) : false;
    if ($uploadsRoot === false || $absolute === false || !str_starts_with($absolute, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($absolute)) {
        http_response_code(404);
        exit('Berkas dokumen tidak ditemukan.');
    }

    $downloadName = trim((string)($download['file_name'] ?? ''));
    if ($downloadName === '') {
        $downloadName = basename($absolute);
    }
    $downloadName = preg_replace('/[\r\n"]+/', '', $downloadName) ?: 'dokumen';
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $absolute);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($absolute));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($absolute);
    exit;
} catch (Throwable $exception) {
    error_log('[Page Builder] Download error: ' . $exception->getMessage());
    http_response_code(503);
    exit('Dokumen belum dapat diunduh.');
}
