<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/includes/backup.php';

function pbBackupSpecs(): array
{
    return [
        'pages' => [
            'columns' => ['id', 'slug', 'title', 'content_json', 'content_css', 'content_html', 'updated_at'],
            'required' => ['slug', 'title', 'content_json', 'content_css', 'content_html'],
        ],
        'posts' => [
            'columns' => ['id', 'title', 'slug', 'content', 'excerpt', 'featured_image', 'category', 'status', 'created_at', 'updated_at'],
            'required' => ['title', 'slug', 'content', 'category', 'status'],
        ],
        'links' => [
            'columns' => ['id', 'title', 'url', 'icon', 'category', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'required' => ['title', 'url'],
        ],
        'events' => [
            'columns' => ['id', 'title', 'slug', 'excerpt', 'content', 'location', 'event_date', 'event_end_date', 'image', 'status', 'created_at', 'updated_at'],
            'required' => ['title', 'slug', 'event_date'],
        ],
        'staff' => [
            'columns' => ['id', 'name', 'position', 'category', 'photo', 'bio', 'email', 'phone', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'required' => ['name', 'position'],
        ],
        'downloads' => [
            'columns' => ['id', 'title', 'description', 'category', 'file_path', 'file_name', 'file_size', 'external_url', 'download_count', 'is_active', 'created_at', 'updated_at'],
            'required' => ['title'],
        ],
        'gallery' => [
            'columns' => ['id', 'title', 'caption', 'file_path', 'original_name', 'mime_type', 'width', 'height', 'file_size', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'required' => ['title', 'file_path'],
        ],
        'settings' => [
            'columns' => ['setting_key', 'setting_value', 'updated_at'],
            'required' => ['setting_key'],
        ],
        'templates' => [
            'columns' => ['id', 'name', 'description', 'content_json', 'content_css', 'created_at'],
            'required' => ['name', 'content_json'],
        ],
        'form_submissions' => [
            'columns' => ['id', 'page_slug', 'form_data', 'sender_ip', 'created_at'],
            'required' => ['page_slug', 'form_data', 'sender_ip'],
        ],
        'users' => [
            'columns' => ['id', 'username', 'password', 'name', 'role', 'created_at'],
            'required' => ['username', 'password'],
        ],
    ];
}

function pbBackupComponentsJson($value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Struktur komponen halaman pada backup tidak valid.');
        }
        $value = $decoded;
    }
    if (!is_array($value)) {
        throw new InvalidArgumentException('Struktur komponen halaman pada backup tidak valid.');
    }
    $clean = pbSanitizeComponents($value);
    return json_encode($clean ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

function pbBackupValidDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

function pbBackupUploadPath(string $path, string $directory): ?string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'uploads/' . $directory . '/')) {
        return null;
    }
    return $path;
}

function pbBackupUrlOrNull(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $safe = pbSafeUrl($value);
    if ($safe === null || preg_match('~^(?:mailto:|tel:)~i', $safe)) {
        throw new InvalidArgumentException('URL pada backup tidak aman.');
    }
    return $safe;
}

