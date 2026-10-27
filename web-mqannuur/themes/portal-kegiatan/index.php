<?php
declare(strict_types=1);

require __DIR__ . '/../_shared/home-data.php';
$nextEvent = $themeEvents[0] ?? null;
$nextEventTime = $nextEvent ? (strtotime((string)$nextEvent['event_date']) ?: time()) : time();
?>
<main>
    <section class="portal-hero">
        <div class="site-container portal-hero-grid">
            <div class="portal-hero-copy">
                <span class="portal-badge"><i class="fa-solid fa-bolt" aria-hidden="true"></i>Portal Kegiatan</span>
                <h1><?= pbE($themeSiteName) ?></h1>
                <p class="portal-lead"><?= pbE($themeDescription !== '' ? $themeDescription : 'Satu tempat untuk mengikuti berita, agenda, dan kegiatan terbaru.') ?></p>
                <div class="portal-hero-actions">
                    <a class="site-btn site-btn-primary" href="#pengumuman"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Lihat Agenda</a>
                    <a class="site-btn portal-btn-ghost" href="#berita">Kabar Terbaru <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="portal-hero-stats">
                    <div><strong><?= count($themeLatestPosts) ?></strong><span>Berita</span></div>
                    <div><strong><?= count($themeEvents) ?></strong><span>Agenda</span></div>
                    <div><strong><?= date('Y') ?></strong><span>Update</span></div>
                </div>
            </div>
            <aside class="portal-next-card">
                <div class="portal-next-head">
                    <span>Agenda Terbaru</span>
                    <span class="portal-live"><span></span>Update</span>
                </div>
                <?php if ($nextEvent): ?>
                    <div class="portal-next-date">
                        <strong><?= pbE(date('d', $nextEventTime)) ?></strong>
                        <span><?= pbE(date('M Y', $nextEventTime)) ?></span>
                    </div>
                    <h2><?= pbE($nextEvent['title']) ?></h2>
                    <?php if (!empty($nextEvent['excerpt'])): ?><p><?= pbE($nextEvent['excerpt']) ?></p><?php endif; ?>
                    <div class="portal-next-meta">
                        <?php if (!empty($nextEvent['location'])): ?><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($nextEvent['location']) ?></span><?php endif; ?>
                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i><?= pbE(date('d M Y', $nextEventTime)) ?></span>
                    </div>
                <?php else: ?>
                    <div class="portal-next-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>Belum ada agenda yang dipublikasikan.</p></div>
                <?php endif; ?>
                <a class="site-btn portal-next-link" href="#pengumuman">Semua Agenda <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
            </aside>
        </div>
    </section>

    <?php if ($themeHomeHtml !== ''): ?>
        <section class="site-section portal-custom">
            <div class="site-container">
                <div class="site-custom-content portal-custom-content"><?= $themeHomeHtml ?></div>
            </div>
        </section>
    <?php endif; ?>

    <section class="site-section portal-news" id="berita">
        <div class="site-container">
            <div class="site-section-head">
                <div><p class="site-eyebrow">Kabar</p><h2>Berita Terbaru</h2></div>
                <a class="site-text-link" href="#pengumuman">Cek agenda <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if ($themeLatestPosts === []): ?>
                <div class="site-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i><p>Belum ada berita yang dipublikasikan.</p></div>
            <?php else: ?>
                <div class="portal-post-grid">
                    <?php foreach ($themeLatestPosts as $post): ?>
                        <?php $image = trim((string)($post['featured_image'] ?? '')); $safeImage = $image !== '' ? pbSafeUrl($image) : null; ?>
                        <article class="portal-post">
                            <a class="portal-post-media" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">
                                <?php if ($safeImage !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeImage)): ?>
                                    <img src="<?= pbE($safeImage) ?>" alt="<?= pbE($post['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                <?php endif; ?>
                                <b class="portal-post-category"><?= pbE($post['category']) ?></b>
                            </a>
                            <div class="portal-post-body">
                                <time datetime="<?= pbE($post['created_at']) ?>"><?= pbE(date('d M Y', strtotime((string)$post['created_at']))) ?></time>
                                <h3><a href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>"><?= pbE($post['title']) ?></a></h3>
                                <p><?= pbE(trim(strip_tags((string)($post['excerpt'] ?? '')))) ?: 'Baca informasi selengkapnya.' ?></p>
                                <a class="site-text-link" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">Baca berita <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <span class="site-anchor" id="agenda" aria-hidden="true"></span>
    <section class="site-section portal-events" id="pengumuman">
        <div class="site-container">
            <div class="portal-events-head">
                <div>
                    <p class="site-eyebrow">Jadwal</p>
                    <h2>Agenda &amp; Kegiatan</h2>
                    <p>Ikuti kegiatan terbaru dan pastikan Anda tidak melewatkan informasi penting.</p>
                </div>
                <?php if ($themeWhatsapp !== ''): ?><a class="site-btn site-btn-primary" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>Tanya Agenda</a><?php endif; ?>
            </div>
            <?php if ($themeEvents === []): ?>
                <div class="site-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>Belum ada agenda yang dipublikasikan.</p></div>
            <?php else: ?>
                <div class="portal-event-grid">
                    <?php foreach ($themeEvents as $event): ?>
                        <?php $timestamp = strtotime((string)$event['event_date']) ?: time(); ?>
                        <article class="portal-event">
                            <div class="portal-event-date">
                                <strong><?= pbE(date('d', $timestamp)) ?></strong>
                                <span><?= pbE(date('M', $timestamp)) ?></span>
                            </div>
                            <div class="portal-event-body">
                                <div class="portal-event-top"><span class="site-tag">Agenda</span><time datetime="<?= pbE($event['event_date']) ?>"><?= pbE(date('Y', $timestamp)) ?></time></div>
                                <h3><?= pbE($event['title']) ?></h3>
                                <?php if (!empty($event['excerpt'])): ?><p><?= pbE($event['excerpt']) ?></p><?php endif; ?>
                                <div class="portal-event-meta">
                                    <?php if (!empty($event['location'])): ?><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($event['location']) ?></span><?php endif; ?>
                                    <span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i><?= pbE(date('d M Y', $timestamp)) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="portal-contact" id="kontak">
        <div class="site-container portal-contact-inner">
            <div class="portal-contact-copy">
                <p>Kontak &amp; Kolaborasi</p>
                <h2>Punya pertanyaan atau ingin ikut kegiatan?</h2>
                <span>Hubungi pengelola melalui kanal resmi berikut.</span>
            </div>
            <div class="portal-contact-links">
                <?php if ($themeWhatsapp !== ''): ?><a href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span><strong>Chat Sekarang</strong></a><?php endif; ?>
                <?php if ($themeEmail !== ''): ?><a href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Email</span><strong><?= pbE($themeEmail) ?></strong></a><?php endif; ?>
                <?php if ($themePhone !== ''): ?><a href="tel:<?= pbE(preg_replace('/[^0-9+]/', '', $themePhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Telepon</span><strong><?= pbE($themePhone) ?></strong></a><?php endif; ?>
                <?php if ($themeWhatsapp === '' && $themeEmail === '' && $themePhone === ''): ?><a href="#berita"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Kontak</span><strong>Atur melalui Identitas Situs</strong></a><?php endif; ?>
            </div>
        </div>
    </section>
</main>
