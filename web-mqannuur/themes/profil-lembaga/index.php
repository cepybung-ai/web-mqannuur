<?php
declare(strict_types=1);

require __DIR__ . '/../_shared/home-data.php';
?>
<main>
    <section class="org-hero">
        <div class="site-container org-hero-grid">
            <div class="org-hero-copy">
                <p class="org-kicker"><span></span>Profil Lembaga</p>
                <h1><?= pbE($themeSiteName) ?></h1>
                <p class="org-tagline"><?= pbE($themeTagline) ?></p>
                <p class="org-intro"><?= pbE($themeDescription !== '' ? $themeDescription : 'Mengenal lebih dekat visi, nilai, dan aktivitas lembaga kami.') ?></p>
                <div class="org-hero-actions">
                    <a class="site-btn site-btn-primary" href="#berita">Kabar Terbaru <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <a class="site-btn" href="#kontak">Hubungi Lembaga</a>
                </div>
                <div class="org-quick-meta">
                    <?php if ($themeAddress !== ''): ?><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($themeAddress) ?></span><?php endif; ?>
                    <?php if ($themeEmail !== ''): ?><a href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><?= pbE($themeEmail) ?></a><?php endif; ?>
                </div>
            </div>
            <aside class="org-identity">
                <div class="org-identity-head">
                    <span class="org-crest" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                    <div><small>Identitas</small><strong><?= pbE($themeSiteName) ?></strong></div>
                </div>
                <p>Lembaga yang tumbuh melalui pendidikan, pembinaan, dan pelayanan masyarakat.</p>
                <ul class="org-identity-list">
                    <li><span>01</span><div><strong>Berita</strong><small>Kabar &amp; dokumentasi terbaru</small></div></li>
                    <li><span>02</span><div><strong>Pengumuman</strong><small>Agenda dan informasi resmi</small></div></li>
                    <li><span>03</span><div><strong>Kontak</strong><small>Kanal komunikasi lembaga</small></div></li>
                </ul>
                <div class="org-identity-stats">
                    <div><strong><?= count($themeLatestPosts) ?></strong><span>Berita</span></div>
                    <div><strong><?= count($themeEvents) ?></strong><span>Pengumuman</span></div>
                    <div><strong><?= date('Y') ?></strong><span>Tahun</span></div>
                </div>
            </aside>
        </div>
    </section>

    <?php if ($themeHomeHtml !== ''): ?>
        <section class="site-section org-profile">
            <div class="site-container org-profile-grid">
                <div class="org-profile-head">
                    <p class="site-eyebrow">Profil</p>
                    <h2>Tentang Lembaga</h2>
                    <span class="org-rule" aria-hidden="true"></span>
                    <p>Visi, nilai, dan informasi yang kami bagikan untuk publik.</p>
                </div>
                <div class="site-custom-content org-custom-content"><?= $themeHomeHtml ?></div>
            </div>
        </section>
    <?php endif; ?>

    <section class="site-section org-news" id="berita">
        <div class="site-container">
            <div class="site-section-head">
                <div><p class="site-eyebrow">Publikasi</p><h2>Kabar Lembaga</h2></div>
                <a class="site-text-link" href="#kontak">Informasi lebih lanjut <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if ($themeLatestPosts === []): ?>
                <div class="site-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i><p>Belum ada berita yang dipublikasikan.</p></div>
            <?php else: ?>
                <div class="org-post-grid">
                    <?php foreach ($themeLatestPosts as $index => $post): ?>
                        <?php $image = trim((string)($post['featured_image'] ?? '')); $safeImage = $image !== '' ? pbSafeUrl($image) : null; ?>
                        <article class="org-post<?= $index === 0 ? ' is-featured' : '' ?>">
                            <a class="org-post-media" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">
                                <?php if ($safeImage !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeImage)): ?>
                                    <img src="<?= pbE($safeImage) ?>" alt="<?= pbE($post['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                <?php endif; ?>
                            </a>
                            <div class="org-post-body">
                                <div class="org-post-meta"><span><?= pbE($post['category']) ?></span><time datetime="<?= pbE($post['created_at']) ?>"><?= pbE(date('d M Y', strtotime((string)$post['created_at']))) ?></time></div>
                                <h3><a href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>"><?= pbE($post['title']) ?></a></h3>
                                <p><?= pbE(trim(strip_tags((string)($post['excerpt'] ?? '')))) ?: 'Baca informasi selengkapnya.' ?></p>
                                <a class="org-post-link" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">Baca selengkapnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <span class="site-anchor" id="agenda" aria-hidden="true"></span>
    <section class="site-section org-announcements" id="pengumuman">
        <div class="site-container org-announcement-grid">
            <div class="org-announcement-intro">
                <p class="site-eyebrow">Resmi</p>
                <h2>Pengumuman &amp; Agenda</h2>
                <p>Informasi resmi lembaga untuk anggota, wali, mitra, dan masyarakat umum.</p>
                <?php if ($themeWhatsapp !== ''): ?><a class="site-btn site-btn-primary" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>Hubungi via WhatsApp</a><?php endif; ?>
            </div>
            <div class="org-timeline">
                <?php if ($themeEvents === []): ?>
                    <div class="site-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>Belum ada pengumuman yang dipublikasikan.</p></div>
                <?php else: ?>
                    <?php foreach ($themeEvents as $event): ?>
                        <?php $timestamp = strtotime((string)$event['event_date']) ?: time(); ?>
                        <article class="org-timeline-item">
                            <span class="org-timeline-dot" aria-hidden="true"></span>
                            <div class="org-timeline-date"><?= pbE(date('d M Y', $timestamp)) ?></div>
                            <h3><?= pbE($event['title']) ?></h3>
                            <?php if (!empty($event['excerpt'])): ?><p><?= pbE($event['excerpt']) ?></p><?php endif; ?>
                            <?php if (!empty($event['location'])): ?><small><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($event['location']) ?></small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="org-contact" id="kontak">
        <div class="site-container org-contact-inner">
            <div>
                <p class="org-contact-kicker">Kontak Lembaga</p>
                <h2>Mari terhubung dan tumbuh bersama.</h2>
            </div>
            <div class="org-contact-links">
                <?php if ($themePhone !== ''): ?><a href="tel:<?= pbE(preg_replace('/[^0-9+]/', '', $themePhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Telepon</span><strong><?= pbE($themePhone) ?></strong></a><?php endif; ?>
                <?php if ($themeEmail !== ''): ?><a href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Email</span><strong><?= pbE($themeEmail) ?></strong></a><?php endif; ?>
                <?php if ($themeWhatsapp !== ''): ?><a href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span><strong>Chat Sekarang</strong></a><?php endif; ?>
                <?php if ($themePhone === '' && $themeEmail === '' && $themeWhatsapp === ''): ?><a href="#berita"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Kontak</span><strong>Atur melalui Identitas Situs</strong></a><?php endif; ?>
            </div>
        </div>
    </section>
</main>
