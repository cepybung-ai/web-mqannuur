<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/optimizer.php';

if (empty($_SESSION['page_builder_csrf'])) {
    $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['page_builder_csrf'];
$errorMessage = '';
$noticeMessage = is_string($_SESSION['posts_flash'] ?? null) ? $_SESSION['posts_flash'] : '';
unset($_SESSION['posts_flash']);

/** Create a URL-safe, unique base slug from a post title. */
function pbPostSlugBase(string $title): string
{
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) : $title;
    $ascii = is_string($ascii) ? $ascii : $title;
    $slug = strtolower($ascii);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'artikel', 0, 220);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        try {
            $pdo = pbDbConnection();
            $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
            if ($action === 'delete') {
                $postId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if ($postId === false || $postId < 1) {
                    throw new InvalidArgumentException('ID artikel tidak valid.');
                }
                $delete = $pdo->prepare('DELETE FROM posts WHERE id = :id');
                $delete->execute(['id' => $postId]);
                if ($delete->rowCount() === 0) {
                    throw new InvalidArgumentException('Artikel tidak ditemukan.');
                }
                $_SESSION['posts_flash'] = 'Artikel berhasil dihapus.';
                header('Location: admin-posts.php', true, 303);
                exit;
            }

            if ($action !== 'create') {
                throw new InvalidArgumentException('Aksi artikel tidak dikenal.');
            }

            $title = trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : '');
            $excerpt = trim(is_string($_POST['excerpt'] ?? null) ? strip_tags($_POST['excerpt']) : '');
            $contentInput = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
            $featuredImage = trim(is_string($_POST['featured_image'] ?? null) ? $_POST['featured_image'] : '');
            $category = trim(is_string($_POST['category'] ?? null) ? strip_tags($_POST['category']) : 'Umum');
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : 'published';
            $titleLength = preg_match_all('/./us', $title, $titleCharacters);
            $excerptLength = preg_match_all('/./us', $excerpt, $excerptCharacters);
            $categoryLength = preg_match_all('/./us', $category, $categoryCharacters);

            if ($title === '' || $titleLength === false || $titleLength > 255) {
                throw new InvalidArgumentException('Judul wajib diisi dan maksimal 255 karakter.');
            }
            if ($excerptLength === false || $excerptLength > 5000 || strlen($contentInput) > 2_000_000) {
                throw new InvalidArgumentException('Excerpt atau isi artikel terlalu panjang.');
            }
            if ($category === '') $category = 'Umum';
            if ($categoryLength === false || $categoryLength > 100) {
                throw new InvalidArgumentException('Kategori maksimal 100 karakter.');
            }
            if (!in_array($status, ['draft', 'published'], true)) {
                throw new InvalidArgumentException('Status artikel tidak valid.');
            }
            if ($featuredImage !== '') {
                $safeImage = pbSafeUrl($featuredImage);
                if ($safeImage === null || preg_match('~^(?:mailto:|tel:)~i', $safeImage)) {
                    throw new InvalidArgumentException('Thumbnail harus menggunakan URL HTTP(S) atau path relatif yang aman.');
                }
                $featuredImage = $safeImage;
                if (preg_match_all('/./us', $featuredImage, $imageCharacters) > 500) {
                    throw new InvalidArgumentException('URL thumbnail maksimal 500 karakter.');
                }
            }

            $content = PageSpeedOptimizer::optimizeImages(
                PageSpeedOptimizer::minifyHtml(pbSanitizeHtml($contentInput))
            );
            $baseSlug = pbPostSlugBase($title);
            $slug = $baseSlug;
            $suffix = 2;
            $slugCheck = $pdo->prepare('SELECT 1 FROM posts WHERE slug = :slug LIMIT 1');
            while (true) {
                $slugCheck->execute(['slug' => $slug]);
                if (!$slugCheck->fetchColumn()) break;
                $slug = substr($baseSlug, 0, 220) . '-' . $suffix++;
            }

            $insert = $pdo->prepare(
                'INSERT INTO posts (title, slug, content, excerpt, featured_image, category, status)
                 VALUES (:title, :slug, :content, :excerpt, :featured_image, :category, :status)'
            );
            $insert->execute([
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'featured_image' => $featuredImage !== '' ? $featuredImage : null,
                'category' => $category,
                'status' => $status
            ]);
            $_SESSION['posts_flash'] = 'Artikel berhasil dibuat.';
            header('Location: admin-posts.php', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('[Page Builder] Post admin error: ' . $exception->getMessage());
            $errorMessage = 'Artikel belum dapat diproses. Periksa koneksi database.';
        }
    }
}

