<?php
declare(strict_types=1);

$themeNotFound = !empty($themeNotFound);
$themeError = (string)($themeError ?? '');
$pageHtml = (string)($pageHtml ?? '');
?>
<main class="site-page">
    <?php if ($themeNotFound): ?>
        <section class="site-page-hero site-page-hero-error">
            <div class="site-container">
                <p class="site-eyebrow">Informasi</p>
                <h1>Halaman tidak tersedia</h1>
                <p><?= pbE($themeError !== '' ? $themeError : 'Halaman yang Anda cari belum tersedia.') ?></p>
            </div>
        </section>
        <div class="site-container site-page-wrap">
            <div class="site-empty site-error-card">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <p>Silakan kembali ke halaman utama atau gunakan navigasi di atas.</p>
                <a class="site-btn site-btn-primary" href="index.php">Kembali ke Home</a>
            </div>
        </div>
    <?php else: ?>
        <header class="site-page-hero">
            <div class="site-container">
                <p class="site-eyebrow">Halaman</p>
                <h1><?= pbE($pageTitle ?? ($page['title'] ?? 'Halaman')) ?></h1>
                <?php if (!empty($page['updated_at'])): ?><p class="site-page-meta">Diperbarui <?= pbE(date('d M Y', strtotime((string)$page['updated_at']))) ?></p><?php endif; ?>
            </div>
        </header>
        <div class="site-container site-page-wrap">
            <article class="site-article">
                <?= $pageHtml !== '' ? $pageHtml : '<div class="site-empty"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><p>Halaman ini belum memiliki konten. Buka editor untuk mulai menata layout.</p></div>' ?>
            </article>
        </div>
    <?php endif; ?>
</main>
