<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/includes/backup.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $expected = trim(pbSetting('backup_token', ''));
    $provided = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Token backup tidak valid.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

try {
    $result = pbBackupRunScheduledIfDue();
    $payload = [
        'ok' => true,
        'due' => (bool)($result['due'] ?? false),
        'schedule' => (string)($result['schedule'] ?? pbSetting('backup_schedule', 'disabled')),
        'file' => (string)($result['file'] ?? ''),
        'size' => (int)($result['size'] ?? 0),
        'message' => (string)($result['message'] ?? 'Backup terjadwal berhasil dibuat.'),
    ];
    if ($isCli) {
        echo ($payload['due'] ? 'Backup dibuat: ' : 'Tidak ada backup: ') . ($payload['file'] !== '' ? $payload['file'] : $payload['message']) . PHP_EOL;
        exit($payload['due'] ? 0 : 0);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('[Page Builder] Scheduled backup error: ' . $exception->getMessage());
    if ($isCli) {
        fwrite(STDERR, 'Backup gagal: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Backup terjadwal gagal diproses. Periksa log server.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
