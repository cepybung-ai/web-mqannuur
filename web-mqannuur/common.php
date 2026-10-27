<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function pbE($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pbLocalConfig(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $config = [];
    $path = __DIR__ . '/config.local.php';
    if (!is_file($path)) {
        return $config;
    }
    try {
        $loaded = require $path;
    } catch (Throwable $exception) {
        error_log('[Page Builder] Config error: ' . $exception->getMessage());
        throw new RuntimeException('File config.local.php tidak valid. Periksa sintaks dan nilainya.');
    }
    if (is_array($loaded)) {
        $config = $loaded;
    }
    return $config;
}

function pbConfigValue(string $key, string $envKey, string $default = ''): string
{
    $config = pbLocalConfig();
    if (array_key_exists($key, $config)) {
        return trim((string)$config[$key]);
    }
    $value = getenv($envKey);
    return $value === false || trim((string)$value) === '' ? $default : trim((string)$value);
}

function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbName = pbConfigValue('db_name', 'WEB_MQ_DB_NAME', 'web_mqannuur');
    $dbUser = pbConfigValue('db_user', 'WEB_MQ_DB_USER', 'web_mq_local');
    $dbPass = pbConfigValue('db_pass', 'WEB_MQ_DB_PASS', '');
    $dbHost = pbConfigValue('db_host', 'WEB_MQ_DB_HOST', '');
    $dbPort = pbConfigValue('db_port', 'WEB_MQ_DB_PORT', '3306');
    if ($dbPort === '' || !preg_match('/^\d{1,5}$/', $dbPort)) {
        $dbPort = '3306';
    }
    if ($dbHost !== '') {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    } else {
        $dbSocket = pbConfigValue('db_socket', 'WEB_MQ_DB_SOCKET', '');
        if ($dbSocket === '') {
            $dbSocket = '/tmp/webmq-mariadb.sock';
        }
        if (!file_exists($dbSocket)) {
            $hint = is_file(__DIR__ . '/config.local.php')
                ? 'Periksa db_host/db_socket di config.local.php.'
                : 'Salin config.example.php menjadi config.local.php lalu isi kredensial database hosting.';
            throw new RuntimeException('Socket database tidak ditemukan: ' . $dbSocket . '. ' . $hint);
        }
        $dsn = "mysql:unix_socket={$dbSocket};dbname={$dbName};charset=utf8mb4";
    }

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        error_log('[Page Builder] Database connection error: ' . $exception->getMessage());
        throw new RuntimeException('Koneksi database gagal. Periksa config.local.php atau environment database.', 0, $exception);
    }

    return $pdo;
}
function pbNormalizeRole($role): string
{
    $role = strtolower(trim((string)$role));
    if (in_array($role, ['super_admin', 'admin', 'operator'], true)) {
        return 'super_admin';
    }
    return 'editor';
}

function pbClientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip)) {
        return '';
    }
    return substr(preg_replace('/[^0-9a-fA-F:.,]/', '', $ip) ?? '', 0, 45);
}

function logActivity(string $action, string $entity = '', $entityId = null, string $details = '', ?string $username = null, ?string $role = null): bool
{
    $actor = null;
    if ($username === null || $role === null) {
        $actor = authUser();
        if (!is_array($actor)) {
            $actor = null;
        }
    }

    $userId = $actor !== null ? (int)($actor['id'] ?? 0) : 0;
    $username = $username ?? (string)($actor['username'] ?? 'system');
    $role = $role ?? pbNormalizeRole($actor['role'] ?? '');
    if ($username === '') {
        $username = 'system';
    }
    if ($role === '') {
        $role = 'guest';
    }

    $action = substr(preg_replace('/[^a-z0-9_.-]/i', '_', $action) ?? 'unknown', 0, 50);
    $entity = substr(preg_replace('/[^a-z0-9_.-]/i', '_', $entity) ?? '', 0, 50);
    $entityId = $entityId === null ? '' : substr((string)$entityId, 0, 64);
    $details = substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $details) ?? '', 0, 2000);
    $username = substr($username, 0, 50);
    $role = substr($role, 0, 30);
    $ip = pbClientIp();
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $statement = getDbConnection()->prepare(
            'INSERT INTO activity_logs (user_id, username, role, action, entity, entity_id, details, ip, user_agent)
             VALUES (:user_id, :username, :role, :action, :entity, :entity_id, :details, :ip, :user_agent)'
        );
        $statement->execute([
            'user_id' => $userId > 0 ? $userId : null,
            'username' => $username,
            'role' => $role,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'details' => $details !== '' ? $details : null,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
        return true;
    } catch (Throwable $exception) {
        error_log('[Page Builder] Audit log error: ' . $exception->getMessage());
        return false;
    }
}

