<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/optimizer.php';
// Article HTML is generated from mutable database content and must be revalidated.
header('Cache-Control: public, max-age=0, must-revalidate');

$slug = trim(is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '');
$post = null;
$title = 'Artikel tidak ditemukan';
$excerpt = '';
$content = '<p>Artikel yang Anda cari tidak tersedia.</p>';
$imageUrl = '';

if ($slug !== '' && strlen($slug) <= 255 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    try {
        $pdo = pbDbConnection();
        $statement = $pdo->prepare("SELECT * FROM posts WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $post = $statement->fetch();
        if ($post) {
            $title = (string)$post['title'];
            $excerpt = trim(strip_tags((string)($post['excerpt'] ?? '')));
            $content = pbSanitizeHtml((string)($post['content'] ?? ''));
            $candidate = trim((string)($post['featured_image'] ?? ''));
            $safeUrl = $candidate !== '' ? pbSafeUrl($candidate) : null;
            if ($safeUrl !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeUrl)) {
                $imageUrl = $safeUrl;
            }
        } else {
            http_response_code(404);
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Post render error: ' . $exception->getMessage());
        http_response_code(503);
        $title = 'Artikel belum siap';
        $content = '<p>Artikel belum dapat dimuat. Periksa koneksi database.</p>';
    }
} else {
    http_response_code(404);
}

$publishedAt = $post ? (string)$post['created_at'] : '';
$category = $post ? (string)($post['category'] ?? 'Umum') : '';
ob_start();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> — MQ An-Nuur</title>
    <meta name="description" content="<?= htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:type" content="article">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&amp;display=swap">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; background: #f8fafc; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; }
        .post-header { padding: 18px max(5vw, 20px); border-bottom: 1px solid #e2e8f0; background: #fff; }
        .post-brand { color: #176b45; font-size: 15px; font-weight: 800; text-decoration: none; }
        .post-main { width: min(900px, calc(100% - 32px)); margin: 38px auto 72px; }
        .post-article { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 14px 40px rgba(15, 23, 42, .06); }
        .post-cover { display: block; width: 100%; max-height: 480px; object-fit: cover; background: #e2e8f0; }
        .post-content { padding: clamp(22px, 5vw, 52px); }
        .post-category { color: #0f766e; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 12px 0; color: #102a27; font-size: clamp(30px, 5vw, 48px); line-height: 1.15; }
        .post-date { color: #64748b; font-size: 13px; }
        .post-excerpt { margin: 22px 0; color: #475569; font-size: 18px; line-height: 1.7; }
        .post-body { color: #334155; font-size: 16px; line-height: 1.85; overflow-wrap: anywhere; }
        .post-body h2, .post-body h3 { margin-top: 1.6em; color: #123b2b; line-height: 1.3; }
        .post-body img { max-width: 100%; height: auto; }
        .post-body a { color: #0f766e; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #176b45; font-size: 14px; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <header class="post-header"><a class="post-brand" href="index.php">MQ An-Nuur</a></header>
    <main class="post-main">
        <a class="back-link" href="index.php">← Kembali ke halaman utama</a>
        <article class="post-article">
            <?php if ($imageUrl !== ''): ?>
                <img class="post-cover" src="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" fetchpriority="high" decoding="async">
            <?php endif; ?>
            <div class="post-content">
                <?php if ($category !== ''): ?><div class="post-category"><?= htmlspecialchars($category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
                <h1><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                <?php if ($publishedAt !== ''): ?><time class="post-date" datetime="<?= htmlspecialchars($publishedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars(date('d M Y · H:i', strtotime($publishedAt)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></time><?php endif; ?>
                <?php if ($excerpt !== ''): ?><p class="post-excerpt"><?= htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
                <div class="post-body"><?= $content ?></div>
            </div>
        </article>
    </main>
</body>
</html>
<?php
$renderedPage = ob_get_clean();
echo PageSpeedOptimizer::injectResourceHints($renderedPage === false ? '' : $renderedPage);
