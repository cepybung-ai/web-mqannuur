<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$csrftoken = pbAdminCsrfToken();
$errorMessage = '';
$themes = pbThemeCatalog();
$activeTheme = pbActiveTheme();

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrftoken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $requestedTheme = is_string($_POST['theme'] ?? null) ? strtolower(trim((string)$_POST['theme'])) : '';
        if ($requestedTheme === '' || !isset($themes[$requestedTheme])) {
            throw new InvalidArgumentException('Tema yang dipilih tidak tersedia.');
        }
        $statement = getDbConnection()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $statement->execute(['key' => 'active_theme', 'value' => $requestedTheme]);
        logActivity('update', 'theme', $requestedTheme, 'Tema aktif diubah menjadi ' . $requestedTheme);
        pbAdminFlash('success', 'Tema "' . $themes[$requestedTheme]['name'] . '" berhasil diaktifkan.');
        header('Location: admin-themes.php', true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin themes error: ' . $exception->getMessage());
    $errorMessage = 'Tema belum dapat diproses. Periksa koneksi database.';
}

$themeIcons = [
    'default' => 'fa-book-open',
    'sekolah' => 'fa-school',
    'profil-lembaga' => 'fa-building-columns',
    'portal-kegiatan' => 'fa-calendar-days',
];

pbAdminPageStart('Tema Website', 'themes', 'Pilih tampilan website yang paling sesuai. Perubahan langsung berlaku untuk semua halaman publik.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <section class="admin-card admin-section">
                <div class="admin-card-head">
                    <div><h2>Tema Aktif</h2><p>Tema saat ini digunakan oleh beranda, halaman, dan detail berita.</p></div>
                    <a class="admin-btn" href="index.php" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Situs</a>
                </div>
                <div class="admin-card-body">
                    <div class="theme-active-panel">
                        <span class="theme-active-icon" style="--theme-accent: <?= pbE($themes[$activeTheme]['accent'] ?? '#0f5a4a') ?>"><i class="fa-solid <?= pbE($themeIcons[$activeTheme] ?? 'fa-palette') ?>" aria-hidden="true"></i></span>
                        <div>
                            <span class="admin-badge success">Aktif</span>
                            <h3><?= pbE($themes[$activeTheme]['name'] ?? ucfirst($activeTheme)) ?></h3>
                            <p><?= pbE($themes[$activeTheme]['description'] ?? 'Tema website aktif.') ?></p>
                        </div>
                        <a class="admin-btn" href="index.php?theme=<?= rawurlencode($activeTheme) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>Preview</a>
                    </div>
                </div>
            </section>

            <div class="theme-grid">
                <?php foreach ($themes as $slug => $theme): ?>
                    <?php $isActive = $slug === $activeTheme; ?>
                    <article class="theme-card<?= $isActive ? ' is-active' : '' ?>" style="--theme-accent: <?= pbE($theme['accent']) ?>">
                        <div class="theme-preview" aria-hidden="true">
                            <span class="theme-preview-top"><i class="fa-solid <?= pbE($themeIcons[$slug] ?? 'fa-palette') ?>"></i><b><?= pbE($theme['name']) ?></b></span>
                            <span class="theme-preview-hero"></span>
                            <span class="theme-preview-lines"><i></i><i></i><i></i></span>
                        </div>
                        <div class="theme-card-body">
                            <div class="theme-card-head">
                                <div><h2><?= pbE($theme['name']) ?></h2><small>Versi <?= pbE($theme['version']) ?></small></div>
                                <?php if ($isActive): ?><span class="admin-badge success">Aktif</span><?php endif; ?>
                            </div>
                            <p><?= pbE($theme['description']) ?></p>
                            <?php if ($theme['features'] !== []): ?>
                                <div class="theme-features">
                                    <?php foreach ($theme['features'] as $feature): ?><span><i class="fa-solid fa-check" aria-hidden="true"></i><?= pbE($feature) ?></span><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="theme-card-actions">
                                <a class="admin-btn" href="index.php?theme=<?= rawurlencode($slug) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Preview</a>
                                <?php if ($isActive): ?>
                                    <button class="admin-btn" type="button" disabled><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Sedang Digunakan</button>
                                <?php else: ?>
                                    <form method="post" action="admin-themes.php" onsubmit="return confirm('Aktifkan tema ini? Tampilan publik akan langsung berubah.');">
                                        <input type="hidden" name="csrf_token" value="<?= pbE($csrftoken) ?>">
                                        <input type="hidden" name="theme" value="<?= pbE($slug) ?>">
                                        <button class="admin-btn primary" type="submit"><i class="fa-solid fa-paintbrush" aria-hidden="true"></i>Pakai Tema</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="admin-tip"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Preview bertanda <code>?theme=</code> hanya dapat dibuka oleh Super Admin. Pengunjung selalu melihat tema aktif. Konten Customize tetap dipertahankan saat berganti tema.</span></div>
<?php pbAdminPageEnd(); ?>
