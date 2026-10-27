<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (empty($_SESSION['page_builder_csrf'])) {
    $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['page_builder_csrf'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <title>Pesan Masuk — Web MQ An-Nuur</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f3f6fb; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; padding: clamp(16px, 4vw, 42px); }
        .dashboard { width: min(1440px, 100%); margin: 0 auto; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
        .eyebrow { margin: 0 0 7px; color: #64748b; font-size: 12px; font-weight: 750; letter-spacing: .09em; text-transform: uppercase; }
        h1 { margin: 0; color: #101828; font-size: clamp(23px, 4vw, 34px); line-height: 1.18; }
        .back-link { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 8px; padding: 11px 15px; border: 1px solid #d7dfeb; border-radius: 10px; background: #fff; color: #26364d; font-size: 14px; font-weight: 700; text-decoration: none; box-shadow: 0 2px 8px rgba(15, 23, 42, .04); }
        .back-link:hover { background: #f8fafc; }
        .header-actions { display: flex; flex: 0 0 auto; gap: 9px; }
        .backup-link { display: inline-flex; align-items: center; justify-content: center; padding: 11px 15px; border-radius: 10px; background: #0f766e; color: #fff; font-size: 14px; font-weight: 750; text-decoration: none; white-space: nowrap; }
        .logout-link { display: inline-flex; align-items: center; justify-content: center; padding: 11px 15px; border-radius: 10px; background: #b42318; color: #fff; font-size: 14px; font-weight: 750; text-decoration: none; }
        .summary { display: grid; grid-template-columns: minmax(200px, 300px) 1fr; align-items: stretch; gap: 16px; margin-bottom: 18px; }
        .stat-card, .messages-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 8px 30px rgba(15, 23, 42, .045); }
        .stat-card { padding: 20px 22px; }
        .stat-label { display: block; color: #64748b; font-size: 13px; font-weight: 650; }
        .stat-number { display: block; margin-top: 9px; color: #0f766e; font-size: 36px; font-weight: 800; line-height: 1; }
        .messages-card { min-width: 0; overflow: hidden; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; border-bottom: 1px solid #e8edf4; }
        .toolbar h2 { margin: 0; font-size: 17px; }
        .toolbar-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 10px; }
        .search-wrap { position: relative; width: min(360px, 100%); }
        .search-wrap input { width: 100%; padding: 11px 14px 11px 38px; border: 1px solid #d7dfeb; border-radius: 10px; outline: none; background: #fff; color: #172033; font: inherit; font-size: 14px; }
        .search-wrap input:focus { border-color: #0f9f78; box-shadow: 0 0 0 3px rgba(15, 159, 120, .12); }
        .export-button { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: 8px; padding: 9px 13px; border: 0; border-radius: 9px; background: #0f9f78; color: #fff; font-size: 13px; font-weight: 750; text-decoration: none; white-space: nowrap; }
        .export-button:hover { background: #0b8062; }
        .search-icon { position: absolute; top: 50%; left: 13px; color: #8290a5; transform: translateY(-50%); pointer-events: none; }
        .message-status { min-height: 0; padding: 0 20px; color: #b42318; font-size: 13px; }
        .message-status:not(:empty) { padding-top: 12px; }
        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px 14px; background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; white-space: nowrap; }
        td { padding: 14px; border-top: 1px solid #edf1f6; color: #344054; font-size: 13px; vertical-align: top; }
        tbody tr:hover { background: #fbfdff; }
        .message-text { min-width: 210px; max-width: 380px; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.55; }
        .person-name { color: #172033; font-weight: 750; }
        .email-link { color: #2563eb; overflow-wrap: anywhere; text-decoration: none; }
        .slug-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: 11px; font-weight: 750; }
        .ip-text, .date-text { color: #64748b; white-space: nowrap; font-size: 12px; }
        .delete-button { padding: 8px 11px; border: 0; border-radius: 8px; background: #fee4e2; color: #b42318; font: inherit; font-size: 12px; font-weight: 750; cursor: pointer; }
        .delete-button:hover { background: #fecdca; }
        .delete-button:disabled { opacity: .55; cursor: wait; }
        .empty-state { padding: 54px 20px; color: #64748b; text-align: center; }
        .empty-icon { display: grid; width: 48px; height: 48px; place-items: center; margin: 0 auto 13px; border-radius: 14px; background: #f1f5f9; color: #64748b; font-size: 21px; }
        @media (max-width: 800px) {
            body { padding: 18px 12px; }
            .header { align-items: flex-start; }
            .summary { grid-template-columns: 1fr; }
            .stat-card { padding: 16px 18px; }
            .stat-number { font-size: 30px; }
        }
        @media (max-width: 600px) {
            .header { flex-direction: column; gap: 14px; }
            .header-actions { width: 100%; flex-direction: column; }
            .backup-link, .back-link, .logout-link { width: 100%; justify-content: center; }
            .toolbar { align-items: stretch; flex-direction: column; padding: 16px; }
            .toolbar-actions { align-items: stretch; flex-direction: column; }
            .search-wrap { width: 100%; }
            .export-button { width: 100%; }
            table, tbody, tr, td { display: block; width: 100%; }
            thead { display: none; }
            tbody { padding: 10px; }
            tbody tr { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; margin-bottom: 12px; padding: 8px 12px; border: 1px solid #e6ebf2; border-radius: 12px; background: #fff; }
            tbody tr:hover { background: #fff; }
            td { display: grid; grid-template-columns: minmax(74px, auto) minmax(0, 1fr); gap: 7px; align-items: start; padding: 9px 0; border: 0; font-size: 12px; }
            td::before { content: attr(data-label); color: #8290a5; font-size: 10px; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
            td[data-label="Isi Pesan"], td[data-label="Email Sender"], td[data-label="Waktu Kirim"] { grid-column: 1 / -1; }
            .message-text { min-width: 0; max-width: none; }
            .ip-text, .date-text { white-space: normal; }
            td[data-label="Aksi"] { align-items: center; }
        }
    </style>
</head>
<body>
<main class="dashboard">
    <header class="header">
        <div>
            <p class="eyebrow">Web MQ An-Nuur · CMS</p>
            <h1>Dashboard Pesan Masuk - Form Builder</h1>
        </div>
        <nav class="header-actions" aria-label="Navigasi admin">
            <a class="backup-link" href="editor.php?openBackup=1">Backup / Restore ZIP</a>
            <a class="back-link" href="editor.php"><span aria-hidden="true">←</span> Kembali ke Editor</a>
            <a class="logout-link" href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="summary" aria-label="Ringkasan pesan">
        <article class="stat-card">
            <span class="stat-label">Total Pesan</span>
            <strong id="total-messages" class="stat-number">—</strong>
        </article>
        <section class="messages-card" aria-labelledby="messages-title">
            <div class="toolbar">
                <h2 id="messages-title">Pesan Pengunjung</h2>
                <div class="toolbar-actions">
                    <label class="search-wrap">
                        <span class="search-icon" aria-hidden="true">⌕</span>
                        <input id="message-search" type="search" placeholder="Cari nama atau email…" autocomplete="off" aria-label="Cari pesan berdasarkan nama atau email">
                    </label>
                    <a id="btn-export-csv" class="export-button" href="api.php?action=export_messages_csv" download>
                        <span aria-hidden="true">⇩</span> Export ke CSV / Excel
                    </a>
                </div>
            </div>
            <p id="message-status" class="message-status" role="status" aria-live="polite"></p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>No</th><th>Waktu Kirim</th><th>Halaman Origin</th><th>Nama Lengkap</th><th>Email Sender</th><th>Isi Pesan</th><th>IP Address</th><th>Aksi</th></tr>
                    </thead>
                    <tbody id="messages-body"><tr><td colspan="8" class="empty-state">Memuat pesan…</td></tr></tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const body = document.getElementById('messages-body');
    const total = document.getElementById('total-messages');
    const search = document.getElementById('message-search');
    const status = document.getElementById('message-status');
    let messages = [];

    function formatDate(value) {
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('id-ID', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    }

    function addCell(row, label, value, className = '') {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        if (className) cell.className = className;
        if (value instanceof Node) cell.appendChild(value);
        else cell.textContent = String(value ?? '—');
        row.appendChild(cell);
        return cell;
    }

    function renderMessages() {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        const filtered = messages.filter(item => {
            const data = item.form_data || {};
            return `${data.name || ''} ${data.email || ''}`.toLocaleLowerCase('id-ID').includes(query);
        });
        total.textContent = String(messages.length);
        body.replaceChildren();

        if (!filtered.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'empty-state';
            cell.textContent = messages.length ? 'Tidak ada pesan yang cocok dengan pencarian.' : 'Belum ada data pesan masuk';
            row.appendChild(cell);
            body.appendChild(row);
            return;
        }

        filtered.forEach((item, index) => {
            const row = document.createElement('tr');
            const data = item.form_data || {};
            addCell(row, 'No', String(index + 1));
            const date = document.createElement('time');
            date.className = 'date-text';
            date.dateTime = String(item.created_at || '');
            date.textContent = formatDate(item.created_at || '');
            addCell(row, 'Waktu Kirim', date);
            const slug = document.createElement('span');
            slug.className = 'slug-pill';
            slug.textContent = String(item.page_slug || '—');
            addCell(row, 'Halaman Origin', slug);
            const name = document.createElement('span');
            name.className = 'person-name';
            name.textContent = String(data.name || '—');
            addCell(row, 'Nama Lengkap', name);
            const email = document.createElement('a');
            email.className = 'email-link';
            const emailValue = String(data.email || '');
            email.href = emailValue ? `mailto:${encodeURIComponent(emailValue)}` : '#';
            email.textContent = emailValue || '—';
            addCell(row, 'Email Sender', email);
            addCell(row, 'Isi Pesan', String(data.message || '—'), 'message-text');
            addCell(row, 'IP Address', String(item.sender_ip || '—'), 'ip-text');

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'delete-button';
            deleteButton.dataset.messageId = String(item.id);
            deleteButton.textContent = 'Hapus';
            addCell(row, 'Aksi', deleteButton);
            body.appendChild(row);
        });
    }

    async function loadMessages() {
        status.textContent = 'Memuat daftar pesan…';
        try {
            const response = await fetch('api.php?action=get_messages', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || !result.success || !Array.isArray(result.data)) {
                throw new Error(result.message || 'Daftar pesan gagal dimuat.');
            }
            messages = result.data;
            status.textContent = '';
            renderMessages();
        } catch (error) {
            status.textContent = error.message || 'Daftar pesan gagal dimuat.';
            body.replaceChildren();
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'empty-state';
            cell.textContent = 'Pesan tidak dapat dimuat. Periksa koneksi database lalu muat ulang halaman.';
            row.appendChild(cell);
            body.appendChild(row);
        }
    }

    search.addEventListener('input', renderMessages);
    body.addEventListener('click', async event => {
        const button = event.target.closest('[data-message-id]');
        if (!button) return;
        const id = Number(button.dataset.messageId);
        if (!Number.isSafeInteger(id) || id < 1 || !confirm('Hapus pesan ini secara permanen?')) return;

        button.disabled = true;
        status.textContent = 'Menghapus pesan…';
        try {
            const response = await fetch('api.php?action=delete_message', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Pesan gagal dihapus.');
            messages = messages.filter(item => Number(item.id) !== id);
            status.textContent = result.message || 'Pesan berhasil dihapus.';
            renderMessages();
        } catch (error) {
            status.textContent = error.message || 'Pesan gagal dihapus.';
            button.disabled = false;
        }
    });

    loadMessages();
})();
</script>
</body>
</html>
