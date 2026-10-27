<?php
declare(strict_types=1);

$themePost = $themePost ?? null;
$themePostContent = (string)($themePostContent ?? '');
$themePostExcerpt = (string)($themePostExcerpt ?? '');
$themePostImage = (string)($themePostImage ?? '');
$themePostCategory = (string)($themePostCategory ?? '');
$themePostDate = (string)($themePostDate ?? '');
?>
<main class="site-page">
    <?php if ($themePost === null): ?>
        <section class="site-page-hero site-page-hero-error">
            <div class="site-container">
                <p class="site-eyebrow">Berita</p>
                <h1>Berita tidak ditemukan</h1>
                <p>Berita yang Anda cari tidak tersedia atau sudah tidak dipublikasikan.</p>
            </div>
        </section>
        <div class="site-container site-page-wrap">
            <div class="site-empty site-error-card">
                <i class="fa-regular fa-newspaper" aria-hidden="true"></i>
                <p>Silakan kembali ke halaman utama untuk melihat berita lainnya.</p>
                <a class="site-btn site-btn-primary" href="index.php#berita">Kembali ke Berita</a>
            </div>
        </div>
    <?php else: ?>
        <header class="site-page-hero site-post-hero">
            <div class="site-container">
                <a class="site-back-link" href="index.php#berita"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Semua Berita</a>
                <?php if ($themePostCategory !== ''): ?><span class="site-tag"><?= pbE($themePostCategory) ?></span><?php endif; ?>
                <h1><?= pbE($themePost['title']) ?></h1>
                <?php if ($themePostDate !== ''): ?><p class="site-page-meta"><?= pbE($themePostDate) ?></p><?php endif; ?>
            </div>
        </header>
        <div class="site-container site-page-wrap">
            <article class="site-article">
                <?php if ($themePostImage !== ''): ?><img class="site-post-cover" src="<?= pbE($themePostImage) ?>" alt="<?= pbE($themePost['title']) ?>" decoding="async"><?php endif; ?>
                <?php if ($themePostExcerpt !== ''): ?><p class="site-post-lead"><?= pbE($themePostExcerpt) ?></p><?php endif; ?>
                <div class="site-prose"><?= $themePostContent ?></div>
            </article>
        </div>
    <?php endif; ?>
</main>