function pbLoginFailureCount(PDO $pdo, string $ip, int $windowSeconds = 900): int
{
    $windowSeconds = max(60, min(86400, $windowSeconds));
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = :ip AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . $windowSeconds . ' SECOND)'
    );
    $statement->execute(['ip' => $ip]);
    return (int)$statement->fetchColumn();
}

function pbRecordLoginFailure(PDO $pdo, string $ip, string $username): void
{
    $statement = $pdo->prepare('INSERT INTO login_attempts (ip, username) VALUES (:ip, :username)');
    $statement->execute([
        'ip' => substr($ip, 0, 45),
        'username' => substr($username, 0, 50),
    ]);
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}

function pbClearLoginFailures(PDO $pdo, string $ip): void
{
    $statement = $pdo->prepare('DELETE FROM login_attempts WHERE ip = :ip');
    $statement->execute(['ip' => substr($ip, 0, 45)]);
}

function pbSetSetting(string $key, string $value): void
{
    $statement = getDbConnection()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute(['key' => $key, 'value' => $value]);
}

if (!function_exists('authUser')) {
    function authUser()
    {
        if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return [
                'id' => (int)($_SESSION['admin_id'] ?? 0),
                'username' => (string)($_SESSION['admin_username'] ?? 'admin'),
                'name' => (string)($_SESSION['admin_name'] ?? ''),
                'role' => pbNormalizeRole($_SESSION['admin_role'] ?? 'editor'),
            ];
        }
        $legacyUser = $_SESSION['web_mqannuur_user'] ?? null;
        if (is_array($legacyUser)) {
            $legacyUser['role'] = pbNormalizeRole($legacyUser['role'] ?? 'editor');
        }
        return $legacyUser;
    }
}
function pbSettingsDefaults(): array
{
    return [
        'site_name' => 'Web MQ An-Nuur',
        'site_tagline' => 'Madrasah Qur\'an An-Nuur',
        'site_description' => 'Pusat pendidikan Al-Qur\'an dan kegiatan keislaman.',
        'site_logo' => '',
        'site_whatsapp' => '',
        'site_email' => '',
        'site_phone' => '',
        'site_address' => '',
        'site_facebook' => '',
        'site_instagram' => '',
        'site_youtube' => '',
        'footer_text' => '',
        'active_theme' => 'default',
        'seo_meta_title' => '',
        'seo_meta_description' => '',
        'seo_og_image' => '',
        'seo_site_url' => '',
        'backup_schedule' => 'disabled',
        'backup_retention' => '10',
        'backup_token' => '',
        'backup_last_run' => '',
        'backup_last_status' => '',
        'backup_last_file' => '',
    ];
}

function pbSettings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }
    $settings = pbSettingsDefaults();
    try {
        $rows = getDbConnection()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if ($key !== '') {
                $settings[$key] = (string)($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Settings load error: ' . $exception->getMessage());
    }
    return $settings;
}

function pbSetting(string $key, string $default = ''): string
{
    $settings = pbSettings();
    $value = trim((string)($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
}

function pbSiteBaseUrl(): string
{
    static $baseUrl = null;
    if (is_string($baseUrl)) {
        return $baseUrl;
    }

    $configured = trim(pbSetting('seo_site_url'));
    if ($configured !== '') {
        $safe = pbSafeUrl($configured);
        $parts = $safe !== null ? parse_url($safe) : false;
        if ($safe !== null && is_array($parts) && !empty($parts['host']) && in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
            $baseUrl = rtrim((string)preg_replace('/[?#].*$/', '', $safe), '/');
            return $baseUrl;
        }
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.\-:\[\]]+$/i', $host)) {
        $host = 'localhost';
    }
    $baseUrl = ($https ? 'https' : 'http') . '://' . $host . pbBasePath();
    return $baseUrl;
}

function pbAbsoluteUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $url)) {
        $safe = pbSafeUrl($url);
        return $safe !== null ? $safe : '';
    }
    $safe = pbSafeUrl($url);
    if ($safe === null || preg_match('~^(?:mailto:|tel:|data:|//)~i', $safe)) {
        return '';
    }
    return pbSiteBaseUrl() . '/' . ltrim($safe, '/');
}

