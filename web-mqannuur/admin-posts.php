<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';

function pbAdminPostSlugBase(string $value, string $title): string
{
    $source = trim($value) !== '' ? trim($value) : $title;
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source);
        if (is_string($transliterated)) {
            $source = $transliterated;
        }
    }
    $source = strtolower($source);
    $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
    $source = trim($source, '-');
    return substr($source !== '' ? $source : 'artikel', 0, 220);
}

function pbAdminUniquePostSlug(PDO $pdo, string $base, int $excludeId = 0): string
{
    $slug = $base;
    $suffix = 2;
    while (true) {
        if ($excludeId > 0) {
            $statement = $pdo->prepare('SELECT 1 FROM posts WHERE slug = :slug AND id <> :id LIMIT 1');
            $statement->execute(['slug' => $slug, 'id' => $excludeId]);
        } else {
            $statement = $pdo->prepare('SELECT 1 FROM posts WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $slug]);
        }
        if (!$statement->fetchColumn()) {
            return $slug;
        }
        $slug = substr($base, 0, 220) . '-' . $suffix++;
    }
}

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = [
    'title' => '',
    'slug' => '',
    'category' => 'Umum',
    'status' => 'published',
    'excerpt' => '',
    'featured_image' => '',
    'content' => '',
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
        $postId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($postId < 1) {
                throw new InvalidArgumentException('ID berita tidak valid.');
            }
            $delete = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $delete->execute(['id' => $postId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Berita tidak ditemukan.');
            }
            logActivity('delete', 'post', $postId, 'Berita dihapus');
            pbAdminFlash('success', 'Berita berhasil dihapus.');
            header('Location: admin-posts.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi berita tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'slug' => trim(is_string($_POST['slug'] ?? null) ? strip_tags($_POST['slug']) : ''),
            'category' => trim(is_string($_POST['category'] ?? null) ? strip_tags($_POST['category']) : ''),
            'status' => is_string($_POST['status'] ?? null) ? $_POST['status'] : 'published',
            'excerpt' => trim(is_string($_POST['excerpt'] ?? null) ? strip_tags($_POST['excerpt']) : ''),
            'featured_image' => trim(is_string($_POST['featured_image'] ?? null) ? $_POST['featured_image'] : ''),
            'content' => is_string($_POST['content'] ?? null) ? $_POST['content'] : '',
        ];

        $titleLength = preg_match_all('/./us', $form['title']);
        $categoryLength = preg_match_all('/./us', $form['category']);
        $excerptLength = preg_match_all('/./us', $form['excerpt']);
        if ($form['title'] === '' || $titleLength === false || $titleLength > 255) {
            throw new InvalidArgumentException('Judul wajib diisi dan maksimal 255 karakter.');
        }
        if ($form['category'] === '') {
            $form['category'] = 'Umum';
        }
        if ($categoryLength === false || $categoryLength > 100) {
            throw new InvalidArgumentException('Kategori maksimal 100 karakter.');
        }
        if ($excerptLength === false || $excerptLength > 5000 || strlen($form['content']) > 2_000_000) {
            throw new InvalidArgumentException('Ringkasan atau isi berita terlalu panjang.');
        }
        if (!in_array($form['status'], ['draft', 'published'], true)) {
            throw new InvalidArgumentException('Status berita tidak valid.');
        }
        if ($form['featured_image'] !== '') {
            $safeImage = pbSafeUrl($form['featured_image']);
            if ($safeImage === null || preg_match('~^(?:mailto:|tel:)~i', $safeImage)) {
                throw new InvalidArgumentException('Gambar utama harus berupa URL HTTP(S) atau path relatif yang aman.');
            }
            $form['featured_image'] = $safeImage;
        }

        $content = pbSanitizeHtml($form['content']);
        $slug = pbAdminUniquePostSlug($pdo, pbAdminPostSlugBase($form['slug'], $form['title']), $action === 'update' ? $postId : 0);
        $values = [
            'title' => $form['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $form['excerpt'] !== '' ? $form['excerpt'] : null,
            'featured_image' => $form['featured_image'] !== '' ? $form['featured_image'] : null,
            'category' => $form['category'],
            'status' => $form['status'],
        ];

        if ($action === 'update') {
            if ($postId < 1) {
                throw new InvalidArgumentException('ID berita tidak valid.');
            }
            $update = $pdo->prepare(
                'UPDATE posts SET title = :title, slug = :slug, content = :content, excerpt = :excerpt,
                 featured_image = :featured_image, category = :category, status = :status WHERE id = :id'
            );
            $update->execute($values + ['id' => $postId]);
            logActivity('update', 'post', $postId, 'Judul: ' . $form['title'] . '; slug: ' . $slug);
            pbAdminFlash('success', 'Berita berhasil diperbarui.');
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO posts (title, slug, content, excerpt, featured_image, category, status)
                 VALUES (:title, :slug, :content, :excerpt, :featured_image, :category, :status)'
            );
            $insert->execute($values);
            logActivity('create', 'post', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title'] . '; slug: ' . $slug);
            pbAdminFlash('success', 'Berita baru berhasil dibuat.');
        }
        header('Location: admin-posts.php', true, 303);
        exit;
    }

    $requestedEdit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedEdit !== false && $requestedEdit > 0) {
        $editId = (int)$requestedEdit;
        $statement = $pdo->prepare('SELECT id, title, slug, content, excerpt, featured_image, category, status FROM posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $existing = $statement->fetch();
        if (!$existing) {
            pbAdminFlash('error', 'Berita yang ingin diedit tidak ditemukan.');
            header('Location: admin-posts.php', true, 303);
            exit;
        }
        $form = [
            'title' => (string)$existing['title'],
            'slug' => (string)$existing['slug'],
            'category' => (string)$existing['category'],
            'status' => (string)$existing['status'],
            'excerpt' => (string)($existing['excerpt'] ?? ''),
            'featured_image' => (string)($existing['featured_image'] ?? ''),
            'content' => (string)$existing['content'],
        ];
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
    $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $editId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;
} catch (Throwable $exception) {
    error_log('[Page Builder] Post admin error: ' . $exception->getMessage());
    $errorMessage = 'Berita belum dapat diproses. Periksa koneksi database.';
}

$posts = [];
$publishedCount = 0;
$draftCount = 0;
try {
    $posts = $pdo->query(
        'SELECT id, title, slug, excerpt, featured_image, category, status, created_at, updated_at
         FROM posts ORDER BY created_at DESC, id DESC'
    )->fetchAll();
    foreach ($posts as $post) {
        if ($post['status'] === 'published') {
            $publishedCount++;
        } else {
            $draftCount++;
        }
    }
} catch (Throwable $exception) {
    error_log('[Page Builder] Post list error: ' . $exception->getMessage());
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Daftar berita belum dapat dimuat. Jalankan migrasi modul dashboard terlebih dahulu.';
}

$isEditing = $editId > 0;

pbAdminPageStart(
    'Kelola Berita',
    'posts',
    'Tulis, edit, dan terbitkan artikel berita website.',
    <<<'CSS'
        .posts-table { min-width: 820px; }
        .post-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .post-thumb { width: 44px; height: 38px; flex: 0 0 44px; object-fit: cover; background: #eef2ff; border-radius: 8px; }
        .post-thumb-placeholder { display: grid; place-items: center; width: 44px; height: 38px; flex: 0 0 44px; color: #6366f1; background: #eef2ff; border-radius: 8px; font-size: 13px; }
        .post-cell strong { display: block; overflow: hidden; color: var(--admin-ink); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .post-cell small { color: #94a3b8; font-size: 10px; }
        .post-counts { display: flex; gap: 6px; flex-wrap: wrap; }
        .post-preview-link { color: #4f46e5; font-size: 10px; text-decoration: none; }
        .post-preview-link:hover { text-decoration: underline; }
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
                            <h2><?= $isEditing ? 'Edit Berita' : 'Tulis Berita Baru' ?></h2>
                            <p><?= $isEditing ? 'Perbarui artikel yang dipilih.' : 'Buat artikel untuk website.' ?></p>
                        </div>
                        <?php if ($isEditing): ?><a class="admin-btn small" href="admin-posts.php"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Batal</a><?php endif; ?>
                    </div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-posts.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $isEditing ? 'update' : 'create' ?>">
                            <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field" for="post-title"><span>Judul</span>
                                <input class="admin-input" id="post-title" name="title" type="text" maxlength="255" required value="<?= pbE($form['title']) ?>" placeholder="Judul berita">
                            </label>
                            <label class="admin-field" for="post-slug"><span>Slug <small>kosongkan untuk otomatis</small></span>
                                <input class="admin-input" id="post-slug" name="slug" type="text" maxlength="255" value="<?= pbE($form['slug']) ?>" placeholder="judul-berita">
                            </label>
                            <div class="admin-form-row">
                                <label class="admin-field" for="post-category"><span>Kategori</span>
                                    <input class="admin-input" id="post-category" name="category" type="text" maxlength="100" required value="<?= pbE($form['category']) ?>" placeholder="Umum">
                                </label>
                                <label class="admin-field" for="post-status"><span>Status</span>
                                    <select class="admin-select" id="post-status" name="status">
                                        <option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Published</option>
                                        <option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                                    </select>
                                </label>
                            </div>
                            <label class="admin-field" for="post-image"><span>Gambar Utama <small>URL atau path relatif</small></span>
                                <input class="admin-input" id="post-image" name="featured_image" type="text" maxlength="500" value="<?= pbE($form['featured_image']) ?>" placeholder="uploads/foto.jpg">
                            </label>
                            <label class="admin-field" for="post-excerpt"><span>Ringkasan <small>maks. 5000 karakter</small></span>
                                <textarea class="admin-textarea" id="post-excerpt" name="excerpt" rows="3" maxlength="5000" placeholder="Ringkasan singkat berita"><?= pbE($form['excerpt']) ?></textarea>
                            </label>
                            <label class="admin-field" for="post-content"><span>Isi Berita <small>HTML diizinkan dan disanitasi</small></span>
                                <textarea class="admin-textarea code" id="post-content" name="content" rows="10" placeholder="<p>Tulis isi berita di sini...</p>"><?= pbE($form['content']) ?></textarea>
                            </label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $isEditing ? 'Simpan Perubahan' : 'Terbitkan Berita' ?></button>
                                <?php if ($isEditing): ?><a class="admin-btn" href="admin-posts.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Daftar Berita (<?= count($posts) ?>)</h2><p>Artikel terbaru tampil paling atas.</p></div>
                        <div class="post-counts">
                            <span class="admin-badge success"><?= $publishedCount ?> terbit</span>
                            <span class="admin-badge warning"><?= $draftCount ?> draft</span>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="admin-table posts-table">
                            <thead><tr><th>Berita</th><th>Kategori</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($posts === []): ?>
                                <tr><td colspan="5"><div class="admin-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i>Belum ada berita. Tulis artikel pertama di formulir.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($posts as $post):
                                    $thumb = '';
                                    $candidateThumb = trim((string)($post['featured_image'] ?? ''));
                                    if ($candidateThumb !== '') {
                                        $safeThumb = pbSafeUrl($candidateThumb);
                                        if ($safeThumb !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeThumb)) {
                                            $thumb = $safeThumb;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="post-cell">
                                                <?php if ($thumb !== ''): ?>
                                                    <img class="post-thumb" src="<?= pbE($thumb) ?>" alt="" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <span class="post-thumb-placeholder" aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                                <?php endif; ?>
                                                <span>
                                                    <strong><?= pbE($post['title']) ?></strong>
                                                    <small>/<?= pbE($post['slug']) ?></small>
                                                    <?php if ($post['status'] === 'published'): ?><br><a class="post-preview-link" href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>" target="_blank" rel="noopener">Lihat artikel ↗</a><?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="admin-badge brand"><?= pbE($post['category']) ?></span></td>
                                        <td><span class="admin-badge <?= $post['status'] === 'published' ? 'success' : 'warning' ?>"><?= pbE($post['status']) ?></span></td>
                                        <td class="admin-muted"><?= pbE(date('d M Y', strtotime((string)$post['created_at']))) ?></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-posts.php?edit=<?= (int)$post['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <form method="post" action="admin-posts.php" data-confirm="Hapus berita ini secara permanen?">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
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
