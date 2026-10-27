<?php
declare(strict_types=1);

$themeNotFound = !empty($themeNotFound);
$themeError = (string)($themeError ?? '');
$pageHtml = (string)($pageHtml ?? '');
?>
<main class="theme-page-main">
    <?php if ($themeNotFound): ?>
        <div class="site-container theme-page-wrap">
            <div class="theme-empty theme-error-card">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <h1>Halaman tidak tersedia</h1>
                <p><?= pbE($themeError !== '' ? $themeError : 'Halaman yang Anda cari belum tersedia.') ?></p>
                <a class="theme-btn primary" href="index.php">Kembali ke Home</a>
            </div>
        </div>
    <?php else: ?>
        <header class="theme-page-hero">
            <div class="site-container">
                <p class="theme-eyebrow">Halaman</p>
                <h1><?= pbE($pageTitle ?? ($page['title'] ?? 'Halaman')) ?></h1>
                <?php if (!empty($page['updated_at'])): ?><p class="theme-page-meta">Diperbarui <?= pbE(date('d M Y', strtotime((string)$page['updated_at']))) ?></p><?php endif; ?>
            </div>
        </header>
        <div class="site-container theme-page-wrap">
            <article class="theme-article">
                <?= $pageHtml !== '' ? $pageHtml : '<div class="theme-empty"><i class="fa-regular fa-file-lines" aria-hidden="true"></i>Halaman ini belum memiliki konten. Buka editor untuk mulai menata layout.</div>' ?>
            </article>
        </div>
    <?php endif; ?>
</main>
