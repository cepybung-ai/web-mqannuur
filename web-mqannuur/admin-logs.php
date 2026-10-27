<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$pdo = null;
$loadError = '';
$logs = [];
$actions = [];
$totalLogs = 0;
$todayLogs = 0;
$todayFailures = 0;
$activeUsers = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$totalPages = 1;
$filteredTotal = 0;

$filters = [
    'q' => trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''),
    'action' => trim(is_string($_GET['action'] ?? null) ? $_GET['action'] : ''),
    'role' => trim(is_string($_GET['role'] ?? null) ? $_GET['role'] : ''),
    'from' => trim(is_string($_GET['from'] ?? null) ? $_GET['from'] : ''),
    'to' => trim(is_string($_GET['to'] ?? null) ? $_GET['to'] : ''),
];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
    $filters['from'] = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
    $filters['to'] = '';
}
if ($filters['role'] !== '' && !in_array($filters['role'], ['super_admin', 'editor', 'guest', 'system'], true)) {
    $filters['role'] = '';
}

try {
    $pdo = getDbConnection();
    $actions = $pdo->query('SELECT DISTINCT action FROM activity_logs WHERE action <> \'\' ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);
    $totalLogs = (int)$pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    $todayLogs = (int)$pdo->query('SELECT COUNT(*) FROM activity_logs WHERE created_at >= CURDATE()')->fetchColumn();
    $todayFailures = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND created_at >= CURDATE()")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(DISTINCT username) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND username <> '' AND username <> 'system'")->fetchColumn();

    $where = [];
    $params = [];
    if ($filters['q'] !== '') {
        $where[] = '(username LIKE :q OR action LIKE :q OR entity LIKE :q OR entity_id LIKE :q OR details LIKE :q OR ip LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }
    if ($filters['action'] !== '') {
        $where[] = 'action = :action';
        $params['action'] = $filters['action'];
    }
    if ($filters['role'] !== '') {
        $where[] = 'role = :role';
        $params['role'] = $filters['role'];
    }
    if ($filters['from'] !== '') {
        $where[] = 'created_at >= :date_from';
        $params['date_from'] = $filters['from'] . ' 00:00:00';
    }
    if ($filters['to'] !== '') {
        $where[] = 'created_at <= :date_to';
        $params['date_to'] = $filters['to'] . ' 23:59:59';
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM activity_logs' . $whereSql);
    $countStatement->execute($params);
    $filteredTotal = (int)$countStatement->fetchColumn();
    $totalPages = max(1, (int)ceil($filteredTotal / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $statement = $pdo->prepare(
        'SELECT id, user_id, username, role, action, entity, entity_id, details, ip, user_agent, created_at
         FROM activity_logs' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $logs = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Audit log page error: ' . $exception->getMessage());
    $loadError = 'Audit log belum dapat dimuat. Pastikan migrasi keamanan database sudah dijalankan.';
}

$actionLabels = [
    'create' => 'Tambah',
    'update' => 'Ubah',
    'delete' => 'Hapus',
    'toggle' => 'Ubah status',
    'upload' => 'Upload',
    'generate' => 'Generate',
    'export' => 'Ekspor',
    'login' => 'Login',
    'login_failed' => 'Login gagal',
    'logout' => 'Logout',
    'restore' => 'Restore',
    'download' => 'Unduh',
    'backup_create' => 'Backup ZIP',
    'schedule_update' => 'Jadwal backup',
];
$roleLabels = [
    'super_admin' => 'Super Admin',
    'editor' => 'Editor',
    'guest' => 'Tamu',
    'system' => 'Sistem',
];

$queryBase = $filters;
unset($queryBase['page']);
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    $queryBase['page'] = $targetPage;
    return 'admin-logs.php?' . http_build_query($queryBase);
};

pbAdminPageStart(
    'Audit Log',
    'logs',
    'Jejak aktivitas pengguna, keamanan login, dan perubahan data CMS.',
    <<<'CSS'
        .log-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 11px; margin-bottom: 16px; }
        .log-stat { padding: 14px 15px; background: #fff; border: 1px solid var(--admin-line); border-radius: 13px; box-shadow: 0 8px 22px rgba(15, 23, 42, .045); }
        .log-stat span { display: flex; align-items: center; gap: 6px; color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
        .log-stat strong { display: block; margin-top: 4px; color: var(--admin-ink); font-size: 21px; letter-spacing: -.03em; }
        .log-filters { display: grid; grid-template-columns: minmax(180px, 2fr) repeat(4, minmax(110px, 1fr)) auto; gap: 9px; align-items: end; }
        .log-table { min-width: 1050px; }
        .log-user strong { display: block; color: var(--admin-ink); font-size: 12px; }
        .log-user small, .log-entity small { display: block; margin-top: 2px; color: #94a3b8; font-size: 9px; }
        .log-time { color: var(--admin-ink-2); font-size: 11px; white-space: nowrap; }
        .log-time small { display: block; color: #94a3b8; font-size: 9px; }
        .log-detail { max-width: 430px; color: #475569; overflow-wrap: anywhere; font-size: 11px; }
        .log-ip { color: #64748b; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; white-space: nowrap; }
        .log-action { display: inline-flex; padding: 3px 8px; color: #334155; background: #f1f5f9; border-radius: 999px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .log-action.login, .log-action.create { color: #047857; background: #ecfdf5; }
        .log-action.login_failed, .log-action.delete, .log-action.restore { color: #b42318; background: #fef3f2; }
        .log-action.update, .log-action.toggle, .log-action.schedule_update { color: #1d4ed8; background: #eff6ff; }
        .log-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; color: var(--admin-muted); font-size: 11px; }
        .log-pagination nav { display: flex; gap: 6px; }
        @media (max-width: 900px) {
            .log-filters { grid-template-columns: 1fr 1fr; }
            .log-filters .wide { grid-column: 1 / -1; }
            .log-table { min-width: 0; }
            .log-table thead { display: none; }
            .log-table, .log-table tbody, .log-table tr, .log-table td { display: block; width: 100%; }
            .log-table tr { margin-bottom: 11px; padding: 13px 14px; background: #fff; border: 1px solid var(--admin-line); border-radius: 12px; }
            .log-table tbody tr:hover { background: #fff; }
            .log-table td { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 9px; padding: 6px 0; border: 0; }
            .log-table td::before { content: attr(data-label); color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .log-detail { max-width: none; }
        }
CSS
);
?>
            <?php if ($loadError !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($loadError) ?></span></div>
            <?php endif; ?>

            <div class="log-stats">
                <div class="log-stat"><span><i class="fa-solid fa-list-check" aria-hidden="true"></i>Total log</span><strong><?= $totalLogs ?></strong></div>
                <div class="log-stat"><span><i class="fa-solid fa-calendar-day" aria-hidden="true"></i>Aktivitas hari ini</span><strong><?= $todayLogs ?></strong></div>
                <div class="log-stat"><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Login gagal hari ini</span><strong><?= $todayFailures ?></strong></div>
                <div class="log-stat"><span><i class="fa-solid fa-users" aria-hidden="true"></i>User aktif 7 hari</span><strong><?= $activeUsers ?></strong></div>
            </div>

            <section class="admin-card">
                <div class="admin-card-head">
                    <div><h2>Riwayat Aktivitas</h2><p>Catatan audit tidak dapat diubah dari antarmuka ini.</p></div>
                    <span class="admin-badge brand"><?= count($logs) ?> ditampilkan</span>
                </div>
                <div class="admin-card-body">
                    <form class="log-filters" method="get" action="admin-logs.php">
                        <label class="admin-field wide"><span>Cari</span><input class="admin-input" type="search" name="q" value="<?= pbE($filters['q']) ?>" placeholder="Username, aksi, detail, IP..."></label>
                        <label class="admin-field"><span>Aksi</span>
                            <select class="admin-input" name="action">
                                <option value="">Semua</option>
                                <?php foreach ($actions as $actionOption): ?>
                                    <option value="<?= pbE($actionOption) ?>" <?= $filters['action'] === $actionOption ? 'selected' : '' ?>><?= pbE($actionLabels[$actionOption] ?? $actionOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="admin-field"><span>Role</span>
                            <select class="admin-input" name="role">
                                <option value="">Semua</option>
                                <?php foreach (['super_admin', 'editor', 'guest', 'system'] as $roleOption): ?>
                                    <option value="<?= pbE($roleOption) ?>" <?= $filters['role'] === $roleOption ? 'selected' : '' ?>><?= pbE($roleLabels[$roleOption]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="admin-field"><span>Dari</span><input class="admin-input" type="date" name="from" value="<?= pbE($filters['from']) ?>"></label>
                        <label class="admin-field"><span>Sampai</span><input class="admin-input" type="date" name="to" value="<?= pbE($filters['to']) ?>"></label>
                        <div class="admin-form-actions" style="margin:0; display:flex; gap:6px;">
                            <button class="admin-btn primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i>Filter</button>
                            <a class="admin-btn" href="admin-logs.php" title="Reset filter"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></a>
                        </div>
                    </form>
                </div>

                <?php if ($logs === [] && $loadError === ''): ?>
                    <div class="admin-card-body"><div class="admin-empty"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Tidak ada log yang cocok dengan filter.</div></div>
                <?php elseif ($logs !== []): ?>
                    <div class="table-wrap">
                        <table class="admin-table log-table">
                            <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Entitas</th><th>Detail</th><th>IP</th></tr></thead>
                            <tbody>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td data-label="Waktu"><span class="log-time"><?= pbE($row['created_at']) ?><small><?= pbE($row['user_agent']) ?></small></span></td>
                                    <td data-label="User"><span class="log-user"><strong><?= pbE($row['username']) ?></strong><small><?= pbE($roleLabels[$row['role']] ?? $row['role']) ?></small></span></td>
                                    <td data-label="Aksi"><span class="log-action <?= pbE($row['action']) ?>"><?= pbE($actionLabels[$row['action']] ?? $row['action']) ?></span></td>
                                    <td data-label="Entitas"><span class="log-entity"><?= pbE($row['entity'] ?: '—') ?><?php if ((string)$row['entity_id'] !== ''): ?><small><?= pbE($row['entity_id']) ?></small><?php endif; ?></span></td>
                                    <td data-label="Detail"><span class="log-detail"><?= pbE($row['details'] ?? '—') ?></span></td>
                                    <td data-label="IP"><span class="log-ip"><?= pbE($row['ip'] ?: '—') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-card-body">
                        <div class="log-pagination">
                            <span>Halaman <?= $page ?> dari <?= $totalPages ?> · <?= $filteredTotal ?> log</span>
                            <nav>
                                <?php if ($page > 1): ?><a class="admin-btn small" href="<?= pbE($pageUrl($page - 1)) ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>Sebelumnya</a><?php endif; ?>
                                <?php if ($page < $totalPages): ?><a class="admin-btn small" href="<?= pbE($pageUrl($page + 1)) ?>">Berikutnya<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
<?php pbAdminPageEnd(); ?>
