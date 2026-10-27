<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/analytics.php';

$themeSettings = isset($themeSettings) && is_array($themeSettings) ? $themeSettings : pbSettings();
$themeSiteName = trim((string)($themeSettings['site_name'] ?? '')) ?: 'Web MQ An-Nuur';
$themeTagline = trim((string)($themeSettings['site_tagline'] ?? '')) ?: 'Madrasah Qur\'an An-Nuur';
$themeDescription = trim((string)($pageDescription ?? ($themeSettings['site_description'] ?? '')));
$themeLogo = trim((string)($themeSettings['site_logo'] ?? ''));
$themeLogoUrl = $themeLogo !== '' ? pbSafeUrl($themeLogo) : null;
$themeWhatsapp = pbWhatsappUrl((string)($themeSettings['site_whatsapp'] ?? ''));
$themeEmail = trim((string)($themeSettings['site_email'] ?? ''));
$themePhone = trim((string)($themeSettings['site_phone'] ?? ''));
$themeLinks = pbPublicLinks();
$themeNavLinks = [];
if ($themeLinks === []) {
    $themeNavLinks = [
        ['title' => 'Berita', 'url' => 'index.php#berita', 'icon' => 'fa-newspaper'],
        ['title' => 'Agenda', 'url' => 'index.php#agenda', 'icon' => 'fa-calendar-days'],
        ['title' => 'Guru', 'url' => 'index.php#guru', 'icon' => 'fa-user-tie'],
        ['title' => 'Galeri', 'url' => 'index.php#galeri', 'icon' => 'fa-images'],
        ['title' => 'Download', 'url' => 'index.php#download', 'icon' => 'fa-file-arrow-down'],
        ['title' => 'Kontak', 'url' => 'index.php#kontak', 'icon' => 'fa-envelope'],
    ];
} else {
    foreach ($themeLinks as $link) {
        $url = pbSafeUrl((string)$link['url']);
        if ($url === null || preg_match('~^(?:mailto:|tel:)~i', $url)) {
            continue;
        }
        $themeNavLinks[] = ['title' => (string)$link['title'], 'url' => $url, 'icon' => (string)$link['icon']];
    }
}
$pageTitle = $pageTitle ?? $themeSiteName;
$pageCss = $pageCss ?? '';
$ogImage = $ogImage ?? '';
$themeCurrentUrl = (string)($themeSlug ?? '');
$seoIsHome = empty($themeSlug) || $themeSlug === 'home';
$seoCanonical = pbSiteBaseUrl() . '/index.php';
if (!empty($themePost['slug'])) {
    $seoCanonical = pbSiteBaseUrl() . '/post.php?slug=' . rawurlencode((string)$themePost['slug']);
} elseif (!$seoIsHome && !empty($themeSlug)) {
    $seoCanonical = pbSiteBaseUrl() . '/index.php?page=' . rawurlencode((string)$themeSlug);
}
$seoMeta = pbSeoMeta([
    'title' => (string)($pageTitle ?? ''),
    'description' => (string)($pageDescription ?? ''),
    'og_image' => (string)($ogImage ?? ''),
    'logo' => (string)($themeLogoUrl ?? ''),
    'site_name' => $themeSiteName,
    'is_home' => $seoIsHome,
    'canonical' => $seoCanonical,
]);
$trackPath = '/';
if (!empty($themePost['slug'])) {
    $trackPath = '/berita/' . (string)$themePost['slug'];
} elseif (!$seoIsHome && !empty($themeSlug)) {
    $trackPath = '/' . (string)$themeSlug;
}
pbTrackVisit($trackPath, (string)($pageTitle ?? ''));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b3d35">
    <title><?= pbE($seoMeta['title']) ?></title>
    <meta name="description" content="<?= pbE($seoMeta['description']) ?>">
    <link rel="canonical" href="<?= pbE($seoMeta['canonical']) ?>">
    <meta property="og:title" content="<?= pbE($seoMeta['title']) ?>">
    <meta property="og:description" content="<?= pbE($seoMeta['description']) ?>">
    <meta property="og:type" content="<?= !empty($themePost) ? 'article' : 'website' ?>">
    <meta property="og:url" content="<?= pbE($seoMeta['canonical']) ?>">
    <meta property="og:site_name" content="<?= pbE($themeSiteName) ?>">
    <?php if ($seoMeta['og_image'] !== ''): ?><meta property="og:image" content="<?= pbE($seoMeta['og_image']) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $seoMeta['og_image'] !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= pbE($seoMeta['title']) ?>">
    <meta name="twitter:description" content="<?= pbE($seoMeta['description']) ?>">
    <?php if ($seoMeta['og_image'] !== ''): ?><meta name="twitter:image" content="<?= pbE($seoMeta['og_image']) ?>"><?php endif; ?>
    <?php if ($themeLogoUrl !== null): ?><link rel="icon" href="<?= pbE($themeLogoUrl) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="themes/default/assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/builder-blocks.css">
    <?php if ($pageCss !== ''): ?><style><?= $pageCss ?></style><?php endif; ?>
