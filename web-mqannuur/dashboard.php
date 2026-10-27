<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/includes/analytics.php';

$isAdmin = pbIsAdmin();
$stats = ['pages' => 0, 'posts' => 0, 'gallery' => 0, 'messages' => 0, 'events' => 0, 'staff' => 0, 'downloads' => 0];
$recentPosts = [];
$recentMessages = [];
$dashboardAnalytics = null;
$loadError = null;

function pbDashboardCount(PDO $pdo, string $table): int
{
    $allowed = ['pages', 'posts', 'gallery', 'form_submissions', 'events', 'staff', 'downloads', 'links', 'users'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }
    try {
        return (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function pbDashboardDate($value): string
{
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? (string)$value : date('d M Y · H:i', $timestamp);
}

function pbDashboardExcerpt($value, int $limit = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '');
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '…' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
}

try {
    $pdo = getDbConnection();
    foreach (array_keys($stats) as $key) {
        $stats[$key] = pbDashboardCount($pdo, $key === 'messages' ? 'form_submissions' : $key);
    }
    try {
        $recentPosts = $pdo->query('SELECT id, title, slug, category, status, created_at FROM posts ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
    } catch (Throwable $exception) {
        $recentPosts = [];
    }
    try {
        $recentMessages = $pdo->query('SELECT id, page_slug, form_data, sender_ip, created_at FROM form_submissions ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
    } catch (Throwable $exception) {
        $recentMessages = [];
    }
    if ($isAdmin) {
        try {
            $dashboardAnalytics = pbAnalyticsData($pdo, 7, 5, 1);
        } catch (Throwable $exception) {
            error_log('[Page Builder] Dashboard analytics error: ' . $exception->getMessage());
            $dashboardAnalytics = null;
        }
    }
} catch (Throwable $exception) {
    error_log('[Page Builder] Dashboard error: ' . $exception->getMessage());
    $loadError = 'Sebagian data dashboard belum dapat dimuat. Periksa koneksi database.';
}

pbAdminPageStart('Dashboard', 'dashboard', 'Ringkasan konten, media, dan aktivitas website.');
?>
            <?php if ($loadError !== null): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($loadError) ?></span></div>
            <?php endif; ?>

            <div class="admin-stat-grid">
                <?php if ($isAdmin): ?>
                    <a class="admin-stat-card" href="admin-pages.php">
                        <span class="admin-stat-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                        <span class="admin-stat-text"><span class="admin-stat-label">Total Halaman</span><span class="admin-stat-value"><?= $stats['pages'] ?></span><span class="admin-stat-meta">Halaman website</span></span>
                    </a>
                <?php endif; ?>
                <a class="admin-stat-card" href="admin-posts.php">
                    <span class="admin-stat-icon" style="color:#9a3412;background:#fff7ed;" aria-hidden="true"><i class="fa-solid fa-newspaper"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Berita</span><span class="admin-stat-value"><?= $stats['posts'] ?></span><span class="admin-stat-meta">Artikel &amp; kabar</span></span>
                </a>
                <a class="admin-stat-card" href="admin-gallery.php">
                    <span class="admin-stat-icon" style="color:#047857;background:#ecfdf5;" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Foto Galeri</span><span class="admin-stat-value"><?= $stats['gallery'] ?></span><span class="admin-stat-meta">Media terunggah</span></span>
                </a>
                <a class="admin-stat-card" href="admin-messages.php">
                    <span class="admin-stat-icon" style="color:#7e22ce;background:#faf5ff;" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Pesan Masuk</span><span class="admin-stat-value"><?= $stats['messages'] ?></span><span class="admin-stat-meta">Dari formulir website</span></span>
                </a>
            </div>

            <?php if ($isAdmin && is_array($dashboardAnalytics)): ?>
                <?php
                $analyticsSeries = $dashboardAnalytics['series'] ?? [];
                $analyticsTopPages = $dashboardAnalytics['top_pages'] ?? [];
                $weekViews = (int)($dashboardAnalytics['range']['views'] ?? 0);
                $weekVisitors = (int)($dashboardAnalytics['range']['visitors'] ?? 0);
                $todayViews = (int)($dashboardAnalytics['today']['views'] ?? 0);
                ?>
                <section class="admin-card admin-section admin-dashboard-analytics">
                    <div class="admin-card-head">
                        <div><h2>Analytics Kunjungan</h2><p>Ringkasan pengunjung website dalam 7 hari terakhir.</p></div>
                        <a class="admin-btn small" href="admin-analytics.php">Buka Analytics <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-card-body">
                        <div class="admin-dashboard-analytics-grid">
                            <div>
                                <div class="admin-dashboard-mini-stats">
                                    <div class="admin-dashboard-mini-stat"><span>Kunjungan hari ini</span><strong><?= $todayViews ?></strong></div>
                                    <div class="admin-dashboard-mini-stat"><span>Unique hari ini</span><strong><?= (int)($dashboardAnalytics['today']['visitors'] ?? 0) ?></strong></div>
                                    <div class="admin-dashboard-mini-stat"><span>7 hari</span><strong><?= $weekViews ?></strong><small><?= $weekVisitors ?> unique</small></div>
                                </div>
                                <?= pbAnalyticsChartSvg($analyticsSeries, 150) ?>
                            </div>
                            <div>
                                <div class="admin-chart-legend" style="margin-bottom:8px;"><span><i style="background:#6366f1;"></i>Kunjungan</span><span><i style="background:#10b981;"></i>Unique</span></div>
                                <div class="admin-dashboard-top-pages">
                                    <?php if ($analyticsTopPages === []): ?>
                                        <div class="admin-empty"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i>Belum ada kunjungan.</div>
                                    <?php else: ?>
                                        <?php foreach ($analyticsTopPages as $index => $page): ?>
                                            <div class="admin-dashboard-top-page">
                                                <span class="analytics-rank"><?= $index + 1 ?></span>
                                                <span class="admin-dashboard-top-page-body">
                                                    <strong><?= pbE((string)($page['page_title'] ?? '') ?: (string)$page['path']) ?></strong>
                                                    <small><?= pbE((string)$page['path']) ?></small>
                                                </span>
                                                <span class="admin-badge brand"><?= (int)$page['views'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-card admin-section">
                <div class="admin-card-head">
                    <div><h2><?= $isAdmin ? 'Customize Site' : 'Kelola Konten' ?></h2><p><?= $isAdmin ? 'Buka editor visual untuk menata halaman utama dan layout website.' : 'Kelola berita, agenda, media, dan dokumen website.' ?></p></div>
                    <?php if ($isAdmin): ?><a class="admin-btn primary" href="editor.php?slug=home"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Customize Site</a><?php endif; ?>
                </div>
                <div class="admin-card-body">
                    <div class="admin-quick-actions">
                        <?php if ($isAdmin): ?><a class="admin-btn" href="admin-pages.php"><i class="fa-solid fa-file-lines" aria-hidden="true"></i>Kelola Halaman</a><?php endif; ?>
                        <a class="admin-btn" href="admin-posts.php"><i class="fa-solid fa-plus" aria-hidden="true"></i>Tulis Berita</a>
                        <a class="admin-btn" href="admin-events.php"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>Tambah Agenda</a>
                        <a class="admin-btn" href="admin-gallery.php"><i class="fa-solid fa-image" aria-hidden="true"></i>Unggah Foto</a>
                        <a class="admin-btn" href="admin-downloads.php"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>Unggah Dokumen</a>
                    </div>
                </div>
            </section>

            <div class="admin-grid two">
                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Pesan Terbaru</h2><p>5 pesan terakhir dari formulir.</p></div>
                        <a class="admin-btn small" href="admin-messages.php">Semua Pesan <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-card-body">
                        <?php if ($recentMessages === []): ?>
                            <div class="admin-empty"><i class="fa-regular fa-envelope" aria-hidden="true"></i>Belum ada pesan masuk.</div>
                        <?php else: ?>
                            <div class="admin-activity">
                                <?php foreach ($recentMessages as $row):
                                    $data = json_decode((string)$row['form_data'], true);
                                    $data = is_array($data) ? $data : [];
                                    $name = trim((string)($data['name'] ?? '')) ?: 'Pengunjung';
                                    $message = (string)($data['message'] ?? '');
                                ?>
                                    <div class="admin-activity-row">
                                        <span class="admin-activity-dot" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
                                        <div class="admin-activity-body">
                                            <strong><?= pbE($name) ?> <span class="admin-badge brand"><?= pbE($row['page_slug']) ?></span></strong>
                                            <p><?= pbE(pbDashboardExcerpt($message, 110)) ?: 'Tanpa isi pesan.' ?></p>
                                            <small><?= pbE(pbDashboardDate($row['created_at'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Berita Terbaru</h2><p>5 artikel terakhir yang dikelola.</p></div>
                        <a class="admin-btn small" href="admin-posts.php">Kelola Berita <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-card-body">
                        <?php if ($recentPosts === []): ?>
                            <div class="admin-empty"><i class="fa-regular fa-newspaper" aria-hidden="true"></i>Belum ada berita. Tambahkan artikel pertama.</div>
                        <?php else: ?>
                            <div class="admin-activity">
                                <?php foreach ($recentPosts as $post): ?>
                                    <div class="admin-activity-row">
                                        <span class="admin-activity-dot" aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
                                        <div class="admin-activity-body">
                                            <strong><?= pbE($post['title']) ?></strong>
                                            <p><?= pbE($post['category']) ?> · <span class="admin-badge <?= $post['status'] === 'published' ? 'success' : 'warning' ?>"><?= pbE($post['status']) ?></span></p>
                                            <small><?= pbE(pbDashboardDate($post['created_at'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="admin-grid two" style="margin-top:16px;">
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Agenda Terdekat</h2><p>Ringkasan kegiatan sekolah.</p></div><a class="admin-btn small" href="admin-events.php">Kelola Agenda</a></div>
                    <div class="admin-card-body"><div class="admin-quick-actions"><span class="admin-badge"><?= $stats['events'] ?> agenda</span><span class="admin-badge"><?= $stats['staff'] ?> guru/staf</span><span class="admin-badge"><?= $stats['downloads'] ?> dokumen</span></div></div>
                </section>
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Akses Cepat</h2><p>Pintasan pengelolaan website.</p></div></div>
                    <div class="admin-card-body">
                        <div class="admin-quick-actions">
                            <a class="admin-btn" href="admin-staff.php"><i class="fa-solid fa-user-tie" aria-hidden="true"></i>Guru &amp; Staf</a>
                            <a class="admin-btn" href="index.php" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Situs</a>
                            <?php if ($isAdmin): ?>
                                <a class="admin-btn" href="admin-links.php"><i class="fa-solid fa-link" aria-hidden="true"></i>Link Menu</a>
                                <a class="admin-btn" href="admin-themes.php"><i class="fa-solid fa-palette" aria-hidden="true"></i>Tema Website</a>
                                <a class="admin-btn" href="admin-settings.php"><i class="fa-solid fa-gear" aria-hidden="true"></i>Identitas Situs</a>
                                <a class="admin-btn" href="admin-seo.php"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>SEO Manager</a>
                                <a class="admin-btn" href="admin-analytics.php"><i class="fa-solid fa-chart-line" aria-hidden="true"></i>Analytics</a>
                                <a class="admin-btn" href="admin-users.php"><i class="fa-solid fa-users" aria-hidden="true"></i>Kelola User</a>
                                <a class="admin-btn" href="site_backup.php"><i class="fa-solid fa-database" aria-hidden="true"></i>Backup</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
<?php pbAdminPageEnd(); ?>
