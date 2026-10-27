<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = [
    'title' => '',
    'description' => '',
    'category' => 'Dokumen',
    'external_url' => '',
    'is_active' => '1',
];
$currentFile = null;
$downloads = [];

try {
    $pdo = getDbConnection();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $downloadId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($downloadId < 1) {
                throw new InvalidArgumentException('ID dokumen tidak valid.');
            }
            $find = $pdo->prepare('SELECT file_path FROM downloads WHERE id = :id LIMIT 1');
            $find->execute(['id' => $downloadId]);
            $filePath = (string)($find->fetchColumn() ?: '');
            $delete = $pdo->prepare('DELETE FROM downloads WHERE id = :id');
            $delete->execute(['id' => $downloadId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Dokumen tidak ditemukan.');
            }
            if ($filePath !== '') {
                pbDeleteUploadedFile($filePath);
            }
            logActivity('delete', 'download', $downloadId, 'Dokumen dihapus');
            pbAdminFlash('success', 'Dokumen berhasil dihapus.');
            header('Location: admin-downloads.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi dokumen tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'description' => trim(is_string($_POST['description'] ?? null) ? strip_tags($_POST['description']) : ''),
            'category' => trim(is_string($_POST['category'] ?? null) ? strip_tags($_POST['category']) : 'Dokumen'),
            'external_url' => trim(is_string($_POST['external_url'] ?? null) ? $_POST['external_url'] : ''),
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
        ];
        if ($form['title'] === '' || preg_match_all('/./us', $form['title']) > 255) {
            throw new InvalidArgumentException('Judul dokumen wajib diisi dan maksimal 255 karakter.');
        }
        if ($form['external_url'] !== '') {
            $safe = pbSafeUrl($form['external_url']);
            if ($safe === null || preg_match('~^(?:mailto:|tel:)~i', $safe)) {
                throw new InvalidArgumentException('URL dokumen tidak valid.');
            }
            $form['external_url'] = $safe;
        }

        $existing = null;
        if ($action === 'update') {
            if ($downloadId < 1) {
                throw new InvalidArgumentException('ID dokumen tidak valid.');
            }
            $find = $pdo->prepare('SELECT * FROM downloads WHERE id = :id LIMIT 1');
            $find->execute(['id' => $downloadId]);
            $existing = $find->fetch();
            if (!$existing) {
                throw new InvalidArgumentException('Dokumen tidak ditemukan.');
            }
        }

        $filePath = $existing['file_path'] ?? null;
        $fileName = $existing['file_name'] ?? null;
        $fileSize = (int)($existing['file_size'] ?? 0);
        $oldFileToDelete = null;
        if (isset($_POST['remove_file'])) {
            $oldFileToDelete = $filePath;
            $filePath = null;
            $fileName = null;
            $fileSize = 0;
        }
        if (isset($_FILES['document']) && is_array($_FILES['document']) && ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = pbUploadedDocument($_FILES['document']);
            if (!$upload['ok']) {
                throw new InvalidArgumentException($upload['error']);
            }
            $oldFileToDelete = $filePath;
            $filePath = $upload['path'];
            $fileName = $upload['file_name'];
            $fileSize = $upload['size'];
        }

        if (($filePath === null || $filePath === '') && $form['external_url'] === '') {
            throw new InvalidArgumentException('Unggah berkas atau isi URL dokumen eksternal.');
        }

        $values = [
            'title' => $form['title'],
            'description' => $form['description'] !== '' ? $form['description'] : null,
            'category' => $form['category'] !== '' ? $form['category'] : 'Dokumen',
            'file_path' => $filePath ?: null,
            'file_name' => $fileName ?: null,
            'file_size' => $fileSize,
            'external_url' => $form['external_url'] !== '' ? $form['external_url'] : null,
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
        ];

        if ($action === 'create') {
            $insert = $pdo->prepare('INSERT INTO downloads (title, description, category, file_path, file_name, file_size, external_url, is_active) VALUES (:title, :description, :category, :file_path, :file_name, :file_size, :external_url, :is_active)');
            $insert->execute($values);
            logActivity('create', 'download', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title']);
            pbAdminFlash('success', 'Dokumen berhasil ditambahkan.');
            header('Location: admin-downloads.php', true, 303);
            exit;
        }

        $values['id'] = $downloadId;
        $update = $pdo->prepare('UPDATE downloads SET title = :title, description = :description, category = :category, file_path = :file_path, file_name = :file_name, file_size = :file_size, external_url = :external_url, is_active = :is_active WHERE id = :id');
        $update->execute($values);
        if ($oldFileToDelete && $oldFileToDelete !== $filePath) {
            pbDeleteUploadedFile((string)$oldFileToDelete);
        }
        logActivity('update', 'download', $downloadId, 'Judul: ' . $form['title']);
        pbAdminFlash('success', 'Dokumen berhasil diperbarui.');
        header('Location: admin-downloads.php', true, 303);
        exit;
    }

    $requestedId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId !== false && $requestedId > 0) {
        $editId = (int)$requestedId;
        $find = $pdo->prepare('SELECT * FROM downloads WHERE id = :id LIMIT 1');
        $find->execute(['id' => $editId]);
        $row = $find->fetch();
        if ($row) {
            $currentFile = $row;
            foreach ($form as $key => $value) {
                $form[$key] = (string)($row[$key] ?? '');
            }
            $form['is_active'] = (string)(int)$row['is_active'];
        } else {
            $editId = 0;
            $errorMessage = 'Dokumen yang ingin diedit tidak ditemukan.';
        }
    }

    $downloads = $pdo->query('SELECT id, title, category, file_path, file_name, file_size, external_url, download_count, is_active, created_at FROM downloads ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin downloads error: ' . $exception->getMessage());
    $errorMessage = 'Modul download belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Pusat Download', 'downloads', 'Kelola dokumen, modul, dan berkas unduhan.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2><?= $editId > 0 ? 'Edit Dokumen' : 'Tambah Dokumen' ?></h2><p>Unggah berkas atau tautkan dokumen eksternal.</p></div></div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-downloads.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field"><span>Judul Dokumen</span><input class="admin-input" type="text" name="title" maxlength="255" required value="<?= pbE($form['title']) ?>" placeholder="Modul Fiqih Kelas VII"></label>
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Kategori</span><input class="admin-input" type="text" name="category" maxlength="100" value="<?= pbE($form['category']) ?>"></label>
                                <label class="admin-field"><span>URL Eksternal <small>Opsional</small></span><input class="admin-input" type="text" name="external_url" maxlength="500" value="<?= pbE($form['external_url']) ?>" placeholder="https://..."></label>
                            </div>
                            <label class="admin-field"><span>Deskripsi</span><textarea class="admin-textarea" name="description" rows="4" maxlength="1000"><?= pbE($form['description']) ?></textarea></label>
                            <label class="admin-field"><span>Berkas <small>PDF/DOC/XLS/PPT/ZIP, maks. 25 MB</small></span><input class="admin-input" type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt"><small class="admin-form-hint">Unggah berkas baru untuk mengganti berkas lama.</small></label>
                            <?php if ($currentFile && !empty($currentFile['file_path'])): ?>
                                <div class="admin-alert info" style="margin:0;"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span><?= pbE($currentFile['file_name'] ?: basename((string)$currentFile['file_path'])) ?> · <?= pbE(pbFormatBytes((int)$currentFile['file_size'])) ?></span></div>
                                <label class="admin-check"><input type="checkbox" name="remove_file" value="1"> Hapus berkas yang sudah ada</label>
                            <?php elseif ($currentFile && !empty($currentFile['external_url'])): ?>
                                <div class="admin-alert info" style="margin:0;"><i class="fa-solid fa-link" aria-hidden="true"></i><span>Dokumen eksternal: <?= pbE($currentFile['external_url']) ?></span></div>
                            <?php endif; ?>
                            <label class="admin-check"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] === '1' ? ' checked' : '' ?>> Tampilkan di website</label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $editId > 0 ? 'Simpan Perubahan' : 'Tambah Dokumen' ?></button>
                                <?php if ($editId > 0): ?><a class="admin-btn" href="admin-downloads.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Daftar Dokumen (<?= count($downloads) ?>)</h2><p>Dokumen aktif tampil di halaman publik.</p></div><a class="admin-btn" href="index.php#download" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Publik</a></div>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Dokumen</th><th>Kategori</th><th>Berkas</th><th>Unduhan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($downloads === []): ?>
                                <tr><td colspan="6"><div class="admin-empty"><i class="fa-regular fa-file" aria-hidden="true"></i>Belum ada dokumen.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($downloads as $item): ?>
                                    <tr>
                                        <td><div class="cell-title"><?= pbE($item['title']) ?></div><div class="cell-sub">ID #<?= (int)$item['id'] ?></div></td>
                                        <td><span class="admin-badge brand"><?= pbE($item['category']) ?></span></td>
                                        <td class="admin-muted"><?= pbE($item['file_name'] ?: ($item['external_url'] ? 'Tautan eksternal' : '—')) ?><br><small><?= $item['file_size'] > 0 ? pbE(pbFormatBytes((int)$item['file_size'])) : '' ?></small></td>
                                        <td class="admin-muted"><?= (int)$item['download_count'] ?></td>
                                        <td><span class="admin-badge <?= $item['is_active'] ? 'success' : 'warning' ?>"><?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-downloads.php?edit=<?= (int)$item['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <?php if ($item['is_active']): ?><button class="admin-btn small" type="button" data-copy-link="download.php?id=<?= (int)$item['id'] ?>"><i class="fa-solid fa-link" aria-hidden="true"></i>Salin</button><?php endif; ?>
                                                <form class="admin-inline-form" method="post" action="admin-downloads.php" data-confirm="Hapus dokumen ini beserta berkasnya?">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                    <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
<?php pbAdminPageEnd(); ?>