function pbSeoMeta(array $context = []): array
{
    $siteName = trim((string)($context['site_name'] ?? '')) ?: pbSetting('site_name', 'Web MQ An-Nuur');
    $pageTitle = trim(strip_tags((string)($context['title'] ?? '')));
    $pageDescription = trim(strip_tags((string)($context['description'] ?? '')));
    $isHome = !empty($context['is_home']);
    $seoTitle = trim(pbSetting('seo_meta_title'));
    $seoDescription = trim(pbSetting('seo_meta_description'));
    $siteDescription = trim(pbSetting('site_description'));

    $title = $pageTitle !== '' ? $pageTitle : $siteName;
    if ($isHome) {
        $title = $seoTitle !== '' ? $seoTitle : $title;
    } else {
        $suffix = $seoTitle !== '' ? $seoTitle : $siteName;
        if ($suffix !== '' && $title !== $suffix) {
            $title .= ' — ' . $suffix;
        }
    }

    $description = $pageDescription !== '' ? $pageDescription : ($seoDescription !== '' ? $seoDescription : $siteDescription);
    $imageSource = trim((string)($context['og_image'] ?? ''));
    if ($imageSource === '') {
        $imageSource = trim(pbSetting('seo_og_image'));
    }
    if ($imageSource === '') {
        $imageSource = trim((string)($context['logo'] ?? ''));
    }

    $canonical = trim((string)($context['canonical'] ?? ''));
    if ($canonical === '') {
        $canonical = pbSiteBaseUrl() . '/index.php';
    }

    return [
        'title' => $title,
        'description' => $description,
        'og_image' => pbAbsoluteUrl($imageSource),
        'canonical' => $canonical,
    ];
}

function pbThemeCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }
    $catalog = [];
    $root = __DIR__ . '/themes';
    $entries = is_dir($root) ? scandir($root) : [];
    if (!is_array($entries)) {
        return $catalog;
    }
    $required = ['header.php', 'footer.php', 'index.php', 'page.php', 'single-post.php'];
    foreach ($entries as $entry) {
        if (!is_string($entry) || !preg_match('/^[a-z0-9][a-z0-9-]{0,49}$/', $entry)) {
            continue;
        }
        $directory = $root . '/' . $entry;
        if (!is_dir($directory)) {
            continue;
        }
        foreach ($required as $file) {
            if (!is_file($directory . '/' . $file)) {
                continue 2;
            }
        }
        $name = ucwords(str_replace('-', ' ', $entry));
        $description = 'Template website ' . $name . '.';
        $accent = '#0f5a4a';
        $version = '1.0.0';
        $features = [];
        $manifest = $directory . '/theme.json';
        if (is_file($manifest)) {
            $decoded = json_decode((string)@file_get_contents($manifest), true);
            if (is_array($decoded)) {
                $candidateName = trim(strip_tags((string)($decoded['name'] ?? '')));
                $candidateDescription = trim(strip_tags((string)($decoded['description'] ?? '')));
                $candidateAccent = trim((string)($decoded['accent'] ?? ''));
                $candidateVersion = trim(strip_tags((string)($decoded['version'] ?? '')));
                if ($candidateName !== '') {
                    $name = function_exists('mb_substr') ? mb_substr($candidateName, 0, 80, 'UTF-8') : substr($candidateName, 0, 80);
                }
                if ($candidateDescription !== '') {
                    $description = function_exists('mb_substr') ? mb_substr($candidateDescription, 0, 240, 'UTF-8') : substr($candidateDescription, 0, 240);
                }
                if (preg_match('/^#[0-9a-f]{6}$/i', $candidateAccent)) {
                    $accent = strtolower($candidateAccent);
                }
                if ($candidateVersion !== '' && preg_match('/^[0-9a-z._-]{1,20}$/i', $candidateVersion)) {
                    $version = $candidateVersion;
                }
                if (is_array($decoded['features'] ?? null)) {
                    foreach ($decoded['features'] as $feature) {
                        if (!is_scalar($feature)) {
                            continue;
                        }
                        $feature = trim(strip_tags((string)$feature));
                        if ($feature !== '') {
                            $features[] = function_exists('mb_substr') ? mb_substr($feature, 0, 80, 'UTF-8') : substr($feature, 0, 80);
                        }
                        if (count($features) >= 6) {
                            break;
                        }
                    }
                }
            }
        }
        $catalog[$entry] = [
            'slug' => $entry,
            'name' => $name,
            'description' => $description,
            'accent' => $accent,
            'version' => $version,
            'features' => $features,
            'path' => 'themes/' . $entry,
        ];
    }
    uksort($catalog, static function (string $a, string $b): int {
        if ($a === 'default') {
            return -1;
        }
        if ($b === 'default') {
            return 1;
        }
        return strcmp($a, $b);
    });
    return $catalog;
}

