<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

header('Cache-Control: no-store, private');

$action = $_GET['action'] ?? '';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($action, ['submit_form', 'load', 'get_templates', 'get_assets', 'tiktok_oembed'], true)) {
    header('Cache-Control: no-store');
} else {
    pbRequireOperatorJson();
}

$superAdminActions = ['get_assets', 'upload_asset', 'get_all_pages', 'create_page', 'delete_page', 'get_templates', 'save_custom_template', 'load', 'save'];
if (in_array($action, $superAdminActions, true) && !pbIsAdmin()) {
    pbJsonResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengelola halaman dan editor.'], 403);
}

function pbUploadsDirectory(): string
{
    $directory = __DIR__ . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Folder upload tidak dapat ditulis oleh server.');
    }
    return $directory;
}

function pbSanitizeUploadedSvg(string $contents): string
{
    if (stripos($contents, '<!DOCTYPE') !== false || !class_exists(DOMDocument::class)) {
        throw new RuntimeException('File SVG tidak valid.');
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        if (!$document->loadXML($contents, LIBXML_NONET) || !$document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
            throw new RuntimeException('File SVG tidak valid.');
        }

        $removeTags = ['script', 'style', 'foreignobject', 'iframe', 'object', 'embed', 'animate', 'animatetransform', 'animatemotion', 'set'];
        $elements = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }
        foreach ($elements as $element) {
            if (!$element->parentNode) {
                continue;
            }
            if (in_array(strtolower($element->localName), $removeTags, true)) {
                $element->parentNode->removeChild($element);
                continue;
            }

            $attributes = [];
            foreach ($element->attributes as $attribute) {
                $attributes[] = [$attribute->name, $attribute->value];
            }
            foreach ($attributes as [$name, $value]) {
                $name = strtolower($name);
                if (str_starts_with($name, 'on') || $name === 'style' || str_contains(strtolower($value), 'url(')) {
                    $element->removeAttribute($name);
                } elseif (in_array($name, ['href', 'xlink:href', 'src'], true) && !str_starts_with(trim($value), '#')) {
                    $element->removeAttribute($name);
                }
            }
        }

        $svg = $document->saveXML($document->documentElement);
        if (!is_string($svg)) {
            throw new RuntimeException('File SVG tidak dapat diproses.');
        }
        return $svg;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function pbUploadedFiles(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }

    $items = [];
    foreach (array_keys($files['name']) as $index) {
        $items[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $items;
}

function pbAssetUrl(string $filename): string
{
    return rtrim(pbBasePath(), '/') . '/uploads/' . rawurlencode($filename);
}

function pbPageTemplates(): array
{
    return [
        [
            'id' => 'landing-hero',
            'name' => 'Landing Page Hero',
            'description' => 'Header navigasi dan hero dengan judul, deskripsi, CTA, serta gambar.',
            'components' => [[
                'tagName' => 'main',
                'attributes' => ['class' => 'tpl-hero-page'],
                'components' => [
                    [
                        'tagName' => 'header',
                        'attributes' => ['class' => 'tpl-hero-header'],
                        'components' => [
                            ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => '#beranda', 'class' => 'tpl-hero-brand'], 'components' => [['type' => 'textnode', 'content' => 'Nama Brand']]],
                            ['tagName' => 'nav', 'attributes' => ['class' => 'tpl-hero-nav'], 'components' => [
                                ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => '#fitur'], 'components' => [['type' => 'textnode', 'content' => 'Fitur']]],
                                ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => '#kontak'], 'components' => [['type' => 'textnode', 'content' => 'Kontak']]],
                            ]],
                        ],
                    ],
                    [
                        'tagName' => 'section',
                        'attributes' => ['id' => 'beranda', 'class' => 'tpl-hero-section'],
                        'components' => [
                            ['tagName' => 'div', 'attributes' => ['class' => 'tpl-hero-copy'], 'components' => [
                                ['tagName' => 'p', 'attributes' => ['class' => 'tpl-eyebrow'], 'components' => [['type' => 'textnode', 'content' => 'Selamat datang']]],
                                ['tagName' => 'h1', 'components' => [['type' => 'textnode', 'content' => 'Wujudkan ide hebat Anda']]],
                                ['tagName' => 'p', 'attributes' => ['class' => 'tpl-hero-description'], 'components' => [['type' => 'textnode', 'content' => 'Perkenalkan layanan atau cerita Anda dengan halaman yang menarik dan mudah dipahami.']]],
                                ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => '#kontak', 'class' => 'tpl-hero-cta'], 'components' => [['type' => 'textnode', 'content' => 'Mulai Sekarang']]],
                            ]],
                            ['tagName' => 'img', 'attributes' => ['class' => 'tpl-hero-image', 'src' => 'https://placehold.co/900x650/e2e8f0/334155?text=Gambar+Hero', 'alt' => 'Gambar hero']],
                        ],
                    ],
                ],
            ]],
            'style' => '.tpl-hero-page{font-family:Arial,sans-serif;color:#172033}.tpl-hero-header{display:flex;justify-content:space-between;align-items:center;padding:22px 7%;background:#fff}.tpl-hero-brand{font-size:22px;font-weight:700;color:#0f766e;text-decoration:none}.tpl-hero-nav{display:flex;gap:24px}.tpl-hero-nav a{color:#475569;text-decoration:none}.tpl-hero-section{display:grid;grid-template-columns:1.05fr .95fr;align-items:center;gap:48px;padding:72px 7%;background:#f0fdfa}.tpl-hero-copy h1{margin:12px 0;font-size:clamp(36px,5vw,58px);line-height:1.08}.tpl-eyebrow{color:#0f766e;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.tpl-hero-description{max-width:540px;color:#526274;font-size:18px;line-height:1.7}.tpl-hero-cta{display:inline-block;margin-top:14px;padding:13px 20px;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700}.tpl-hero-image{display:block;width:100%;border-radius:18px;object-fit:cover}@media(max-width:700px){.tpl-hero-header{align-items:flex-start;gap:16px;flex-direction:column}.tpl-hero-nav{gap:16px}.tpl-hero-section{grid-template-columns:1fr;padding:42px 6%;gap:28px}}',
        ],
        [
            'id' => 'feature-services',
            'name' => 'Feature List / Services',
            'description' => 'Tiga kartu layanan dengan ikon FontAwesome, judul, dan deskripsi.',
            'components' => [[
                'tagName' => 'section',
                'attributes' => ['id' => 'fitur', 'class' => 'tpl-features'],
                'components' => [
                    ['tagName' => 'div', 'attributes' => ['class' => 'tpl-features-heading'], 'components' => [
                        ['tagName' => 'p', 'attributes' => ['class' => 'tpl-eyebrow'], 'components' => [['type' => 'textnode', 'content' => 'Layanan Kami']]],
                        ['tagName' => 'h2', 'components' => [['type' => 'textnode', 'content' => 'Semua yang Anda butuhkan']]],
                        ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Solusi praktis untuk membantu Anda melangkah lebih jauh.']]],
                    ]],
                    ['tagName' => 'div', 'attributes' => ['class' => 'tpl-feature-grid'], 'components' => [
                        ['tagName' => 'article', 'attributes' => ['class' => 'tpl-feature-card'], 'components' => [
                            ['tagName' => 'i', 'attributes' => ['class' => 'fa-solid fa-bolt tpl-feature-icon', 'aria-hidden' => 'true']],
                            ['tagName' => 'h3', 'components' => [['type' => 'textnode', 'content' => 'Cepat & Efisien']]],
                            ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Alur kerja ringkas yang menghemat waktu dan tenaga Anda.']]],
                        ]],
                        ['tagName' => 'article', 'attributes' => ['class' => 'tpl-feature-card'], 'components' => [
                            ['tagName' => 'i', 'attributes' => ['class' => 'fa-solid fa-shield-halved tpl-feature-icon', 'aria-hidden' => 'true']],
                            ['tagName' => 'h3', 'components' => [['type' => 'textnode', 'content' => 'Aman & Tepercaya']]],
                            ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Dukungan dan layanan yang dapat Anda andalkan.']]],
                        ]],
                        ['tagName' => 'article', 'attributes' => ['class' => 'tpl-feature-card'], 'components' => [
                            ['tagName' => 'i', 'attributes' => ['class' => 'fa-solid fa-headset tpl-feature-icon', 'aria-hidden' => 'true']],
                            ['tagName' => 'h3', 'components' => [['type' => 'textnode', 'content' => 'Dukungan Ramah']]],
                            ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Tim kami siap membantu saat Anda membutuhkan.']]],
                        ]],
                    ]],
                ],
            ]],
            'style' => '.tpl-features{padding:72px 7%;font-family:Arial,sans-serif;color:#172033;background:#fff}.tpl-features-heading{max-width:650px;margin:0 auto 38px;text-align:center}.tpl-features-heading h2{margin:10px 0;font-size:38px}.tpl-features-heading>p:last-child{color:#64748b;line-height:1.6}.tpl-eyebrow{color:#0f766e;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.tpl-feature-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.tpl-feature-card{padding:28px;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 8px 25px rgba(15,23,42,.05)}.tpl-feature-icon{display:grid;place-items:center;width:50px;height:50px;border-radius:12px;background:#ccfbf1;color:#0f766e;font-size:22px}.tpl-feature-card h3{margin:20px 0 8px}.tpl-feature-card p{margin:0;color:#64748b;line-height:1.7}@media(max-width:700px){.tpl-features{padding:48px 6%}.tpl-feature-grid{grid-template-columns:1fr}.tpl-features-heading h2{font-size:30px}}',
        ],
        [
            'id' => 'contact-section',
            'name' => 'Contact Section',
            'description' => 'Layout dua kolom untuk informasi kontak dan kartu tautan lokasi peta.',
            'components' => [[
                'tagName' => 'section',
                'attributes' => ['id' => 'kontak', 'class' => 'tpl-contact'],
                'components' => [
                    ['tagName' => 'div', 'attributes' => ['class' => 'tpl-contact-copy'], 'components' => [
                        ['tagName' => 'p', 'attributes' => ['class' => 'tpl-eyebrow'], 'components' => [['type' => 'textnode', 'content' => 'Hubungi Kami']]],
                        ['tagName' => 'h2', 'components' => [['type' => 'textnode', 'content' => 'Mari berbincang']]],
                        ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Kami siap membantu menjawab pertanyaan Anda. Hubungi kami melalui informasi berikut.']]],
                        ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Alamat: Jl. Contoh No. 1, Kota Anda']]],
                        ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Telepon: +62 812-3456-7890']]],
                        ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => 'mailto:halo@example.com', 'class' => 'tpl-contact-link'], 'components' => [['type' => 'textnode', 'content' => 'Email: halo@example.com']]],
                    ]],
                    ['tagName' => 'div', 'attributes' => ['class' => 'tpl-map-card'], 'components' => [
                        ['tagName' => 'i', 'attributes' => ['class' => 'fa-solid fa-map-location-dot tpl-map-icon', 'aria-hidden' => 'true']],
                        ['tagName' => 'h3', 'components' => [['type' => 'textnode', 'content' => 'Temukan lokasi kami']]],
                        ['tagName' => 'p', 'components' => [['type' => 'textnode', 'content' => 'Ganti tautan ini dengan alamat Google Maps Anda.']]],
                        ['tagName' => 'a', 'type' => 'link', 'attributes' => ['href' => 'https://maps.google.com/?q=Jakarta', 'target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'tpl-map-button'], 'components' => [['type' => 'textnode', 'content' => 'Buka Google Maps']]],
                    ]],
                ],
            ]],
            'style' => '.tpl-contact{display:grid;grid-template-columns:1fr 1fr;align-items:center;gap:48px;padding:72px 7%;font-family:Arial,sans-serif;color:#172033;background:#f8fafc}.tpl-contact-copy h2{margin:10px 0;font-size:40px}.tpl-contact-copy>p{color:#526274;line-height:1.7}.tpl-contact-link{color:#0f766e;font-weight:700;text-decoration:none}.tpl-map-card{min-height:280px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:36px;border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(135deg,#dbeafe,#ecfeff)}.tpl-map-icon{color:#0f766e;font-size:38px}.tpl-map-card p{color:#526274;line-height:1.6}.tpl-map-button{display:inline-block;margin-top:8px;padding:12px 18px;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700}.tpl-eyebrow{color:#0f766e;font-weight:700;text-transform:uppercase;letter-spacing:.12em}@media(max-width:700px){.tpl-contact{grid-template-columns:1fr;padding:48px 6%;gap:28px}.tpl-contact-copy h2{font-size:32px}}',
        ],
    ];
}

