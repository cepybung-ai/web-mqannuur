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
    'name' => '',
    'position' => '',
    'category' => 'Guru',
    'photo' => '',
    'bio' => '',
    'email' => '',
    'phone' => '',
    'sort_order' => '0',
    'is_active' => '1',
];
$staff = [];

try {
    $pdo = getDbConnection();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $staffId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($staffId < 1) {
                throw new InvalidArgumentException('ID staf tidak valid.');
            }
            $delete = $pdo->prepare('DELETE FROM staff WHERE id = :id');
            $delete->execute(['id' => $staffId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Data staf tidak ditemukan.');
            }
            logActivity('delete', 'staff', $staffId, 'Data guru/staf dihapus');
            pbAdminFlash('success', 'Data guru/staf berhasil dihapus.');
            header('Location: admin-staff.php', true, 303);
            exit;
        }

        if ($action === 'toggle') {
            if ($staffId < 1) {
                throw new InvalidArgumentException('ID staf tidak valid.');
            }
            $toggle = $pdo->prepare('UPDATE staff SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $toggle->execute(['id' => $staffId]);
            logActivity('toggle', 'staff', $staffId, 'Status tampil diubah');
            pbAdminFlash('success', 'Status tampil guru/staf diperbarui.');
            header('Location: admin-staff.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi guru/staf tidak dikenal.');
        }

        $form = [
            'name' => trim(is_string($_POST['name'] ?? null) ? strip_tags($_POST['name']) : ''),
            'position' => trim(is_string($_POST['position'] ?? null) ? strip_tags($_POST['position']) : ''),
            'category' => trim(is_string($_POST['category'] ?? null) ? strip_tags($_POST['category']) : 'Guru'),
            'photo' => trim(is_string($_POST['photo'] ?? null) ? $_POST['photo'] : ''),
            'bio' => trim(is_string($_POST['bio'] ?? null) ? strip_tags($_POST['bio']) : ''),
            'email' => trim(is_string($_POST['email'] ?? null) ? strip_tags($_POST['email']) : ''),
            'phone' => trim(is_string($_POST['phone'] ?? null) ? strip_tags($_POST['phone']) : ''),
            'sort_order' => is_string($_POST['sort_order'] ?? null) ? $_POST['sort_order'] : '0',
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
        ];
        if ($form['name'] === '' || preg_match_all('/./us', $form['name']) > 150) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 150 karakter.');
        }
        if ($form['position'] === '' || preg_match_all('/./us', $form['position']) > 150) {
            throw new InvalidArgumentException('Jabatan wajib diisi dan maksimal 150 karakter.');
        }
        if (!in_array($form['category'], ['Guru', 'Staf', 'Instruktur'], true)) {
            $form['category'] = 'Guru';
        }
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Alamat email tidak valid.');
        }
        if ($form['photo'] !== '') {
            $safePhoto = pbSafeUrl($form['photo']);
            if ($safePhoto === null || preg_match('~^(?:mailto:|tel:)~i', $safePhoto)) {
                throw new InvalidArgumentException('URL foto tidak valid.');
            }
            $form['photo'] = $safePhoto;
        }
        $sortOrder = filter_var($form['sort_order'], FILTER_VALIDATE_INT);
        $values = [
            'name' => $form['name'],
            'position' => $form['position'],
            'category' => $form['category'],
            'photo' => $form['photo'] !== '' ? $form['photo'] : null,
            'bio' => $form['bio'] !== '' ? $form['bio'] : null,
            'email' => $form['email'] !== '' ? $form['email'] : null,
            'phone' => $form['phone'] !== '' ? $form['phone'] : null,
            'sort_order' => $sortOrder === false ? 0 : $sortOrder,
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
        ];

        if ($action === 'create') {
            $insert = $pdo->prepare('INSERT INTO staff (name, position, category, photo, bio, email, phone, sort_order, is_active) VALUES (:name, :position, :category, :photo, :bio, :email, :phone, :sort_order, :is_active)');
            $insert->execute($values);
            logActivity('create', 'staff', (int)$pdo->lastInsertId(), 'Nama: ' . $form['name'] . '; jabatan: ' . $form['position']);
            pbAdminFlash('success', 'Data guru/staf berhasil ditambahkan.');
            header('Location: admin-staff.php', true, 303);
            exit;
        }
        if ($staffId < 1) {
            throw new InvalidArgumentException('ID staf tidak valid.');
        }
        $values['id'] = $staffId;
        $update = $pdo->prepare('UPDATE staff SET name = :name, position = :position, category = :category, photo = :photo, bio = :bio, email = :email, phone = :phone, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
        $update->execute($values);
        logActivity('update', 'staff', $staffId, 'Nama: ' . $form['name'] . '; jabatan: ' . $form['position']);
        pbAdminFlash('success', 'Data guru/staf berhasil diperbarui.');
        header('Location: admin-staff.php', true, 303);
        exit;
    }

    $requestedId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId !== false && $requestedId > 0) {
        $editId = (int)$requestedId;
        $find = $pdo->prepare('SELECT * FROM staff WHERE id = :id LIMIT 1');
        $find->execute(['id' => $editId]);
        $row = $find->fetch();
        if ($row) {
            foreach ($form as $key => $value) {
                $form[$key] = (string)($row[$key] ?? '');
            }
        } else {
            $editId = 0;
            $errorMessage = 'Data guru/staf yang ingin diedit tidak ditemukan.';
        }
    }

    $staff = $pdo->query('SELECT id, name, position, category, photo, email, sort_order, is_active FROM staff ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin staff error: ' . $exception->getMessage());
    $errorMessage = 'Modul guru/staf belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Guru & Staf', 'staff', 'Direktori guru, staf, dan instruktur ekstrakurikuler.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2><?= $editId > 0 ? 'Edit Guru/Staf' : 'Tambah Guru/Staf' ?></h2><p>Data profil dan kontak.</p></div></div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-staff.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field"><span>Nama Lengkap</span><input class="admin-input" type="text" name="name" maxlength="150" required value="<?= pbE($form['name']) ?>"></label>
                            <label class="admin-field"><span>Jabatan</span><input class="admin-input" type="text" name="position" maxlength="150" required value="<?= pbE($form['position']) ?>" placeholder="Guru Fiqih"></label>
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Kategori</span><select class="admin-select" name="category"><?php foreach (['Guru', 'Staf', 'Instruktur'] as $category): ?><option value="<?= pbE($category) ?>"<?= $form['category'] === $category ? ' selected' : '' ?>><?= pbE($category) ?></option><?php endforeach; ?></select></label>
                                <label class="admin-field"><span>Urutan</span><input class="admin-input" type="number" name="sort_order" value="<?= pbE($form['sort_order']) ?>"></label>
                            </div>
                            <label class="admin-field"><span>Foto <small>URL</small></span><input class="admin-input" type="text" name="photo" maxlength="500" value="<?= pbE($form['photo']) ?>" placeholder="uploads/gallery/foto.webp"><small class="admin-form-hint">Salin URL foto dari Galeri Media.</small></label>
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Email</span><input class="admin-input" type="email" name="email" maxlength="150" value="<?= pbE($form['email']) ?>"></label>
                                <label class="admin-field"><span>Telepon</span><input class="admin-input" type="text" name="phone" maxlength="50" value="<?= pbE($form['phone']) ?>"></label>
                            </div>
                            <label class="admin-field"><span>Biografi Singkat</span><textarea class="admin-textarea" name="bio" rows="5" maxlength="1000"><?= pbE($form['bio']) ?></textarea></label>
                            <label class="admin-check"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] === '1' ? ' checked' : '' ?>> Tampilkan di website</label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $editId > 0 ? 'Simpan Perubahan' : 'Tambah Data' ?></button>
                                <?php if ($editId > 0): ?><a class="admin-btn" href="admin-staff.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Direktori (<?= count($staff) ?>)</h2><p>Urutan tampil di halaman publik.</p></div><a class="admin-btn" href="index.php#guru" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Publik</a></div>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Nama</th><th>Kategori</th><th>Kontak</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($staff === []): ?>
                                <tr><td colspan="6"><div class="admin-empty"><i class="fa-regular fa-user" aria-hidden="true"></i>Belum ada data guru/staf.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($staff as $person): ?>
                                    <tr>
                                        <td><div class="cell-title"><?= pbE($person['name']) ?></div><div class="cell-sub"><?= pbE($person['position']) ?></div></td>
                                        <td><span class="admin-badge brand"><?= pbE($person['category']) ?></span></td>
                                        <td class="admin-muted"><?= pbE($person['email'] ?: '—') ?></td>
                                        <td class="admin-muted"><?= (int)$person['sort_order'] ?></td>
                                        <td><span class="admin-badge <?= $person['is_active'] ? 'success' : 'warning' ?>"><?= $person['is_active'] ? 'Tampil' : 'Disembunyikan' ?></span></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-staff.php?edit=<?= (int)$person['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <form class="admin-inline-form" method="post" action="admin-staff.php">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
                                                    <button class="admin-btn small" type="submit"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i></button>
                                                </form>
                                                <form class="admin-inline-form" method="post" action="admin-staff.php" data-confirm="Hapus data guru/staf ini?">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
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