</head>
<body class="<?= !empty($themePreview) ? 'preview-mode' : '' ?>">
<header class="site-header">
    <div class="site-topline">
        <div class="site-container site-topline-inner">
            <div class="site-topline-contact">
                <?php if ($themeEmail !== ''): ?><a href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><?= pbE($themeEmail) ?></a><?php endif; ?>
                <?php if ($themePhone !== ''): ?><a href="tel:<?= pbE(preg_replace('/[^0-9+]/', '', $themePhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><?= pbE($themePhone) ?></a><?php endif; ?>
            </div>
            <div class="site-topline-social">
                <?php foreach (['site_facebook' => 'fa-brands fa-facebook-f', 'site_instagram' => 'fa-brands fa-instagram', 'site_youtube' => 'fa-brands fa-youtube'] as $key => $icon): ?>
                    <?php $safeSocial = trim((string)($themeSettings[$key] ?? '')); if ($safeSocial !== '' && pbSafeUrl($safeSocial) !== null): ?>
                        <a href="<?= pbE($safeSocial) ?>" target="_blank" rel="noopener" aria-label="<?= pbE(str_replace('site_', '', $key)) ?>"><i class="<?= pbE($icon) ?>" aria-hidden="true"></i></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="site-container site-header-main">
        <a class="site-brand" href="index.php">
            <?php if ($themeLogoUrl !== null): ?>
                <img class="site-brand-logo" src="<?= pbE($themeLogoUrl) ?>" alt="<?= pbE($themeSiteName) ?>">
            <?php else: ?>
                <span class="site-brand-mark" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
            <?php endif; ?>
            <span class="site-brand-text"><strong><?= pbE($themeSiteName) ?></strong><small><?= pbE($themeTagline) ?></small></span>
        </a>
        <button class="site-menu-toggle" id="theme-menu-toggle" type="button" aria-controls="theme-nav" aria-expanded="false" aria-label="Buka navigasi">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <nav class="site-nav" id="theme-nav" aria-label="Navigasi utama">
            <a href="index.php" class="<?= empty($themeSlug) || $themeSlug === 'home' ? 'active' : '' ?>">Home</a>
            <?php foreach ($themeNavLinks as $link): ?>
                <?php $isExternal = preg_match('~^https?://~i', $link['url']) === 1; ?>
                <a href="<?= pbE($link['url']) ?>"<?= $isExternal ? ' target="_blank" rel="noopener"' : '' ?>>
                    <?php if ($link['icon'] !== ''): ?>
                        <?php if (preg_match('/^(?:fa[srbldt]?|fa-(?:solid|regular|brands|light|thin|duotone))\s+fa-/i', trim($link['icon']))): ?><i class="<?= pbE($link['icon']) ?>" aria-hidden="true"></i><?php endif; ?>
                    <?php endif; ?>
                    <?= pbE($link['title']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($themeWhatsapp !== ''): ?><a class="site-nav-cta" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>WhatsApp</a><?php endif; ?>
        </nav>
    </div>
</header>
