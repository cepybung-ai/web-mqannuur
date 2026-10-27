<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

function pbSeoWriteFile(string $filename, string $content): void
{
    if (!in_array($filename, ['sitemap.xml', 'robots.txt'], true)) {
        throw new InvalidArgumentException('Nama file SEO tidak valid.');
    }
    $target = __DIR__ . '/' . $filename;
    $temp = tempnam(__DIR__, $filename . '.tmp.');
    if ($temp === false) {
        throw new RuntimeException('File sementara SEO tidak dapat dibuat.');
    }
    try {
        if (file_put_contents($temp, $content) === false) {
            throw new RuntimeException('File ' . $filename . ' tidak dapat ditulis.');
        }
        if (!@rename($temp, $target)) {
            throw new RuntimeException('File ' . $filename . ' tidak dapat diperbarui. Periksa izin folder.');
        }
        @chmod($target, 0644);
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
}

function pbSeoSitemap(PDO $pdo): array
{
    $baseUrl = pbSiteBaseUrl();
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $entries = [];
    $homeLastmod = null;

    $pages = $pdo->query('SELECT slug, updated_at FROM pages ORDER BY updated_at DESC')->fetchAll();
    foreach ($pages as $page) {
        $slug = (string)($page['slug'] ?? '');
        if (!pbValidSlug($slug)) {
            continue;
        }
        if ($slug === 'home') {
            $homeLastmod = (string)($page['updated_at'] ?? '');
            continue;
        }
        $entries[] = [
            'loc' => $baseUrl . '/index.php?page=' . rawurlencode($slug),
            'lastmod' => (string)($page['updated_at'] ?? ''),
            'priority' => '0.8',
        ];
    }

    $posts = $pdo->query("SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY updated_at DESC")->fetchAll();
    foreach ($posts as $post) {
        $slug = (string)($post['slug'] ?? '');
        if (!pbValidSlug($slug)) {
            continue;
        }
        $entries[] = [
            'loc' => $baseUrl . '/post.php?slug=' . rawurlencode($slug),
            'lastmod' => (string)($post['updated_at'] ?? ''),
            'priority' => '0.7',
        ];
    }

    array_unshift($entries, [
        'loc' => $baseUrl . '/index.php',
        'lastmod' => $homeLastmod ?? '',
        'priority' => '1.0',
    ]);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $lastmod = '';
        if ($entry['lastmod'] !== '') {
            $timestamp = strtotime($entry['lastmod']);
            if ($timestamp !== false) {
                $lastmod = date('c', $timestamp);
            }
        }
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $escape($entry['loc']) . '</loc>' . "\n";
        if ($lastmod !== '') {
            $xml .= '    <lastmod>' . $escape($lastmod) . '</lastmod>' . "\n";
        }
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>' . $escape($entry['priority']) . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    $xml .= '</urlset>' . "\n";

    return ['xml' => $xml, 'count' => count($entries)];
}

function pbSeoRobots(): string
{
    $baseUrl = pbSiteBaseUrl();
    $basePath = (string)(parse_url($baseUrl, PHP_URL_PATH) ?? '');
    $prefix = $basePath !== '' && $basePath !== '/' ? rtrim($basePath, '/') . '/' : '/';
    $disallow = [
        $prefix . 'admin-',
        $prefix . 'api.php',
        $prefix . 'editor.php',
        $prefix . 'login.php',
        $prefix . 'logout.php',
        $prefix . 'site_backup.php',
        $prefix . 'backup-cron.php',
        $prefix . 'backups/',
        $prefix . 'database/',
        $prefix . 'includes/',
    ];

    $lines = [
        'User-agent: *',
        'Allow: ' . $prefix,
    ];
    foreach ($disallow as $path) {
        $lines[] = 'Disallow: ' . $path;
    }
    $lines[] = '';
    $lines[] = 'Sitemap: ' . $baseUrl . '/sitemap.xml';
    $lines[] = '';
    return implode("\n", $lines);
}

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$settings = pbSettings();
$fields = ['seo_meta_title', 'seo_meta_description', 'seo_og_image', 'seo_site_url'];

try {
    $pdo = getDbConnection();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        if ($action === 'save') {
            $input = [];
            foreach ($fields as $field) {
                $input[$field] = trim(is_string($_POST[$field] ?? null) ? strip_tags($_POST[$field]) : '');
            }
            $input['seo_meta_title'] = preg_replace('/\s+/u', ' ', $input['seo_meta_title']) ?? '';
            $input['seo_meta_description'] = preg_replace('/\s+/u', ' ', $input['seo_meta_description']) ?? '';
            if (function_exists('mb_substr')) {
                $input['seo_meta_title'] = mb_substr($input['seo_meta_title'], 0, 255, 'UTF-8');
                $input['seo_meta_description'] = mb_substr($input['seo_meta_description'], 0, 500, 'UTF-8');
            } else {
                $input['seo_meta_title'] = substr($input['seo_meta_title'], 0, 255);
                $input['seo_meta_description'] = substr($input['seo_meta_description'], 0, 500);
            }

            if ($input['seo_site_url'] !== '') {
                $safeSiteUrl = pbSafeUrl($input['seo_site_url']);
                $siteParts = $safeSiteUrl !== null ? parse_url($safeSiteUrl) : false;
                if ($safeSiteUrl === null || !is_array($siteParts) || empty($siteParts['host']) || !in_array(strtolower((string)$siteParts['scheme']), ['http', 'https'], true)) {
                    throw new InvalidArgumentException('URL situs harus berupa alamat lengkap http:// atau https:// dengan host yang valid.');
                }
                $input['seo_site_url'] = rtrim((string)preg_replace('/[?#].*$/', '', $safeSiteUrl), '/');
            }

            if ($input['seo_og_image'] !== '') {
                $safeImage = pbSafeUrl($input['seo_og_image']);
                if ($safeImage === null || preg_match('~^(?:mailto:|tel:|data:)~i', $safeImage)) {
                    throw new InvalidArgumentException('URL gambar Open Graph tidak valid.');
                }
                $input['seo_og_image'] = $safeImage;
            }

            $oldImage = (string)($settings['seo_og_image'] ?? '');
            $deleteOldImage = '';
            if (isset($_FILES['seo_og_image_file']) && is_array($_FILES['seo_og_image_file']) && ($_FILES['seo_og_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = pbUploadedImage($_FILES['seo_og_image_file'], 'seo', 4_194_304);
                if (!$upload['ok']) {
                    throw new InvalidArgumentException($upload['error']);
                }
                $input['seo_og_image'] = $upload['path'];
                if ($oldImage !== '' && str_starts_with($oldImage, 'uploads/seo/') && $oldImage !== $input['seo_og_image']) {
                    $deleteOldImage = $oldImage;
                }
            }

            $statement = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($fields as $field) {
                $statement->execute(['key' => $field, 'value' => (string)($input[$field] ?? '')]);
            }
            if ($deleteOldImage !== '') {
                pbDeleteUploadedFile($deleteOldImage);
            }
            logActivity('update', 'seo', 'settings', 'Pengaturan SEO diperbarui');
            pbAdminFlash('success', 'Pengaturan SEO berhasil disimpan.');
            header('Location: admin-seo.php', true, 303);
            exit;
        }

        if ($action === 'generate_sitemap') {
            $result = pbSeoSitemap($pdo);
            pbSeoWriteFile('sitemap.xml', $result['xml']);
            logActivity('generate', 'seo', 'sitemap.xml', 'Sitemap dibuat dengan ' . $result['count'] . ' URL');
            pbAdminFlash('success', 'sitemap.xml berhasil dibuat dengan ' . $result['count'] . ' URL.');
            header('Location: admin-seo.php', true, 303);
            exit;
        }

        if ($action === 'generate_robots') {
            pbSeoWriteFile('robots.txt', pbSeoRobots());
            logActivity('generate', 'seo', 'robots.txt', 'robots.txt dibuat');
            pbAdminFlash('success', 'robots.txt berhasil dibuat.');
            header('Location: admin-seo.php', true, 303);
            exit;
        }

        throw new InvalidArgumentException('Aksi SEO tidak dikenal.');
    }

    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    foreach ($rows as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = trim(is_string($_POST[$field]) ? strip_tags($_POST[$field]) : '');
            }
        }
    }
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin SEO error: ' . $exception->getMessage());
    $errorMessage = 'Modul SEO belum dapat diproses. Periksa koneksi database dan izin folder.';
}

