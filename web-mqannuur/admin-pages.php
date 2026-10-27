<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = ['title' => '', 'slug' => ''];
$pages = [];

try {
    $pdo = getDbConnection();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $pageId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($pageId < 1) {
                throw new InvalidArgumentException('ID halaman tidak valid.');
            }
            $find = $pdo->prepare('SELECT id, slug, title FROM pages WHERE id = :id LIMIT 1');
            $find->execute(['id' => $pageId]);
            $target = $find->fetch();
            if (!$target) {
                throw new InvalidArgumentException('Halaman tidak ditemukan.');
            }
            if ((string)$target['slug'] === 'home') {
                throw new InvalidArgumentException('Halaman home tidak dapat dihapus karena menjadi halaman utama website.');
            }
            $delete = $pdo->prepare('DELETE FROM pages WHERE id = :id');
            $delete->execute(['id' => $pageId]);
            logActivity('delete', 'page', $pageId, 'Judul: ' . (string)$target['title'] . '; slug: ' . (string)$target['slug']);
            pbAdminFlash('success', 'Halaman berhasil dihapus.');
            header('Location: admin-pages.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi halaman tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'slug' => strtolower(trim(is_string($_POST['slug'] ?? null) ? strip_tags($_POST['slug']) : '')),
        ];
        if ($form['title'] === '' || preg_match_all('/./us', $form['title']) > 255) {
            throw new InvalidArgumentException('Judul halaman wajib diisi dan maksimal 255 karakter.');
        }
        if ($form['slug'] !== '' && !pbValidSlug($form['slug'])) {
            throw new InvalidArgumentException('Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.');
        }
        $base = $form['slug'] !== '' ? $form['slug'] : pbAdminSlugBase($form['title'], 'halaman');
        $slug = pbAdminUniqueSlug($pdo, 'pages', $base, $action === 'update' ? $pageId : 0);

        if ($action === 'create') {
            $insert = $pdo->prepare("INSERT INTO pages (slug, title, content_json, content_css, content_html) VALUES (:slug, :title, '[]', '', '')");
            $insert->execute(['slug' => $slug, 'title' => $form['title']]);
            logActivity('create', 'page', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title'] . '; slug: ' . $slug);
            pbAdminFlash('success', 'Halaman baru berhasil dibuat. Silakan Customize layout-nya.');
            header('Location: admin-pages.php', true, 303);
            exit;
        }

        if ($pageId < 1) {
            throw new InvalidArgumentException('ID halaman tidak valid.');
        }
        $update = $pdo->prepare('UPDATE pages SET title = :title, slug = :slug WHERE id = :id');
        $update->execute(['title' => $form['title'], 'slug' => $slug, 'id' => $pageId]);
        logActivity('update', 'page', $pageId, 'Judul: ' . $form['title'] . '; slug: ' . $slug);
        pbAdminFlash('success', 'Data halaman berhasil diperbarui.');
        header('Location: admin-pages.php', true, 303);
        exit;
    }

    $requestedId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId !== false && $requestedId > 0) {
        $editId = (int)$requestedId;
        $find = $pdo->prepare('SELECT id, title, slug FROM pages WHERE id = :id LIMIT 1');
        $find->execute(['id' => $editId]);
        $row = $find->fetch();
        if ($row) {
            $form = ['title' => (string)$row['title'], 'slug' => (string)$row['slug']];
        } else {
            $editId = 0;
            $errorMessage = 'Halaman yang ingin diedit tidak ditemukan.';
        }
    }

    $pages = $pdo->query('SELECT id, title, slug, updated_at FROM pages ORDER BY updated_at DESC, id DESC')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin pages error: ' . $exception->getMessage());
    $errorMessage = 'Modul halaman belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Kelola Halaman', 'pages', 'Daftar halaman website dan akses cepat ke editor visual.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2><?= $editId > 0 ? 'Edit Halaman' : 'Buat Halaman Baru' ?></h2><p>Judul dan slug halaman. Layout diatur melalui editor.</p></div>
                    </div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-pages.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field"><span>Judul Halaman</span><input class="admin-input" type="text" name="title" maxlength="255" required value="<?= pbE($form['title']) ?>" placeholder="Contoh: Profil Madrasah"></label>
                            <label class="admin-field"><span>Slug <small>Opsional</small></span><input class="admin-input" type="text" name="slug" maxlength="100" value="<?= pbE($form['slug']) ?>" placeholder="profil-madrasah"><small class="admin-form-hint">Kosongkan untuk membuat slug otomatis dari judul.</small></label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $editId > 0 ? 'Simpan Perubahan' : 'Buat Halaman' ?></button>
                                <?php if ($editId > 0): ?><a class="admin-btn" href="admin-pages.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Daftar Halaman (<?= count($pages) ?>)</h2><p>Klik Customize untuk membuka layout editor.</p></div>
                        <a class="admin-btn" href="editor.php?slug=home"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Editor Home</a>
                    </div>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Halaman</th><th>Slug</th><th>Diperbarui</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($pages === []): ?>
                                <tr><td colspan="4"><div class="admin-empty"><i class="fa-regular fa-file" aria-hidden="true"></i>Belum ada halaman. Buat halaman pertama di formulir.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td><div class="cell-title"><?= pbE($page['title']) ?></div><div class="cell-sub">ID #<?= (int)$page['id'] ?></div></td>
                                        <td><span class="admin-badge brand"><?= pbE($page['slug']) ?></span></td>
                                        <td class="admin-muted"><?= pbE(date('d M Y · H:i', strtotime((string)$page['updated_at']))) ?></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small primary" href="editor.php?slug=<?= rawurlencode((string)$page['slug']) ?>" title="Customize layout"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Customize</a>
                                                <a class="admin-btn small" href="index.php?page=<?= rawurlencode((string)$page['slug']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat</a>
                                                <a class="admin-btn small" href="admin-pages.php?edit=<?= (int)$page['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <?php if ((string)$page['slug'] !== 'home'): ?>
                                                    <form class="admin-inline-form" method="post" action="admin-pages.php" data-confirm="Hapus halaman ini? Tindakan tidak dapat dibatalkan.">
                                                        <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                        <input type="hidden" name="form_action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                                                        <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                <?php endif; ?>
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
