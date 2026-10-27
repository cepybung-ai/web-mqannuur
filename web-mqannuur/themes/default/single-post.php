<?php
declare(strict_types=1);

$themePost = $themePost ?? null;
$themePostContent = (string)($themePostContent ?? '');
$themePostExcerpt = (string)($themePostExcerpt ?? '');
$themePostImage = (string)($themePostImage ?? '');
$themePostCategory = (string)($themePostCategory ?? '');
$themePostDate = (string)($themePostDate ?? '');
?>
<main class="theme-page-main">
    <?php if ($themePost === null): ?>
        <div class="site-container theme-page-wrap">
            <div class="theme-empty theme-error-card">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <h1>Berita tidak ditemukan</h1>
                <p>Berita yang Anda cari tidak tersedia atau sudah tidak dipublikasikan.</p>
                <a class="theme-btn primary" href="index.php">Kembali ke Home</a>
            </div>
        </div>
    <?php else: ?>
        <header class="theme-page-hero theme-post-hero">
            <div class="site-container">
                <a class="theme-back-link" href="index.php#berita"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Semua Berita</a>
                <?php if ($themePostCategory !== ''): ?><span class="theme-tag"><?= pbE($themePostCategory) ?></span><?php endif; ?>
                <h1><?= pbE($themePost['title']) ?></h1>
                <?php if ($themePostDate !== ''): ?><p class="theme-page-meta"><?= pbE($themePostDate) ?></p><?php endif; ?>
            </div>
        </header>
        <div class="site-container theme-page-wrap">
            <article class="theme-article">
                <?php if ($themePostImage !== ''): ?><img class="theme-post-cover" src="<?= pbE($themePostImage) ?>" alt="<?= pbE($themePost['title']) ?>" decoding="async"><?php endif; ?>
                <?php if ($themePostExcerpt !== ''): ?><p class="theme-post-lead"><?= pbE($themePostExcerpt) ?></p><?php endif; ?>
                <div class="theme-prose"><?= $themePostContent ?></div>
            </article>
        </div>
    <?php endif; ?>
</main>