function pbActiveTheme(): string
{
    $catalog = pbThemeCatalog();
    $active = trim(pbSetting('active_theme', 'default'));
    if (isset($catalog[$active])) {
        return $active;
    }
    if (isset($catalog['default'])) {
        return 'default';
    }
    $first = array_key_first($catalog);
    return is_string($first) ? $first : 'default';
}

function pbCurrentTheme(): string
{
    $catalog = pbThemeCatalog();
    if (pbIsAdmin() && isset($_GET['theme']) && is_string($_GET['theme'])) {
        $candidate = strtolower(trim($_GET['theme']));
        if (isset($catalog[$candidate])) {
            return $candidate;
        }
    }
    return pbActiveTheme();
}

function pbThemeBaseUrl(?string $theme = null): string
{
    $theme = $theme !== null ? trim($theme) : pbCurrentTheme();
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,49}$/', $theme)) {
        $theme = pbActiveTheme();
    }
    return 'themes/' . rawurlencode($theme);
}

function pbThemeHomeHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('/^\s*<main\b[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<\/main>\s*$/i', '', $html) ?? $html;
    return trim($html);
}

function pbPublicLinks(): array
{
    try {
        return getDbConnection()
            ->query('SELECT title, url, icon, category FROM links WHERE is_active = 1 ORDER BY category ASC, sort_order ASC, id ASC')
            ->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function pbWhatsappUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $value)) {
        return $value;
    }
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    return $digits !== '' ? 'https://wa.me/' . $digits : '';
}

function pbFormatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', '.') . ' ' . $units[$power];
}

function pbDeleteUploadedFile(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'uploads/')) {
        return false;
    }
    $uploadsRoot = realpath(__DIR__ . '/uploads');
    $absolute = realpath(__DIR__ . '/' . $relativePath);
    if ($uploadsRoot === false || $absolute === false || !str_starts_with($absolute, $uploadsRoot . DIRECTORY_SEPARATOR)) {
        return false;
    }
    return is_file($absolute) && unlink($absolute);
}

function pbUploadedImage(array $file, string $subdir, int $maxBytes = 8_388_608): array
{
    $result = ['ok' => false, 'error' => '', 'path' => '', 'original_name' => '', 'mime' => '', 'size' => 0, 'width' => 0, 'height' => 0, 'converted' => false, 'warning' => ''];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $result['error'] = 'Berkas gambar gagal diunggah.';
        return $result;
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    $result['original_name'] = substr(basename((string)($file['name'] ?? 'gambar')), 0, 255);
    $result['size'] = $size;
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $result['error'] = 'Berkas gambar tidak valid.';
        return $result;
    }
    if ($size < 1 || $size > $maxBytes) {
        $result['error'] = 'Ukuran gambar maksimal ' . pbFormatBytes($maxBytes) . '.';
        return $result;
    }
    $info = @getimagesize($tmp);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        $result['error'] = 'File bukan gambar yang didukung.';
        return $result;
    }
    $mime = (string)($info['mime'] ?? '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $result['error'] = 'Format gambar harus JPG, PNG, GIF, atau WebP.';
        return $result;
    }
    $result['mime'] = $mime;
    $result['width'] = (int)$info[0];
    $result['height'] = (int)$info[1];
    if (!preg_match('/^[a-z0-9_-]+$/', $subdir)) {
        $result['error'] = 'Folder unggahan tidak valid.';
        return $result;
    }
    $directory = __DIR__ . '/uploads/' . $subdir;
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        $result['error'] = 'Folder unggahan tidak dapat dibuat.';
        return $result;
    }
    $base = bin2hex(random_bytes(12));
    $converted = false;
    if ($mime !== 'image/webp' && function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
        $image = @imagecreatefromstring((string)file_get_contents($tmp));
        if ($image !== false) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $destination = $directory . '/' . $base . '.webp';
            if (@imagewebp($image, $destination, 84)) {
                $converted = true;
                $result['path'] = 'uploads/' . $subdir . '/' . $base . '.webp';
                $result['mime'] = 'image/webp';
                $result['size'] = (int)(filesize($destination) ?: 0);
            }
            imagedestroy($image);
        }
    }
    if (!$converted) {
        $extension = $allowed[$mime];
        $destination = $directory . '/' . $base . '.' . $extension;
        if (!@move_uploaded_file($tmp, $destination)) {
            $result['error'] = 'Gambar tidak dapat disimpan.';
            return $result;
        }
        $result['path'] = 'uploads/' . $subdir . '/' . $base . '.' . $extension;
        if (!function_exists('imagewebp')) {
            $result['warning'] = 'GD/WebP tidak tersedia di server; file asli disimpan tanpa konversi.';
        }
    }
    $result['converted'] = $converted;
    $result['ok'] = true;
    return $result;
}