try {
    if ($method === 'GET' && $action === 'tiktok_oembed') {
        $url = $_GET['url'] ?? '';
        if (!is_string($url) || trim($url) === '') {
            pbJsonResponse(['success' => false, 'message' => 'URL TikTok wajib diisi.'], 422);
        }
        try {
            $data = pbTikTokOEmbed($url);
            pbJsonResponse(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            pbJsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('[Page Builder] TikTok oEmbed error: ' . $exception->getMessage());
            pbJsonResponse(['success' => false, 'message' => 'Data TikTok belum dapat dimuat. Coba lagi nanti.'], 502);
        }
    }

    if ($method === 'POST' && $action === 'submit_form') {
        $isJson = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
        if ($isJson) {
            $rawBody = file_get_contents('php://input');
            if ($rawBody === false || strlen($rawBody) > 20_000) {
                pbJsonResponse(['status' => 'error', 'message' => 'Ukuran pesan terlalu besar.'], 413);
            }
            try {
                $form = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                pbJsonResponse(['status' => 'error', 'message' => 'Data formulir tidak valid.'], 400);
            }
            if (!is_array($form)) {
                pbJsonResponse(['status' => 'error', 'message' => 'Data formulir tidak valid.'], 400);
            }
        } else {
            $form = $_POST;
        }

        if (!empty($form['website'] ?? '')) {
            pbJsonResponse(['status' => 'success', 'message' => 'Pesan Anda berhasil terkirim!']);
        }

        $pageSlug = $form['page_slug'] ?? '';
        $name = trim(strip_tags(is_string($form['name'] ?? null) ? $form['name'] : ''));
        $email = trim(is_string($form['email'] ?? null) ? $form['email'] : '');
        $message = trim(strip_tags(is_string($form['message'] ?? null) ? $form['message'] : ''));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';

        if (!pbValidSlug($pageSlug) || $name === '' || strlen($name) > 200
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || $message === '' || strlen($message) > 10_000) {
            pbJsonResponse(['status' => 'error', 'message' => 'Mohon periksa nama, email, dan pesan Anda.'], 422);
        }

        $pdo = getDbConnection();
        if (!$pdo instanceof PDO) {
            pbJsonResponse(['status' => 'error', 'message' => 'Layanan formulir sedang tidak tersedia.'], 503);
        }
        $pageQuery = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
        $pageQuery->execute(['slug' => $pageSlug]);
        if (!$pageQuery->fetchColumn()) {
            pbJsonResponse(['status' => 'error', 'message' => 'Halaman formulir tidak ditemukan.'], 404);
        }

        $senderIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 50);
        if ($senderIp !== '') {
            $rateCheck = $pdo->prepare(
                'SELECT COUNT(*) FROM form_submissions
                 WHERE sender_ip = :sender_ip AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)'
            );
            $rateCheck->execute(['sender_ip' => $senderIp]);
            if ((int)$rateCheck->fetchColumn() >= 5) {
                pbJsonResponse(['status' => 'error', 'message' => 'Terlalu banyak pesan. Silakan coba lagi beberapa menit lagi.'], 429);
            }
        }

        $formData = json_encode(
            ['name' => $name, 'email' => $email, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $insert = $pdo->prepare(
            'INSERT INTO form_submissions (page_slug, form_data, sender_ip)
             VALUES (:page_slug, :form_data, :sender_ip)'
        );
        $insert->execute([
            'page_slug' => $pageSlug,
            'form_data' => $formData,
            'sender_ip' => $senderIp,
        ]);

        $recipient = getenv('PB_FORM_RECIPIENT') ?: 'admin@domain.com';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('[Page Builder] Invalid PB_FORM_RECIPIENT; submission logged but email not sent.');
            pbJsonResponse(['status' => 'success', 'message' => 'Pesan tersimpan, tetapi notifikasi email belum dikonfigurasi.']);
        }
        $subject = 'Pesan baru dari formulir website';
        $body = "Nama: {$name}\nEmail: {$email}\nHalaman: {$pageSlug}\n\nPesan:\n{$message}";
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email];
        if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
            error_log('[Page Builder] Form submission saved but notification email could not be sent.');
        }

        if ($isJson) {
            pbJsonResponse(['status' => 'success', 'message' => 'Pesan Anda berhasil terkirim!']);
        }
        header('Location: index.php?page=' . rawurlencode($pageSlug) . '&form=sent#contact-form');
        exit;
    }

    if ($method === 'GET' && $action === 'get_assets') {
        $directory = pbUploadsDirectory();
        $assets = [];
        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename === '.' || $filename === '..' || !is_file($directory . '/' . $filename)) {
                continue;
            }
            if (preg_match('/^[a-f0-9]{32}\.(?:png|jpe?g|gif|webp|svg)$/i', $filename)) {
                $assets[] = pbAssetUrl($filename);
            }
        }
        pbJsonResponse($assets);
    }

    if ($method === 'POST' && $action === 'upload_asset') {
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
        if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
            pbJsonResponse(['message' => 'Token keamanan tidak valid. Muat ulang editor lalu coba lagi.'], 403);
        }
        if (!isset($_FILES['files']) || !is_array($_FILES['files'])) {
            pbJsonResponse(['message' => 'Pilih gambar untuk diunggah.'], 400);
        }

        $directory = pbUploadsDirectory();
        $urls = [];
        $mimeByExtension = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        foreach (pbUploadedFiles($_FILES['files']) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
                pbJsonResponse(['message' => 'Upload gambar gagal. Periksa ukuran file dan coba lagi.'], 400);
            }
            $size = (int)($file['size'] ?? 0);
            if ($size < 1 || $size > 5 * 1024 * 1024) {
                pbJsonResponse(['message' => 'Ukuran gambar maksimal 5 MB.'], 413);
            }

            $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                pbJsonResponse(['message' => 'Format gambar harus PNG, JPG, GIF, WEBP, atau SVG.'], 415);
            }
            $contents = file_get_contents((string)$file['tmp_name']);
            if ($contents === false) {
                pbJsonResponse(['message' => 'File gambar tidak dapat dibaca.'], 400);
            }

            if ($extension === 'svg') {
                $contents = pbSanitizeUploadedSvg($contents);
                if (file_put_contents((string)$file['tmp_name'], $contents, LOCK_EX) === false) {
                    pbJsonResponse(['message' => 'File SVG tidak dapat diproses.'], 400);
                }
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file((string)$file['tmp_name']);
                $imageInfo = getimagesize((string)$file['tmp_name']);
                if ($mime !== ($mimeByExtension[$extension] ?? null) || !is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
                    pbJsonResponse(['message' => 'Isi file tidak sesuai dengan format gambar.'], 415);
                }
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file((string)$file['tmp_name'], $directory . '/' . $filename)) {
                error_log('[Page Builder] Failed to move uploaded asset.');
                pbJsonResponse(['message' => 'Gambar gagal disimpan. Periksa izin folder uploads.'], 500);
            }
            $urls[] = pbAssetUrl($filename);
        }
        logActivity('upload', 'asset', null, count($urls) . ' aset media diunggah');
        pbJsonResponse(['data' => $urls]);
    }

    $pdo = getDbConnection();
    if (!$pdo instanceof PDO) {
        pbJsonResponse(['success' => false, 'message' => 'Koneksi database tidak tersedia. Periksa konfigurasi aplikasi.'], 503);
    }

    if ($method === 'GET' && $action === 'export_messages_csv') {
        $statement = $pdo->query('SELECT id, page_slug, form_data, sender_ip, created_at FROM form_submissions ORDER BY created_at DESC');
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="form-submissions-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('File ekspor tidak dapat dibuat.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Halaman', 'Nama', 'Email', 'Pesan', 'IP Pengirim', 'Waktu']);
        foreach ($statement->fetchAll() as $row) {
            $formData = json_decode((string)$row['form_data'], true);
            $cells = [
                $row['id'],
                $row['page_slug'],
                $formData['name'] ?? '',
                $formData['email'] ?? '',
                $formData['message'] ?? '',
                $row['sender_ip'],
                $row['created_at'],
            ];
            foreach ($cells as &$cell) {
                $cell = (string)$cell;
                if (preg_match('/^[\s]*[=+\-@]/u', $cell)) {
                    $cell = "'" . $cell;
                }
            }
            unset($cell);
            fputcsv($output, $cells);
        }
        fclose($output);
        logActivity('export', 'message', null, 'Pesan formulir diekspor ke CSV');
        exit;
    }

    if ($method === 'POST' && $action === 'delete_message') {
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
        if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
            pbJsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 403);
        }
        $rawBody = file_get_contents('php://input');
        try {
            $payload = json_decode(is_string($rawBody) ? $rawBody : '', true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        if (!is_array($payload)) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            pbJsonResponse(['success' => false, 'message' => 'ID pesan tidak valid.'], 422);
        }
        $delete = $pdo->prepare('DELETE FROM form_submissions WHERE id = :id');
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() === 0) {
            pbJsonResponse(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        }
        logActivity('delete', 'message', (int)$id, 'Pesan formulir dihapus');
        pbJsonResponse(['success' => true, 'message' => 'Pesan berhasil dihapus.']);
    }

    if ($method === 'GET' && $action === 'get_all_pages') {
        $statement = $pdo->query('SELECT id, title, slug, updated_at FROM pages ORDER BY title ASC');
        pbJsonResponse($statement->fetchAll());
    }

    if ($method === 'POST' && in_array($action, ['create_page', 'delete_page'], true)) {
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
        if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
            pbJsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang editor lalu coba lagi.'], 403);
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || strlen($rawBody) > 20_000) {
            pbJsonResponse(['success' => false, 'message' => 'Ukuran payload tidak valid.'], 413);
        }
        try {
            $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            pbJsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid.'], 400);
        }
        if (!is_array($payload)) {
            pbJsonResponse(['success' => false, 'message' => 'Payload harus berupa objek JSON.'], 400);
        }

        if ($action === 'create_page') {
            $title = trim(is_string($payload['title'] ?? null) ? $payload['title'] : '');
            $rawSlug = strtolower(trim(is_string($payload['slug'] ?? null) ? $payload['slug'] : ''));
            $slug = trim(preg_replace('/[^a-z0-9-]/', '-', $rawSlug) ?? '', '-');
            $slug = preg_replace('/-+/', '-', $slug) ?? '';
            if ($title === '' || preg_match_all('/./us', $title) > 255) {
                pbJsonResponse(['success' => false, 'message' => 'Judul wajib diisi dan maksimal 255 karakter.'], 422);
            }
            if (!pbValidSlug($slug)) {
                pbJsonResponse(['success' => false, 'message' => 'Slug tidak valid. Gunakan huruf, angka, dan tanda hubung.'], 422);
            }

            $statement = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $slug]);
            if ($statement->fetchColumn()) {
                pbJsonResponse(['success' => false, 'message' => 'Slug tersebut sudah digunakan.'], 409);
            }

            $statement = $pdo->prepare(
                'INSERT INTO pages (slug, title, content_json, content_css, content_html)
                 VALUES (:slug, :title, :content_json, :content_css, :content_html)'
            );
            $statement->execute([
                'slug' => $slug,
                'title' => $title,
                'content_json' => '[]',
                'content_css' => '',
                'content_html' => '',
            ]);
            logActivity('create', 'page', (int)$pdo->lastInsertId(), 'Judul: ' . $title . '; slug: ' . $slug);
            pbJsonResponse(['success' => true, 'message' => 'Halaman berhasil dibuat.', 'slug' => $slug], 201);
        }

        $slug = $payload['slug'] ?? '';
        if (!is_string($slug) || !pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug halaman tidak valid.'], 422);
        }
        if ($slug === 'home') {
            pbJsonResponse(['success' => false, 'message' => 'Halaman utama tidak dapat dihapus.'], 422);
        }
        $statement = $pdo->prepare('DELETE FROM pages WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        if ($statement->rowCount() === 0) {
            pbJsonResponse(['success' => false, 'message' => 'Halaman tidak ditemukan.'], 404);
        }
        logActivity('delete', 'page', $slug, 'Halaman dihapus');
        pbJsonResponse(['success' => true, 'message' => 'Halaman berhasil dihapus.']);
    }

    if ($method === 'GET' && $action === 'get_templates') {
        $statement = $pdo->query('SELECT id, name, description, content_json, content_css, created_at FROM templates ORDER BY id DESC');
        $customTemplates = [];
        foreach ($statement->fetchAll() as $row) {
            $components = json_decode((string)$row['content_json'], true);
            if (!is_array($components)) {
                continue;
            }
            $customTemplates[] = [
                'id' => 'custom-' . (int)$row['id'],
                'name' => (string)$row['name'],
                'description' => (string)($row['description'] ?? ''),
                'components' => pbSanitizeComponents($components) ?? [],
                'style' => pbSanitizeCss((string)($row['content_css'] ?? '')),
                'created_at' => $row['created_at'],
            ];
        }
        pbJsonResponse(array_merge(pbPageTemplates(), $customTemplates));
    }

    if ($method === 'POST' && $action === 'save_custom_template') {
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
        if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
            pbJsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang editor lalu coba lagi.'], 403);
        }

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
            pbJsonResponse(['success' => false, 'message' => 'Data template tidak valid.'], 400);
        }

        $name = trim(is_string($payload['name'] ?? null) ? $payload['name'] : '');
        $description = trim(is_string($payload['description'] ?? null) ? $payload['description'] : '');
        if ($name === '' || preg_match_all('/./us', $name) > 255) {
            pbJsonResponse(['success' => false, 'message' => 'Nama template wajib diisi dan maksimal 255 karakter.'], 422);
        }
        if (preg_match_all('/./us', $description) > 2000) {
            pbJsonResponse(['success' => false, 'message' => 'Deskripsi maksimal 2000 karakter.'], 422);
        }
        if (!isset($payload['json']) || !is_array($payload['json'])) {
            pbJsonResponse(['success' => false, 'message' => 'Struktur komponen template tidak valid.'], 422);
        }

        $components = pbSanitizeComponents($payload['json']);
        $componentJson = json_encode(
            $components ?? [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $css = pbSanitizeCss(is_string($payload['css'] ?? null) ? $payload['css'] : '');
        $statement = $pdo->prepare(
            'INSERT INTO templates (name, description, content_json, content_css)
             VALUES (:name, :description, :content_json, :content_css)'
        );
        $statement->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'content_json' => $componentJson,
            'content_css' => $css,
        ]);
        logActivity('create', 'template', (int)$pdo->lastInsertId(), 'Nama template: ' . $name);
        pbJsonResponse([
            'success' => true,
            'message' => 'Custom Template berhasil disimpan!',
            'id' => (int)$pdo->lastInsertId(),
        ], 201);
    }

    if ($method === 'GET' && $action === 'load') {
        $slug = $_GET['slug'] ?? 'home';
        if (!pbValidSlug($slug)) {
            pbJsonResponse(['success' => false, 'message' => 'Slug halaman tidak valid.'], 422);
        }

        $statement = $pdo->prepare('SELECT slug, title, content_json, content_css, content_html FROM pages WHERE slug = :slug LIMIT 1');
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
                    'html' => ''
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
                'html' => pbSanitizeHtml((string)$row['content_html'])
            ]
        ]);
    }

    if ($method === 'POST' && $action === 'save') {
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['page_builder_csrf'] ?? '';
        if (!is_string($sentToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
            pbJsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang editor lalu coba lagi.'], 403);
        }

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
        $css = pbSanitizeCss(is_string($payload['css'] ?? null) ? $payload['css'] : '');
        $html = pbSanitizeHtml(is_string($payload['html'] ?? null) ? $payload['html'] : '');
        $title = trim(is_string($payload['title'] ?? null) ? $payload['title'] : '');
        if ($title === '') {
            $title = pbTitleFromSlug($slug);
        } elseif (preg_match_all('/./us', $title) > 255) {
            pbJsonResponse(['success' => false, 'message' => 'Judul halaman maksimal 255 karakter.'], 422);
        }

        $statement = $pdo->prepare(
            'INSERT INTO pages (slug, title, content_json, content_css, content_html)
             VALUES (:slug, :title, :content_json, :content_css, :content_html)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content_json = VALUES(content_json),
                content_css = VALUES(content_css),
                content_html = VALUES(content_html),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'slug' => $slug,
            'title' => $title,
            'content_json' => $componentJson,
            'content_css' => $css,
            'content_html' => $html
        ]);
        logActivity('update', 'page', $slug, 'Editor menyimpan halaman: ' . $title);

        pbJsonResponse(['success' => true, 'message' => 'Halaman berhasil disimpan.', 'slug' => $slug]);
    }

    header('Allow: GET, POST');
    pbJsonResponse(['success' => false, 'message' => 'Endpoint atau metode tidak ditemukan.'], 404);
} catch (PDOException $exception) {
    error_log('[Page Builder] Database error: ' . $exception->getMessage());
    pbJsonResponse(['success' => false, 'message' => 'Operasi database gagal. Pastikan tabel pages sudah dibuat.'], 500);
} catch (Throwable $exception) {
    error_log('[Page Builder] Request error: ' . $exception->getMessage());
    pbJsonResponse(['success' => false, 'message' => 'Permintaan tidak dapat diproses.'], 500);
}
