<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/optimizer.php';
require_once __DIR__ . '/site_backup.php';

header('Cache-Control: no-store, private');
$action = $_GET['action'] ?? '';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Public contact submissions are handled before the CMS admin gate.
if ($action === 'submit_form') {
    if ($method !== 'POST') {
        header('Allow: POST');
        pbJsonResponse(['status' => 'error', 'message' => 'Metode pengiriman tidak didukung.'], 405);
    }

    try {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            $payload = json_decode($rawBody === false ? '' : $rawBody, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Data formulir tidak valid.');
            }
        } else {
            $payload = $_POST;
        }

        // Silently accept honeypot submissions without storing or emailing them.
        if (trim(is_string($payload['website'] ?? null) ? $payload['website'] : '') !== '') {
            pbJsonResponse(['status' => 'success', 'message' => 'Pesan Anda berhasil terkirim!']);
        }

        $cleanText = static function ($value, int $limit, bool $multiline = false): string {
            if (!is_string($value)) {
                return '';
            }
            $value = strip_tags($value);
            $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
            $value = preg_replace($pattern, '', $value) ?? '';
            $value = trim($value);
            return substr($value, 0, $limit);
        };

        $slug = $cleanText($payload['page_slug'] ?? '', 100);
        $name = $cleanText($payload['name'] ?? '', 150);
        $email = $cleanText($payload['email'] ?? '', 254);
        $message = $cleanText($payload['message'] ?? '', 5000, true);
        if (!pbValidSlug($slug)) {
            throw new InvalidArgumentException('Halaman formulir tidak valid.');
        }
        if ($name === '' || $email === '' || $message === '') {
            throw new InvalidArgumentException('Nama, email, dan pesan wajib diisi.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Format alamat email tidak valid.');
        }

        $pdo = pbDbConnection();
        $pageCheck = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
        $pageCheck->execute(['slug' => $slug]);
        if (!$pageCheck->fetchColumn()) {
            throw new InvalidArgumentException('Halaman formulir tidak ditemukan.');
        }

        $senderIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (filter_var($senderIp, FILTER_VALIDATE_IP) === false) {
            $senderIp = 'unknown';
        }
        $rateLimit = $pdo->prepare(
            'SELECT COUNT(*) FROM form_submissions
             WHERE sender_ip = :sender_ip AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $rateLimit->execute(['sender_ip' => $senderIp]);
        if ((int)$rateLimit->fetchColumn() >= 8) {
            pbJsonResponse(['status' => 'error', 'message' => 'Terlalu banyak pengiriman. Coba lagi beberapa menit.'], 429);
        }

        $formData = json_encode(
            ['name' => $name, 'email' => $email, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $insert = $pdo->prepare(
            'INSERT INTO form_submissions (page_slug, form_data, sender_ip)
             VALUES (:page_slug, :form_data, :sender_ip)'
        );
        $insert->execute(['page_slug' => $slug, 'form_data' => $formData, 'sender_ip' => $senderIp]);

        $adminEmail = getenv('FORM_ADMIN_EMAIL') ?: 'admin@domain.com';
        $emailSent = false;
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = 'Pesan baru dari website MQ An-Nuur';
            $body = "Halaman: {$slug}\nNama: {$name}\nEmail: {$email}\n\nPesan:\n{$message}\n";
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: Web MQ An-Nuur <no-reply@localhost>',
                'Reply-To: ' . $email
            ];
            $emailSent = @mail($adminEmail, $subject, $body, implode("\r\n", $headers));
        }

        pbJsonResponse([
            'status' => 'success',
            'message' => 'Pesan Anda berhasil terkirim!',
            'email_sent' => $emailSent
        ]);
    } catch (InvalidArgumentException $exception) {
        pbJsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 422);
    } catch (JsonException $exception) {
        pbJsonResponse(['status' => 'error', 'message' => 'Data formulir tidak valid.'], 400);
    } catch (Throwable $exception) {
        error_log('[Page Builder] Contact form error: ' . $exception->getMessage());
        pbJsonResponse(['status' => 'error', 'message' => 'Pesan belum dapat dikirim. Silakan coba lagi nanti.'], 500);
    }
}

$publicActions = ['load', 'get_templates', 'get_assets', 'get_latest_posts'];
if (!in_array($action, $publicActions, true)) {
    pbRequireAdminJson();
}

/** Validate CSRF tokens for actions that change data or write files. */
function pbRequireCsrfToken(): void
{
    $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
    if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
        pbJsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang editor lalu coba lagi.'], 403);
    }
}

/** Create and return the public asset directory. */
function pbUploadsDirectory(): string
{
    $directory = __DIR__ . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder uploads tidak dapat dibuat.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Folder uploads tidak dapat ditulis oleh PHP.');
    }

    $rulesFile = $directory . '/.htaccess';
    if (!is_file($rulesFile)) {
        $rules = implode(PHP_EOL, [
            'Options -Indexes -ExecCGI',
            '<FilesMatch "\\.(?:php[0-9]?|phtml|phar)$">',
            'Require all denied',
            '</FilesMatch>',
            ''
        ]);
        @file_put_contents($rulesFile, $rules, LOCK_EX);
    }
    return $directory;
}

/** Sanitize SVG markup so uploaded vectors cannot execute scripts or load active content. */
function pbSanitizeUploadedSvg(string $temporaryPath): string
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('Ekstensi PHP DOM diperlukan untuk unggahan SVG.');
    }
    $source = file_get_contents($temporaryPath);
    if ($source === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) {
        throw new InvalidArgumentException('SVG dengan deklarasi DOCTYPE atau ENTITY tidak diizinkan.');
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (!$document->loadXML($source, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('File SVG tidak valid.');
        }
        $root = $document->documentElement;
        if (!$root || strtolower((string)$root->localName) !== 'svg') {
            throw new InvalidArgumentException('File SVG tidak valid.');
        }

        $removeTags = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'animate', 'animatemotion', 'animatetransform', 'set', 'style'];
        $nodes = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }
        foreach ($nodes as $node) {
            if (!$node->parentNode) {
                continue;
            }
            if (in_array(strtolower((string)$node->localName), $removeTags, true)) {
                $node->parentNode->removeChild($node);
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[] = [$attribute->name, $attribute->value];
            }
            foreach ($attributes as [$name, $value]) {
                $name = strtolower($name);
                $value = trim($value);
                if (str_starts_with($name, 'on') || $name === 'style') {
                    $node->removeAttribute($name);
                    continue;
                }
                if (in_array($name, ['href', 'xlink:href'], true) && $value !== '' && !str_starts_with($value, '#')) {
                    $node->removeAttribute($name);
                    continue;
                }
                if (stripos($value, 'url(') !== false && !preg_match('/^url\\(\\s*#[a-z0-9_.:-]+\\s*\\)$/i', $value)) {
                    $node->removeAttribute($name);
                }
            }
        }

        $cleanSvg = $document->saveXML($root);
        if ($cleanSvg === false) {
            throw new RuntimeException('SVG tidak dapat disimpan setelah dibersihkan.');
        }
        return $cleanSvg;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** Normalize single-file and multi-file PHP upload arrays. */
