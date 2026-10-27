<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = ['title' => '', 'caption' => '', 'sort_order' => '0', 'is_active' => '1'];
$currentImage = null;
$gallery = [];
$webpReady = function_exists('imagewebp') && function_exists('imagecreatefromstring');

try {
    $pdo = getDbConnection();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $imageId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($imageId < 1) {
                throw new InvalidArgumentException('ID gambar tidak valid.');
            }
            $find = $pdo->prepare('SELECT file_path FROM gallery WHERE id = :id LIMIT 1');
            $find->execute(['id' => $imageId]);
            $filePath = (string)($find->fetchColumn() ?: '');
            $delete = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
            $delete->execute(['id' => $imageId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Gambar tidak ditemukan.');
            }
            if ($filePath !== '') {
                pbDeleteUploadedFile($filePath);
            }
            logActivity('delete', 'gallery', $imageId, 'Gambar dihapus');
            pbAdminFlash('success', 'Gambar berhasil dihapus.');
            header('Location: admin-gallery.php', true, 303);
            exit;
        }

        if ($action === 'toggle') {
            if ($imageId < 1) {
                throw new InvalidArgumentException('ID gambar tidak valid.');
            }
            $toggle = $pdo->prepare('UPDATE gallery SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $toggle->execute(['id' => $imageId]);
            logActivity('toggle', 'gallery', $imageId, 'Status tampil diubah');
            pbAdminFlash('success', 'Status gambar diperbarui.');
            header('Location: admin-gallery.php', true, 303);
            exit;
        }

        if (!in_array($action, ['upload', 'update'], true)) {
            throw new InvalidArgumentException('Aksi galeri tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'caption' => trim(is_string($_POST['caption'] ?? null) ? strip_tags($_POST['caption']) : ''),
            'sort_order' => is_string($_POST['sort_order'] ?? null) ? $_POST['sort_order'] : '0',
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
        ];
        if ($form['title'] === '' || preg_match_all('/./us', $form['title']) > 255) {
            throw new InvalidArgumentException('Judul gambar wajib diisi dan maksimal 255 karakter.');
        }
        $sortOrder = filter_var($form['sort_order'], FILTER_VALIDATE_INT);
        $sortOrder = $sortOrder === false ? 0 : $sortOrder;
        $existing = null;
        if ($action === 'update') {
            if ($imageId < 1) {
                throw new InvalidArgumentException('ID gambar tidak valid.');
            }
            $find = $pdo->prepare('SELECT * FROM gallery WHERE id = :id LIMIT 1');
            $find->execute(['id' => $imageId]);
            $existing = $find->fetch();
            if (!$existing) {
                throw new InvalidArgumentException('Gambar tidak ditemukan.');
            }
        }

        $newUpload = null;
        if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = pbUploadedImage($_FILES['image'], 'gallery');
            if (!$upload['ok']) {
                throw new InvalidArgumentException($upload['error']);
            }
            $newUpload = $upload;
        }
        if ($action === 'upload' && $newUpload === null) {
            throw new InvalidArgumentException('Pilih gambar yang akan diunggah.');
        }

        if ($action === 'upload') {
            $insert = $pdo->prepare('INSERT INTO gallery (title, caption, file_path, original_name, mime_type, width, height, file_size, sort_order, is_active) VALUES (:title, :caption, :file_path, :original_name, :mime_type, :width, :height, :file_size, :sort_order, :is_active)');
            $insert->execute([
                'title' => $form['title'],
                'caption' => $form['caption'] !== '' ? $form['caption'] : null,
                'file_path' => $newUpload['path'],
                'original_name' => $newUpload['original_name'],
                'mime_type' => $newUpload['mime'],
                'width' => $newUpload['width'],
                'height' => $newUpload['height'],
                'file_size' => $newUpload['size'],
                'sort_order' => $sortOrder,
                'is_active' => $form['is_active'] === '1' ? 1 : 0,
            ]);
            $message = 'Gambar berhasil diunggah.';
            if (($newUpload['warning'] ?? '') !== '') {
                $message .= ' ' . $newUpload['warning'];
            }
            logActivity('create', 'gallery', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title']);
            pbAdminFlash('success', $message);
            header('Location: admin-gallery.php', true, 303);
            exit;
        }

        $filePath = (string)$existing['file_path'];
        $oldFileToDelete = null;
        $metadata = [
            'original_name' => (string)$existing['original_name'],
            'mime_type' => (string)$existing['mime_type'],
            'width' => (int)$existing['width'],
            'height' => (int)$existing['height'],
            'file_size' => (int)$existing['file_size'],
        ];
        if ($newUpload !== null) {
            $oldFileToDelete = $filePath;
            $filePath = $newUpload['path'];
            $metadata = [
                'original_name' => $newUpload['original_name'],
                'mime_type' => $newUpload['mime'],
                'width' => $newUpload['width'],
                'height' => $newUpload['height'],
                'file_size' => $newUpload['size'],
            ];
        }
        $update = $pdo->prepare('UPDATE gallery SET title = :title, caption = :caption, file_path = :file_path, original_name = :original_name, mime_type = :mime_type, width = :width, height = :height, file_size = :file_size, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
        $update->execute([
            'title' => $form['title'],
            'caption' => $form['caption'] !== '' ? $form['caption'] : null,
            'file_path' => $filePath,
            'original_name' => $metadata['original_name'],
            'mime_type' => $metadata['mime_type'],
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'file_size' => $metadata['file_size'],
            'sort_order' => $sortOrder,
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
            'id' => $imageId,
        ]);
        if ($oldFileToDelete && $oldFileToDelete !== $filePath) {
            pbDeleteUploadedFile($oldFileToDelete);
        }
        logActivity('update', 'gallery', $imageId, 'Judul: ' . $form['title']);
        pbAdminFlash('success', 'Data gambar berhasil diperbarui.');
        header('Location: admin-gallery.php', true, 303);
        exit;
    }

    $requestedId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId !== false && $requestedId > 0) {
        $editId = (int)$requestedId;
        $find = $pdo->prepare('SELECT * FROM gallery WHERE id = :id LIMIT 1');
        $find->execute(['id' => $editId]);
        $row = $find->fetch();
        if ($row) {
            $currentImage = $row;
            $form = [
                'title' => (string)$row['title'],
                'caption' => (string)($row['caption'] ?? ''),
                'sort_order' => (string)(int)$row['sort_order'],
                'is_active' => (string)(int)$row['is_active'],
            ];
        } else {
            $editId = 0;
            $errorMessage = 'Gambar yang ingin diedit tidak ditemukan.';
        }
    }

    $gallery = $pdo->query('SELECT id, title, caption, file_path, original_name, mime_type, width, height, file_size, sort_order, is_active FROM gallery ORDER BY sort_order ASC, id DESC')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin gallery error: ' . $exception->getMessage());
    $errorMessage = 'Modul galeri belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Galeri Media', 'gallery', 'Unggah foto, konversi WebP otomatis, dan salin tautan gambar.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>
            <?php if (!$webpReady): ?>
                <div class="admin-alert info" role="status"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>GD/WebP belum aktif di server ini. Gambar tetap tersimpan dalam format asli; konversi WebP otomatis akan aktif saat ekstensi GD tersedia.</span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2><?= $editId > 0 ? 'Edit Gambar' : 'Unggah Gambar' ?></h2><p><?= $webpReady ? 'Gambar otomatis dikonversi ke WebP.' : 'Konversi WebP memerlukan ekstensi GD.' ?></p></div></div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-gallery.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editId > 0 ? 'update' : 'upload' ?>">
                            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field"><span>Judul Gambar</span><input class="admin-input" type="text" name="title" maxlength="255" required value="<?= pbE($form['title']) ?>" placeholder="Kegiatan Tahfidz"></label>
                            <label class="admin-field"><span>Caption</span><textarea class="admin-textarea" name="caption" rows="3" maxlength="500"><?= pbE($form['caption']) ?></textarea></label>
                            <label class="admin-field"><span><?= $editId > 0 ? 'Ganti Gambar' : 'Pilih Gambar' ?> <small>JPG/PNG/GIF/WebP, maks. 8 MB</small></span><input class="admin-input" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"<?= $editId > 0 ? '' : ' required' ?> data-image-input="gallery-preview"></label>
                            <?php if ($currentImage): ?>
                                <img id="gallery-preview" class="admin-logo-preview" src="<?= pbE($currentImage['file_path']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <img id="gallery-preview" class="admin-logo-preview" alt="" hidden>
                            <?php endif; ?>
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Urutan</span><input class="admin-input" type="number" name="sort_order" value="<?= pbE($form['sort_order']) ?>"></label>
                                <label class="admin-check" style="align-self:end;padding-bottom:9px;"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] === '1' ? ' checked' : '' ?>> Tampilkan</label>
                            </div>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i><?= $editId > 0 ? 'Simpan Perubahan' : 'Unggah Gambar' ?></button>
                                <?php if ($editId > 0): ?><a class="admin-btn" href="admin-gallery.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Galeri (<?= count($gallery) ?>)</h2><p>Klik Salin untuk mendapatkan URL gambar.</p></div><a class="admin-btn" href="index.php#galeri" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Publik</a></div>
                    <div class="admin-card-body">
                        <?php if ($gallery === []): ?>
                            <div class="admin-empty"><i class="fa-regular fa-images" aria-hidden="true"></i>Belum ada gambar di galeri.</div>
                        <?php else: ?>
                            <div class="admin-media-grid">
                                <?php foreach ($gallery as $image): ?>
                                    <article class="admin-media-card">
                                        <img class="admin-media-thumb" src="<?= pbE($image['file_path']) ?>" alt="<?= pbE($image['title']) ?>" loading="lazy">
                                        <div class="admin-media-body">
                                            <strong><?= pbE($image['title']) ?></strong>
                                            <small><?= (int)$image['width'] ?>×<?= (int)$image['height'] ?> · <?= pbE(pbFormatBytes((int)$image['file_size'])) ?> · <?= pbE(strtoupper((string)pathinfo((string)$image['file_path'], PATHINFO_EXTENSION))) ?></small>
                                            <span class="admin-badge <?= $image['is_active'] ? 'success' : 'warning' ?>" style="justify-self:start;"><?= $image['is_active'] ? 'Tampil' : 'Disembunyikan' ?></span>
                                        </div>
                                        <div class="admin-media-actions">
                                            <button class="admin-btn small" type="button" data-copy-link="<?= pbE($image['file_path']) ?>"><i class="fa-solid fa-copy" aria-hidden="true"></i>Salin</button>
                                            <a class="admin-btn small" href="admin-gallery.php?edit=<?= (int)$image['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                            <form class="admin-inline-form" method="post" action="admin-gallery.php">
                                                <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$image['id'] ?>">
                                                <button class="admin-btn small" type="submit"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i></button>
                                            </form>
                                            <form class="admin-inline-form" method="post" action="admin-gallery.php" data-confirm="Hapus gambar ini dari galeri dan server?">
                                                <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$image['id'] ?>">
                                                <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
<?php pbAdminPageEnd(); ?>
