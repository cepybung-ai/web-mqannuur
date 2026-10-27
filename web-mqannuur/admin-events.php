<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin', 'editor']);
require_once __DIR__ . '/admin-layout.php';

$csrfToken = pbAdminCsrfToken();
$pdo = null;
$errorMessage = '';
$editId = 0;
$form = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'location' => '',
    'event_date' => date('Y-m-d'),
    'event_end_date' => '',
    'image' => '',
    'status' => 'published',
];
$events = [];

function pbEventValidDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

try {
    $pdo = getDbConnection();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = is_string($_POST['form_action'] ?? null) ? $_POST['form_action'] : '';
        $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $eventId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($eventId < 1) {
                throw new InvalidArgumentException('ID agenda tidak valid.');
            }
            $delete = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $delete->execute(['id' => $eventId]);
            if ($delete->rowCount() === 0) {
                throw new InvalidArgumentException('Agenda tidak ditemukan.');
            }
            logActivity('delete', 'event', $eventId, 'Agenda dihapus');
            pbAdminFlash('success', 'Agenda berhasil dihapus.');
            header('Location: admin-events.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi agenda tidak dikenal.');
        }

        $form = [
            'title' => trim(is_string($_POST['title'] ?? null) ? strip_tags($_POST['title']) : ''),
            'slug' => strtolower(trim(is_string($_POST['slug'] ?? null) ? strip_tags($_POST['slug']) : '')),
            'excerpt' => trim(is_string($_POST['excerpt'] ?? null) ? strip_tags($_POST['excerpt']) : ''),
            'content' => is_string($_POST['content'] ?? null) ? pbSanitizeHtml($_POST['content']) : '',
            'location' => trim(is_string($_POST['location'] ?? null) ? strip_tags($_POST['location']) : ''),
            'event_date' => trim(is_string($_POST['event_date'] ?? null) ? $_POST['event_date'] : ''),
            'event_end_date' => trim(is_string($_POST['event_end_date'] ?? null) ? $_POST['event_end_date'] : ''),
            'image' => trim(is_string($_POST['image'] ?? null) ? $_POST['image'] : ''),
            'status' => (string)($_POST['status'] ?? 'published'),
        ];
        if ($form['title'] === '' || preg_match_all('/./us', $form['title']) > 255) {
            throw new InvalidArgumentException('Judul agenda wajib diisi dan maksimal 255 karakter.');
        }
        if ($form['slug'] !== '' && !pbValidSlug($form['slug'])) {
            throw new InvalidArgumentException('Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.');
        }
        if (!pbEventValidDate($form['event_date'])) {
            throw new InvalidArgumentException('Tanggal agenda tidak valid.');
        }
        if ($form['event_end_date'] !== '') {
            if (!pbEventValidDate($form['event_end_date'])) {
                throw new InvalidArgumentException('Tanggal berakhir tidak valid.');
            }
            if ($form['event_end_date'] < $form['event_date']) {
                throw new InvalidArgumentException('Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.');
            }
        }
        if ($form['image'] !== '') {
            $safeImage = pbSafeUrl($form['image']);
            if ($safeImage === null || preg_match('~^(?:mailto:|tel:)~i', $safeImage)) {
                throw new InvalidArgumentException('URL gambar tidak valid.');
            }
            $form['image'] = $safeImage;
        }
        if (!in_array($form['status'], ['draft', 'published'], true)) {
            $form['status'] = 'published';
        }
        $base = $form['slug'] !== '' ? $form['slug'] : pbAdminSlugBase($form['title'], 'agenda');
        $slug = pbAdminUniqueSlug($pdo, 'events', $base, $action === 'update' ? $eventId : 0);

        $values = [
            'title' => $form['title'],
            'slug' => $slug,
            'excerpt' => $form['excerpt'],
            'content' => $form['content'],
            'location' => $form['location'],
            'event_date' => $form['event_date'],
            'event_end_date' => $form['event_end_date'] !== '' ? $form['event_end_date'] : null,
            'image' => $form['image'] !== '' ? $form['image'] : null,
            'status' => $form['status'],
        ];

        if ($action === 'create') {
            $insert = $pdo->prepare('INSERT INTO events (title, slug, excerpt, content, location, event_date, event_end_date, image, status) VALUES (:title, :slug, :excerpt, :content, :location, :event_date, :event_end_date, :image, :status)');
            $insert->execute($values);
            logActivity('create', 'event', (int)$pdo->lastInsertId(), 'Judul: ' . $form['title'] . '; slug: ' . $slug);
            pbAdminFlash('success', 'Agenda berhasil ditambahkan.');
            header('Location: admin-events.php', true, 303);
            exit;
        }
        if ($eventId < 1) {
            throw new InvalidArgumentException('ID agenda tidak valid.');
        }
        $values['id'] = $eventId;
        $update = $pdo->prepare('UPDATE events SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, location = :location, event_date = :event_date, event_end_date = :event_end_date, image = :image, status = :status WHERE id = :id');
        $update->execute($values);
        logActivity('update', 'event', $eventId, 'Judul: ' . $form['title'] . '; slug: ' . $slug);
        pbAdminFlash('success', 'Agenda berhasil diperbarui.');
        header('Location: admin-events.php', true, 303);
        exit;
    }

    $requestedId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedId !== false && $requestedId > 0) {
        $editId = (int)$requestedId;
        $find = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $find->execute(['id' => $editId]);
        $row = $find->fetch();
        if ($row) {
            foreach ($form as $key => $value) {
                $form[$key] = (string)($row[$key] ?? '');
            }
        } else {
            $editId = 0;
            $errorMessage = 'Agenda yang ingin diedit tidak ditemukan.';
        }
    }

    $events = $pdo->query('SELECT id, title, slug, location, event_date, event_end_date, status FROM events ORDER BY event_date DESC, id DESC')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Page Builder] Admin events error: ' . $exception->getMessage());
    $errorMessage = 'Modul agenda belum dapat diproses. Periksa koneksi database.';
}

