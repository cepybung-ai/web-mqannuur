<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = [
    'title' => '',
    'url' => '',
    'icon' => '',
    'category' => 'Menu Utama',
    'sort_order' => '0',
    'is_active' => '1',
];

try {
    $pdo = getDbConnection();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $linkId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($linkId < 1) {
                throw new InvalidArgumentException('ID link tidak valid.');
            }
            $delete = $pdo->prepare('DELETE FROM links WHERE id = :id');
            $delete->execute(['id' => $linkId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Link tidak ditemukan.');
            }
            logActivity('delete', 'link', $linkId, 'Link dihapus');
            pbAdminFlash('success', 'Link berhasil dihapus.');
            header('Location: admin-links.php', true, 303);
            exit;
        }

        if ($action === 'toggle') {
            if ($linkId < 1) {
                throw new InvalidArgumentException('ID link tidak valid.');
            }
            $toggle = $pdo->prepare('UPDATE links SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $toggle->execute(['id' => $linkId]);
            if ($toggle->rowCount() === 0) {
                throw new InvalidArgumentException('Link tidak ditemukan.');
            }
            logActivity('toggle', 'link', $linkId, 'Status link diubah');
            pbAdminFlash('success', 'Status link diperbarui.');
            header('Location: admin-links.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi link tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'url' => trim(is_string($_POST['url'] ?? null) ? $_POST['url'] : ''),
            'icon' => trim(is_string($_POST['icon'] ?? null) ? strip_tags($_POST['icon']) : ''),
            'category' => trim(is_string($_POST['category'] ?? null) ? strip_tags($_POST['category']) : ''),
            'sort_order' => is_string($_POST['sort_order'] ?? null) ? $_POST['sort_order'] : '0',
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
        ];

        $titleLength = preg_match_all('/./us', $form['title']);
        $iconLength = preg_match_all('/./us', $form['icon']);
        $categoryLength = preg_match_all('/./us', $form['category']);
        if ($form['title'] === '' || $titleLength === false || $titleLength > 150) {
            throw new InvalidArgumentException('Nama link wajib diisi dan maksimal 150 karakter.');
        }
        if ($form['url'] === '' || strlen($form['url']) > 500) {
            throw new InvalidArgumentException('URL wajib diisi dan maksimal 500 karakter.');
        }
        $safeUrl = pbSafeUrl($form['url']);
        if ($safeUrl === null) {
            throw new InvalidArgumentException('URL tidak aman. Gunakan http(s), mailto, tel, atau path relatif.');
        }
        $form['url'] = $safeUrl;
        if ($iconLength === false || $iconLength > 100) {
            throw new InvalidArgumentException('Ikon maksimal 100 karakter.');
        }
        if ($form['category'] === '') {
            $form['category'] = 'Menu Utama';
        }
        if ($categoryLength === false || $categoryLength > 100) {
            throw new InvalidArgumentException('Kategori maksimal 100 karakter.');
        }
        $sortOrder = filter_var($form['sort_order'], FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 9999) {
            throw new InvalidArgumentException('Urutan harus berupa angka 0 sampai 9999.');
        }
        $form['sort_order'] = (string)$sortOrder;

        $values = [
            'title' => $form['title'],
            'url' => $form['url'],
            'icon' => $form['icon'],
            'category' => $form['category'],
            'sort_order' => (int)$form['sort_order'],
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
        ];

        if ($action === 'update') {
            if ($linkId < 1) {
                throw new InvalidArgumentException('ID link tidak valid.');
            }
            $update = $pdo->prepare(
                'UPDATE links SET title = :title, url = :url, icon = :icon, category = :category,
                 sort_order = :sort_order, is_active = :is_active WHERE id = :id'
            );
            $update->execute($values + ['id' => $linkId]);
            logActivity('update', 'link', $linkId, 'Judul: ' . $form['title']);
            pbAdminFlash('success', 'Link berhasil diperbarui.');
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO links (title, url, icon, category, sort_order, is_active)
                 VALUES (:title, :url, :icon, :category, :sort_order, :is_active)'
            );
            $insert->execute($values);
            logActivity('create', 'link', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title']);
            pbAdminFlash('success', 'Link baru berhasil ditambahkan.');
        }
        header('Location: admin-links.php', true, 303);
        exit;
    }

    $requestedEdit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedEdit !== false && $requestedEdit > 0) {
        $editId = (int)$requestedEdit;
        $statement = $pdo->prepare('SELECT id, title, url, icon, category, sort_order, is_active FROM links WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $existing = $statement->fetch();
        if (!$existing) {
            pbAdminFlash('error', 'Link yang ingin diedit tidak ditemukan.');
            header('Location: admin-links.php', true, 303);
            exit;
        }
        $form = [
            'title' => (string)$existing['title'],
            'url' => (string)$existing['url'],
            'icon' => (string)$existing['icon'],
            'category' => (string)$existing['category'],
            'sort_order' => (string)$existing['sort_order'],
            'is_active' => (int)$existing['is_active'] === 1 ? '1' : '0',
        ];
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
    $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $editId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;
} catch (Throwable $exception) {
    error_log('[Page Builder] Link admin error: ' . $exception->getMessage());
    $errorMessage = 'Link belum dapat diproses. Periksa koneksi database dan pastikan migrasi modul sudah dijalankan.';
}

$links = [];
try {
    $links = $pdo->query(
        'SELECT id, title, url, icon, category, sort_order, is_active, updated_at
         FROM links ORDER BY category ASC, sort_order ASC, id ASC'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Link list error: ' . $exception->getMessage());
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Daftar link belum dapat dimuat. Jalankan migrasi modul dashboard terlebih dahulu.';
}

$isEditing = $editId > 0;

pbAdminPageStart(
    'Link Aplikasi & Menu',
    'links',
    'Kelola tautan navigasi, aplikasi, dan menu website.',
    <<<'CSS'
        .links-table { min-width: 780px; }
        .link-preview { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .link-preview-icon { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; color: #4f46e5; background: #eef2ff; border-radius: 9px; font-size: 14px; }
        .link-preview-icon .emoji { font-size: 17px; }
        .link-preview-text { min-width: 0; }
        .link-preview-text strong { display: block; overflow: hidden; color: var(--admin-ink); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .link-preview-text small { color: #94a3b8; font-size: 10px; }
        .link-url { display: block; max-width: 260px; overflow-wrap: anywhere; color: #4f46e5; font-size: 11px; text-decoration: none; }
        .link-url:hover { text-decoration: underline; }
        .link-order { color: var(--admin-muted); font-variant-numeric: tabular-nums; }
CSS
);
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h2><?= $isEditing ? 'Edit Link' : 'Tambah Link Baru' ?></h2>
                            <p><?= $isEditing ? 'Perbarui detail tautan yang dipilih.' : 'Tambahkan tautan aplikasi atau menu navigasi.' ?></p>
                        </div>
                        <?php if ($isEditing): ?><a class="admin-btn small" href="admin-links.php"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Batal</a><?php endif; ?>
                    </div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-links.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $isEditing ? 'update' : 'create' ?>">
                            <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field" for="link-title"><span>Nama Link</span>
                                <input class="admin-input" id="link-title" name="title" type="text" maxlength="150" required value="<?= pbE($form['title']) ?>" placeholder="Contoh: Aplikasi Raport">
                            </label>
                            <label class="admin-field" for="link-url"><span>URL <small>http(s), mailto, tel, atau path relatif</small></span>
                                <input class="admin-input" id="link-url" name="url" type="text" maxlength="500" required value="<?= pbE($form['url']) ?>" placeholder="https://contoh.com/aplikasi">
                            </label>
                            <div class="admin-form-row">
                                <label class="admin-field" for="link-category"><span>Kategori</span>
                                    <input class="admin-input" id="link-category" name="category" type="text" maxlength="100" required value="<?= pbE($form['category']) ?>" placeholder="Menu Utama">
                                </label>
                                <label class="admin-field" for="link-order"><span>Urutan</span>
                                    <input class="admin-input" id="link-order" name="sort_order" type="number" min="0" max="9999" value="<?= pbE($form['sort_order']) ?>">
                                </label>
                            </div>
                            <label class="admin-field" for="link-icon"><span>Ikon <small>Font Awesome atau emoji</small></span>
                                <input class="admin-input" id="link-icon" name="icon" type="text" maxlength="100" value="<?= pbE($form['icon']) ?>" placeholder="fa-solid fa-link">
                            </label>
                            <label class="admin-check"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] === '1' ? ' checked' : '' ?>> Tampilkan link di website</label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $isEditing ? 'Simpan Perubahan' : 'Tambah Link' ?></button>
                                <?php if ($isEditing): ?><a class="admin-btn" href="admin-links.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Daftar Link (<?= count($links) ?>)</h2><p>Urut berdasarkan kategori dan urutan tampil.</p></div>
                    </div>
                    <div class="table-wrap">
                        <table class="admin-table links-table">
                            <thead><tr><th>Link</th><th>Kategori</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($links === []): ?>
                                <tr><td colspan="5"><div class="admin-empty"><i class="fa-solid fa-link" aria-hidden="true"></i>Belum ada link. Tambahkan link pertama di formulir.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($links as $link): ?>
                                    <?php
                                    $icon = trim((string)$link['icon']);
                                    $isFa = $icon !== '' && pbAdminIconIsFontAwesome($icon);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="link-preview">
                                                <span class="link-preview-icon" aria-hidden="true">
                                                    <?php if ($isFa): ?><i class="<?= pbE($icon) ?>"></i><?php elseif ($icon !== ''): ?><span class="emoji"><?= pbE($icon) ?></span><?php else: ?><i class="fa-solid fa-link"></i><?php endif; ?>
                                                </span>
                                                <span class="link-preview-text">
                                                    <strong><?= pbE($link['title']) ?></strong>
                                                    <a class="link-url" href="<?= pbE($link['url']) ?>" target="_blank" rel="noopener"><?= pbE($link['url']) ?></a>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="admin-badge brand"><?= pbE($link['category']) ?></span></td>
                                        <td class="link-order"><?= (int)$link['sort_order'] ?></td>
                                        <td><span class="admin-badge <?= (int)$link['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int)$link['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-links.php?edit=<?= (int)$link['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <form method="post" action="admin-links.php">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                                                    <button class="admin-btn small" type="submit" title="Ubah status"><i class="fa-solid fa-power-off" aria-hidden="true"></i><?= (int)$link['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                                </form>
                                                <form method="post" action="admin-links.php" data-confirm="Hapus link ini secara permanen?">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                                                    <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Hapus</button>
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