function pbUploadedFileList(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }
    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0
        ];
    }
    return $normalized;
}

/** Remove a temporary extraction tree without following symbolic links. */
function pbRemoveTemporaryTree(string $path): void
{
    if (!file_exists($path) || is_link($path)) return;
    if (is_file($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        pbRemoveTemporaryTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

/** Provide starter layouts for the editor's Template Library. */
function pbPageTemplates(): array
{
    return [
        [
            'id' => 'landing-hero',
            'name' => 'Landing Page Hero',
            'description' => 'Header navigasi dan hero dengan judul, deskripsi, CTA, serta gambar.',
            'components' => <<<'HTML'
<header class="tpl-hero-header"><a class="tpl-brand" href="#">MQ An-Nuur</a><nav><a href="#layanan">Layanan</a><a href="#kontak">Kontak</a></nav></header><main><section class="tpl-hero"><div class="tpl-hero-copy"><p class="tpl-eyebrow">Pendidikan Islami · MQ An-Nuur</p><h1>Membangun Generasi Qurani yang Berakhlak Mulia</h1><p class="tpl-lead">Tempat belajar, bertumbuh, dan menyiapkan masa depan dengan nilai Al-Qur’an.</p><a class="tpl-cta" href="#kontak">Kenali Program Kami</a></div><img class="tpl-hero-image" src="https://placehold.co/900x680/e8f4ef/176b45?text=MQ+An-Nuur" alt="Suasana belajar MQ An-Nuur"></section></main>
HTML,
            'style' => <<<'CSS'
.tpl-hero-header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:22px max(5vw,24px);border-bottom:1px solid #e2e8f0;font-family:system-ui,sans-serif}.tpl-brand{font-size:20px;font-weight:800;color:#176b45;text-decoration:none}.tpl-hero-header nav{display:flex;gap:22px}.tpl-hero-header nav a{color:#334155;text-decoration:none}.tpl-hero{display:grid;grid-template-columns:1.05fr .95fr;align-items:center;gap:48px;padding:72px max(5vw,24px);font-family:system-ui,sans-serif}.tpl-hero-copy h1{max-width:680px;margin:12px 0;font-size:clamp(36px,5vw,62px);line-height:1.08;color:#123b2b}.tpl-eyebrow{color:#0f9f78;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.tpl-lead{max-width:570px;color:#475569;font-size:18px;line-height:1.7}.tpl-cta{display:inline-block;margin-top:14px;padding:13px 20px;border-radius:8px;background:#0f9f78;color:#fff;text-decoration:none;font-weight:700}.tpl-hero-image{display:block;width:100%;height:auto;border-radius:20px}@media(max-width:700px){.tpl-hero-header nav{gap:12px}.tpl-hero{grid-template-columns:1fr;gap:28px;padding-top:42px}.tpl-hero-image{max-width:560px}}
CSS
        ],
        [
            'id' => 'feature-services',
            'name' => 'Feature List / Services',
            'description' => 'Bagian layanan dengan tiga kartu fitur dan ikon FontAwesome.',
            'components' => <<<'HTML'
<section class="tpl-features" id="layanan"><div class="tpl-section-heading"><p class="tpl-eyebrow">Program Unggulan</p><h2>Layanan untuk Tumbuh Bersama</h2><p>Pilih program yang mendukung perjalanan belajar dan pengembangan diri.</p></div><div class="tpl-feature-grid"><article class="tpl-feature-card"><i class="fa-solid fa-book-open" aria-hidden="true"></i><h3>Pembelajaran Qur’an</h3><p>Pembelajaran terarah untuk membantu peserta memahami dan mencintai Al-Qur’an.</p></article><article class="tpl-feature-card"><i class="fa-solid fa-users" aria-hidden="true"></i><h3>Pembinaan Karakter</h3><p>Lingkungan yang mendukung tumbuhnya akhlak, kedisiplinan, dan kepedulian.</p></article><article class="tpl-feature-card"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><h3>Pendampingan Belajar</h3><p>Pendampingan yang membantu setiap peserta mengembangkan potensinya.</p></article></div></section>
HTML,
            'style' => <<<'CSS'
.tpl-features{padding:72px max(5vw,24px);background:#f7faf8;font-family:system-ui,sans-serif}.tpl-section-heading{max-width:700px;margin:0 auto 38px;text-align:center}.tpl-section-heading h2{margin:8px 0;font-size:clamp(30px,4vw,44px);color:#123b2b}.tpl-section-heading>p:last-child{color:#64748b;line-height:1.6}.tpl-feature-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;max-width:1180px;margin:0 auto}.tpl-feature-card{padding:28px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}.tpl-feature-card i{display:inline-grid;width:52px;height:52px;place-items:center;border-radius:12px;background:#e8f4ef;color:#0f9f78;font-size:23px}.tpl-feature-card h3{margin:20px 0 8px;color:#123b2b}.tpl-feature-card p{margin:0;color:#64748b;line-height:1.7}@media(max-width:760px){.tpl-feature-grid{grid-template-columns:1fr}.tpl-features{padding-top:48px;padding-bottom:48px}}
CSS
        ],
        [
            'id' => 'contact-section',
            'name' => 'Contact Section',
            'description' => 'Dua kolom berisi informasi kontak dan panel lokasi dengan tautan peta.',
            'components' => <<<'HTML'
<section class="tpl-contact" id="kontak"><div class="tpl-contact-info"><p class="tpl-eyebrow">Hubungi Kami</p><h2>Mari Terhubung dengan MQ An-Nuur</h2><p class="tpl-contact-lead">Tim kami siap membantu menjawab pertanyaan tentang program dan pendaftaran.</p><div class="tpl-contact-item"><i class="fa-solid fa-envelope" aria-hidden="true"></i><a href="mailto:info@mqannuur.id">info@mqannuur.id</a></div><div class="tpl-contact-item"><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:+620000000000">+62 000 0000 0000</a></div><div class="tpl-contact-item"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Alamat MQ An-Nuur, Indonesia</span></div></div><div class="tpl-map-card"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i><h3>Kunjungi Lokasi Kami</h3><p>Tambahkan alamat lengkap MQ An-Nuur, lalu buka petunjuk lokasi melalui peta.</p><a href="https://www.google.com/maps/search/?api=1&amp;query=MQ+An-Nuur" target="_blank" rel="noopener">Buka Google Maps</a></div></section>
HTML,
            'style' => <<<'CSS'
.tpl-contact{display:grid;grid-template-columns:1fr 1fr;align-items:center;gap:56px;padding:76px max(5vw,24px);font-family:system-ui,sans-serif}.tpl-contact-info h2{max-width:560px;margin:8px 0 14px;color:#123b2b;font-size:clamp(32px,4vw,48px);line-height:1.12}.tpl-contact-lead{max-width:560px;margin-bottom:26px;color:#64748b;line-height:1.7}.tpl-contact-item{display:flex;align-items:center;gap:13px;margin:14px 0;color:#334155}.tpl-contact-item i{width:22px;color:#0f9f78}.tpl-contact-item a{color:#334155;text-decoration:none}.tpl-map-card{min-height:300px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:34px;border:1px solid #d8e8df;border-radius:18px;background:linear-gradient(145deg,#e8f4ef,#f7faf8)}.tpl-map-card>i{color:#0f9f78;font-size:38px}.tpl-map-card h3{margin:20px 0 6px;color:#123b2b;font-size:24px}.tpl-map-card p{color:#64748b;line-height:1.7}.tpl-map-card a{display:inline-block;margin-top:8px;padding:11px 16px;border-radius:8px;background:#176b45;color:#fff;text-decoration:none;font-weight:700}@media(max-width:700px){.tpl-contact{grid-template-columns:1fr;gap:30px;padding-top:48px;padding-bottom:48px}}
CSS
        ]
    ];
}

try {
    if ($method === 'GET' && $action === 'export_messages_csv') {
        $pdo = pbDbConnection();
        $statement = $pdo->query(
            'SELECT id, page_slug, form_data, sender_ip, created_at
             FROM form_submissions
             ORDER BY created_at DESC'
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pesan-masuk-' . date('Y-m-d') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new RuntimeException('Stream ekspor CSV tidak dapat dibuka.');
        }
        // UTF-8 BOM helps Excel recognize Indonesian and other non-ASCII text.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Waktu Kirim', 'Halaman', 'Nama', 'Email', 'Pesan', 'IP Address']);

        $spreadsheetSafe = static function ($value): string {
            $text = is_scalar($value) ? (string)$value : '';
            return preg_match('/^[\x00-\x20]*[=+\-@]/u', $text) === 1 ? "'" . $text : $text;
        };
        while ($row = $statement->fetch()) {
            $formData = json_decode((string)$row['form_data'], true);
            $formData = is_array($formData) ? $formData : [];
            fputcsv($output, [
                (int)$row['id'],
                (string)$row['created_at'],
                $spreadsheetSafe($row['page_slug']),
                $spreadsheetSafe($formData['name'] ?? ''),
                $spreadsheetSafe($formData['email'] ?? ''),
                $spreadsheetSafe($formData['message'] ?? ''),
                $spreadsheetSafe($row['sender_ip'])
            ]);
        }
        fclose($output);
        exit;
    }

    if ($method === 'GET' && $action === 'get_messages') {
        $pdo = pbDbConnection();
        $statement = $pdo->query(
            'SELECT id, page_slug, form_data, sender_ip, created_at
             FROM form_submissions
             ORDER BY created_at DESC, id DESC'
        );
        $messages = array_map(static function (array $row): array {
            $formData = json_decode((string)$row['form_data'], true);
            return [
                'id' => (int)$row['id'],
                'page_slug' => (string)$row['page_slug'],
                'form_data' => is_array($formData) ? $formData : [],
                'sender_ip' => (string)$row['sender_ip'],
                'created_at' => (string)$row['created_at']
            ];
        }, $statement->fetchAll());
        pbJsonResponse(['success' => true, 'data' => $messages]);
    }

    if ($method === 'GET' && $action === 'get_latest_posts') {
        $requestedLimit = filter_var($_GET['limit'] ?? 6, FILTER_VALIDATE_INT);
        $limit = $requestedLimit === false ? 6 : max(1, min(20, $requestedLimit));
        $pdo = pbDbConnection();
        $statement = $pdo->prepare(
            "SELECT id, title, slug, excerpt, featured_image, category, created_at
             FROM posts WHERE status = 'published'
             ORDER BY created_at DESC, id DESC LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $posts = array_map(static function (array $row): array {
            $image = trim((string)($row['featured_image'] ?? ''));
            $safeImage = $image !== '' ? pbSafeUrl($image) : null;
            if ($safeImage !== null && preg_match('~^(?:mailto:|tel:)~i', $safeImage)) {
                $safeImage = null;
            }
            return [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'slug' => (string)$row['slug'],
                'excerpt' => trim(strip_tags((string)($row['excerpt'] ?? ''))),
                'featured_image' => $safeImage ?? '',
                'category' => (string)($row['category'] ?? 'Umum'),
                'created_at' => (string)$row['created_at']
            ];
        }, $statement->fetchAll());
        pbJsonResponse(['success' => true, 'data' => $posts]);
    }

    if ($method === 'GET' && $action === 'get_revisions') {
        $pageSlug = $_GET['page_slug'] ?? '';
        if (!pbValidSlug($pageSlug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug halaman tidak valid.'], 422);
        }
        $pdo = pbDbConnection();
        $statement = $pdo->prepare(
            'SELECT id, page_slug, created_at
             FROM page_revisions
             WHERE page_slug = :page_slug
             ORDER BY id DESC'
        );
        $statement->execute(['page_slug' => $pageSlug]);
        $revisions = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'page_slug' => (string)$row['page_slug'],
                'created_at' => (string)$row['created_at']
            ];
        }, $statement->fetchAll());
        pbJsonResponse(['success' => true, 'data' => $revisions]);
    }

    if ($method === 'POST' && $action === 'restore_revision') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 10_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload tidak valid.'], 400);
        }
        try {
            $payload = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $revisionId = is_array($payload) ? filter_var($payload['revision_id'] ?? null, FILTER_VALIDATE_INT) : false;
        if ($revisionId === false || $revisionId < 1) {
            pbJsonResponse(['success' => false, 'message' => 'ID revisi tidak valid.'], 422);
        }

        $pdo = pbDbConnection();
        $pdo->beginTransaction();
        try {
            $revisionQuery = $pdo->prepare(
                'SELECT id, page_slug, content_json, content_css, content_html
                 FROM page_revisions WHERE id = :id LIMIT 1'
            );
            $revisionQuery->execute(['id' => $revisionId]);
            $revision = $revisionQuery->fetch();
            if (!$revision) {
                $pdo->rollBack();
                pbJsonResponse(['success' => false, 'message' => 'Revisi tidak ditemukan.'], 404);
            }

            $pageQuery = $pdo->prepare(
                'SELECT title, content_json, content_css, content_html
                 FROM pages WHERE slug = :slug LIMIT 1 FOR UPDATE'
            );
            $pageQuery->execute(['slug' => $revision['page_slug']]);
            $currentPage = $pageQuery->fetch();
            if (!$currentPage) {
                $pdo->rollBack();
                pbJsonResponse(['success' => false, 'message' => 'Halaman untuk revisi ini sudah tidak ada.'], 404);
            }

            // Keep the state being replaced so a restore can itself be undone later.
            $snapshot = $pdo->prepare(
                'INSERT INTO page_revisions (page_slug, content_json, content_css, content_html)
                 VALUES (:page_slug, :content_json, :content_css, :content_html)'
            );
            $snapshot->execute([
                'page_slug' => $revision['page_slug'],
                'content_json' => (string)$currentPage['content_json'],
                'content_css' => $currentPage['content_css'],
                'content_html' => $currentPage['content_html']
            ]);

            $decodedComponents = json_decode((string)$revision['content_json'], true);
            $components = is_array($decodedComponents) ? (pbSanitizeComponents($decodedComponents) ?? []) : [];
            $componentJson = json_encode(
                $components,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
            $css = pbSanitizeCss((string)($revision['content_css'] ?? ''));
            $html = pbSanitizeHtml((string)($revision['content_html'] ?? ''));

            $updatePage = $pdo->prepare(
                'UPDATE pages
                 SET content_json = :content_json, content_css = :content_css,
                     content_html = :content_html, updated_at = CURRENT_TIMESTAMP
                 WHERE slug = :page_slug'
            );
            $updatePage->execute([
                'content_json' => $componentJson,
                'content_css' => $css,
                'content_html' => $html,
                'page_slug' => $revision['page_slug']
            ]);

            $trimRevisions = $pdo->prepare(
                'DELETE FROM page_revisions
                 WHERE page_slug = ?
                   AND id NOT IN (
                       SELECT id FROM (
                           SELECT id FROM page_revisions
                           WHERE page_slug = ? ORDER BY id DESC LIMIT 15
                       ) AS recent_revisions
                   )'
            );
            $trimRevisions->execute([$revision['page_slug'], $revision['page_slug']]);
            $pdo->commit();

            pbJsonResponse([
                'success' => true,
                'message' => 'Halaman berhasil dipulihkan ke versi lama!',
                'page' => [
                    'slug' => (string)$revision['page_slug'],
                    'title' => (string)$currentPage['title'],
                    'json' => $components,
                    'css' => $css,
                    'html' => $html
                ]
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if ($method === 'POST' && $action === 'delete_message') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 10_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload tidak valid.'], 400);
        }
        try {
            $payload = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $id = is_array($payload) ? filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1) {
            pbJsonResponse(['success' => false, 'message' => 'ID pesan tidak valid.'], 422);
        }
        $pdo = pbDbConnection();
        $statement = $pdo->prepare('DELETE FROM form_submissions WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            pbJsonResponse(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        }
        pbJsonResponse(['success' => true, 'message' => 'Pesan berhasil dihapus.', 'id' => (int)$id]);
    }

    if ($method === 'GET' && $action === 'get_templates') {
        $templates = pbPageTemplates();
        $statement = pbDbConnection()->query(
            'SELECT id, name, description, content_json, content_css FROM templates ORDER BY id DESC'
        );
        foreach ($statement->fetchAll() as $row) {
            $components = json_decode((string)$row['content_json'], true);
            if (!is_array($components)) {
                continue;
            }
            $templates[] = [
                'id' => 'custom-' . (int)$row['id'],
                'name' => (string)$row['name'],
                'description' => (string)($row['description'] ?? ''),
                'components' => pbSanitizeComponents($components) ?? [],
                'style' => pbSanitizeCss((string)($row['content_css'] ?? ''))
            ];
        }
        pbJsonResponse(['success' => true, 'data' => $templates]);
    }

    if ($method === 'GET' && $action === 'get_assets') {
        $directory = pbUploadsDirectory();
        $assets = [];
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        foreach (glob($directory . '/*') ?: [] as $filePath) {
            if (!is_file($filePath) || is_link($filePath)) {
                continue;
            }
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (in_array($extension, $allowedExtensions, true)) {
                $filename = basename($filePath);
                $asset = [
                    'src' => rtrim(pbBasePath(), '/') . '/uploads/' . rawurlencode($filename),
                    'name' => $filename
                ];
                $dimensions = @getimagesize($filePath);
                if ($dimensions !== false) {
                    $asset['width'] = (int)$dimensions[0];
                    $asset['height'] = (int)$dimensions[1];
                }
                $assets[] = $asset;
            }
        }
        pbJsonResponse($assets);
    }

    if ($method === 'POST' && $action === 'upload_asset') {
        pbRequireCsrfToken();
        if (!class_exists(finfo::class)) {
            pbJsonResponse(['success' => false, 'message' => 'Ekstensi PHP Fileinfo diperlukan untuk memvalidasi gambar.'], 503);
        }
        if (!isset($_FILES['files']) || !is_array($_FILES['files'])) {
            pbJsonResponse(['success' => false, 'message' => 'Tidak ada file yang diunggah pada field files.'], 400);
        }

        $directory = pbUploadsDirectory();
        $files = pbUploadedFileList($_FILES['files']);
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        $expectedMimes = [
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml', 'text/xml', 'application/xml']
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $prepared = [];

        foreach ($files as $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Ukuran gambar melebihi batas upload PHP.'
                    : 'File gagal diunggah.';
                throw new InvalidArgumentException($message);
            }

            $originalName = (string)($file['name'] ?? '');
            $temporaryPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new InvalidArgumentException('Ekstensi yang diizinkan: PNG, JPG, JPEG, GIF, WEBP, dan SVG.');
            }
            if ($size <= 0 || $size > 5 * 1024 * 1024 || !is_file($temporaryPath)) {
                throw new InvalidArgumentException('Ukuran setiap gambar harus lebih dari 0 dan maksimal 5 MB.');
            }

            $mime = $finfo->file($temporaryPath);
            if (!in_array($mime, $expectedMimes[$extension], true)) {
                throw new InvalidArgumentException('Isi file tidak cocok dengan ekstensi gambar.');
            }

            $sanitizedSvg = null;
            $imageInfo = null;
            if ($extension === 'svg') {
                $sanitizedSvg = pbSanitizeUploadedSvg($temporaryPath);
            } else {
                $imageInfo = @getimagesize($temporaryPath);
                if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', $expectedMimes[$extension], true)) {
                    throw new InvalidArgumentException('File bukan gambar raster yang valid.');
                }
            }

            $prepared[] = [
                'temporaryPath' => $temporaryPath,
                'extension' => $extension,
                'svg' => $sanitizedSvg,
                'originalName' => $originalName,
                'width' => isset($imageInfo[0]) ? (int)$imageInfo[0] : null,
                'height' => isset($imageInfo[1]) ? (int)$imageInfo[1] : null
            ];
        }

        $assets = [];
        foreach ($prepared as $file) {
            $extension = $file['extension'];
            $width = $file['width'];
            $height = $file['height'];
            $webpSource = in_array($extension, ['jpg', 'jpeg', 'png'], true);
            if ($webpSource) {
                $decoder = $extension === 'png' ? 'imagecreatefrompng' : 'imagecreatefromjpeg';
                if (!function_exists($decoder) || !function_exists('imagewebp')) {
                    pbJsonResponse([
                        'success' => false,
                        'message' => 'Konversi WebP memerlukan GD dengan dukungan JPEG/PNG dan WebP.'
                    ], 503);
                }
            }

            $originalBase = pathinfo($file['originalName'], PATHINFO_FILENAME);
            $baseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $originalBase) ?? '';
            $baseName = trim(substr($baseName, 0, 60), '-_');
            $baseName = $baseName !== '' ? $baseName : 'image';
            $outputExtension = $webpSource ? 'webp' : $extension;
            $filename = $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $outputExtension;
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;

            if ($webpSource) {
                $image = @$decoder($file['temporaryPath']);
                if ($image === false) {
                    throw new InvalidArgumentException('Gambar JPG/PNG tidak dapat dibaca untuk konversi WebP.');
                }
                if ($extension === 'png') {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                $saved = @imagewebp($image, $destination, 80);
                if (function_exists('imagedestroy')) {
                    imagedestroy($image);
                }
                if ($saved) {
                    $outputDimensions = @getimagesize($destination);
                    if ($outputDimensions !== false) {
                        $width = (int)$outputDimensions[0];
                        $height = (int)$outputDimensions[1];
                    }
                }
            } elseif ($extension === 'svg') {
                $saved = file_put_contents($destination, $file['svg'], LOCK_EX) !== false;
            } else {
                $saved = move_uploaded_file($file['temporaryPath'], $destination);
            }
            if (!$saved) {
                if (is_file($destination)) {
                    @unlink($destination);
                }
                throw new RuntimeException('Gambar tidak dapat disimpan ke folder uploads.');
            }
            $asset = [
                'src' => rtrim(pbBasePath(), '/') . '/uploads/' . rawurlencode($filename),
                'name' => $filename
            ];
            if ($width !== null && $height !== null) {
                $asset['width'] = $width;
                $asset['height'] = $height;
            }
            $assets[] = $asset;
        }

        pbJsonResponse(['data' => $assets]);
    }

    if ($method === 'POST' && $action === 'delete_asset') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 10_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload file tidak valid.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $src = is_array($payload) && is_string($payload['src'] ?? null) ? trim($payload['src']) : '';
        if ($src === '' || strlen($src) > 2048) {
            pbJsonResponse(['success' => false, 'message' => 'Sumber gambar tidak valid.'], 422);
        }

        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path)) {
            pbJsonResponse(['success' => false, 'message' => 'Path gambar tidak valid.'], 422);
        }
        $srcHost = parse_url($src, PHP_URL_HOST);
        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $srcPort = parse_url($src, PHP_URL_PORT);
        $sourceAuthority = is_string($srcHost)
            ? strtolower($srcHost) . ($srcPort !== null ? ':' . $srcPort : '')
            : '';
        if ($sourceAuthority !== '' && $sourceAuthority !== $requestHost) {
            pbJsonResponse(['success' => false, 'message' => 'Gambar harus berasal dari situs ini.'], 422);
        }
        $prefix = rtrim(pbBasePath(), '/') . '/uploads/';
        if (str_starts_with($path, $prefix)) {
            $relativeName = substr($path, strlen($prefix));
        } elseif (str_starts_with($path, 'uploads/')) {
            $relativeName = substr($path, strlen('uploads/'));
        } else {
            pbJsonResponse(['success' => false, 'message' => 'Gambar harus berasal dari folder uploads.'], 422);
        }
        $filename = rawurldecode($relativeName);
        if ($filename === '' || basename($filename) !== $filename
            || !preg_match('/^[a-zA-Z0-9_-]+\.(?:png|jpe?g|gif|webp|svg)$/i', $filename)) {
            pbJsonResponse(['success' => false, 'message' => 'Nama file gambar tidak valid.'], 422);
        }

        $directory = pbUploadsDirectory();
        $realDirectory = realpath($directory);
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        $realTarget = realpath($target);
        if ($realDirectory === false || $realTarget === false || is_link($target)
            || !str_starts_with($realTarget, $realDirectory . DIRECTORY_SEPARATOR)
            || !is_file($realTarget)) {
            pbJsonResponse(['success' => false, 'message' => 'Gambar tidak ditemukan.'], 404);
        }
        if (!unlink($realTarget)) {
            throw new RuntimeException('Gambar tidak dapat dihapus dari server.');
        }
        pbJsonResponse(['success' => true, 'message' => 'Gambar berhasil dihapus.']);
    }

    if (in_array($action, ['export_full_site', 'import_full_site'], true) && !class_exists(ZipArchive::class)) {
        pbJsonResponse(['success' => false, 'message' => 'PHP ZipExtension (ZipArchive) belum aktif di server.'], 503);
    }

    if ($method === 'GET' && $action === 'export_full_site') {
        pbExportFullSite();
    }

    if ($method === 'POST' && $action === 'import_full_site') {
        pbRequireCsrfToken();
        if (!isset($_FILES['backup_zip']) || !is_array($_FILES['backup_zip'])) {
            pbJsonResponse(['status' => 'error', 'message' => 'Pilih file backup ZIP terlebih dahulu.'], 400);
        }
        try {
            pbJsonResponse(pbRestoreFullSite($_FILES['backup_zip']));
        } catch (InvalidArgumentException $exception) {
            pbJsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('[Page Builder] Full-site restore failed: ' . $exception->getMessage());
            pbJsonResponse(['status' => 'error', 'message' => 'Restore gagal. Periksa arsip dan konfigurasi server.'], 500);
        }
    }

    $pdo = pbDbConnection();

    if ($method === 'POST' && $action === 'create_translation') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 100_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload terjemahan terlalu besar.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $parentSlug = is_array($payload) && is_string($payload['parent_slug'] ?? null) ? $payload['parent_slug'] : '';
        $targetLang = is_array($payload) && is_string($payload['target_lang'] ?? null)
            ? strtolower(trim($payload['target_lang']))
            : '';
        if (!pbValidSlug($parentSlug) || !preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $targetLang)) {
            pbJsonResponse(['success' => false, 'message' => 'Halaman asal atau kode bahasa tidak valid.'], 422);
        }

        $pdo->beginTransaction();
        try {
            $sourceQuery = $pdo->prepare(
                'SELECT id, slug, title, content_json, content_css, content_html, lang, translation_group
                 FROM pages WHERE slug = :slug LIMIT 1 FOR UPDATE'
            );
            $sourceQuery->execute(['slug' => $parentSlug]);
            $source = $sourceQuery->fetch();
            if (!$source) {
                $pdo->rollBack();
                pbJsonResponse(['success' => false, 'message' => 'Halaman asal tidak ditemukan.'], 404);
            }
            $sourceLang = strtolower((string)($source['lang'] ?? 'id'));
            if ($targetLang === $sourceLang) {
                $pdo->rollBack();
                pbJsonResponse(['success' => false, 'message' => 'Pilih bahasa yang berbeda dari halaman asal.'], 422);
            }

            $group = trim((string)($source['translation_group'] ?? ''));
            if ($group === '') {
                $group = 'translation-' . (int)$source['id'];
                $setGroup = $pdo->prepare('UPDATE pages SET translation_group = :group_id WHERE id = :id');
                $setGroup->execute(['group_id' => $group, 'id' => (int)$source['id']]);
            }

            $existingQuery = $pdo->prepare(
                'SELECT slug, title, lang, translation_group FROM pages
                 WHERE translation_group = :group_id AND lang = :lang LIMIT 1'
            );
            $existingQuery->execute(['group_id' => $group, 'lang' => $targetLang]);
            $existing = $existingQuery->fetch();
            if ($existing) {
                $pdo->commit();
                pbJsonResponse(['success' => true, 'existing' => true, 'page' => $existing]);
            }

            $slugBase = strtolower($targetLang . '-' . (string)$source['slug']);
            $slugBase = preg_replace('/[^a-z0-9-]+/', '-', $slugBase) ?? '';
            $slugBase = trim(substr(preg_replace('/-+/', '-', $slugBase) ?? '', 0, 100), '-');
            $newSlug = $slugBase;
            $suffix = 2;
            $slugExists = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
            while (true) {
                $slugExists->execute(['slug' => $newSlug]);
                if (!$slugExists->fetchColumn()) break;
                $suffixText = '-' . $suffix++;
                $newSlug = rtrim(substr($slugBase, 0, 100 - strlen($suffixText)), '-') . $suffixText;
            }

            $languageLabel = strtoupper($targetLang);
            $translatedTitle = trim((string)$source['title']) . ' (' . $languageLabel . ')';
            $insert = $pdo->prepare(
                'INSERT INTO pages (slug, title, content_json, content_css, content_html, lang, translation_group)
                 VALUES (:slug, :title, :content_json, :content_css, :content_html, :lang, :translation_group)'
            );
            $insert->execute([
                'slug' => $newSlug,
                'title' => function_exists('mb_substr')
                    ? mb_substr($translatedTitle, 0, 255, 'UTF-8')
                    : (preg_match('/^.{0,255}/us', $translatedTitle, $titleMatch) ? $titleMatch[0] : substr($translatedTitle, 0, 255)),
                'content_json' => (string)$source['content_json'],
                'content_css' => (string)$source['content_css'],
                'content_html' => (string)$source['content_html'],
                'lang' => $targetLang,
                'translation_group' => $group
            ]);
            $pdo->commit();
            pbJsonResponse([
                'success' => true,
                'existing' => false,
                'page' => [
                    'slug' => $newSlug,
                    'title' => $translatedTitle,
                    'lang' => $targetLang,
                    'translation_group' => $group
                ]
            ], 201);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    if ($method === 'GET' && $action === 'get_all_pages') {
        $statement = $pdo->query('SELECT id, title, slug, lang, translation_group, updated_at FROM pages ORDER BY title ASC');
        pbJsonResponse(['success' => true, 'data' => $statement->fetchAll()]);
    }

    if ($method === 'POST' && $action === 'create_page') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 100_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload halaman terlalu besar.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        if (!is_array($payload)) {
            pbJsonResponse(['success' => false, 'message' => 'Payload harus berupa objek JSON.'], 400);
        }

        $title = trim(is_string($payload['title'] ?? null) ? $payload['title'] : '');
        $slugInput = trim(is_string($payload['slug'] ?? null) ? $payload['slug'] : '');
        $titleLength = preg_match_all('/./us', $title, $titleCharacters);
        if ($title === '' || $titleLength === false || $titleLength > 255) {
            pbJsonResponse(['success' => false, 'message' => 'Judul halaman wajib diisi dan maksimal 255 karakter.'], 422);
        }

        $slug = strtolower($slugInput);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if (!pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug harus berisi huruf, angka, dan tanda hubung.'], 422);
        }

        if ((array_key_exists('lang', $payload) && !is_string($payload['lang']))
            || (array_key_exists('translation_group', $payload) && $payload['translation_group'] !== null && !is_string($payload['translation_group']))) {
            pbJsonResponse(['success' => false, 'message' => 'Nilai bahasa atau grup terjemahan tidak valid.'], 422);
        }
        $lang = is_string($payload['lang'] ?? null) ? strtolower(trim($payload['lang'])) : 'id';
        $translationGroup = is_string($payload['translation_group'] ?? null)
            ? trim($payload['translation_group'])
            : '';
        if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $lang)) {
            pbJsonResponse(['success' => false, 'message' => 'Kode bahasa tidak valid.'], 422);
        }
        if (strlen($translationGroup) > 100 || ($translationGroup !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $translationGroup))) {
            pbJsonResponse(['success' => false, 'message' => 'Grup terjemahan tidak valid.'], 422);
        }

        $exists = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
        $exists->execute(['slug' => $slug]);
        if ($exists->fetchColumn()) {
            pbJsonResponse(['success' => false, 'message' => 'Slug sudah digunakan halaman lain.'], 409);
        }

        $statement = $pdo->prepare(
            'INSERT INTO pages (slug, title, content_json, content_css, content_html, lang, translation_group)
             VALUES (:slug, :title, :content_json, :content_css, :content_html, :lang, :translation_group)'
        );
        $statement->execute([
            'slug' => $slug,
            'title' => $title,
            'content_json' => '[]',
            'content_css' => '',
            'content_html' => '',
            'lang' => $lang,
            'translation_group' => $translationGroup !== '' ? $translationGroup : null
        ]);
        pbJsonResponse(['success' => true, 'message' => 'Halaman baru berhasil dibuat.', 'slug' => $slug, 'lang' => $lang], 201);
    }

    if ($method === 'POST' && $action === 'delete_page') {
        pbRequireCsrfToken();
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 100_000) {
            pbJsonResponse(['success' => false, 'message' => 'Payload halaman terlalu besar.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $slug = is_array($payload) && is_string($payload['slug'] ?? null) ? $payload['slug'] : '';
        if (!pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug halaman tidak valid.'], 422);
        }
        if ($slug === 'home') {
            pbJsonResponse(['success' => false, 'message' => 'Halaman home tidak dapat dihapus.'], 422);
        }

        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('DELETE FROM pages WHERE slug = :slug');
            $statement->execute(['slug' => $slug]);
            if ($statement->rowCount() === 0) {
                $pdo->rollBack();
                pbJsonResponse(['success' => false, 'message' => 'Halaman tidak ditemukan.'], 404);
            }
            $deleteRevisions = $pdo->prepare('DELETE FROM page_revisions WHERE page_slug = :slug');
            $deleteRevisions->execute(['slug' => $slug]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        pbJsonResponse(['success' => true, 'message' => 'Halaman berhasil dihapus.', 'slug' => $slug]);
    }

    if ($method === 'POST' && $action === 'save_custom_template') {
        pbRequireCsrfToken();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 5_000_000) {
            pbJsonResponse(['success' => false, 'message' => 'Ukuran payload template terlalu besar.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        if (!is_array($payload)) {
            pbJsonResponse(['success' => false, 'message' => 'Payload harus berupa objek JSON.'], 400);
        }

        $name = trim(is_string($payload['name'] ?? null) ? $payload['name'] : '');
        $description = trim(is_string($payload['description'] ?? null) ? $payload['description'] : '');
        $rawComponents = $payload['json'] ?? null;
        $cssInput = $payload['css'] ?? '';
        $nameLength = preg_match_all('/./us', $name, $nameCharacters);
        $descriptionLength = preg_match_all('/./us', $description, $descriptionCharacters);
        if ($name === '' || $nameLength === false || $nameLength > 255) {
            pbJsonResponse(['success' => false, 'message' => 'Nama template wajib diisi dan maksimal 255 karakter.'], 422);
        }
        if ($descriptionLength === false || $descriptionLength > 5000) {
            pbJsonResponse(['success' => false, 'message' => 'Deskripsi maksimal 5000 karakter.'], 422);
        }
        if (!is_array($rawComponents) || !is_string($cssInput)) {
            pbJsonResponse(['success' => false, 'message' => 'Data komponen atau CSS template tidak valid.'], 422);
        }

        $components = pbSanitizeComponents($rawComponents);
        if (!is_array($components)) {
            pbJsonResponse(['success' => false, 'message' => 'Struktur komponen template tidak valid.'], 422);
        }
        $componentJson = json_encode(
            $components,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $css = pbSanitizeCss($cssInput);

        $statement = $pdo->prepare(
            'INSERT INTO templates (name, description, content_json, content_css)
             VALUES (:name, :description, :content_json, :content_css)'
        );
        $statement->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'content_json' => $componentJson,
            'content_css' => $css
        ]);

        pbJsonResponse([
            'success' => true,
            'message' => 'Custom Template berhasil disimpan!',
            'id' => (int)$pdo->lastInsertId()
        ], 201);
    }

    if ($method === 'GET' && $action === 'load') {
        $slug = $_GET['slug'] ?? 'home';
        if (!pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug halaman tidak valid.'], 422);
        }

        $statement = $pdo->prepare('SELECT slug, title, content_json, content_css, content_html, meta_title, meta_description, meta_keywords, og_image, lang, translation_group FROM pages WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        if (!$row) {
            pbJsonResponse([
                'success' => true,
                'page' => [
                    'slug' => $slug,
                    'title' => pbTitleFromSlug($slug),
                    'json' => [],
                    'css' => '',
                    'html' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                    'meta_keywords' => '',
                    'og_image' => '',
                    'lang' => 'id',
                    'translation_group' => null
                ]
            ]);
        }

        $components = json_decode((string)$row['content_json'], true);
        if (!is_array($components)) {
            $components = [];
        }

        pbJsonResponse([
            'success' => true,
            'page' => [
                'slug' => $row['slug'],
                'title' => $row['title'],
                'json' => pbSanitizeComponents($components) ?? [],
                'css' => pbSanitizeCss((string)$row['content_css']),
                'html' => pbSanitizeHtml((string)$row['content_html']),
                'meta_title' => (string)($row['meta_title'] ?? ''),
                'meta_description' => (string)($row['meta_description'] ?? ''),
                'meta_keywords' => (string)($row['meta_keywords'] ?? ''),
                'og_image' => (string)($row['og_image'] ?? ''),
                'lang' => (string)($row['lang'] ?? 'id'),
                'translation_group' => (string)($row['translation_group'] ?? '')
            ]
        ]);
    }

    if ($method === 'POST' && $action === 'save') {
        pbRequireCsrfToken();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 5_000_000) {
            pbJsonResponse(['success' => false, 'message' => 'Ukuran payload terlalu besar.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        if (!is_array($payload)) {
            pbJsonResponse(['success' => false, 'message' => 'Payload harus berupa objek JSON.'], 400);
        }

        $slug = $payload['slug'] ?? '';
        if (!pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.'], 422);
        }
        if (!isset($payload['json']) || !is_array($payload['json'])) {
            pbJsonResponse(['success' => false, 'message' => 'Data komponen tidak valid.'], 422);
        }

        $components = pbSanitizeComponents($payload['json']);
        $componentJson = json_encode($components ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $css = PageSpeedOptimizer::minifyCss(
            pbSanitizeCss(is_string($payload['css'] ?? null) ? $payload['css'] : '')
        );
        $html = PageSpeedOptimizer::optimizeImages(
            PageSpeedOptimizer::minifyHtml(
                pbSanitizeHtml(is_string($payload['html'] ?? null) ? $payload['html'] : '')
            )
        );
        $title = pbTitleFromSlug($slug);

        foreach (['meta_title', 'meta_description', 'meta_keywords', 'og_image'] as $metaField) {
            if (array_key_exists($metaField, $payload) && $payload[$metaField] !== null && !is_string($payload[$metaField])) {
                throw new InvalidArgumentException('Nilai metadata tidak valid.');
            }
        }

        $cleanMeta = static function ($value, int $maxCharacters, string $label): string {
            if (!is_string($value)) {
                return '';
            }
            $value = strip_tags($value);
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
            $value = trim($value);
            $length = preg_match_all('/./us', $value, $characters);
            if ($length === false || $length > $maxCharacters) {
                throw new InvalidArgumentException($label . ' maksimal ' . $maxCharacters . ' karakter.');
            }
            return $value;
        };
        $metaTitle = $cleanMeta($payload['meta_title'] ?? '', 255, 'Meta title');
        $metaDescription = $cleanMeta($payload['meta_description'] ?? '', 160, 'Meta description');
        $metaKeywords = $cleanMeta($payload['meta_keywords'] ?? '', 2000, 'Meta keywords');
        $ogImage = $cleanMeta($payload['og_image'] ?? '', 500, 'URL gambar OpenGraph');
        if ($ogImage !== '') {
            $safeOgImage = pbSafeUrl($ogImage);
            if ($safeOgImage === null || preg_match('~^(?:mailto:|tel:)~i', $safeOgImage)) {
                throw new InvalidArgumentException('URL gambar OpenGraph harus berupa URL HTTP(S) atau path relatif yang aman.');
            }
            $ogImage = $safeOgImage;
        }

        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO pages (slug, title, content_json, content_css, content_html, meta_title, meta_description, meta_keywords, og_image)
                 VALUES (:slug, :title, :content_json, :content_css, :content_html, :meta_title, :meta_description, :meta_keywords, :og_image)
                 ON DUPLICATE KEY UPDATE
                    content_json = VALUES(content_json),
                    content_css = VALUES(content_css),
                    content_html = VALUES(content_html),
                    meta_title = VALUES(meta_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords = VALUES(meta_keywords),
                    og_image = VALUES(og_image),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $statement->execute([
                'slug' => $slug,
                'title' => $title,
                'content_json' => $componentJson,
                'content_css' => $css,
                'content_html' => $html,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                'og_image' => $ogImage !== '' ? $ogImage : null
            ]);

            $revisionInsert = $pdo->prepare(
                'INSERT INTO page_revisions (page_slug, content_json, content_css, content_html)
                 VALUES (:page_slug, :content_json, :content_css, :content_html)'
            );
            $revisionInsert->execute([
                'page_slug' => $slug,
                'content_json' => $componentJson,
                'content_css' => $css,
                'content_html' => $html
            ]);

            $trimRevisions = $pdo->prepare(
                'DELETE FROM page_revisions
                 WHERE page_slug = ?
                   AND id NOT IN (
                       SELECT id FROM (
                           SELECT id FROM page_revisions
                           WHERE page_slug = ? ORDER BY id DESC LIMIT 15
                       ) AS recent_revisions
                   )'
            );
            $trimRevisions->execute([$slug, $slug]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        pbJsonResponse(['success' => true, 'message' => 'Halaman berhasil disimpan.', 'slug' => $slug]);
    }

    header('Allow: GET, POST');
    pbJsonResponse(['success' => false, 'message' => 'Endpoint atau metode tidak ditemukan.'], 404);
} catch (InvalidArgumentException $exception) {
    pbJsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (PDOException $exception) {
    error_log('[Page Builder] Database error: ' . $exception->getMessage());
    pbJsonResponse(['success' => false, 'message' => 'Operasi database gagal. Pastikan skema database proyek sudah dibuat.'], 500);
} catch (Throwable $exception) {
    error_log('[Page Builder] Request error: ' . $exception->getMessage());
    pbJsonResponse(['success' => false, 'message' => 'Permintaan tidak dapat diproses.'], 500);
}