function pbUploadedDocument(array $file, int $maxBytes = 26_214_400): array
{
    $result = ['ok' => false, 'error' => '', 'path' => '', 'file_name' => '', 'size' => 0];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $result;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $result['error'] = 'Berkas gagal diunggah.';
        return $result;
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    $name = substr(basename((string)($file['name'] ?? 'dokumen')), 0, 255);
    if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > $maxBytes) {
        $result['error'] = 'Berkas tidak valid atau melebihi batas ' . pbFormatBytes($maxBytes) . '.';
        return $result;
    }
    $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt'];
    if (!in_array($extension, $allowed, true)) {
        $result['error'] = 'Format berkas harus salah satu dari: ' . implode(', ', $allowed) . '.';
        return $result;
    }
    $directory = __DIR__ . '/uploads/downloads';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        $result['error'] = 'Folder unduhan tidak dapat dibuat.';
        return $result;
    }
    $stored = bin2hex(random_bytes(12)) . '.' . $extension;
    if (!@move_uploaded_file($tmp, $directory . '/' . $stored)) {
        $result['error'] = 'Berkas tidak dapat disimpan.';
        return $result;
    }
    $result['ok'] = true;
    $result['path'] = 'uploads/downloads/' . $stored;
    $result['file_name'] = $name;
    $result['size'] = $size;
    return $result;
}

function pbIsAdmin(): bool
{
    $user = authUser();
    return is_array($user) && pbNormalizeRole($user['role'] ?? '') === 'super_admin';
}

function pbJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function pbBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if (PHP_SAPI === 'cli' || $scriptName === '' || $scriptName[0] !== '/') {
        return '';
    }
    $scriptDir = dirname($scriptName);
    $base = preg_replace('~/page-builder$~', '', rtrim($scriptDir, '/')) ?? '';
    return $base === '' ? '' : $base;
}
function pbValidSlug($slug): bool { return is_string($slug) && strlen($slug) <= 100 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1; }
function pbTitleFromSlug(string $slug): string { return ucwords(str_replace('-', ' ', $slug)); }
function pbSafeUrl(string $url, bool $allowImageData = false): ?string
{
    $url = trim($url); $decodedUrl = rawurldecode($url);
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/[\x00-\x1f\x7f\\\\]/', $decodedUrl)) return null;
    if ($allowImageData && preg_match('~^data:image/(?:png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$~i', $url)) return $url;
    if (preg_match('~^(?:https?://|mailto:|tel:)~i', $url)) return $url;
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $decodedUrl) || str_starts_with($url, '//')) return null;
    return $url;
}
function pbValidTikTokUrl(string $url): bool
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048) return false;
    $safe = pbSafeUrl($url);
    if ($safe === null) return false;
    $parts = parse_url($safe);
    if (!is_array($parts) || empty($parts['host'])) return false;
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return false;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) return false;
    $host = strtolower((string)$parts['host']);
    if ($host !== 'tiktok.com' && !str_ends_with($host, '.tiktok.com')) return false;
    $path = (string)($parts['path'] ?? '');
    return preg_match('~^/(?:@[a-z0-9._-]+/(?:video|photo)/\d+|t/[a-z0-9]+)/?$~i', $path) === 1;
}

function pbTikTokCacheDirectory(): string
{
    $directory = trim((string)(getenv('PB_TIKTOK_CACHE_DIR') ?: ''));
    if ($directory === '') {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'web-mq-tiktok-cache';
    }
    $directory = rtrim($directory, '/\\');
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder cache TikTok tidak dapat dibuat.');
    }
    return $directory;
}

