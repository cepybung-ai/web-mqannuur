<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);
require_once __DIR__ . '/admin-layout.php';

$currentUser = authUser() ?: [];
$currentUsername = (string)($currentUser['username'] ?? '');
$csrfToken = pbAdminCsrfToken();

$pdo = null;
$errorMessage = '';
$editId = 0;
$form = [
    'username' => '',
    'name' => '',
    'role' => 'editor',
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
        $userId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;

        if ($action === 'delete') {
            if ($userId < 1) {
                throw new InvalidArgumentException('ID user tidak valid.');
            }
            $find = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
            $find->execute(['id' => $userId]);
            $target = $find->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User tidak ditemukan.');
            }
            if (strcasecmp((string)$target['username'], $currentUsername) === 0) {
                throw new InvalidArgumentException('Anda tidak dapat menghapus akun yang sedang digunakan.');
            }
            if (strtolower((string)$target['role']) === 'super_admin') {
                $admins = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = :role AND id <> :id');
                $admins->execute(['role' => 'super_admin', 'id' => $userId]);
                if ((int)$admins->fetchColumn() === 0) {
                    throw new InvalidArgumentException('Super Admin terakhir tidak dapat dihapus.');
                }
            }
            $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $delete->execute(['id' => $userId]);
            logActivity('delete', 'user', $userId, 'Username: ' . (string)$target['username'] . '; role: ' . (string)$target['role']);
            pbAdminFlash('success', 'User berhasil dihapus.');
            header('Location: admin-users.php', true, 303);
            exit;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Aksi user tidak dikenal.');
        }

        $form = [
            'username' => strtolower(trim(is_string($_POST['username'] ?? null) ? strip_tags($_POST['username']) : '')),
            'name' => trim(is_string($_POST['name'] ?? null) ? strip_tags($_POST['name']) : ''),
            'role' => strtolower(trim(is_string($_POST['role'] ?? null) ? $_POST['role'] : 'editor')),
        ];
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $usernameLength = preg_match_all('/./us', $form['username']);
        $nameLength = preg_match_all('/./us', $form['name']);

        if ($form['username'] === '' || $usernameLength === false || $usernameLength > 50 || preg_match('/^[a-z0-9._-]{3,50}$/', $form['username']) !== 1) {
            throw new InvalidArgumentException('Username wajib 3–50 karakter dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.');
        }
        if ($nameLength === false || $nameLength > 100) {
            throw new InvalidArgumentException('Nama maksimal 100 karakter.');
        }
        if (!in_array($form['role'], ['super_admin', 'editor'], true)) {
            throw new InvalidArgumentException('Peran user tidak valid.');
        }
        if ($action === 'create' && $password === '') {
            throw new InvalidArgumentException('Kata sandi wajib diisi untuk user baru.');
        }
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 1024)) {
            throw new InvalidArgumentException('Kata sandi harus 8–1024 karakter.');
        }

        if ($action === 'update') {
            if ($userId < 1) {
                throw new InvalidArgumentException('ID user tidak valid.');
            }
            $find = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
            $find->execute(['id' => $userId]);
            $target = $find->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User tidak ditemukan.');
            }
            if (strtolower((string)$target['role']) === 'super_admin' && $form['role'] !== 'super_admin') {
                $admins = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = :role AND id <> :id');
                $admins->execute(['role' => 'super_admin', 'id' => $userId]);
                if ((int)$admins->fetchColumn() === 0) {
                    throw new InvalidArgumentException('Super Admin terakhir tidak dapat diturunkan menjadi editor.');
                }
            }
            $duplicate = $pdo->prepare('SELECT 1 FROM users WHERE username = :username AND id <> :id LIMIT 1');
            $duplicate->execute(['username' => $form['username'], 'id' => $userId]);
            if ($duplicate->fetchColumn()) {
                throw new InvalidArgumentException('Username tersebut sudah digunakan.');
            }

            $name = $form['name'] !== '' ? $form['name'] : $form['username'];
            if ($password !== '') {
                $update = $pdo->prepare(
                    'UPDATE users SET username = :username, name = :name, role = :role, password = :password WHERE id = :id'
                );
                $update->execute([
                    'username' => $form['username'],
                    'name' => $name,
                    'role' => $form['role'],
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $userId,
                ]);
            } else {
                $update = $pdo->prepare('UPDATE users SET username = :username, name = :name, role = :role WHERE id = :id');
                $update->execute([
                    'username' => $form['username'],
                    'name' => $name,
                    'role' => $form['role'],
                    'id' => $userId,
                ]);
            }

            logActivity(
                'update',
                'user',
                $userId,
                'Username: ' . $form['username'] . '; role: ' . $form['role'] . '; password_changed: ' . ($password !== '' ? 'ya' : 'tidak')
            );
            if (strcasecmp((string)$target['username'], $currentUsername) === 0) {
                $_SESSION['admin_username'] = $form['username'];
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_role'] = $form['role'];
            }
            pbAdminFlash('success', 'User berhasil diperbarui.');
        } else {
            $duplicate = $pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
            $duplicate->execute(['username' => $form['username']]);
            if ($duplicate->fetchColumn()) {
                throw new InvalidArgumentException('Username tersebut sudah digunakan.');
            }
            $name = $form['name'] !== '' ? $form['name'] : $form['username'];
            $insert = $pdo->prepare(
                'INSERT INTO users (username, password, name, role) VALUES (:username, :password, :name, :role)'
            );
            $insert->execute([
                'username' => $form['username'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'role' => $form['role'],
            ]);
            logActivity('create', 'user', (int)$pdo->lastInsertId(), 'Username: ' . $form['username'] . '; role: ' . $form['role']);
            pbAdminFlash('success', 'User baru berhasil ditambahkan.');
        }

        header('Location: admin-users.php', true, 303);
        exit;
    }

    $requestedEdit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedEdit !== false && $requestedEdit > 0) {
        $editId = (int)$requestedEdit;
        $statement = $pdo->prepare('SELECT id, username, name, role FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $existing = $statement->fetch();
        if (!$existing) {
            pbAdminFlash('error', 'User yang ingin diedit tidak ditemukan.');
            header('Location: admin-users.php', true, 303);
            exit;
        }
        $form = [
            'username' => (string)$existing['username'],
            'name' => (string)($existing['name'] ?? ''),
            'role' => pbNormalizeRole($existing['role']),
        ];
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
    $postedId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $editId = $postedId !== false && $postedId > 0 ? (int)$postedId : 0;
} catch (Throwable $exception) {
    error_log('[Page Builder] User admin error: ' . $exception->getMessage());
    $errorMessage = 'User belum dapat diproses. Periksa koneksi database.';
}

$users = [];
$superAdminCount = 0;
$editorCount = 0;
try {
    $users = $pdo->query('SELECT id, username, name, role, created_at FROM users ORDER BY role ASC, username ASC')->fetchAll();
    foreach ($users as $user) {
        if (strtolower((string)$user['role']) === 'super_admin') {
            $superAdminCount++;
        } else {
            $editorCount++;
        }
    }
} catch (Throwable $exception) {
    error_log('[Page Builder] User list error: ' . $exception->getMessage());
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Daftar user belum dapat dimuat.';
}

$isEditing = $editId > 0;

pbAdminPageStart(
    'Kelola User',
    'users',
    'Atur akun Super Admin dan editor website.',
    <<<'CSS'
        .users-table { min-width: 720px; }
        .user-cell { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .user-avatar { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; color: #e0e7ff; background: linear-gradient(135deg, #4f46e5, #8b5cf6); border-radius: 50%; font-size: 12px; font-weight: 800; }
        .user-cell strong { display: block; overflow: hidden; color: var(--admin-ink); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .user-cell small { color: #94a3b8; font-size: 10px; }
        .user-counts { display: flex; gap: 7px; flex-wrap: wrap; }
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
                            <h2><?= $isEditing ? 'Edit User' : 'Tambah User Baru' ?></h2>
                            <p><?= $isEditing ? 'Perbarui akun, peran, atau kata sandi.' : 'Buat akun Super Admin atau editor.' ?></p>
                        </div>
                        <?php if ($isEditing): ?><a class="admin-btn small" href="admin-users.php"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Batal</a><?php endif; ?>
                    </div>
                    <div class="admin-card-body">
                        <form class="admin-form" method="post" action="admin-users.php">
                            <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $isEditing ? 'update' : 'create' ?>">
                            <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
                            <label class="admin-field" for="user-username"><span>Username <small>huruf kecil, angka, . _ -</small></span>
                                <input class="admin-input" id="user-username" name="username" type="text" maxlength="50" required value="<?= pbE($form['username']) ?>" placeholder="nama.pengguna" autocomplete="off">
                            </label>
                            <label class="admin-field" for="user-name"><span>Nama Tampilan</span>
                                <input class="admin-input" id="user-name" name="name" type="text" maxlength="100" value="<?= pbE($form['name']) ?>" placeholder="Nama lengkap">
                            </label>
                            <label class="admin-field" for="user-role"><span>Peran</span>
                                <select class="admin-select" id="user-role" name="role">
                                    <option value="editor"<?= $form['role'] === 'editor' ? ' selected' : '' ?>>Editor — kelola berita, agenda, staf, media, dan pesan</option>
                                    <option value="super_admin"<?= $form['role'] === 'super_admin' ? ' selected' : '' ?>>Super Admin — akses penuh</option>
                                </select>
                            </label>
                            <label class="admin-field" for="user-password"><span>Kata Sandi <small><?= $isEditing ? 'kosongkan jika tidak diubah' : 'minimal 8 karakter' ?></small></span>
                                <input class="admin-input" id="user-password" name="password" type="password" minlength="8" maxlength="1024"<?= $isEditing ? '' : ' required' ?> autocomplete="new-password" placeholder="<?= $isEditing ? '••••••••' : 'Minimal 8 karakter' ?>">
                            </label>
                            <div class="admin-form-actions">
                                <button class="admin-btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $isEditing ? 'Simpan Perubahan' : 'Tambah User' ?></button>
                                <?php if ($isEditing): ?><a class="admin-btn" href="admin-users.php">Batal</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <div><h2>Daftar User (<?= count($users) ?>)</h2><p>Akun yang dapat masuk ke central admin.</p></div>
                        <div class="user-counts">
                            <span class="admin-badge brand"><?= $superAdminCount ?> super admin</span>
                            <span class="admin-badge"><?= $editorCount ?> editor</span>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="admin-table users-table">
                            <thead><tr><th>User</th><th>Peran</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($users === []): ?>
                                <tr><td colspan="4"><div class="admin-empty"><i class="fa-solid fa-users" aria-hidden="true"></i>Belum ada user.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $isSelf = strcasecmp((string)$user['username'], $currentUsername) === 0;
                                    $role = strtolower((string)$user['role']);
                                    $displayName = trim((string)($user['name'] ?? '')) ?: (string)$user['username'];
                                    $initial = strtoupper(substr($displayName, 0, 1));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <span class="user-avatar" aria-hidden="true"><?= pbE($initial) ?></span>
                                                <span>
                                                    <strong><?= pbE($displayName) ?><?= $isSelf ? ' <span class="admin-badge success">Anda</span>' : '' ?></strong>
                                                    <small>@<?= pbE($user['username']) ?></small>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="admin-badge <?= $role === 'super_admin' ? 'brand' : '' ?>"><?= pbE(pbAdminRoleLabel($role)) ?></span></td>
                                        <td class="admin-muted"><?= pbE(date('d M Y', strtotime((string)$user['created_at']))) ?></td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="admin-btn small" href="admin-users.php?edit=<?= (int)$user['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
                                                <?php if (!$isSelf): ?>
                                                    <form method="post" action="admin-users.php" data-confirm="Hapus user ini?">
                                                        <input type="hidden" name="csrf_token" value="<?= pbE($csrfToken) ?>">
                                                        <input type="hidden" name="form_action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                        <button class="admin-btn small danger" type="submit"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Hapus</button>
                                                    </form>
                                                <?php endif; ?>
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