$posts = [];
try {
    $posts = pbDbConnection()->query(
        'SELECT id, title, slug, category, status, created_at FROM posts ORDER BY created_at DESC, id DESC'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Post list error: ' . $exception->getMessage());
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Daftar artikel belum dapat dimuat. Pastikan tabel posts tersedia.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <title>Kelola Artikel — Web MQ An-Nuur</title>
    <style>
        * { box-sizing: border-box; }
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f3f6fb; }
        body { margin: 0; padding: clamp(16px, 4vw, 40px); }
        .dashboard { width: min(1180px, 100%); margin: 0 auto; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .eyebrow { margin: 0 0 6px; color: #0f766e; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        h1 { margin: 0; color: #102a27; font-size: clamp(24px, 4vw, 34px); }
        .actions { display: flex; gap: 8px; }
        .actions a { padding: 10px 13px; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .editor-link { background: #176b45; }
        .logout-link { background: #b42318; }
        .layout { display: grid; grid-template-columns: minmax(280px, .8fr) minmax(0, 1.2fr); align-items: start; gap: 18px; }
        .card { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 8px 28px rgba(15, 23, 42, .05); }
        .card h2 { margin: 0; padding: 17px 19px; border-bottom: 1px solid #e8edf4; font-size: 16px; }
        .post-form { display: grid; gap: 13px; padding: 18px 19px 20px; }
        .field { display: grid; gap: 6px; color: #344054; font-size: 12px; font-weight: 750; }
        .field input, .field textarea, .field select { width: 100%; padding: 10px 11px; border: 1px solid #d0d9e5; border-radius: 8px; outline-color: #0f9f78; background: #fff; color: #172033; font-family: inherit; font-size: 13px; line-height: 1.5; }
        .field textarea { resize: vertical; }
        #post-content { min-height: 210px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .field-hint { color: #8290a5; font-size: 10px; font-weight: 500; }
        .submit-button { padding: 11px 14px; border: 0; border-radius: 8px; background: #0f9f78; color: #fff; font-size: 13px; font-weight: 800; cursor: pointer; }
        .notice, .error { margin: 0 0 16px; padding: 11px 13px; border-radius: 8px; font-size: 13px; }
        .notice { background: #ecfdf5; color: #047857; }
        .error { background: #fef2f2; color: #b42318; }
        .post-list { display: grid; gap: 0; }
        .post-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding: 15px 18px; border-bottom: 1px solid #edf1f6; }
        .post-row:last-child { border-bottom: 0; }
        .post-row h3 { margin: 0 0 5px; color: #172033; font-size: 14px; }
        .post-meta { color: #64748b; font-size: 11px; line-height: 1.6; }
        .post-meta a { color: #2563eb; overflow-wrap: anywhere; text-decoration: none; }
        .status-pill { display: inline-block; margin-left: 5px; padding: 2px 7px; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: 10px; font-weight: 800; }
        .status-pill.draft { background: #fef3c7; color: #92400e; }
        .delete-button { align-self: center; padding: 8px 10px; border: 0; border-radius: 7px; background: #fee4e2; color: #b42318; font-size: 11px; font-weight: 800; cursor: pointer; }
        .empty { padding: 28px 18px; color: #64748b; font-size: 13px; text-align: center; }
        @media (max-width: 850px) { .layout { grid-template-columns: 1fr; } }
        @media (max-width: 580px) { body { padding: 15px 10px; } .header { align-items: flex-start; flex-direction: column; } .actions { width: 100%; } .actions a { flex: 1; text-align: center; } .post-row { grid-template-columns: 1fr; } .delete-button { justify-self: start; } }
    </style>
</head>
<body>
<main class="dashboard">
    <header class="header">
        <div><p class="eyebrow">Web MQ An-Nuur · CMS</p><h1>Kelola Artikel Blog</h1></div>
        <nav class="actions" aria-label="Navigasi admin">
            <a class="editor-link" href="editor.php">Kembali ke Editor</a>
            <a class="logout-link" href="logout.php">Logout</a>
        </nav>
    </header>

    <?php if ($noticeMessage !== ''): ?><p class="notice" role="status"><?= htmlspecialchars($noticeMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>

    <div class="layout">
        <section class="card" aria-labelledby="create-post-title">
            <h2 id="create-post-title">Buat Artikel Baru</h2>
            <form class="post-form" method="post" action="admin-posts.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="form_action" value="create">
                <label class="field" for="post-title">Judul
                    <input id="post-title" name="title" type="text" maxlength="255" required placeholder="Judul artikel">
                </label>
                <label class="field" for="post-image">Thumbnail URL
                    <input id="post-image" name="featured_image" type="url" maxlength="500" placeholder="https://domain.com/uploads/foto.jpg">
                </label>
                <label class="field" for="post-category">Kategori
                    <input id="post-category" name="category" type="text" maxlength="100" value="Umum" required>
                </label>
                <label class="field" for="post-excerpt">Excerpt
                    <textarea id="post-excerpt" name="excerpt" rows="3" maxlength="5000" placeholder="Ringkasan singkat untuk kartu artikel"></textarea>
                </label>
                <label class="field" for="post-content">Content (HTML)
                    <textarea id="post-content" name="content" rows="9" placeholder="<p>Tulis isi artikel...</p>"></textarea>
                    <small class="field-hint">HTML akan dibersihkan dari script dan atribut berbahaya sebelum disimpan.</small>
                </label>
                <label class="field" for="post-status">Status
                    <select id="post-status" name="status"><option value="published">Published</option><option value="draft">Draft</option></select>
                </label>
                <button class="submit-button" type="submit">Simpan Artikel</button>
            </form>
        </section>

        <section class="card" aria-labelledby="post-list-title">
            <h2 id="post-list-title">Daftar Artikel (<?= count($posts) ?>)</h2>
            <div class="post-list">
                <?php if (!$posts): ?>
                    <p class="empty">Belum ada artikel.</p>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="post-row">
                            <div>
                                <h3><?= htmlspecialchars((string)$post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                                <div class="post-meta">
                                    <?= htmlspecialchars((string)$post['category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> ·
                                    <?= htmlspecialchars(date('d M Y H:i', strtotime((string)$post['created_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <span class="status-pill<?= $post['status'] === 'draft' ? ' draft' : '' ?>"><?= htmlspecialchars((string)$post['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><br>
                                    Slug: <a href="post.php?slug=<?= rawurlencode((string)$post['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string)$post['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                                </div>
                            </div>
                            <form method="post" action="admin-posts.php" class="delete-post-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                <button class="delete-button" type="submit">Hapus</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script>
document.querySelectorAll('.delete-post-form').forEach(form => {
    form.addEventListener('submit', event => {
        if (!confirm('Hapus artikel ini secara permanen?')) event.preventDefault();
    });
});
</script>
</body>
</html>