function pbTikTokHttpGet(string $url): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Permintaan TikTok tidak dapat dimulai.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'WebMQ-TikTokOEmbed/1.0 (+https://webmq.local)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('TikTok oEmbed gagal diambil' . ($error !== '' ? ': ' . $error : '.'));
        }
        if (strlen($raw) > 1_000_000) throw new RuntimeException('Respons TikTok terlalu besar.');
        return $raw;
    }

    if (!filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        throw new RuntimeException('Ekstensi cURL atau allow_url_fopen diperlukan untuk mengambil TikTok oEmbed.');
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 3,
            'header' => "Accept: application/json\r\nUser-Agent: WebMQ-TikTokOEmbed/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) {
        throw new RuntimeException('TikTok oEmbed tidak dapat dihubungi.');
    }
    $responseHeaders = $http_response_header ?? [];
    $statusLine = is_array($responseHeaders) ? (string)($responseHeaders[0] ?? '') : '';
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $statusLine, $matches) === 1 && ((int)$matches[1] < 200 || (int)$matches[1] >= 300)) {
        throw new RuntimeException('TikTok oEmbed mengembalikan status ' . $matches[1] . '.');
    }
    if (strlen($raw) > 1_000_000) throw new RuntimeException('Respons TikTok terlalu besar.');
    return $raw;
}

function pbTikTokNormalizeOEmbed(string $url, array $payload): array
{
    $title = trim(strip_tags((string)($payload['title'] ?? '')));
    $author = trim(strip_tags((string)($payload['author_name'] ?? '')));
    $authorUrl = pbSafeUrl((string)($payload['author_url'] ?? ''));
    $thumbnail = pbSafeUrl((string)($payload['thumbnail_url'] ?? ''));
    if ($authorUrl !== null && !preg_match('~^https?://~i', $authorUrl)) $authorUrl = null;
    if ($thumbnail !== null && !preg_match('~^https?://~i', $thumbnail)) $thumbnail = null;
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    if ($author === '' && preg_match('~^/@([a-z0-9._-]+)/~i', $path, $matches) === 1) {
        $author = (string)$matches[1];
    }
    if ($authorUrl === null && $author !== '') {
        $authorUrl = 'https://www.tiktok.com/@' . rawurlencode($author);
    }
    if ($title === '') {
        $title = $author !== '' ? 'Video TikTok dari @' . $author : 'Video TikTok';
    }
    $videoId = preg_replace('/\D+/', '', (string)($payload['embed_product_id'] ?? '')) ?? '';
    if ($videoId === '' && preg_match('~/(?:video|photo)/(\d+)~', $path, $matches) === 1) {
        $videoId = (string)$matches[1];
    }
    return [
        'url' => $url,
        'title' => substr($title, 0, 500),
        'author_name' => substr($author, 0, 200),
        'author_url' => $authorUrl,
        'thumbnail_url' => $thumbnail,
        'video_id' => $videoId,
        'provider_name' => 'TikTok',
        'fetched_at' => gmdate('c'),
    ];
}

function pbTikTokOEmbed(string $url, int $ttl = 21600): array
{
    $url = trim($url);
    if (!pbValidTikTokUrl($url)) {
        throw new InvalidArgumentException('URL TikTok tidak valid. Gunakan tautan video TikTok.');
    }

    $cacheDirectory = pbTikTokCacheDirectory();
    $cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR . hash('sha256', $url) . '.json';
    $cached = [];
    if (is_file($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decoded = is_string($rawCache) ? json_decode($rawCache, true) : null;
        if (is_array($decoded) && isset($decoded['title'], $decoded['fetched_at']) && is_string($decoded['title'])) {
            $cached = $decoded;
            $cachedAt = strtotime((string)$decoded['fetched_at']);
            if ($cachedAt !== false && (time() - $cachedAt) < max(300, $ttl)) {
                return $cached;
            }
        }
    }

    try {
        $endpoint = 'https://www.tiktok.com/oembed?url=' . rawurlencode($url);
        $payload = json_decode(pbTikTokHttpGet($endpoint), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) throw new RuntimeException('Respons TikTok oEmbed tidak valid.');
        $normalized = pbTikTokNormalizeOEmbed($url, $payload);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $temporary = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $encoded) !== false) {
            @rename($temporary, $cacheFile);
        }
        @unlink($temporary);
        return $normalized;
    } catch (Throwable $exception) {
        if ($cached !== []) {
            $cached['stale'] = true;
            return $cached;
        }
        throw new RuntimeException('Data TikTok belum dapat dimuat: ' . $exception->getMessage(), 0, $exception);
    }
}

