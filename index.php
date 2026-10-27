<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/optimizer.php';
// This route renders mutable database content; revalidate HTML on every visit.
header('Cache-Control: public, max-age=0, must-revalidate');

$isPreview = ($_GET['preview'] ?? '') === 'true';
$metaTitle = '';
$metaDescription = '';
$metaKeywords = '';
$ogImage = '';
$activeLang = 'id';
$translationMap = [];
$requestedLang = is_string($_GET['lang'] ?? null) ? strtolower(trim($_GET['lang'])) : '';
if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $requestedLang)) {
    $requestedLang = '';
}
$slug = trim(is_string($_GET['page'] ?? null) ? $_GET['page'] : '');
if ($slug === '') {
    $slug = 'home';
}
if (!pbValidSlug($slug)) {
    http_response_code(400);
    $page = null;
    $title = 'Permintaan tidak valid';
    $metaTitle = $title;
    $css = '';
    $html = '<main><h1>Nama halaman tidak valid</h1></main>';
} else {
    try {
        $pdo = pbDbConnection();

        $statement = $pdo->prepare('SELECT title, content_css, content_html, meta_title, meta_description, meta_keywords, og_image, lang, translation_group FROM pages WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $page = $statement->fetch();

        // Honor ?lang= when the current page has a matching translation in its group.
        if ($page && $requestedLang !== '' && strtolower((string)($page['lang'] ?? 'id')) !== $requestedLang
            && trim((string)($page['translation_group'] ?? '')) !== '') {
            $translatedPageQuery = $pdo->prepare(
                'SELECT title, content_css, content_html, meta_title, meta_description, meta_keywords, og_image, lang, translation_group
                 FROM pages WHERE translation_group = :group_id AND lang = :lang LIMIT 1'
            );
            $translatedPageQuery->execute([
                'group_id' => $page['translation_group'],
                'lang' => $requestedLang
            ]);
            $translatedPage = $translatedPageQuery->fetch();
            if ($translatedPage) {
                $page = $translatedPage;
                $translatedSlugQuery = $pdo->prepare(
                    'SELECT slug FROM pages WHERE translation_group = :group_id AND lang = :lang LIMIT 1'
                );
                $translatedSlugQuery->execute([
                    'group_id' => $page['translation_group'],
                    'lang' => $requestedLang
                ]);
                $slug = (string)$translatedSlugQuery->fetchColumn();
            }
        }

        if (!$page) {
            http_response_code(404);
            $title = 'Halaman tidak ditemukan';
            $metaTitle = $title;
            $css = '';
            $html = '<main><h1>Halaman belum tersedia</h1><p>Halaman <strong>' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> belum dibuat atau belum disimpan.</p></main>';
        } else {
            $activeLang = strtolower((string)($page['lang'] ?? 'id'));
            $translationGroup = trim((string)($page['translation_group'] ?? ''));
            $translationMap[$activeLang] = $slug;
            if ($translationGroup !== '') {
                $translationQuery = $pdo->prepare(
                    'SELECT slug, lang FROM pages WHERE translation_group = :group_id ORDER BY lang ASC'
                );
                $translationQuery->execute(['group_id' => $translationGroup]);
                foreach ($translationQuery->fetchAll() as $translation) {
                    $code = strtolower((string)($translation['lang'] ?? 'id'));
                    if (preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $code)) {
                        $translationMap[$code] = (string)$translation['slug'];
                    }
                }
            }
            $title = (string)$page['title'];
            $metaTitle = trim((string)($page['meta_title'] ?? '')) !== '' ? (string)$page['meta_title'] : $title;
            $metaDescription = (string)($page['meta_description'] ?? '');
            $metaKeywords = (string)($page['meta_keywords'] ?? '');
            $ogImageCandidate = trim((string)($page['og_image'] ?? ''));
            $safeOgImage = $ogImageCandidate !== '' ? pbSafeUrl($ogImageCandidate) : null;
            $ogImage = $safeOgImage !== null && !preg_match('~^(?:mailto:|tel:)~i', $safeOgImage) ? $safeOgImage : '';
            $css = pbSanitizeCss((string)$page['content_css']);
            $html = pbSanitizeHtml((string)$page['content_html']);
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Render error: ' . $exception->getMessage());
        http_response_code(503);
        $title = 'Halaman belum siap';
        $metaTitle = $title;
        $css = '';
        $html = '<main><h1>Halaman belum siap</h1><p>Periksa koneksi database dan pastikan tabel <code>pages</code> sudah dibuat.</p></main>';
    }
}
ob_start();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($activeLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($metaTitle !== '' ? $metaTitle : $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($metaTitle !== '' ? $metaTitle : $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($metaTitle !== '' ? $metaTitle : $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&amp;family=Lato:wght@300;400;700&amp;family=Merriweather:wght@300;400;700&amp;family=Montserrat:wght@300;400;600;700&amp;family=Nunito:wght@300;400;600;700&amp;family=Open+Sans:wght@300;400;600;700&amp;family=Oswald:wght@400;600&amp;family=Playfair+Display:wght@400;700&amp;family=Poppins:wght@300;400;600;700&amp;family=Raleway:wght@300;400;600;700&amp;family=Roboto:wght@300;400;500;700&amp;display=swap">
    <style id="page-css"><?= $css ?></style>
    <style>
        .blog-grid-container { width: 100%; }
        .blog-posts-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 20px; }
        .blog-post-card { min-width: 0; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; box-shadow: 0 6px 20px rgba(15, 23, 42, .07); }
        .blog-post-media { display: block; width: 100%; height: 190px; overflow: hidden; background: linear-gradient(135deg, #dbeafe, #dcfce7); }
        .blog-post-media img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .blog-post-body { padding: 16px; }
        .blog-post-category { color: #2563eb; font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .blog-post-title { margin: 8px 0; color: #172033; font-size: 18px; line-height: 1.35; }
        .blog-post-excerpt { margin: 0 0 14px; color: #64748b; font-size: 14px; line-height: 1.6; }
        .blog-post-link { color: #2563eb; font-size: 13px; font-weight: 750; text-decoration: none; }
        .blog-post-link:hover { text-decoration: underline; }
        .blog-grid-message { grid-column: 1 / -1; margin: 0; padding: 20px; border-radius: 9px; background: #f8fafc; color: #64748b; text-align: center; }
        .custom-form-feedback { margin-top: 12px; padding: 11px 13px; border: 1px solid transparent; border-radius: 7px; font: 500 14px/1.5 system-ui, sans-serif; }
        .custom-form-feedback[hidden] { display: none; }
        .custom-form-feedback.is-success { border-color: #86efac; background: #f0fdf4; color: #166534; }
        .custom-form-feedback.is-error { border-color: #fca5a5; background: #fef2f2; color: #991b1b; }
    </style>
    <?php if ($isPreview): ?>
    <style>
        #draft-preview-badge { position: fixed; z-index: 2147483647; right: 16px; bottom: 16px; padding: 9px 13px; border: 1px solid rgba(15, 23, 42, .16); border-radius: 999px; background: #fbbf24; color: #1f2937; box-shadow: 0 5px 18px rgba(15, 23, 42, .2); font: 800 11px/1.2 system-ui, sans-serif; letter-spacing: .04em; pointer-events: none; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let previewHtml = null;
            let previewCss = null;
            try {
                previewHtml = localStorage.getItem('grapesjs_preview_html');
                previewCss = localStorage.getItem('grapesjs_preview_css');
            } catch (_) {
                return;
            }

            if (previewHtml === null && previewCss === null) return;
            document.body.innerHTML = previewHtml ?? '';
            const pageStyle = document.getElementById('page-css');
            if (pageStyle) pageStyle.textContent = previewCss ?? '';

            const badge = document.createElement('div');
            badge.id = 'draft-preview-badge';
            badge.textContent = 'MODE PREVIEW (DRAFT)';
            document.body.appendChild(badge);
        });
    </script>
    <?php endif; ?>
</head>
<body>
<?= $html ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const languagePages = <?= json_encode($translationMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.documentElement.lang = <?= json_encode($activeLang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.querySelectorAll('.language-switcher').forEach(switcher => {
        const links = Array.from(switcher.querySelectorAll('a'));
        let available = 0;
        links.forEach(link => {
            let targetLang = link.dataset.lang || '';
            if (!targetLang) {
                try {
                    targetLang = new URL(link.href, window.location.href).searchParams.get('lang') || '';
                } catch (_) {}
            }
            if (!targetLang) {
                const label = link.textContent.toLowerCase();
                targetLang = /\ben\b|🇬🇧|english/.test(label) ? 'en' : (/\bid\b|🇮🇩|indonesia/.test(label) ? 'id' : '');
            }
            const targetSlug = languagePages[String(targetLang).toLowerCase()];
            if (!targetSlug) {
                link.hidden = true;
                return;
            }
            link.hidden = false;
            link.href = `index.php?page=${encodeURIComponent(targetSlug)}&lang=${encodeURIComponent(targetLang)}`;
            available++;
        });
        if (available < 2) switcher.hidden = true;
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const currentSlug = <?= json_encode($slug, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.querySelectorAll('form.custom-form').forEach(form => {
        const pageField = form.querySelector('[name="page_slug"]');
        if (pageField) pageField.value = currentSlug;

        const feedback = document.createElement('div');
        feedback.className = 'custom-form-feedback';
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        feedback.hidden = true;
        form.insertAdjacentElement('afterend', feedback);

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const submitButton = form.querySelector('[type="submit"]');
            const originalLabel = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Mengirim…';
            }
            feedback.hidden = true;
            feedback.className = 'custom-form-feedback';
            try {
                const formData = new FormData(form);
                formData.set('page_slug', currentSlug);
                const response = await fetch(form.action || 'api.php?action=submit_form', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Pesan gagal dikirim.');
                }
                form.reset();
                if (pageField) pageField.value = currentSlug;
                feedback.textContent = result.message || 'Pesan Anda berhasil terkirim!';
                feedback.classList.add('is-success');
            } catch (error) {
                feedback.textContent = error.message || 'Pesan gagal dikirim. Silakan coba kembali.';
                feedback.classList.add('is-error');
            } finally {
                feedback.hidden = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalLabel;
                }
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const containers = document.querySelectorAll('.blog-grid-container');
    if (!containers.length) return;

    const safeImageUrl = value => {
        if (!value) return '';
        try {
            const url = new URL(value, window.location.href);
            return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
        } catch (_) {
            return '';
        }
    };
    const showMessage = (wrapper, message) => {
        wrapper.replaceChildren();
        const notice = document.createElement('p');
        notice.className = 'blog-grid-message';
        notice.textContent = message;
        wrapper.appendChild(notice);
    };

    await Promise.all([...containers].map(async container => {
        const wrapper = container.querySelector('.blog-posts-wrapper') || container;
        const parsedLimit = Number.parseInt(container.dataset.postsLimit || '6', 10);
        const limit = Number.isFinite(parsedLimit) ? Math.min(20, Math.max(1, parsedLimit)) : 6;
        try {
            const response = await fetch(`api.php?action=get_latest_posts&limit=${limit}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || !result.success || !Array.isArray(result.data)) {
                throw new Error(result.message || 'Artikel gagal dimuat.');
            }
            if (!result.data.length) {
                showMessage(wrapper, 'Belum ada artikel yang dipublikasikan.');
                return;
            }

            wrapper.replaceChildren();
            result.data.forEach(post => {
                const card = document.createElement('article');
                card.className = 'blog-post-card';
                const media = document.createElement('div');
                media.className = 'blog-post-media';
                const imageUrl = safeImageUrl(post.featured_image);
                if (imageUrl) {
                    const image = document.createElement('img');
                    image.src = imageUrl;
                    image.alt = String(post.title || 'Gambar artikel');
                    image.loading = 'lazy';
                    image.onerror = () => image.remove();
                    media.appendChild(image);
                }

                const content = document.createElement('div');
                content.className = 'blog-post-body';
                const category = document.createElement('span');
                category.className = 'blog-post-category';
                category.textContent = String(post.category || 'Umum');
                const title = document.createElement('h3');
                title.className = 'blog-post-title';
                title.textContent = String(post.title || 'Artikel');
                const excerpt = document.createElement('p');
                excerpt.className = 'blog-post-excerpt';
                excerpt.textContent = String(post.excerpt || 'Baca artikel selengkapnya.');
                const link = document.createElement('a');
                link.className = 'blog-post-link';
                link.href = `post.php?slug=${encodeURIComponent(String(post.slug || ''))}`;
                link.textContent = 'Baca Selengkapnya →';
                content.append(category, title, excerpt, link);
                card.append(media, content);
                wrapper.appendChild(card);
            });
        } catch (_) {
            showMessage(wrapper, 'Artikel belum dapat dimuat. Silakan coba lagi nanti.');
        }
    }));
});
</script>
</body>
</html>
<?php
$renderedPage = ob_get_clean();
echo PageSpeedOptimizer::injectResourceHints($renderedPage === false ? '' : $renderedPage);