function pbBackupNormalizeRow(string $table, array $row): array
{
    if ($table === 'pages') {
        $slug = trim((string)($row['slug'] ?? ''));
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        if (!pbValidSlug($slug) || $title === '') {
            throw new InvalidArgumentException('Data halaman pada backup tidak valid.');
        }
        $row['slug'] = $slug;
        $row['title'] = $title;
        $row['content_json'] = pbBackupComponentsJson($row['content_json'] ?? '[]');
        $row['content_css'] = pbSanitizeCss((string)($row['content_css'] ?? ''));
        $row['content_html'] = pbSanitizeHtml((string)($row['content_html'] ?? ''));
        return $row;
    }

    if ($table === 'posts') {
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        $slug = trim((string)($row['slug'] ?? ''));
        if ($title === '' || !pbValidSlug($slug)) {
            throw new InvalidArgumentException('Data berita pada backup tidak valid.');
        }
        $category = trim(strip_tags((string)($row['category'] ?? 'Umum'))) ?: 'Umum';
        $status = (string)($row['status'] ?? 'published');
        $featuredImage = trim((string)($row['featured_image'] ?? ''));
        if ($featuredImage !== '') {
            $safeImage = pbSafeUrl($featuredImage);
            if ($safeImage === null || preg_match('~^(?:mailto:|tel:)~i', $safeImage)) {
                throw new InvalidArgumentException('Gambar berita pada backup tidak aman.');
            }
            $featuredImage = $safeImage;
        }
        $row['title'] = $title;
        $row['slug'] = $slug;
        $row['category'] = function_exists('mb_substr') ? mb_substr($category, 0, 100, 'UTF-8') : substr($category, 0, 100);
        $row['status'] = in_array($status, ['draft', 'published'], true) ? $status : 'published';
        $row['content'] = pbSanitizeHtml((string)($row['content'] ?? ''));
        $row['excerpt'] = trim(strip_tags((string)($row['excerpt'] ?? ''))) ?: null;
        $row['featured_image'] = $featuredImage !== '' ? $featuredImage : null;
        return $row;
    }

    if ($table === 'links') {
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        $url = trim((string)($row['url'] ?? ''));
        $safeUrl = $url !== '' ? pbSafeUrl($url) : null;
        if ($title === '' || $safeUrl === null) {
            throw new InvalidArgumentException('Data link pada backup tidak valid atau tidak aman.');
        }
        $row['title'] = $title;
        $row['url'] = $safeUrl;
        $row['icon'] = trim(strip_tags((string)($row['icon'] ?? '')));
        $row['category'] = trim(strip_tags((string)($row['category'] ?? 'Menu Utama'))) ?: 'Menu Utama';
        $row['sort_order'] = max(0, min(9999, (int)($row['sort_order'] ?? 0)));
        $row['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
        return $row;
    }

    if ($table === 'templates') {
        $name = trim(strip_tags((string)($row['name'] ?? '')));
        if ($name === '') {
            throw new InvalidArgumentException('Nama template pada backup tidak valid.');
        }
        $row['name'] = function_exists('mb_substr') ? mb_substr($name, 0, 255, 'UTF-8') : substr($name, 0, 255);
        $row['description'] = trim(strip_tags((string)($row['description'] ?? ''))) ?: null;
        $row['content_json'] = pbBackupComponentsJson($row['content_json'] ?? '[]');
        $row['content_css'] = pbSanitizeCss((string)($row['content_css'] ?? ''));
        return $row;
    }

    if ($table === 'form_submissions') {
        $pageSlug = trim((string)($row['page_slug'] ?? ''));
        if ($pageSlug === '') {
            throw new InvalidArgumentException('Data formulir pada backup tidak valid.');
        }
        $formData = $row['form_data'] ?? null;
        if (is_array($formData)) {
            $formData = json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        }
        $formData = (string)$formData;
        $decoded = json_decode($formData, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Isi formulir pada backup bukan JSON yang valid.');
        }
        $row['page_slug'] = function_exists('mb_substr') ? mb_substr($pageSlug, 0, 100, 'UTF-8') : substr($pageSlug, 0, 100);
        $row['form_data'] = $formData;
        $row['sender_ip'] = substr((string)($row['sender_ip'] ?? ''), 0, 50);
        return $row;
    }

    if ($table === 'users') {
        $username = strtolower(trim((string)($row['username'] ?? '')));
        $password = (string)($row['password'] ?? '');
        $role = strtolower(trim((string)($row['role'] ?? 'editor')));
        if (preg_match('/^[a-z0-9._-]{3,50}$/', $username) !== 1 || $password === '' || strlen($password) > 255) {
            throw new InvalidArgumentException('Data user pada backup tidak valid.');
        }
        $row['username'] = $username;
        $row['password'] = $password;
        $row['name'] = trim(strip_tags((string)($row['name'] ?? ''))) ?: $username;
        $row['role'] = in_array($role, ['super_admin', 'admin', 'operator'], true) ? 'super_admin' : 'editor';
        return $row;
    }

    if ($table === 'events') {
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        $slug = trim((string)($row['slug'] ?? ''));
        $eventDate = trim((string)($row['event_date'] ?? ''));
        $eventEnd = trim((string)($row['event_end_date'] ?? ''));
        if ($title === '' || !pbValidSlug($slug) || !pbBackupValidDate($eventDate)) {
            throw new InvalidArgumentException('Data agenda pada backup tidak valid.');
        }
        $status = (string)($row['status'] ?? 'published');
        $row['title'] = function_exists('mb_substr') ? mb_substr($title, 0, 255, 'UTF-8') : substr($title, 0, 255);
        $row['slug'] = $slug;
        $row['excerpt'] = trim(strip_tags((string)($row['excerpt'] ?? ''))) ?: null;
        $row['content'] = pbSanitizeHtml((string)($row['content'] ?? ''));
        $row['location'] = trim(strip_tags((string)($row['location'] ?? ''))) ?: null;
        $row['event_date'] = $eventDate;
        $row['event_end_date'] = $eventEnd !== '' && pbBackupValidDate($eventEnd) && $eventEnd >= $eventDate ? $eventEnd : null;
        $row['image'] = pbBackupUrlOrNull((string)($row['image'] ?? ''));
        $row['status'] = in_array($status, ['draft', 'published'], true) ? $status : 'published';
        return $row;
    }

    if ($table === 'staff') {
        $name = trim(strip_tags((string)($row['name'] ?? '')));
        $position = trim(strip_tags((string)($row['position'] ?? '')));
        if ($name === '' || $position === '') {
            throw new InvalidArgumentException('Data guru/staf pada backup tidak valid.');
        }
        $category = trim(strip_tags((string)($row['category'] ?? 'Guru')));
        $email = trim((string)($row['email'] ?? ''));
        $row['name'] = function_exists('mb_substr') ? mb_substr($name, 0, 150, 'UTF-8') : substr($name, 0, 150);
        $row['position'] = function_exists('mb_substr') ? mb_substr($position, 0, 150, 'UTF-8') : substr($position, 0, 150);
        $row['category'] = in_array($category, ['Guru', 'Staf', 'Instruktur'], true) ? $category : 'Guru';
        $row['photo'] = pbBackupUrlOrNull((string)($row['photo'] ?? ''));
        $row['bio'] = trim(strip_tags((string)($row['bio'] ?? ''))) ?: null;
        $row['email'] = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $row['phone'] = trim(strip_tags((string)($row['phone'] ?? ''))) ?: null;
        $row['sort_order'] = max(-9999, min(9999, (int)($row['sort_order'] ?? 0)));
        $row['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
        return $row;
    }

    if ($table === 'downloads') {
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        if ($title === '') {
            throw new InvalidArgumentException('Data dokumen pada backup tidak valid.');
        }
        $filePath = trim((string)($row['file_path'] ?? ''));
        if ($filePath !== '' && pbBackupUploadPath($filePath, 'downloads') === null) {
            throw new InvalidArgumentException('Path dokumen pada backup tidak aman.');
        }
        $externalUrl = pbBackupUrlOrNull((string)($row['external_url'] ?? ''));
        if ($filePath === '' && $externalUrl === null) {
            throw new InvalidArgumentException('Dokumen pada backup tidak memiliki berkas atau URL.');
        }
        $row['title'] = function_exists('mb_substr') ? mb_substr($title, 0, 255, 'UTF-8') : substr($title, 0, 255);
        $row['description'] = trim(strip_tags((string)($row['description'] ?? ''))) ?: null;
        $row['category'] = trim(strip_tags((string)($row['category'] ?? 'Dokumen'))) ?: 'Dokumen';
        $row['file_path'] = $filePath !== '' ? $filePath : null;
        $row['file_name'] = trim(strip_tags((string)($row['file_name'] ?? ''))) ?: null;
        $row['file_size'] = max(0, (int)($row['file_size'] ?? 0));
        $row['external_url'] = $externalUrl;
        $row['download_count'] = max(0, (int)($row['download_count'] ?? 0));
        $row['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
        return $row;
    }

    if ($table === 'gallery') {
        $title = trim(strip_tags((string)($row['title'] ?? '')));
        $filePath = trim((string)($row['file_path'] ?? ''));
        if ($title === '' || pbBackupUploadPath($filePath, 'gallery') === null) {
            throw new InvalidArgumentException('Data galeri pada backup tidak valid.');
        }
        $row['title'] = function_exists('mb_substr') ? mb_substr($title, 0, 255, 'UTF-8') : substr($title, 0, 255);
        $row['caption'] = trim(strip_tags((string)($row['caption'] ?? ''))) ?: null;
        $row['file_path'] = $filePath;
        $row['original_name'] = trim(strip_tags((string)($row['original_name'] ?? ''))) ?: null;
        $row['mime_type'] = in_array((string)($row['mime_type'] ?? ''), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) ? (string)$row['mime_type'] : 'image/webp';
        $row['width'] = max(0, min(20000, (int)($row['width'] ?? 0)));
        $row['height'] = max(0, min(20000, (int)($row['height'] ?? 0)));
        $row['file_size'] = max(0, (int)($row['file_size'] ?? 0));
        $row['sort_order'] = max(-9999, min(9999, (int)($row['sort_order'] ?? 0)));
        $row['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
        return $row;
    }

    if ($table === 'settings') {
        $key = trim((string)($row['setting_key'] ?? ''));
        $allowed = array_keys(pbSettingsDefaults());
        if (!in_array($key, $allowed, true)) {
            throw new InvalidArgumentException('Kunci pengaturan pada backup tidak dikenal.');
        }
        $value = (string)($row['setting_value'] ?? '');
        if ($key === 'site_logo') {
            $value = (string)(pbBackupUrlOrNull($value) ?? '');
        } elseif (in_array($key, ['seo_og_image'], true)) {
            $value = (string)(pbBackupUrlOrNull($value) ?? '');
        } elseif ($key === 'seo_site_url') {
            $safeSiteUrl = pbBackupUrlOrNull($value);
            $value = $safeSiteUrl !== null && preg_match('~^https?://~i', $safeSiteUrl) ? rtrim($safeSiteUrl, '/') : '';
        } elseif (in_array($key, ['site_facebook', 'site_instagram', 'site_youtube'], true)) {
            $value = (string)(pbBackupUrlOrNull($value) ?? '');
        } elseif ($key === 'site_email') {
            $value = $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
        } else {
            $value = trim(strip_tags($value));
        }
        $row['setting_key'] = $key;
        $row['setting_value'] = $value;
        return $row;
    }

    return $row;
}

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$specs = pbBackupSpecs();

try {
    $pdo = getDbConnection();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';

        if ($action === 'save_schedule') {
            $schedule = strtolower(trim(is_string($_POST['backup_schedule'] ?? null) ? $_POST['backup_schedule'] : 'disabled'));
            if (!in_array($schedule, ['disabled', 'daily', 'weekly', 'monthly'], true)) {
                throw new InvalidArgumentException('Jadwal backup tidak valid.');
            }
            $retentionValue = filter_var($_POST['backup_retention'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($retentionValue === false) {
                throw new InvalidArgumentException('Retensi backup harus berupa angka 1–50.');
            }
            $token = trim(pbSetting('backup_token', ''));
            if (!empty($_POST['regenerate_token']) || $token === '') {
                $token = bin2hex(random_bytes(32));
            }
            pbSetSetting('backup_schedule', $schedule);
            pbSetSetting('backup_retention', (string)$retentionValue);
            pbSetSetting('backup_token', $token);
            logActivity('schedule_update', 'backup', 'schedule', 'Jadwal: ' . $schedule . '; retensi: ' . $retentionValue);
            pbAdminFlash('success', 'Jadwal backup dan retensi berhasil disimpan.');
            header('Location: site_backup.php', true, 303);
            exit;
        }

        if ($action === 'create_zip') {
            $result = pbBackupRunNow('manual');
            $message = 'Backup ZIP penuh berhasil dibuat: ' . $result['file'] . ' (' . pbFormatBytes((int)$result['size']) . ').';
            if ((int)($result['pruned'] ?? 0) > 0) {
                $message .= ' ' . (int)$result['pruned'] . ' backup lama dihapus sesuai retensi.';
            }
            pbAdminFlash('success', $message);
            header('Location: site_backup.php', true, 303);
            exit;
        }

        if ($action === 'download_zip') {
            $file = is_string($_POST['backup_file'] ?? null) ? $_POST['backup_file'] : '';
            $path = pbBackupSafeFile($file);
            if ($path === null) {
                throw new InvalidArgumentException('File backup tidak ditemukan atau tidak valid.');
            }
            $fileName = basename($path);
            logActivity('download', 'backup', $fileName, 'File backup ZIP diunduh');
            session_write_close();
            @set_time_limit(0);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . (string)(filesize($path) ?: 0));
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        }

        if ($action === 'delete_zip') {
            $file = is_string($_POST['backup_file'] ?? null) ? $_POST['backup_file'] : '';
            $path = pbBackupSafeFile($file);
            if ($path === null) {
                throw new InvalidArgumentException('File backup tidak ditemukan atau tidak valid.');
            }
            $fileName = basename($path);
            if (!@unlink($path)) {
                throw new RuntimeException('File backup tidak dapat dihapus.');
            }
            logActivity('delete', 'backup', $fileName, 'File backup ZIP dihapus');
            pbAdminFlash('success', 'File backup ' . $fileName . ' berhasil dihapus.');
            header('Location: site_backup.php', true, 303);
            exit;
        }

        if ($action === 'export') {
            $backup = [
                'format' => 'web-mqannuur-json-backup',
                'version' => 2,
                'created_at' => date(DATE_ATOM),
                'tables' => [],
            ];
            foreach ($specs as $table => $spec) {
                try {
                    $backup['tables'][$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
                } catch (Throwable $exception) {
                    $backup['tables'][$table] = [];
                }
            }
            $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            $filename = 'web-mqannuur-backup-' . date('Y-m-d-His') . '.json';
            logActivity('export', 'backup', $filename, 'Backup JSON diekspor');
            session_write_close();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string)strlen($json));
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            echo $json;
            exit;
        }

        if ($action === 'restore') {
            $upload = $_FILES['backup_file'] ?? null;
            if (!is_array($upload)) {
                throw new InvalidArgumentException('Pilih file backup JSON terlebih dahulu.');
            }
            $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
            $uploadPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
            $uploadName = is_string($upload['name'] ?? null) ? $upload['name'] : '';
            $uploadSize = (int)($upload['size'] ?? 0);
            if ($uploadError !== UPLOAD_ERR_OK || $uploadSize <= 0 || $uploadSize > 25 * 1024 * 1024
                || strtolower(pathinfo($uploadName, PATHINFO_EXTENSION)) !== 'json'
                || !is_uploaded_file($uploadPath)) {
                throw new InvalidArgumentException('File harus berupa backup JSON yang valid dan maksimal 25 MB.');
            }
            $json = file_get_contents($uploadPath);
            if ($json === false) {
                throw new RuntimeException('File backup tidak dapat dibaca.');
            }
            try {
                $backup = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Struktur file backup tidak valid.');
            }
            $backupVersion = (int)($backup['version'] ?? 0);
            if (!is_array($backup) || ($backup['format'] ?? '') !== 'web-mqannuur-json-backup'
                || !in_array($backupVersion, [1, 2], true) || !is_array($backup['tables'] ?? null)) {
                throw new InvalidArgumentException('File bukan backup Web MQ An-Nuur yang dikenali.');
            }

            $legacyTables = ['pages', 'posts', 'links', 'templates', 'form_submissions', 'users'];
            $providedTables = [];
            $prepared = [];
            foreach ($specs as $table => $spec) {
                if (!isset($backup['tables'][$table]) || !is_array($backup['tables'][$table])) {
                    if (in_array($table, $legacyTables, true) || $backupVersion >= 2) {
                        throw new InvalidArgumentException('Data tabel ' . $table . ' tidak ditemukan pada backup.');
                    }
                    $prepared[$table] = [];
                    continue;
                }
                $providedTables[$table] = true;
                $rows = $backup['tables'][$table];
                if (count($rows) > 50000) {
                    throw new InvalidArgumentException('Jumlah baris tabel ' . $table . ' melebihi batas.');
                }
                $normalized = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        throw new InvalidArgumentException('Baris pada tabel ' . $table . ' tidak valid.');
                    }
                    foreach ($spec['required'] as $column) {
                        if (!array_key_exists($column, $row) || (!is_scalar($row[$column]) && $row[$column] !== null)) {
                            throw new InvalidArgumentException('Kolom wajib ' . $column . ' hilang pada tabel ' . $table . '.');
                        }
                    }
                    $normalized[] = pbBackupNormalizeRow($table, $row);
                }
                $prepared[$table] = $normalized;
            }
            if ($prepared['users'] === []) {
                throw new InvalidArgumentException('Backup tidak berisi user; restore dibatalkan agar akses admin tidak hilang.');
            }
            $hasSuperAdmin = false;
            foreach ($prepared['users'] as $userRow) {
                if (($userRow['role'] ?? '') === 'super_admin') {
                    $hasSuperAdmin = true;
                    break;
                }
            }
            if (!$hasSuperAdmin) {
                $prepared['users'][0]['role'] = 'super_admin';
            }

            $pdo->beginTransaction();
            try {
                foreach (['form_submissions', 'downloads', 'gallery', 'staff', 'events', 'posts', 'links', 'templates', 'pages', 'settings', 'users'] as $table) {
                    if (isset($providedTables[$table])) {
                        $pdo->exec('DELETE FROM `' . $table . '`');
                    }
                }
                foreach ($specs as $table => $spec) {
                    foreach ($prepared[$table] as $row) {
                        $columns = array_values(array_intersect($spec['columns'], array_keys($row)));
                        if ($columns === []) {
                            continue;
                        }
                        $parameters = array_map(static fn(string $column): string => ':' . $column, $columns);
                        $statement = $pdo->prepare(
                            'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $parameters) . ')'
                        );
                        $values = [];
                        foreach ($columns as $column) {
                            $values[$column] = $row[$column] ?? null;
                        }
                        $statement->execute($values);
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            logActivity('restore', 'backup', 'json', 'Restore backup JSON selesai');
            $admin = $pdo->query("SELECT id, username, name, role FROM users WHERE role = 'super_admin' ORDER BY id ASC LIMIT 1")->fetch();
            if ($admin) {
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = (string)$admin['username'];
                $_SESSION['admin_name'] = (string)($admin['name'] ?? '');
                $_SESSION['admin_role'] = 'super_admin';
            }
            pbAdminFlash('success', 'Backup berhasil dipulihkan. Data halaman, berita, agenda, guru/staf, galeri, dokumen, link, pengaturan, pesan, template, dan user telah diganti.');
            header('Location: site_backup.php', true, 303);
            exit;
        }

        throw new InvalidArgumentException('Aksi backup tidak dikenal.');
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Backup error: ' . $exception->getMessage());
    $errorMessage = 'Backup atau restore belum dapat diproses. Periksa file dan koneksi database.';
}

$counts = [];
foreach ($specs as $table => $spec) {
    try {
        $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    } catch (Throwable $exception) {
        $counts[$table] = 0;
    }
}
$totalRows = array_sum($counts);

$backupFiles = [];
$backupDirectory = '';
$backupError = '';
$backupTotalSize = 0;
try {
    $backupDirectory = pbBackupDirectory();
    $backupFiles = pbBackupFiles();
    $backupTotalSize = (int)array_sum(array_column($backupFiles, 'size'));
} catch (Throwable $exception) {
    $backupError = $exception->getMessage();
}
$backupSchedule = strtolower(trim(pbSetting('backup_schedule', 'disabled')));
if (!in_array($backupSchedule, ['disabled', 'daily', 'weekly', 'monthly'], true)) {
    $backupSchedule = 'disabled';
}
$backupRetention = (int)pbSetting('backup_retention', '10');
if ($backupRetention < 1 || $backupRetention > 50) {
    $backupRetention = 10;
}
$backupToken = trim(pbSetting('backup_token', ''));
$cronUrl = $backupToken !== '' ? pbSiteBaseUrl() . '/backup-cron.php?token=' . rawurlencode($backupToken) : '';
$backupLastRun = trim(pbSetting('backup_last_run', ''));
$backupLastStatus = trim(pbSetting('backup_last_status', ''));

pbAdminPageStart(
    'Backup & Restore Site',
    'backup',
    'Buat backup ZIP penuh (database + uploads), atur jadwal otomatis, atau ekspor JSON.',
    <<<'CSS'
        .backup-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .backup-stat { padding: 12px 13px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; }
        .backup-stat span { display: block; color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
        .backup-stat strong { display: block; margin-top: 3px; color: var(--admin-ink); font-size: 19px; letter-spacing: -.03em; }
        .backup-warning { margin: 14px 0 0; padding: 12px 13px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; font-size: 11px; line-height: 1.6; }
        .backup-list { margin: 0; padding-left: 18px; color: var(--admin-muted); font-size: 11px; line-height: 1.8; }
        .backup-file { padding: 11px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 9px; }
        .backup-zip-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .backup-zip-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .backup-file-list { display: grid; gap: 8px; margin-top: 12px; }
        .backup-file-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; }
        .backup-file-row strong { display: block; color: var(--admin-ink); font-size: 11px; overflow-wrap: anywhere; }
        .backup-file-row small { display: block; margin-top: 2px; color: #94a3b8; font-size: 9px; }
        .backup-file-actions { display: flex; gap: 5px; }
        .backup-schedule-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr); gap: 16px; }
        .backup-cron-box { padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; }
        .backup-cron-box label { display: block; margin-bottom: 6px; color: #64748b; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .backup-cron-box input { width: 100%; padding: 9px 10px; color: #334155; background: #fff; border: 1px solid #dbe3ec; border-radius: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; }
        .backup-cron-box code { display: block; margin-top: 8px; padding: 8px; color: #334155; background: #fff; border: 1px solid #eef2f7; border-radius: 7px; font-size: 10px; overflow-wrap: anywhere; }
        .backup-meta { display: flex; gap: 12px; flex-wrap: wrap; color: var(--admin-muted); font-size: 10px; }
        .backup-meta span { display: inline-flex; align-items: center; gap: 5px; }
        @media (max-width: 820px) {
            .backup-schedule-grid { grid-template-columns: 1fr; }
            .backup-file-row { grid-template-columns: 1fr; }
        }
CSS
);
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>
            <?php if ($backupError !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>Folder backup bermasalah: <?= pbE($backupError) ?></span></div>
            <?php endif; ?>

            <section class="admin-card" style="margin-bottom:16px;">
                <div class="admin-card-head">
                    <div><h2>Full Backup ZIP</h2><p>Database SQL, seluruh folder <code>uploads/</code>, dan manifest dalam satu arsip.</p></div>
                    <span class="admin-badge success"><?= count($backupFiles) ?> file · <?= pbFormatBytes($backupTotalSize) ?></span>
                </div>
                <div class="admin-card-body">
                    <div class="backup-zip-head">
                        <div>
                            <div class="backup-meta">
                                <span><i class="fa-solid fa-clock" aria-hidden="true"></i>Terakhir: <?= pbE($backupLastRun !== '' ? $backupLastRun : 'belum pernah') ?></span>
                                <span><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Status: <?= pbE($backupLastStatus !== '' ? $backupLastStatus : '—') ?></span>
                                <span><i class="fa-solid fa-folder" aria-hidden="true"></i><?= pbE($backupDirectory !== '' ? $backupDirectory : '—') ?></span>
                            </div>
                            <p class="admin-form-hint" style="margin-top:10px;">Arsip berisi <code>database.sql</code>, <code>manifest.json</code>, dan folder <code>uploads/</code>. File disimpan di luar document root dan hanya dapat diunduh oleh Super Admin.</p>
                        </div>
                        <form method="post" action="site_backup.php" class="backup-zip-actions" data-confirm="Buat backup ZIP penuh sekarang? Proses dapat memakan waktu jika folder uploads besar.">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="create_zip">
                            <button class="admin-btn primary" type="submit"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i>Buat Backup ZIP Sekarang</button>
                        </form>
                    </div>

                    <?php if ($backupFiles === []): ?>
                        <div class="backup-file-list"><div class="admin-empty"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i>Belum ada file backup ZIP. Klik tombol di atas untuk membuat backup pertama.</div></div>
                    <?php else: ?>
                        <div class="backup-file-list">
                            <?php foreach ($backupFiles as $fileRow): ?>
                                <div class="backup-file-row">
                                    <div><strong><?= pbE($fileRow['name']) ?></strong><small><?= pbFormatBytes((int)$fileRow['size']) ?> · <?= pbE($fileRow['modified_at']) ?></small></div>
                                    <div class="backup-file-actions">
                                        <form method="post" action="site_backup.php">
                                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                            <input type="hidden" name="form_action" value="download_zip">
                                            <input type="hidden" name="backup_file" value="<?= pbE($fileRow['name']) ?>">
                                            <button class="admin-btn small" type="submit"><i class="fa-solid fa-download" aria-hidden="true"></i>Unduh</button>
                                        </form>
                                        <form method="post" action="site_backup.php" data-confirm="Hapus file backup ini?">
                                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                            <input type="hidden" name="form_action" value="delete_zip">
                                            <input type="hidden" name="backup_file" value="<?= pbE($fileRow['name']) ?>">
                                            <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-card" style="margin-bottom:16px;">
                <div class="admin-card-head">
                    <div><h2>Jadwal Backup Otomatis</h2><p>Jalankan backup via cron menggunakan token rahasia.</p></div>
                    <span class="admin-badge <?= $backupSchedule === 'disabled' ? '' : 'brand' ?>"><?= $backupSchedule === 'disabled' ? 'Nonaktif' : pbE(ucfirst($backupSchedule)) ?></span>
                </div>
                <div class="admin-card-body">
                    <div class="backup-schedule-grid">
                        <form class="admin-form" method="post" action="site_backup.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="save_schedule">
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Frekuensi</span>
                                    <select class="admin-input" name="backup_schedule">
                                        <option value="disabled" <?= $backupSchedule === 'disabled' ? 'selected' : '' ?>>Nonaktif</option>
                                        <option value="daily" <?= $backupSchedule === 'daily' ? 'selected' : '' ?>>Harian</option>
                                        <option value="weekly" <?= $backupSchedule === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                                        <option value="monthly" <?= $backupSchedule === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                    </select>
                                </label>
                                <label class="admin-field"><span>Retensi <small>1–50 file</small></span>
                                    <input class="admin-input" type="number" name="backup_retention" min="1" max="50" value="<?= $backupRetention ?>" required>
                                </label>
                            </div>
                            <label class="admin-field" style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="regenerate_token" value="1"> <span style="margin:0;">Buat ulang token cron</span></label>
                            <div class="admin-form-actions"><button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Simpan Jadwal</button></div>
                        </form>
                        <div class="backup-cron-box">
                            <?php if ($cronUrl !== ''): ?>
                                <label for="backup-cron-url">URL cron</label>
                                <input id="backup-cron-url" type="text" readonly value="<?= pbE($cronUrl) ?>">
                                <code><?= pbE('php84 ' . __DIR__ . '/backup-cron.php') ?></code>
                                <small class="admin-form-hint">Panggil URL di atas secara berkala (misalnya tiap jam) dari cron/job scheduler. Backup hanya dibuat jika interval jadwal sudah terlewati.</small>
                            <?php else: ?>
                                <p class="admin-form-hint">Token cron belum dibuat. Simpan jadwal untuk membuat token otomatis.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-grid two">
                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Backup JSON (Database)</h2><p>Ekspor data database tanpa folder uploads.</p></div>
                        <span class="admin-badge success"><?= $totalRows ?> baris</span>
                    </div>
                    <div class="admin-card-body">
                        <div class="backup-stats">
                            <div class="backup-stat"><span>Halaman</span><strong><?= $counts['pages'] ?></strong></div>
                            <div class="backup-stat"><span>Berita</span><strong><?= $counts['posts'] ?></strong></div>
                            <div class="backup-stat"><span>Agenda</span><strong><?= $counts['events'] ?></strong></div>
                            <div class="backup-stat"><span>Guru/Staf</span><strong><?= $counts['staff'] ?></strong></div>
                            <div class="backup-stat"><span>Galeri</span><strong><?= $counts['gallery'] ?></strong></div>
                            <div class="backup-stat"><span>Dokumen</span><strong><?= $counts['downloads'] ?></strong></div>
                            <div class="backup-stat"><span>Link</span><strong><?= $counts['links'] ?></strong></div>
                            <div class="backup-stat"><span>Pesan</span><strong><?= $counts['form_submissions'] ?></strong></div>
                            <div class="backup-stat"><span>Template</span><strong><?= $counts['templates'] ?></strong></div>
                            <div class="backup-stat"><span>Pengaturan</span><strong><?= $counts['settings'] ?></strong></div>
                            <div class="backup-stat"><span>User</span><strong><?= $counts['users'] ?></strong></div>
                        </div>
                        <p class="backup-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Backup JSON berisi data database, termasuk hash kata sandi. File media di folder <code>uploads/</code> tidak termasuk; gunakan <strong>Full Backup ZIP</strong> di atas untuk menyertakan media.</p>
                        <form method="post" action="site_backup.php" style="margin-top:14px;">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="export">
                            <button class="admin-btn primary" type="submit"><i class="fa-solid fa-download" aria-hidden="true"></i>Download Backup JSON</button>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Restore Backup JSON</h2><p>Ganti data website dengan file backup JSON.</p></div>
                        <span class="admin-badge warning">Hati-hati</span>
                    </div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="site_backup.php" enctype="multipart/form-data" data-confirm="Restore akan MENGGANTI seluruh data halaman, berita, agenda, guru/staf, galeri, dokumen, link, pengaturan, pesan, template, dan user. Lanjutkan?">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="restore">
                            <label class="admin-field" for="backup-file"><span>File Backup JSON <small>maks. 25 MB</small></span>
                                <input class="admin-input backup-file" id="backup-file" name="backup_file" type="file" accept=".json,application/json" required>
                            </label>
                            <p class="backup-warning"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Proses restore akan menghapus data saat ini lalu mengimpor data dari file. Pastikan Anda sudah membuat backup terbaru.</p>
                            <div class="admin-form-actions">
                                <button class="admin-btn danger" type="submit"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>Restore Sekarang</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>

            <section class="admin-card" style="margin-top:16px;">
                <div class="admin-card-head"><div><h2>Isi Backup</h2><p>Tabel yang disertakan dalam file.</p></div></div>
                <div class="admin-card-body">
                    <ul class="backup-list">
                        <li><strong>pages</strong> — halaman dan layout page builder.</li>
                        <li><strong>posts</strong> — berita dan artikel.</li>
                        <li><strong>events</strong> — agenda dan pengumuman.</li>
                        <li><strong>staff</strong> — direktori guru, staf, dan instruktur.</li>
                        <li><strong>downloads</strong> — data dokumen dan tautan unduhan.</li>
                        <li><strong>gallery</strong> — metadata foto dan path media.</li>
                        <li><strong>links</strong> — link aplikasi dan menu website.</li>
                        <li><strong>settings</strong> — identitas situs, logo, kontak, dan sosial media.</li>
                        <li><strong>templates</strong> — template kustom editor.</li>
                        <li><strong>form_submissions</strong> — pesan dari formulir.</li>
                        <li><strong>users</strong> — akun admin dan editor beserta hash kata sandi.</li>
                    </ul>
                </div>
            </section>
<?php pbAdminPageEnd(); ?>