function pbSanitizeCss(string $css): string
{
    $css = substr($css, 0, 2_000_000);
    $css = preg_replace('~</?style\b[^>]*>|</?script\b[^>]*>|<!--|-->~i', '', $css) ?? '';
    $css = preg_replace('~@import\b[^;]*(?:;|$)~i', '', $css) ?? '';
    $css = preg_replace('~expression\s*\([^)]*\)|-moz-binding\s*:[^;}]*(?:;|$)|\bbehavior\s*:[^;}]*(?:;|$)~i', '', $css) ?? '';
    $css = preg_replace('~(?:javascript|vbscript)\s*:~i', '', $css) ?? '';
    $css = preg_replace_callback('~url\s*\((.*?)\)~is', static function (array $match): string {
        $url = trim($match[1], " \t\n\r\0\x0B\"'"); $safe = pbSafeUrl($url, true);
        if ($safe === null || preg_match('~^(?:mailto:|tel:)~i', $safe)) return 'none';
        $safe = str_replace(['\\', '"'], ['\\\\', '\\"'], $safe); return 'url("' . $safe . '")';
    }, $css) ?? ''; return $css;
}
function pbAllowedHtmlTags(): array { return ['section','div','main','header','footer','article','aside','nav','h1','h2','h3','h4','h5','h6','p','span','small','strong','b','em','i','u','s','br','hr','a','img','button','ul','ol','li','blockquote','figure','figcaption','table','thead','tbody','tr','th','td','details','summary','video','source','form','label','input','textarea']; }
function pbSanitizeElementAttributes(DOMElement $element): void
{
    $allowed = ['class','id','title','alt','width','height','href','src','target','rel','role','type','loading','decoding','controls','poster','preload','playsinline','loop','muted','open','action','method','name','placeholder','required','rows','maxlength','minlength','autocomplete','hidden','value'];
    $attributes = []; foreach ($element->attributes as $attribute) { $attributes[] = [$attribute->name, $attribute->value]; }
    foreach ($attributes as [$name,$value]) {
        $name = strtolower($name);
        if (str_starts_with($name,'on') || $name==='style') { $element->removeAttribute($name); continue; }
        if (!in_array($name,$allowed,true) && !preg_match('/^(?:aria|data)-[a-z0-9_.:-]+$/',$name)) { $element->removeAttribute($name); continue; }
        if ($name==='href' || $name==='src' || $name==='poster' || $name==='action') { $safe = pbSafeUrl($value,$name==='src'||$name==='poster'); if ($safe===null) $element->removeAttribute($name); else $element->setAttribute($name,$safe); }
        if ($name==='target' && !in_array($value,['_blank','_self'],true)) $element->removeAttribute($name);
        if (($name==='width'||$name==='height') && !preg_match('/^\d{1,4}$/',$value)) $element->removeAttribute($name);
        if ($name==='type' && $element->tagName==='button' && !in_array($value,['button','submit','reset'],true)) $element->setAttribute('type','button');
        if ($element->tagName==='form' && $name==='method' && strtoupper($value)!=='POST') $element->setAttribute('method','POST');
        if ($element->tagName==='input' && $name==='type' && !in_array(strtolower($value),['text','email','hidden'],true)) $element->setAttribute('type','text');
        if ($name==='rows' && !preg_match('/^\d{1,3}$/',$value)) $element->removeAttribute($name);
        if (($name==='maxlength'||$name==='minlength') && !preg_match('/^\d{1,5}$/',$value)) $element->removeAttribute($name);
    }
    if ($element->tagName==='a' && $element->getAttribute('target')==='_blank') $element->setAttribute('rel','noopener noreferrer');
    if ($element->tagName==='button' && !in_array($element->getAttribute('type'),['button','submit','reset'],true)) $element->setAttribute('type','button');
}
function pbSanitizeHtmlChildren(DOMNode $parent): void
{
    $allowed = pbAllowedHtmlTags(); $dropEntirely = ['script','style','iframe','object','embed','svg','math','link','meta','base','select','option'];
    $children = []; foreach ($parent->childNodes as $child) { $children[]=$child; }
    foreach ($children as $child) {
        if ($child->parentNode!==$parent) continue;
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) { $parent->removeChild($child); continue; }
        if (!$child instanceof DOMElement) continue;
        $tag = strtolower($child->tagName);
        if (in_array($tag,$dropEntirely,true)) { $parent->removeChild($child); continue; }
        pbSanitizeHtmlChildren($child);
        if (!in_array($tag,$allowed,true)) { while ($child->firstChild) { $parent->insertBefore($child->firstChild,$child); } $parent->removeChild($child); continue; }
        pbSanitizeElementAttributes($child);
    }
}
function pbSanitizeHtml(string $html): string
{
    if (!class_exists(DOMDocument::class)) throw new RuntimeException('Ekstensi PHP DOM diperlukan');
    $html = substr($html,0,2_000_000); $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0','UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body><div id="pb-sanitize-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document); $root = $xpath->query('//*[@id="pb-sanitize-root"]')->item(0);
        if (!$root instanceof DOMElement) return ''; pbSanitizeHtmlChildren($root);
        $result=''; foreach ($root->childNodes as $child) { $result.=$document->saveHTML($child); } return $result;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}
