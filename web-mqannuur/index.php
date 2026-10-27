<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$themePreview = ($_GET['preview'] ?? '') === 'true';
$themeSlug = is_string($_GET['page'] ?? null) && $_GET['page'] !== '' ? (string)$_GET['page'] : 'home';
$themeSettings = pbSettings();
$pageTitle = pbSetting('site_name', 'Web MQ An-Nuur');
$pageCss = '';
$pageHtml = '';
$page = null;
$themeNotFound = false;
$themeError = '';

if ($themePreview) {
    $pageTitle = 'Preview Draft';
} elseif (!pbValidSlug($themeSlug)) {
    http_response_code(400);
    $themeNotFound = true;
    $themeError = 'Nama halaman tidak valid.';
    $pageTitle = 'Permintaan tidak valid';
} else {
    try {
        $statement = getDbConnection()->prepare('SELECT title, content_css, content_html, updated_at FROM pages WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $themeSlug]);
        $page = $statement->fetch();
        if ($page) {
            $pageTitle = (string)$page['title'];
            $pageCss = pbSanitizeCss((string)$page['content_css']);
            $pageHtml = pbSanitizeHtml((string)$page['content_html']);
            if ($themeSlug === 'home') {
                if (trim($pageHtml) === '') {
                    $page = null;
                    $pageCss = '';
                }
                $pageTitle = pbSetting('site_name', 'Web MQ An-Nuur');
            }
        } elseif ($themeSlug !== 'home') {
            http_response_code(404);
            $themeNotFound = true;
            $themeError = 'Halaman ' . $themeSlug . ' belum tersedia.';
            $pageTitle = 'Halaman tidak ditemukan';
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Theme render error: ' . $exception->getMessage());
        http_response_code(503);
        $themeNotFound = true;
        $themeError = 'Halaman belum dapat dimuat. Periksa koneksi database.';
        $pageTitle = 'Halaman belum siap';
    }
}

$themeKey = pbCurrentTheme();
$themeBaseUrl = pbThemeBaseUrl($themeKey);
$themeDir = __DIR__ . '/themes/' . $themeKey;

require $themeDir . '/header.php';
if ($themePreview) {
    // Footer memuat script preview dari localStorage.
} elseif ($themeNotFound || ($page !== null && $themeSlug !== 'home')) {
    require $themeDir . '/page.php';
} else {
    require $themeDir . '/index.php';
}
require $themeDir . '/footer.php';
