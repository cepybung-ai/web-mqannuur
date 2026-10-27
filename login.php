<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
header('Cache-Control: no-store, private');

if (($_SESSION['admin_logged_in'] ?? null) === true) {
    header('Location: editor.php', true, 302);
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['login_csrf'];
$errorMessage = '';
$usernameValue = '';
$dummyHashProvider = static function (): string {
    static $hash = null;
    if ($hash === null) {
        $hash = password_hash('login-timing-placeholder', PASSWORD_DEFAULT);
    }
    return $hash;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $usernameValue = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $sentToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($usernameValue === '' || strlen($usernameValue) > 50 || $password === '' || strlen($password) > 1024) {
        $errorMessage = 'Username atau password salah!';
    } else {
        try {
            $pdo = pbDbConnection();
            $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
                $clientIp = 'unknown';
            }
            // Rate-limit by source IP so trying many usernames cannot evade the lockout.
            $attemptKey = hash('sha256', $clientIp);
            $attemptQuery = $pdo->prepare(
                'SELECT attempts, window_started_at, locked_until,
                        TIMESTAMPDIFF(SECOND, window_started_at, NOW()) AS window_age,
                        TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS lock_seconds,
                        NOW() AS database_now
                 FROM login_attempts WHERE attempt_key = :attempt_key LIMIT 1'
            );
            $attemptQuery->execute(['attempt_key' => $attemptKey]);
            $attemptState = $attemptQuery->fetch();

            if ($attemptState && (int)($attemptState['lock_seconds'] ?? 0) > 0) {
                $errorMessage = 'Username atau password salah!';
            } else {
                $userQuery = $pdo->prepare('SELECT id, username, password, name FROM users WHERE username = :username LIMIT 1');
                $userQuery->execute(['username' => $usernameValue]);
                $user = $userQuery->fetch();

                // Verify a dummy hash for unknown users to reduce username timing leaks.
                $passwordIsValid = password_verify($password, $user ? (string)$user['password'] : $dummyHashProvider());

                if ($user && $passwordIsValid) {
                    $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key')->execute(['attempt_key' => $attemptKey]);
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = (string)$user['username'];
                    $_SESSION['admin_name'] = (string)($user['name'] ?? $user['username']);
                    $_SESSION['admin_user_id'] = (int)$user['id'];
                    $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
                    unset($_SESSION['login_csrf']);
                    header('Location: editor.php', true, 303);
                    exit;
                }

                $withinWindow = $attemptState && (int)$attemptState['window_age'] <= 900;
                $attempts = $withinWindow ? (int)$attemptState['attempts'] + 1 : 1;
                $windowStartedAt = $withinWindow
                    ? (string)$attemptState['window_started_at']
                    : (string)($attemptState['database_now'] ?? $pdo->query('SELECT NOW()')->fetchColumn());
                $upsertAttempt = $pdo->prepare(
                    'INSERT INTO login_attempts (attempt_key, attempts, window_started_at, locked_until)
                     VALUES (:attempt_key, :attempts, :window_started_at,
                             IF(:should_lock = 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL))
                     ON DUPLICATE KEY UPDATE attempts = VALUES(attempts),
                         window_started_at = VALUES(window_started_at), locked_until = VALUES(locked_until)'
                );
                $upsertAttempt->execute([
                    'attempt_key' => $attemptKey,
                    'attempts' => $attempts,
                    'window_started_at' => $windowStartedAt,
                    'should_lock' => $attempts >= 5 ? 1 : 0
                ]);
                $errorMessage = 'Username atau password salah!';
            }
        } catch (Throwable $exception) {
            error_log('[Page Builder] Login error: ' . $exception->getMessage());
            $errorMessage = 'Login belum dapat diproses. Periksa koneksi database.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — Web MQ An-Nuur</title>
    <style>
        * { box-sizing: border-box; }
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #eef4f1; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 22px; background: radial-gradient(circle at 15% 10%, #d9f2e7, transparent 34%), linear-gradient(140deg, #f7faf9, #eaf1f8); }
        .login-card { width: min(430px, 100%); padding: clamp(25px, 6vw, 40px); border: 1px solid rgba(203, 213, 225, .8); border-radius: 20px; background: rgba(255, 255, 255, .96); box-shadow: 0 24px 70px rgba(15, 45, 38, .12); }
        .brand-mark { display: grid; width: 48px; height: 48px; place-items: center; margin-bottom: 22px; border-radius: 15px; background: #0f766e; color: #fff; font-size: 20px; font-weight: 850; }
        .eyebrow { margin: 0 0 8px; color: #0f766e; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; color: #102a27; font-size: 28px; letter-spacing: -.03em; }
        .intro { margin: 9px 0 25px; color: #64748b; font-size: 14px; line-height: 1.6; }
        .field { display: grid; gap: 7px; margin: 16px 0; }
        .field label { color: #344054; font-size: 13px; font-weight: 750; }
        .field input { width: 100%; padding: 12px 13px; border: 1px solid #d0d9e5; border-radius: 9px; outline: none; background: #fff; color: #172033; font: inherit; font-size: 15px; }
        .field input:focus { border-color: #0f9f78; box-shadow: 0 0 0 3px rgba(15, 159, 120, .13); }
        .error { margin: 0 0 15px; padding: 11px 12px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 13px; line-height: 1.5; }
        .submit-button { width: 100%; margin-top: 7px; padding: 13px 16px; border: 0; border-radius: 9px; background: #0f766e; color: #fff; font: inherit; font-size: 14px; font-weight: 800; cursor: pointer; }
        .submit-button:hover { background: #0b625c; }
        .footer { margin: 22px 0 0; color: #8290a5; font-size: 11px; text-align: center; }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="brand-mark" aria-hidden="true">MQ</div>
        <p class="eyebrow">Web MQ An-Nuur · CMS</p>
        <h1>Login Admin</h1>
        <p class="intro">Masuk untuk mengelola halaman website dan pesan pengunjung.</p>
        <?php if ($errorMessage !== ''): ?>
            <p class="error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="50" required autocomplete="username" value="<?= htmlspecialchars($usernameValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" maxlength="1024" required autocomplete="current-password">
            </div>
            <button class="submit-button" type="submit">Masuk ke Dashboard</button>
        </form>
        <p class="footer">Akses hanya untuk administrator yang berwenang.</p>
    </main>
</body>
</html>