function pbSanitizeComponents($value,int $depth=0)
{
    if ($depth>64 || !is_array($value)) return null;
    $isList = $value===[] || array_keys($value)===range(0,count($value)-1);
    if ($isList) { $clean=[]; foreach ($value as $item) { $item=pbSanitizeComponents($item,$depth+1); if ($item!==null) $clean[]=$item; } return $clean; }
    $tag = strtolower((string)($value['tagName']??'')); if (in_array($tag,['script','style','iframe','object','embed','svg','math','select'],true)) return null;
    $clean=[]; foreach ($value as $key=>$item) {
        $key=(string)$key; if (preg_match('/^on/i',$key) || in_array(strtolower($key),['script','script-export'],true)) continue;
        if ($key==='attributes' && is_array($item)) {
            $attributes=[]; foreach ($item as $attribute=>$attributeValue) {
                $attribute=strtolower((string)$attribute); if (str_starts_with($attribute,'on') || $attribute==='style') continue;
                if (!in_array($attribute,['class','id','title','alt','width','height','href','src','target','rel','role','type','loading','decoding','controls','poster','preload','playsinline','loop','muted','open','action','method','name','placeholder','required','rows','maxlength','minlength','autocomplete','hidden','value','tabindex'],true) && !preg_match('/^(?:aria|data)-[a-z0-9_.:-]+$/',$attribute)) continue;
                if (($attribute==='href'||$attribute==='src'||$attribute==='poster'||$attribute==='action') && is_string($attributeValue)) { $attributeValue=pbSafeUrl($attributeValue,$attribute==='src'||$attribute==='poster'); if ($attributeValue===null) continue; }
                if ($attribute==='action' && is_string($attributeValue) && preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $attributeValue)) continue;
                $attributes[$attribute]=$attributeValue;
            } $clean[$key]=$attributes;
        } elseif ($key==='style' && is_array($item)) {
            $styles=[]; foreach ($item as $property=>$styleValue) {
                $property=strtolower((string)$property); if (!preg_match('/^(?:--[a-z0-9_-]+|[a-z][a-z0-9-]*)$/',$property) || in_array($property,['behavior','-moz-binding'],true)) continue;
                if (is_scalar($styleValue) && !preg_match('~(?:expression\s*\(|javascript\s*:|vbscript\s*:|</style|url\s*\()~i',(string)$styleValue)) $styles[$property]=$styleValue;
            } $clean[$key]=$styles;
        } elseif ($key==='classes' && is_array($item)) {
            $classes=[]; foreach ($item as $className) {
                if (!is_string($className) || strlen($className)>200 || !preg_match('/^[a-z0-9 _:.\/\-\[\]%#]+$/i',$className)) continue;
                $className=trim($className); if ($className!=='') $classes[]=$className;
            } $clean[$key]=$classes;
        } elseif ($key==='content' && is_string($item)) $clean[$key]=pbSanitizeHtml($item);
        elseif (is_array($item)) { $nested=pbSanitizeComponents($item,$depth+1); if ($nested!==null) $clean[$key]=$nested; }
        else $clean[$key]=$item;
    } return $clean;
}
function pbRequireOperatorJson(): void
{
    $user = authUser();
    if (!$user) {
        pbJsonResponse(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
    }
    $role = pbNormalizeRole($user['role'] ?? '');
    if (!in_array($role, ['super_admin', 'editor'], true)) {
        pbJsonResponse(['success' => false, 'message' => 'Akses hanya untuk pengguna admin atau editor.'], 403);
    }
}
