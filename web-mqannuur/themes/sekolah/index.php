<?php
declare(strict_types=1);

require __DIR__ . '/../_shared/home-data.php';
?>
<main>
    <section class="school-hero">
        <div class="site-container school-hero-grid">
            <div class="school-hero-copy">
                <span class="school-kicker"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>Sekolah &amp; Madrasah</span>
                <h1><?= pbE($themeSiteName) ?></h1>
                <p class="school-lead"><?= pbE($themeDescription !== '' ? $themeDescription : 'Ruang belajar yang menumbuhkan ilmu, adab, dan prestasi.') ?></p>
                <div class="school-hero-actions">
                    <a class="site-btn site-btn-primary" href="#berita"><i class="fa-regular fa-newspaper" aria-hidden="true"></i>Berita Sekolah</a>
                    <a class="site-btn" href="#kontak"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Hubungi Kami</a>
                </div>
                <dl class="school-stats">
                    <div><dt>Berita</dt><dd><?= count($themeLatestPosts) ?></dd></div>
                    <div><dt>Pengumuman</dt><dd><?= count($themeEvents) ?></dd></div>
                    <div><dt>Tahun</dt><dd><?= date('Y') ?></dd></div>
                </dl>
            </div>
            <aside class="school-hero-panel">
                <span class="school-panel-mark" aria-hidden="true"><i class="fa-solid fa-book-open-reader"></i></span>
                <h2>Informasi Sekolah</h2>
                <p>Berita terbaru, pengumuman resmi, dan kanal komunikasi sekolah dalam satu halaman.</p>
                <ul class="school-panel-list">
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Informasi kegiatan belajar</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Pengumuman untuk wali murid</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Kontak resmi sekolah</li>
                </ul>
                <?php if ($themeWhatsapp !== ''): ?><a class="site-btn site-btn-soft" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>WhatsApp Sekolah</a><?php endif; ?>
            </aside>
        </div>
    </section>

    <?php if ($themeHomeHtml !== ''): ?>
        <section class="site-section school-custom-section">
            <div class="site-container">
                <div class="site-custom-content school-custom-content"><?= $themeHomeHtml ?></div>
            </div>
        </section>
    <?php endif; ?>

    <section class="site-section" id="berita">
        <div class="site-container">
            <div class="site-section-head">
                <div><p class="site-eyebrow">Informasi</p><h2>Berita Sekolah</h2></div>
                <a class="site-text-link" href="#pengumuman">Lihat pengumuman <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if ($themeLatestPosts === []): ?>
                <div class="site-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i><p>Belum ada berita yang dipublikasikan.</p></div>
            <?php else: ?>
                <div class="school-post-grid">
                    <?php foreach ($themeLatestPosts as $post): ?>
                        <?php $image = trim((string)($post['featured_image'] ?? '')); $safeImage = $image !== '' ? pbSafeUrl($image) : null; ?>
                        <article class="school-post">
                            <a class="school-post-media" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">
                                <?php if ($safeImage !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeImage)): ?>
                                    <img src="<?= pbE($safeImage) ?>" alt="<?= pbE($post['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="school-post-placeholder" aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                <?php endif; ?>
                            </a>
                            <div class="school-post-body">
                                <span class="site-tag"><?= pbE($post['category']) ?></span>
                                <h3><a href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>"><?= pbE($post['title']) ?></a></h3>
                                <p><?= pbE(trim(strip_tags((string)($post['excerpt'] ?? '')))) ?: 'Baca informasi selengkapnya.' ?></p>
                                <div class="school-post-foot">
                                    <time datetime="<?= pbE($post['created_at']) ?>"><?= pbE(date('d M Y', strtotime((string)$post['created_at']))) ?></time>
                                    <a class="site-text-link" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>">Baca <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <span class="site-anchor" id="agenda" aria-hidden="true"></span>
    <section class="site-section school-announcement" id="pengumuman">
        <div class="site-container school-announcement-grid">
            <div class="school-announcement-copy">
                <p class="site-eyebrow">Pengumuman</p>
                <h2>Agenda &amp; Pengumuman Sekolah</h2>
                <p>Ikuti jadwal kegiatan, ujian, rapat wali murid, dan informasi penting lainnya.</p>
                <a class="site-btn site-btn-primary" href="#kontak"><i class="fa-regular fa-circle-question" aria-hidden="true"></i>Tanyakan Informasi</a>
            </div>
            <div class="school-announcement-list">
                <?php if ($themeEvents === []): ?>
                    <div class="site-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>Belum ada pengumuman yang dipublikasikan.</p></div>
                <?php else: ?>
                    <?php foreach ($themeEvents as $event): ?>
                        <?php $timestamp = strtotime((string)$event['event_date']) ?: time(); ?>
                        <article class="school-announcement-item">
                            <time class="school-announcement-date" datetime="<?= pbE($event['event_date']) ?>">
                                <strong><?= pbE(date('d', $timestamp)) ?></strong>
                                <span><?= pbE(date('M Y', $timestamp)) ?></span>
                            </time>
                            <div>
                                <span class="site-tag">Agenda</span>
                                <h3><?= pbE($event['title']) ?></h3>
                                <?php if (!empty($event['excerpt'])): ?><p><?= pbE($event['excerpt']) ?></p><?php endif; ?>
                                <small>
                                    <?php if (!empty($event['location'])): ?><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= pbE($event['location']) ?><?php endif; ?>
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i><?= pbE(date('d M Y', $timestamp)) ?>
                                </small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="site-section school-contact" id="kontak">
        <div class="site-container school-contact-panel">
            <div class="school-contact-copy">
                <p class="site-eyebrow">Kontak</p>
                <h2>Terhubung dengan Sekolah</h2>
                <p>Sampaikan pertanyaan, koordinasi wali murid, atau kebutuhan informasi sekolah melalui kanal resmi berikut.</p>
            </div>
            <div class="school-contact-grid">
                <?php if ($themeAddress !== ''): ?><div class="school-contact-item"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Alamat</span><strong><?= pbE($themeAddress) ?></strong></div><?php endif; ?>
                <?php if ($themePhone !== ''): ?><a class="school-contact-item" href="tel:<?= pbE(preg_replace('/[^0-9+]/', '', $themePhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Telepon</span><strong><?= pbE($themePhone) ?></strong></a><?php endif; ?>
                <?php if ($themeEmail !== ''): ?><a class="school-contact-item" href="mailto:<?= pbE($themeEmail) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Email</span><strong><?= pbE($themeEmail) ?></strong></a><?php endif; ?>
                <?php if ($themeWhatsapp !== ''): ?><a class="school-contact-item" href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span><strong>Chat Sekarang</strong></a><?php endif; ?>
                <?php if ($themeAddress === '' && $themePhone === '' && $themeEmail === '' && $themeWhatsapp === ''): ?>
                    <div class="school-contact-item"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Kontak</span><strong>Atur melalui Identitas Situs</strong></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
