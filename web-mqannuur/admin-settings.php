<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$csrftoken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$settings = pbSettings();
$fields = ['site_name', 'site_tagline', 'site_description', 'site_logo', 'site_whatsapp', 'site_email', 'site_phone', 'site_address', 'site_facebook', 'site_instagram', 'site_youtube', 'footer_text'];

try {
    $pdo = getDbConnection();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrftoken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $input = [];
        foreach ($fields as $field) {
            $input[$field] = trim(is_string($_POST[$field] ?? null) ? strip_tags($_POST[$field]) : '');
        }
        if ($input['site_name'] === '') {
            throw new InvalidArgumentException('Nama situs wajib diisi.');
        }
        if ($input['site_email'] !== '' && !filter_var($input['site_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Alamat email situs tidak valid.');
        }
        foreach (['site_facebook', 'site_instagram', 'site_youtube', 'site_logo'] as $urlField) {
            if ($input[$urlField] !== '') {
                $safe = pbSafeUrl($input[$urlField]);
                if ($safe === null || preg_match('~^(?:mailto:|tel:)~i', $safe)) {
                    throw new InvalidArgumentException('URL ' . str_replace('_', ' ', $urlField) . ' tidak valid.');
                }
                $input[$urlField] = $safe;
            }
        }
        if (isset($_FILES['logo']) && is_array($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = pbUploadedImage($_FILES['logo'], 'settings', 4_194_304);
            if (!$upload['ok']) {
                throw new InvalidArgumentException($upload['error']);
            }
            $oldLogo = (string)($settings['site_logo'] ?? '');
            $input['site_logo'] = $upload['path'];
            if ($oldLogo !== '' && str_starts_with($oldLogo, 'uploads/settings/') && $oldLogo !== $input['site_logo']) {
                pbDeleteUploadedFile($oldLogo);
            }
        }
        $statement = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($fields as $field) {
            $statement->execute(['key' => $field, 'value' => (string)($input[$field] ?? '')]);
        }
        logActivity('update', 'settings', 'site', 'Identitas situs diperbarui');
        pbAdminFlash('success', 'Identitas situs berhasil disimpan.');
        header('Location: admin-settings.php', true, 303);
        exit;
    }
    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    foreach ($rows as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $settings[$field] = trim(is_string($_POST[$field]) ? strip_tags($_POST[$field]) : '');
        }
    }
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin settings error: ' . $exception->getMessage());
    $errorMessage = 'Pengaturan situs belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Identitas Situs', 'settings', 'Nama, logo, kontak, dan media sosial website.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <form class="admin-form" method="post" action="admin-settings.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= pbE($csrftoken) ?>">
                <div class="admin-grid two">
                    <section class="admin-card">
                        <div class="admin-card-head"><div><h2>Identitas Website</h2><p>Nama dan deskripsi yang tampil di situs publik.</p></div></div>
                        <div class="admin-card-body admin-form">
                            <label class="admin-field"><span>Nama Situs</span><input class="admin-input" type="text" name="site_name" maxlength="150" required value="<?= pbE($settings['site_name'] ?? '') ?>"></label>
                            <label class="admin-field"><span>Slogan</span><input class="admin-input" type="text" name="site_tagline" maxlength="180" value="<?= pbE($settings['site_tagline'] ?? '') ?>"></label>
                            <label class="admin-field"><span>Deskripsi Singkat</span><textarea class="admin-textarea" name="site_description" rows="3" maxlength="500"><?= pbE($settings['site_description'] ?? '') ?></textarea></label>
                            <label class="admin-field"><span>Logo <small>JPG/PNG/WebP, maks. 4 MB</small></span><input class="admin-input" type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" data-image-input="logo-preview"><small class="admin-form-hint">Kosongkan jika tidak ingin mengganti logo.</small></label>
                            <label class="admin-field"><span>URL Logo <small>Opsional</small></span><input class="admin-input" type="text" name="site_logo" maxlength="500" value="<?= pbE($settings['site_logo'] ?? '') ?>" placeholder="uploads/settings/logo.webp"></label>
                            <?php if (!empty($settings['site_logo'])): ?><img id="logo-preview" class="admin-logo-preview" src="<?= pbE($settings['site_logo']) ?>" alt="Logo" loading="lazy"><?php else: ?><img id="logo-preview" class="admin-logo-preview" alt="" hidden><?php endif; ?>
                            <label class="admin-field"><span>Teks Footer</span><textarea class="admin-textarea" name="footer_text" rows="2" maxlength="300"><?= pbE($settings['footer_text'] ?? '') ?></textarea></label>
                        </div>
                    </section>

                    <div class="admin-grid" style="gap:16px;">
                        <section class="admin-card">
                            <div class="admin-card-head"><div><h2>Kontak</h2><p>Informasi kontak yang tampil di website.</p></div></div>
                            <div class="admin-card-body admin-form">
                                <label class="admin-field"><span>WhatsApp <small>Nomor atau URL wa.me</small></span><input class="admin-input" type="text" name="site_whatsapp" maxlength="100" value="<?= pbE($settings['site_whatsapp'] ?? '') ?>" placeholder="6281234567890"></label>
                                <div class="admin-form-row">
                                    <label class="admin-field"><span>Email</span><input class="admin-input" type="email" name="site_email" maxlength="150" value="<?= pbE($settings['site_email'] ?? '') ?>"></label>
                                    <label class="admin-field"><span>Telepon</span><input class="admin-input" type="text" name="site_phone" maxlength="80" value="<?= pbE($settings['site_phone'] ?? '') ?>"></label>
                                </div>
                                <label class="admin-field"><span>Alamat</span><textarea class="admin-textarea" name="site_address" rows="2" maxlength="300"><?= pbE($settings['site_address'] ?? '') ?></textarea></label>
                            </div>
                        </section>
                        <section class="admin-card">
                            <div class="admin-card-head"><div><h2>Media Sosial</h2><p>Masukkan URL profil lengkap.</p></div></div>
                            <div class="admin-card-body admin-form">
                                <label class="admin-field"><span>Facebook</span><input class="admin-input" type="text" name="site_facebook" maxlength="500" value="<?= pbE($settings['site_facebook'] ?? '') ?>"></label>
                                <label class="admin-field"><span>Instagram</span><input class="admin-input" type="text" name="site_instagram" maxlength="500" value="<?= pbE($settings['site_instagram'] ?? '') ?>"></label>
                                <label class="admin-field"><span>YouTube</span><input class="admin-input" type="text" name="site_youtube" maxlength="500" value="<?= pbE($settings['site_youtube'] ?? '') ?>"></label>
                            </div>
                        </section>
                    </div>
                </div>
                <div class="admin-form-actions"><button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Simpan Identitas</button><a class="admin-btn" href="index.php" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Situs</a></div>
            </form>
<?php pbAdminPageEnd(); ?>