$sitemapPath = __DIR__ . '/sitemap.xml';
$robotsPath = __DIR__ . '/robots.txt';
$sitemapInfo = is_file($sitemapPath) ? ['size' => (int)filesize($sitemapPath), 'time' => (int)filemtime($sitemapPath)] : null;
$robotsInfo = is_file($robotsPath) ? ['size' => (int)filesize($robotsPath), 'time' => (int)filemtime($robotsPath)] : null;
$previewTitle = trim((string)($settings['seo_meta_title'] ?? '')) ?: pbSetting('site_name', 'Web MQ An-Nuur');
$previewDescription = trim((string)($settings['seo_meta_description'] ?? '')) ?: pbSetting('site_description');
$previewOgImage = pbAbsoluteUrl((string)($settings['seo_og_image'] ?? ''));
$siteUrl = trim((string)($settings['seo_site_url'] ?? '')) ?: pbSiteBaseUrl();

pbAdminPageStart(
    'SEO Manager',
    'seo',
    'Kelola meta tag, Open Graph, sitemap, dan arahan crawler.',
    <<<'CSS'
        .seo-preview { padding: 15px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
        .seo-preview-url { color: #047857; font-size: 11px; overflow-wrap: anywhere; }
        .seo-preview-title { display: block; margin-top: 4px; color: #1a0dab; font-size: 18px; font-weight: 600; line-height: 1.35; }
        .seo-preview-desc { margin: 4px 0 0; color: #4d5156; font-size: 12px; line-height: 1.6; }
        .seo-og-preview { display: block; width: 100%; max-width: 520px; aspect-ratio: 1200 / 630; object-fit: cover; background: #f1f5f9; border: 1px solid var(--admin-line); border-radius: 12px; }
        .seo-file { display: flex; align-items: flex-start; gap: 11px; padding: 13px; background: #f8fafc; border: 1px solid #e8edf4; border-radius: 11px; }
        .seo-file-icon { display: grid; place-items: center; width: 38px; height: 38px; flex: 0 0 38px; color: #4f46e5; background: #eef2ff; border-radius: 10px; font-size: 14px; }
        .seo-file strong { display: block; color: var(--admin-ink); font-size: 12px; }
        .seo-file small { display: block; margin-top: 2px; color: #94a3b8; font-size: 10px; }
        .seo-file-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 9px; }
        .seo-meta-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .seo-meta-row small { color: #94a3b8; font-size: 10px; font-weight: 500; }
        .seo-preview-label { display: block; margin-bottom: 7px; color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
CSS
);
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <form method="post" action="admin-seo.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                <input type="hidden" name="form_action" value="save">
                <div class="admin-grid two">
                    <section class="admin-card">
                        <div class="admin-card-head">
                            <div><h2>Meta Tag Utama</h2><p>Judul dan deskripsi default untuk mesin pencari.</p></div>
                        </div>
                        <div class="admin-card-body admin-form">
                            <label class="admin-field">
                                <span class="seo-meta-row"><span>Meta Title</span><small>Ideal 50–60 karakter</small></span>
                                <input class="admin-input" type="text" name="seo_meta_title" maxlength="255" value="<?= pbE($settings['seo_meta_title'] ?? '') ?>" placeholder="<?= pbE(pbSetting('site_name', 'Web MQ An-Nuur')) ?>">
                            </label>
                            <label class="admin-field">
                                <span class="seo-meta-row"><span>Meta Description</span><small>Ideal 140–160 karakter</small></span>
                                <textarea class="admin-textarea" name="seo_meta_description" rows="4" maxlength="500" placeholder="<?= pbE(pbSetting('site_description')) ?>"><?= pbE($settings['seo_meta_description'] ?? '') ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span class="seo-meta-row"><span>URL Situs</span><small>Untuk canonical, sitemap, dan robots</small></span>
                                <input class="admin-input" type="text" name="seo_site_url" maxlength="500" value="<?= pbE($settings['seo_site_url'] ?? '') ?>" placeholder="<?= pbE(pbSiteBaseUrl()) ?>">
                                <small class="admin-form-hint">Kosongkan untuk mendeteksi otomatis dari permintaan saat ini.</small>
                            </label>
                            <div class="seo-preview">
                                <span class="seo-preview-label">Pratinjau hasil pencarian</span>
                                <span class="seo-preview-url"><?= pbE(rtrim($siteUrl, '/') . '/index.php') ?></span>
                                <strong class="seo-preview-title"><?= pbE($previewTitle) ?></strong>
                                <p class="seo-preview-desc"><?= pbE($previewDescription) ?></p>
                            </div>
                        </div>
                    </section>

                    <section class="admin-card">
                        <div class="admin-card-head">
                            <div><h2>Open Graph Image</h2><p>Gambar unggulan saat halaman dibagikan di media sosial.</p></div>
                        </div>
                        <div class="admin-card-body admin-form">
                            <label class="admin-field">
                                <span class="seo-meta-row"><span>URL Gambar</span><small>Disarankan 1200 × 630 px</small></span>
                                <input class="admin-input" type="text" name="seo_og_image" maxlength="500" value="<?= pbE($settings['seo_og_image'] ?? '') ?>" placeholder="uploads/seo/og-image.webp">
                            </label>
                            <label class="admin-field">
                                <span class="seo-meta-row"><span>Unggah Gambar</span><small>JPG/PNG/WebP, maks. 4 MB</small></span>
                                <input class="admin-input" type="file" name="seo_og_image_file" accept="image/jpeg,image/png,image/gif,image/webp" data-image-input="seo-og-preview">
                            </label>
                            <?php if ($previewOgImage !== ''): ?>
                                <img id="seo-og-preview" class="seo-og-preview" src="<?= pbE($previewOgImage) ?>" alt="Pratinjau Open Graph" loading="lazy">
                            <?php else: ?>
                                <img id="seo-og-preview" class="seo-og-preview" alt="" hidden>
                            <?php endif; ?>
                            <small class="admin-form-hint">Gambar unggulan berita tetap diprioritaskan saat halaman berita dibagikan. Gambar ini menjadi fallback untuk halaman lain.</small>
                        </div>
                    </section>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Simpan Pengaturan SEO</button>
                    <a class="admin-btn" href="index.php" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Situs</a>
                </div>
            </form>

            <div class="admin-grid two" style="margin-top:16px;">
                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Sitemap XML</h2><p>Daftar URL halaman dan berita publik untuk mesin pencari.</p></div>
                        <?php if ($sitemapInfo !== null): ?><span class="admin-badge success">Tersedia</span><?php else: ?><span class="admin-badge warning">Belum dibuat</span><?php endif; ?>
                    </div>
                    <div class="admin-card-body">
                        <div class="seo-file">
                            <span class="seo-file-icon" aria-hidden="true"><i class="fa-solid fa-sitemap"></i></span>
                            <div>
                                <strong>sitemap.xml</strong>
                                <small><?= $sitemapInfo !== null ? pbE(number_format($sitemapInfo['size'] / 1024, 1, ',', '.') . ' KB · diperbarui ' . date('d M Y H:i', $sitemapInfo['time'])) : 'Belum ada file. Generate untuk membuat sitemap pertama.' ?></small>
                                <div class="seo-file-actions">
                                    <form method="post" action="admin-seo.php">
                                        <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                        <input type="hidden" name="form_action" value="generate_sitemap">
                                        <button class="admin-btn primary" type="submit"><i class="fa-solid fa-rotate" aria-hidden="true"></i>Generate sitemap.xml</button>
                                    </form>
                                    <?php if ($sitemapInfo !== null): ?><a class="admin-btn" href="sitemap.xml" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>Buka</a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <small class="admin-form-hint" style="display:block;margin-top:10px;">URL situs: <code class="admin-code"><?= pbE(pbSiteBaseUrl()) ?></code></small>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Robots.txt</h2><p>Arahan crawler dan lokasi sitemap website.</p></div>
                        <?php if ($robotsInfo !== null): ?><span class="admin-badge success">Tersedia</span><?php else: ?><span class="admin-badge warning">Belum dibuat</span><?php endif; ?>
                    </div>
                    <div class="admin-card-body">
                        <div class="seo-file">
                            <span class="seo-file-icon" aria-hidden="true"><i class="fa-solid fa-robot"></i></span>
                            <div>
                                <strong>robots.txt</strong>
                                <small><?= $robotsInfo !== null ? pbE(number_format($robotsInfo['size'] / 1024, 1, ',', '.') . ' KB · diperbarui ' . date('d M Y H:i', $robotsInfo['time'])) : 'Belum ada file. Generate untuk membuat arahan crawler.' ?></small>
                                <div class="seo-file-actions">
                                    <form method="post" action="admin-seo.php">
                                        <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                        <input type="hidden" name="form_action" value="generate_robots">
                                        <button class="admin-btn primary" type="submit"><i class="fa-solid fa-rotate" aria-hidden="true"></i>Generate robots.txt</button>
                                    </form>
                                    <?php if ($robotsInfo !== null): ?><a class="admin-btn" href="robots.txt" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>Buka</a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <small class="admin-form-hint" style="display:block;margin-top:10px;">robots.txt memblokir area admin, API, editor, login, database, dan folder includes.</small>
                    </div>
                </section>
            </div>
<?php pbAdminPageEnd(); ?>
