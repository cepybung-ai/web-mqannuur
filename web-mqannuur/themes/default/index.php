<?php
declare(strict_types=1);

$themeSettings = isset($themeSettings) && is_array($themeSettings) ? $themeSettings : pbSettings();
$themeSiteName = trim((string)($themeSettings['site_name'] ?? '')) ?: 'Web MQ An-Nuur';
$themeTagline = trim((string)($themeSettings['site_tagline'] ?? '')) ?: 'Madrasah Qur\'an An-Nuur';
$themeDescription = trim((string)($themeSettings['site_description'] ?? ''));
$themeWhatsapp = pbWhatsappUrl((string)($themeSettings['site_whatsapp'] ?? ''));
$themeEmail = trim((string)($themeSettings['site_email'] ?? ''));
$latestPosts = [];
$events = [];
$staff = [];
$gallery = [];
$downloads = [];
$pageHtml = (string)($pageHtml ?? '');
try {
    $pdo = getDbConnection();
    $latestPosts = $pdo->query("SELECT title, slug, excerpt, featured_image, category, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 6")->fetchAll();
    $events = $pdo->query("SELECT title, slug, excerpt, location, event_date, event_end_date FROM events WHERE status = 'published' ORDER BY event_date DESC, id DESC LIMIT 4")->fetchAll();
    $staff = $pdo->query('SELECT name, position, category, photo, bio FROM staff WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 4')->fetchAll();
    $gallery = $pdo->query('SELECT title, caption, file_path FROM gallery WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 8')->fetchAll();
    $downloads = $pdo->query('SELECT id, title, description, category, file_name, file_size, external_url FROM downloads WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 6')->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Theme home data error: ' . $exception->getMessage());
}
?>
<main>
    <?php if (trim($pageHtml) !== ''): ?>
        <?php
        $themeHomeHtml = trim($pageHtml);
        $themeHomeHtml = preg_replace('/^\s*<main\b[^>]*>/i', '', $themeHomeHtml) ?? $themeHomeHtml;
        $themeHomeHtml = preg_replace('/<\/main>\s*$/i', '', $themeHomeHtml) ?? $themeHomeHtml;
        ?>
        <div class="theme-custom-home"><?= $themeHomeHtml ?></div>
    <?php else: ?>
    <section class="theme-hero">
        <div class="site-container theme-hero-grid">
            <div class="theme-hero-copy">
                <p class="theme-eyebrow"><?= pbE($themeTagline) ?></p>
                <h1><?= pbE($themeSiteName) ?></h1>
                <p class="theme-lead"><?= pbE($themeDescription !== '' ? $themeDescription : 'Membangun generasi Qur\'ani yang berilmu, berakhlak, dan bermanfaat.') ?></p>
                <div class="theme-hero-actions">
                    <?php if ($themeWhatsapp !== ''): ?><a class="theme-btn primary" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>Hubungi Kami</a><?php endif; ?>
                    <a class="theme-btn" href="#berita">Jelajahi Website <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="theme-hero-card">
                <span class="theme-hero-mark" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                <h2>Belajar, Tumbuh, Beramal</h2>
                <p>Informasi kegiatan, agenda, galeri, dan dokumen madrasah dalam satu tempat.</p>
                <div class="theme-hero-stats">
                    <div><strong><?= count($latestPosts) ?></strong><span>Berita</span></div>
                    <div><strong><?= count($events) ?></strong><span>Agenda</span></div>
                    <div><strong><?= count($staff) ?></strong><span>Guru</span></div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="theme-section" id="berita">
        <div class="site-container">
            <div class="theme-section-head">
                <div><p class="theme-eyebrow">Informasi</p><h2>Berita Terbaru</h2></div>
                <?php if ($latestPosts !== []): ?><a class="theme-text-link" href="index.php#berita">Semua berita <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
            </div>
            <?php if ($latestPosts === []): ?>
                <div class="theme-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i>Belum ada berita yang dipublikasikan.</div>
            <?php else: ?>
                <div class="theme-post-grid">
                    <?php foreach ($latestPosts as $post): ?>
                        <?php $image = trim((string)($post['featured_image'] ?? '')); $safeImage = $image !== '' ? pbSafeUrl($image) : null; ?>
                        <article class="theme-post-card">
                            <a class="theme-post-image" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">
                                <?php if ($safeImage !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeImage)): ?>
                                    <img src="<?= pbE($safeImage) ?>" alt="<?= pbE($post['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="theme-post-placeholder" aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                <?php endif; ?>
                            </a>
                            <div class="theme-post-body">
                                <span class="theme-tag"><?= pbE($post['category']) ?></span>
                                <h3><a href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>"><?= pbE($post['title']) ?></a></h3>
                                <p><?= pbE(trim(strip_tags((string)($post['excerpt'] ?? '')))) ?: 'Baca informasi selengkapnya.' ?></p>
                                <time datetime="<?= pbE($post['created_at']) ?>"><?= pbE(date('d M Y', strtotime((string)$post['created_at']))) ?></time>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="theme-section theme-section-alt" id="agenda">
        <div class="site-container">
            <div class="theme-section-head"><div><p class="theme-eyebrow">Kegiatan</p><h2>Agenda &amp; Pengumuman</h2></div></div>
            <?php if ($events === []): ?>
                <div class="theme-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Belum ada agenda yang dipublikasikan.</div>
            <?php else: ?>
                <div class="theme-event-grid">
                    <?php foreach ($events as $event): ?>
                        <?php $date = strtotime((string)$event['event_date']); ?>
                        <article class="theme-event-card">
                            <div class="theme-event-date"><strong><?= pbE(date('d', $date)) ?></strong><span><?= pbE(date('M Y', $date)) ?></span></div>
                            <div class="theme-event-body">
                                <h3><?= pbE($event['title']) ?></h3>
                                <?php if (!empty($event['excerpt'])): ?><p><?= pbE($event['excerpt']) ?></p><?php endif; ?>
                                <div class="theme-event-meta">
                                    <?php if (!empty($event['location'])): ?><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($event['location']) ?></span><?php endif; ?>
                                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i><?= pbE(date('d M Y', $date)) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="theme-section" id="guru">
        <div class="site-container">
            <div class="theme-section-head"><div><p class="theme-eyebrow">SDM</p><h2>Guru &amp; Staf</h2></div></div>
            <?php if ($staff === []): ?>
                <div class="theme-empty"><i class="fa-regular fa-user" aria-hidden="true"></i>Data guru dan staf belum tersedia.</div>
            <?php else: ?>
                <div class="theme-staff-grid">
                    <?php foreach ($staff as $person): ?>
                        <?php $photo = trim((string)($person['photo'] ?? '')); $safePhoto = $photo !== '' ? pbSafeUrl($photo) : null; ?>
                        <article class="theme-staff-card">
                            <?php if ($safePhoto !== null && !preg_match('~^(?:mailto:|tel:)~i', $safePhoto)): ?>
                                <img src="<?= pbE($safePhoto) ?>" alt="<?= pbE($person['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="theme-avatar" aria-hidden="true"><?= pbE(strtoupper(substr((string)$person['name'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <h3><?= pbE($person['name']) ?></h3>
                            <p><?= pbE($person['position']) ?></p>
                            <?php if (!empty($person['bio'])): ?><small><?= pbE($person['bio']) ?></small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="theme-section theme-section-alt" id="galeri">
        <div class="site-container">
            <div class="theme-section-head"><div><p class="theme-eyebrow">Dokumentasi</p><h2>Galeri Media</h2></div></div>
            <?php if ($gallery === []): ?>
                <div class="theme-empty"><i class="fa-regular fa-images" aria-hidden="true"></i>Belum ada foto di galeri.</div>
            <?php else: ?>
                <div class="theme-gallery-grid">
                    <?php foreach ($gallery as $image): ?>
                        <button class="theme-gallery-item" type="button" data-lightbox="<?= pbE($image['file_path']) ?>" data-caption="<?= pbE($image['title']) ?>">
                            <img src="<?= pbE($image['file_path']) ?>" alt="<?= pbE($image['title']) ?>" loading="lazy">
                            <span><?= pbE($image['title']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="theme-section" id="download">
        <div class="site-container">
            <div class="theme-section-head"><div><p class="theme-eyebrow">Dokumen</p><h2>Pusat Download</h2></div></div>
            <?php if ($downloads === []): ?>
                <div class="theme-empty"><i class="fa-regular fa-file" aria-hidden="true"></i>Belum ada dokumen yang tersedia.</div>
            <?php else: ?>
                <div class="theme-download-grid">
                    <?php foreach ($downloads as $item): ?>
                        <article class="theme-download-card">
                            <span class="theme-download-icon" aria-hidden="true"><i class="fa-regular fa-file-pdf"></i></span>
                            <div>
                                <span class="theme-tag"><?= pbE($item['category']) ?></span>
                                <h3><?= pbE($item['title']) ?></h3>
                                <?php if (!empty($item['description'])): ?><p><?= pbE($item['description']) ?></p><?php endif; ?>
                                <small><?= !empty($item['file_name']) ? pbE($item['file_name']) : 'Dokumen eksternal' ?><?= !empty($item['file_size']) ? ' · ' . pbE(pbFormatBytes((int)$item['file_size'])) : '' ?></small>
                            </div>
                            <a class="theme-btn small" href="download.php?id=<?= (int)$item['id'] ?>"><i class="fa-solid fa-download" aria-hidden="true"></i>Unduh</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="theme-cta" id="kontak">
        <div class="site-container theme-cta-inner">
            <div><p class="theme-eyebrow">Terhubung</p><h2>Ingin bertanya atau bekerja sama?</h2><p>Hubungi madrasah melalui WhatsApp atau email resmi.</p></div>
            <div class="theme-hero-actions">
                <?php if ($themeWhatsapp !== ''): ?><a class="theme-btn primary" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>WhatsApp</a><?php endif; ?>
                <?php if ($themeEmail !== ''): ?><a class="theme-btn" href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i>Email</a><?php endif; ?>
            </div>
        </div>
    </section>
</main>
<div class="theme-lightbox" id="theme-lightbox" hidden>
    <button class="theme-lightbox-close" type="button" aria-label="Tutup">&times;</button>
    <figure><img src="" alt=""><figcaption></figcaption></figure>
</div>
