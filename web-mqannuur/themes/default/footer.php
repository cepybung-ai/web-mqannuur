<?php
declare(strict_types=1);

$themeSettings = isset($themeSettings) && is_array($themeSettings) ? $themeSettings : pbSettings();
$themeSiteName = trim((string)($themeSettings['site_name'] ?? '')) ?: 'Web MQ An-Nuur';
$themeTagline = trim((string)($themeSettings['site_tagline'] ?? '')) ?: 'Madrasah Qur\'an An-Nuur';
$themeAddress = trim((string)($themeSettings['site_address'] ?? ''));
$themeEmail = trim((string)($themeSettings['site_email'] ?? ''));
$themePhone = trim((string)($themeSettings['site_phone'] ?? ''));
$themeWhatsapp = pbWhatsappUrl((string)($themeSettings['site_whatsapp'] ?? ''));
$themeFooterText = trim((string)($themeSettings['footer_text'] ?? ''));
$themeNavLinks = $themeNavLinks ?? [];
?>
<footer class="site-footer" id="kontak">
    <div class="site-container site-footer-grid">
        <div>
            <a class="site-footer-brand" href="index.php"><?= pbE($themeSiteName) ?></a>
            <p><?= pbE($themeFooterText !== '' ? $themeFooterText : $themeTagline) ?></p>
            <div class="site-footer-social">
                <?php foreach (['site_facebook' => 'fa-brands fa-facebook-f', 'site_instagram' => 'fa-brands fa-instagram', 'site_youtube' => 'fa-brands fa-youtube'] as $key => $icon): ?>
                    <?php $safeSocial = trim((string)($themeSettings[$key] ?? '')); if ($safeSocial !== '' && pbSafeUrl($safeSocial) !== null): ?>
                        <a href="<?= pbE($safeSocial) ?>" target="_blank" rel="noopener" aria-label="<?= pbE(str_replace('site_', '', $key)) ?>"><i class="<?= pbE($icon) ?>" aria-hidden="true"></i></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h2>Navigasi</h2>
            <ul>
                <li><a href="index.php">Home</a></li>
                <?php foreach (array_slice($themeNavLinks, 0, 6) as $link): ?>
                    <li><a href="<?= pbE($link['url']) ?>"><?= pbE($link['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h2>Kontak</h2>
            <ul class="site-footer-contact">
                <?php if ($themeAddress !== ''): ?><li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><?= pbE($themeAddress) ?></span></li><?php endif; ?>
                <?php if ($themePhone !== ''): ?><li><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:<?= pbE(preg_replace('/[^0-9+]/', '', $themePhone)) ?>"><?= pbE($themePhone) ?></a></li><?php endif; ?>
                <?php if ($themeEmail !== ''): ?><li><i class="fa-regular fa-envelope" aria-hidden="true"></i><a href="mailto:<?= pbE($themeEmail) ?>"><?= pbE($themeEmail) ?></a></li><?php endif; ?>
                <?php if ($themeWhatsapp !== ''): ?><li><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><a href="<?= pbE($themeWhatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="site-container site-footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= pbE($themeSiteName) ?>. Hak cipta dilindungi.</span>
        <a href="login.php">Masuk Admin</a>
    </div>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/waypoints/4.0.1/jquery.waypoints.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Counter-Up/1.0.0/jquery.counterup.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/typed.js/2.1.0/typed.umd.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script src="themes/default/assets/js/theme.js"></script>
<script src="themes/default/assets/js/theme-enhancements.js"></script>
<?php if (!empty($themePreview)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    try {
        const draftHtml = localStorage.getItem('grapesjs_preview_html');
        const draftCss = localStorage.getItem('grapesjs_preview_css');
        if (draftHtml === null || draftCss === null) {
            document.body.textContent = 'Draft preview tidak ditemukan. Buka Preview dari editor lagi.';
        } else {
            document.body.innerHTML = draftHtml;
            const style = document.createElement('style');
            style.textContent = draftCss;
            document.head.append(style);
            if (typeof window.pbThemeEnhance === 'function') {
                window.pbThemeEnhance({ reset: true });
            }
        }
    } catch (error) {
        document.body.textContent = 'Draft preview tidak dapat dimuat di browser ini.';
    }
    const badge = document.createElement('div');
    badge.textContent = 'MODE PREVIEW (DRAFT)';
    badge.setAttribute('role', 'status');
    badge.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2147483647;padding:10px 14px;border:1px solid rgba(255,255,255,.25);border-radius:999px;background:#4338ca;color:#fff;box-shadow:0 8px 24px rgba(15,23,42,.25);font:700 11px/1.2 system-ui,sans-serif;letter-spacing:.04em';
    document.body.append(badge);
});
</script>
<?php endif; ?>
</body>
</html>
