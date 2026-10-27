<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/includes/analytics.php';

$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}

$errorMessage = '';
$analytics = null;
try {
    $analytics = pbAnalyticsData(getDbConnection(), $days, 10, 30);
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin analytics error: ' . $exception->getMessage());
    $errorMessage = 'Data analytics belum dapat dimuat. Jalankan migrasi SEO dan Analytics.';
}

$todayViews = (int)($analytics['today']['views'] ?? 0);
$todayVisitors = (int)($analytics['today']['visitors'] ?? 0);
$rangeViews = (int)($analytics['range']['views'] ?? 0);
$rangeVisitors = (int)($analytics['range']['visitors'] ?? 0);
$averageViews = $analytics !== null ? (int)round($rangeViews / max(1, $days)) : 0;
$series = $analytics['series'] ?? [];
$topPages = $analytics['top_pages'] ?? [];
$recentVisits = $analytics['recent'] ?? [];

pbAdminPageStart('Analytics Dashboard', 'analytics', 'Pantau kunjungan, unique visitor, dan halaman terpopuler website.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-toolbar analytics-range" aria-label="Rentang analytics">
                <?php foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $rangeDays => $rangeLabel): ?>
                    <a class="admin-btn small<?= $days === $rangeDays ? ' primary' : '' ?>" href="admin-analytics.php?days=<?= $rangeDays ?>"><?= pbE($rangeLabel) ?></a>
                <?php endforeach; ?>
                <span class="admin-badge"><?= pbE(date('d M Y', strtotime('-'.($days - 1).' days')) . ' — ' . date('d M Y')) ?></span>
            </div>

            <div class="admin-stat-grid">
                <div class="admin-stat-card">
                    <span class="admin-stat-icon" style="color:#4f46e5;background:#eef2ff;" aria-hidden="true"><i class="fa-solid fa-eye"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Kunjungan Hari Ini</span><span class="admin-stat-value"><?= $todayViews ?></span><span class="admin-stat-meta">Pageview pengunjung</span></span>
                </div>
                <div class="admin-stat-card">
                    <span class="admin-stat-icon" style="color:#047857;background:#ecfdf5;" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Unique Hari Ini</span><span class="admin-stat-value"><?= $todayVisitors ?></span><span class="admin-stat-meta">Visitor unik per hari</span></span>
                </div>
                <div class="admin-stat-card">
                    <span class="admin-stat-icon" style="color:#9a3412;background:#fff7ed;" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Kunjungan <?= $days ?> Hari</span><span class="admin-stat-value"><?= $rangeViews ?></span><span class="admin-stat-meta">Rata-rata <?= $averageViews ?> per hari</span></span>
                </div>
                <div class="admin-stat-card">
                    <span class="admin-stat-icon" style="color:#7e22ce;background:#faf5ff;" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                    <span class="admin-stat-text"><span class="admin-stat-label">Unique <?= $days ?> Hari</span><span class="admin-stat-value"><?= $rangeVisitors ?></span><span class="admin-stat-meta">Visitor unik dalam periode</span></span>
                </div>
            </div>

            <section class="admin-card admin-section">
                <div class="admin-card-head">
                    <div><h2>Tren Kunjungan</h2><p>Pageview dan unique visitor per hari.</p></div>
                    <div class="admin-chart-legend"><span><i style="background:#6366f1;"></i>Kunjungan</span><span><i style="background:#10b981;"></i>Unique visitor</span></div>
                </div>
                <div class="admin-card-body">
                    <?= pbAnalyticsChartSvg($series, 230) ?>
                </div>
            </section>

            <section class="admin-card admin-section">
                <div class="admin-card-head">
                    <div><h2>Halaman Terpopuler</h2><p>10 halaman dengan kunjungan tertinggi dalam <?= $days ?> hari.</p></div>
                    <span class="admin-badge"><?= count($topPages) ?> halaman</span>
                </div>
                <?php if ($topPages === []): ?>
                    <div class="admin-card-body"><div class="admin-empty"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i>Belum ada kunjungan pada periode ini.</div></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table analytics-pages-table">
                            <thead><tr><th>#</th><th>Halaman</th><th>Kunjungan</th><th>Unique</th><th>Terakhir</th></tr></thead>
                            <tbody>
                            <?php foreach ($topPages as $index => $page): ?>
                                <tr>
                                    <td><span class="analytics-rank"><?= $index + 1 ?></span></td>
                                    <td>
                                        <strong class="cell-title"><?= pbE((string)($page['page_title'] ?? '') ?: (string)$page['path']) ?></strong>
                                        <span class="cell-sub"><?= pbE((string)$page['path']) ?></span>
                                    </td>
                                    <td><span class="admin-badge brand"><?= (int)$page['views'] ?></span></td>
                                    <td><?= (int)$page['visitors'] ?></td>
                                    <td class="admin-muted"><?= pbE(date('d M Y H:i', strtotime((string)$page['last_visit']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-section">
                <div class="admin-card-head">
                    <div><h2>Kunjungan Terbaru</h2><p>30 kunjungan terakhir beserta alamat IP pengunjung.</p></div>
                </div>
                <?php if ($recentVisits === []): ?>
                    <div class="admin-card-body"><div class="admin-empty"><i class="fa-regular fa-clock" aria-hidden="true"></i>Belum ada kunjungan tercatat.</div></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Waktu</th><th>Halaman</th><th>IP Pengunjung</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentVisits as $visit): ?>
                                <tr>
                                    <td class="admin-muted"><?= pbE(date('d M Y H:i:s', strtotime((string)$visit['created_at']))) ?></td>
                                    <td>
                                        <strong class="cell-title"><?= pbE((string)($visit['page_title'] ?? '') ?: (string)$visit['path']) ?></strong>
                                        <span class="cell-sub"><?= pbE((string)$visit['path']) ?></span>
                                    </td>
                                    <td><code class="admin-code"><?= pbE((string)($visit['visitor_ip'] ?? '') ?: '—') ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <div class="admin-tip"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Kunjungan dicatat otomatis dari halaman publik. Sesi admin, preview tema, respons error, dan permintaan non-GET tidak dihitung. Unique visitor memakai hash IP + user agent per hari.</span></div>
<?php pbAdminPageEnd(); ?>