pbAdminPageStart('Agenda & Pengumuman', 'events', 'Kelola pengumuman dan agenda kegiatan sekolah.');
?>
            <?php if ($errorMessage !== ''): ?>
                <div class="admin-alert error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= pbE($errorMessage) ?></span></div>
            <?php endif; ?>

            <div class="admin-grid sidebar">
                <section class="admin-card">
                    <div class="admin-card-head"><div><h2><?= $editId > 0 ? 'Edit Agenda' : 'Tambah Agenda' ?></h2><p>Jadwal, lokasi, dan deskripsi kegiatan.</p></div></div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-events.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field"><span>Judul Agenda</span><input class="admin-input" type="text" name="title" maxlength="255" required value="<?= pbE($form['title']) ?>" placeholder="Contoh: Peringatan Maulid Nabi"></label>
                            <label class="admin-field"><span>Slug <small>Opsional</small></span><input class="admin-input" type="text" name="slug" maxlength="255" value="<?= pbE($form['slug']) ?>" placeholder="maulid-nabi"></label>
                            <div class="admin-form-row">
                                <label class="admin-field"><span>Tanggal Mulai</span><input class="admin-input" type="date" name="event_date" required value="<?= pbE($form['event_date']) ?>"></label>
                                <label class="admin-field"><span>Tanggal Berakhir <small>Opsional</small></span><input class="admin-input" type="date" name="event_end_date" value="<?= pbE($form['event_end_date']) ?>"></label>
                            </div>
                            <label class="admin-field"><span>Lokasi</span><input class="admin-input" type="text" name="location" maxlength="255" value="<?= pbE($form['location']) ?>" placeholder="Aula Madrasah"></label>
                            <label class="admin-field"><span>Ringkasan</span><textarea class="admin-textarea" name="excerpt" rows="3" maxlength="500" placeholder="Ringkasan singkat agenda"><?= pbE($form['excerpt']) ?></textarea></label>
                            <label class="admin-field"><span>Deskripsi</span><textarea class="admin-textarea" name="content" rows="7" placeholder="Detail kegiatan, peserta, dan informasi lain"><?= pbE($form['content']) ?></textarea></label>
                            <label class="admin-field"><span>Gambar <small>URL</small></span><input class="admin-input" type="text" name="image" maxlength="500" value="<?= pbE($form['image']) ?>" placeholder="uploads/gallery/foto.webp"><small class="admin-form-hint">Gunakan URL gambar atau salin link dari Galeri Media.</small></label>
                            <label class="admin-field"><span>Status</span><select class="admin-select" name="status"><option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Draft</option></select></label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $editId > 0 ? 'Simpan Perubahan' : 'Tambah Agenda' ?></button>
                                <?php if ($editId > 0): ?><a class="admin-btn" href="admin-events.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head"><div><h2>Daftar Agenda (<?= count($events) ?>)</h2><p>Urut berdasarkan tanggal kegiatan terbaru.</p></div><a class="admin-btn" href="index.php#agenda" target="_blank" rel="noopener"><i class="fa-solid fa-eye" aria-hidden="true"></i>Lihat Publik</a></div>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Agenda</th><th>Tanggal</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($events === []): ?>
                                <tr><td colspan="5"><div class="admin-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Belum ada agenda. Tambahkan kegiatan pertama.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <?php $dateLabel = date('d M Y', strtotime((string)$event['event_date'])); if (!empty($event['event_end_date'])) { $dateLabel .= ' – ' . date('d M Y', strtotime((string)$event['event_end_date'])); } ?>
                                    <tr>
                                        <td><div class="cell-title"><?= pbE($event['title']) ?></div><div class="cell-sub"><?= pbE($event['slug']) ?></div></td>
                                        <td class="admin-muted"><?= pbE($dateLabel) ?></td>
                                        <td class="admin-muted"><?= pbE($event['location']) ?: '—' ?></td>
                                        <td><span class="admin-badge <?= $event['status'] === 'published' ? 'success' : 'warning' ?>"><?= pbE($event['status']) ?></span></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-events.php?edit=<?= (int)$event['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <form class="admin-inline-form" method="post" action="admin-events.php" data-confirm="Hapus agenda ini?">
                                                    <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                                                    <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
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
