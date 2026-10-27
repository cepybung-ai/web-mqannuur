<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$messages = [];
$loadError = null;
try {
    $messages = getDbConnection()
        ->query('SELECT id, page_slug, form_data, sender_ip, created_at FROM form_submissions ORDER BY created_at DESC LIMIT 500')
        ->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin messages error: ' . $exception->getMessage());
    $loadError = 'Pesan belum dapat dimuat. Pastikan migrasi database form_submissions sudah dijalankan.';
}

$totalMessages = count($messages);
$uniquePages = count(array_unique(array_column($messages, 'page_slug')));
$latestMessage = $totalMessages > 0 ? (string)$messages[0]['created_at'] : '—';

pbAdminPageStart(
    'Pesan Masuk',
    'messages',
    'Pesan dari formulir halaman publik.',
    <<<'CSS'
        .msg-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 11px; margin-bottom: 16px; }
        .msg-stat { padding: 14px 15px; background: #fff; border: 1px solid var(--admin-line); border-radius: 13px; box-shadow: 0 8px 22px rgba(15, 23, 42, .045); }
        .msg-stat span { display: flex; align-items: center; gap: 6px; color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
        .msg-stat strong { display: block; margin-top: 4px; overflow: hidden; color: var(--admin-ink); font-size: 19px; letter-spacing: -.03em; text-overflow: ellipsis; white-space: nowrap; }
        .msg-table { min-width: 760px; }
        .msg-cell-time { color: var(--admin-ink-2); font-size: 11px; white-space: nowrap; }
        .msg-cell-time small { display: block; color: #94a3b8; font-size: 9px; }
        .msg-sender strong { display: block; color: var(--admin-ink); font-size: 12px; }
        .msg-sender a { color: #4f46e5; font-size: 10px; text-decoration: none; overflow-wrap: anywhere; }
        .msg-sender a:hover { text-decoration: underline; }
        .msg-body { max-width: 420px; color: #475569; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; }
        .msg-toast { position: fixed; right: 18px; bottom: 18px; z-index: 200; max-width: min(360px, calc(100% - 36px)); padding: 11px 14px; color: #fff; background: #0f172a; border-radius: 10px; box-shadow: 0 16px 36px rgba(15, 23, 42, .3); font-size: 12px; opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
        .msg-toast:not(:empty) { opacity: 1; transform: none; }
        .msg-toast[data-state="error"] { background: #b42318; }
        @media (max-width: 760px) {
            .msg-table { min-width: 0; }
            .msg-table thead { display: none; }
            .msg-table, .msg-table tbody, .msg-table tr, .msg-table td { display: block; width: 100%; }
            .msg-table tr { margin-bottom: 11px; padding: 13px 14px; background: #fff; border: 1px solid var(--admin-line); border-radius: 12px; }
            .msg-table tbody tr:hover { background: #fff; }
            .msg-table td { display: grid; grid-template-columns: 95px minmax(0, 1fr); gap: 9px; padding: 6px 0; border: 0; }
            .msg-table td::before { content: attr(data-label); color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .msg-body { max-width: none; }
            .msg-table .delete-message { width: 100%; }
        }
CSS
);
?>
            <?php if ($loadError !== null): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($loadError) ?></span></div>
            <?php endif; ?>

            <div class="msg-stats">
                <div class="msg-stat"><span><i class="fa-solid fa-inbox" aria-hidden="true"></i>Total pesan</span><strong id="msg-total-count"><?= $totalMessages ?></strong></div>
                <div class="msg-stat"><span><i class="fa-solid fa-file-lines" aria-hidden="true"></i>Halaman terkait</span><strong><?= $uniquePages ?></strong></div>
                <div class="msg-stat"><span><i class="fa-solid fa-clock" aria-hidden="true"></i>Pesan terbaru</span><strong style="font-size:13px;"><?= pbE($latestMessage) ?></strong></div>
            </div>

            <p id="message-status" class="msg-toast" role="status" aria-live="polite"></p>

            <?php if ($messages === []): ?>
                <div class="admin-card">
                    <div class="admin-card-body">
                        <div class="admin-empty"><i class="fa-regular fa-envelope" aria-hidden="true"></i>Belum ada pesan masuk. Pesan dari formulir akan tampil di sini.</div>
                    </div>
                </div>
            <?php else: ?>
                <section class="admin-card" id="messages-app" data-csrf="<?= pbE($csrfToken) ?>">
                    <div class="admin-card-head">
                        <div><h2>Daftar Pesan (<?= $totalMessages ?>)</h2><p>Maksimal 500 pesan terbaru ditampilkan.</p></div>
                        <a class="admin-btn" href="api.php?action=export_messages_csv"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>Export CSV</a>
                    </div>
                    <div class="table-wrap">
                        <table class="admin-table msg-table">
                            <thead><tr><th>Waktu</th><th>Halaman</th><th>Pengirim</th><th>Pesan</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($messages as $row):
                                $data = json_decode((string)$row['form_data'], true);
                                $data = is_array($data) ? $data : [];
                                $email = (string)($data['email'] ?? '');
                            ?>
                                <tr data-message-id="<?= (int)$row['id'] ?>">
                                    <td data-label="Waktu"><span class="msg-cell-time"><?= pbE($row['created_at']) ?><small><?= pbE($row['sender_ip']) ?></small></span></td>
                                    <td data-label="Halaman"><span class="admin-badge brand"><?= pbE($row['page_slug']) ?></span></td>
                                    <td data-label="Pengirim"><span class="msg-sender"><strong><?= pbE($data['name'] ?? '') ?></strong><?php if ($email !== ''): ?><a href="mailto:<?= pbE($email) ?>"><?= pbE($email) ?></a><?php endif; ?></span></td>
                                    <td data-label="Pesan" class="msg-body"><?= pbE($data['message'] ?? '') ?></td>
                                    <td data-label="Aksi">
                                        <button class="admin-btn small danger delete-message" type="button" data-id="<?= (int)$row['id'] ?>"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
<script>
(() => {
    const app = document.getElementById('messages-app');
    if (!app) return;
    const csrf = app.dataset.csrf || '';
    const status = document.getElementById('message-status');
    const total = document.getElementById('msg-total-count');
    document.querySelectorAll('.delete-message').forEach(button => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Hapus pesan ini?')) return;
            button.disabled = true;
            try {
                const response = await fetch('api.php?action=delete_message', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ id: Number(button.dataset.id) })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Pesan gagal dihapus.');
                const row = button.closest('tr');
                if (row) row.remove();
                if (total) total.textContent = String(Math.max(0, Number(total.textContent || '0') - 1));
                status.dataset.state = 'success';
                status.textContent = result.message;
                if (!document.querySelector('.delete-message')) {
                    window.setTimeout(() => window.location.reload(), 700);
                }
            } catch (error) {
                status.dataset.state = 'error';
                status.textContent = error.message || 'Pesan gagal dihapus.';
                button.disabled = false;
            }
        });
    });
})();
</script>
<?php pbAdminPageEnd(); ?>
