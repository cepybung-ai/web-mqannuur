<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$slug = trim(is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '');
$themePost = null;
$themePostContent = '<p>Berita yang Anda cari tidak tersedia.</p>';
$themePostExcerpt = '';
$themePostImage = '';
$themePostCategory = '';
$themePostDate = '';
$pageTitle = 'Berita tidak ditemukan';
$pageDescription = '';
$ogImage = '';
$themeSlug = 'berita';
$themeSettings = pbSettings();

if ($slug !== '' && strlen($slug) <= 255 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    try {
        $statement = getDbConnection()->prepare("SELECT title, slug, content, excerpt, featured_image, category, status, created_at, updated_at FROM posts WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $themePost = $statement->fetch() ?: null;
        if ($themePost) {
            $pageTitle = (string)$themePost['title'];
            $themePostExcerpt = trim(strip_tags((string)($themePost['excerpt'] ?? '')));
            $themePostContent = pbSanitizeHtml((string)$themePost['content']);
            $candidate = trim((string)($themePost['featured_image'] ?? ''));
            $safeUrl = $candidate !== '' ? pbSafeUrl($candidate) : null;
            if ($safeUrl !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeUrl)) {
                $themePostImage = $safeUrl;
                $ogImage = $safeUrl;
            }
            $themePostCategory = (string)($themePost['category'] ?? '');
            $publishedAt = (string)$themePost['created_at'];
            $themePostDate = $publishedAt !== '' ? date('d M Y · H:i', strtotime($publishedAt)) : '';
            $pageDescription = $themePostExcerpt;
        } else {
            http_response_code(404);
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Post render error: ' . $exception->getMessage());
        http_response_code(503);
        $pageTitle = 'Berita belum siap';
        $themePostContent = '<p>Berita belum dapat dimuat. Periksa koneksi database.</p>';
    }
} else {
    http_response_code(404);
}

$themeKey = pbCurrentTheme();
$themeBaseUrl = pbThemeBaseUrl($themeKey);
$themeDir = __DIR__ . '/themes/' . $themeKey;

require $themeDir . '/header.php';
require $themeDir . '/single-post.php';
require $themeDir . '/footer.php';
