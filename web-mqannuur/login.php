<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

if (($_SESSION['admin_logged_in'] ?? false) === true) {
    header('Location: ' . pbBasePath() . '/dashboard.php');
    exit;
}
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$e = null;
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $sessionToken = $_SESSION['login_csrf'] ?? '';
    $ip = pbClientIp();

    try {
        $pdo = getDbConnection();
        $failures = pbLoginFailureCount($pdo, $ip);

        if ($failures >= 5) {
            $e = 'Terlalu banyak percobaan login. Coba lagi setelah 15 menit.';
        } elseif ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
            $e = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
        } elseif ($username === '' || strlen($username) > 50 || $password === '' || strlen($password) > 1024) {
            $e = 'Username atau password salah!';
            pbRecordLoginFailure($pdo, $ip, $username);
            logActivity('login_failed', 'auth', null, 'Kredensial tidak lengkap', $username, 'guest');
        } else {
            $query = $pdo->prepare('SELECT id, username, password, name, role FROM users WHERE username = :username LIMIT 1');
            $query->execute(['username' => $username]);
            $user = $query->fetch();
            $storedPassword = $user['password'] ?? null;
            if (!$user) {
                $legacyQuery = $pdo->prepare(
                    'SELECT id, username, password_hash AS password, role FROM page_builder_users WHERE username = :username LIMIT 1'
                );
                $legacyQuery->execute(['username' => $username]);
                $legacyUser = $legacyQuery->fetch();
                if ($legacyUser && strtoupper((string)$legacyUser['role']) === 'OPERATOR') {
                    $user = $legacyUser;
                    $storedPassword = $legacyUser['password'];
                }
            }
            $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            $passwordMatches = password_verify($password, $storedPassword ?? $dummyHash);

            if ($user && $passwordMatches) {
                pbClearLoginFailures($pdo, $ip);
                session_regenerate_id(true);
                $_SESSION = [
                    'admin_logged_in' => true,
                    'admin_id' => (int)($user['id'] ?? 0),
                    'admin_username' => $user['username'],
                    'admin_name' => (string)($user['name'] ?? ''),
                    'admin_role' => pbNormalizeRole($user['role'] ?? 'editor'),
                ];
                logActivity(
                    'login',
                    'auth',
                    (int)($user['id'] ?? 0),
                    'Login berhasil',
                    (string)$user['username'],
                    pbNormalizeRole($user['role'] ?? 'editor')
                );
                header('Location: ' . pbBasePath() . '/dashboard.php');
                exit;
            }

            pbRecordLoginFailure($pdo, $ip, $username);
            logActivity('login_failed', 'auth', null, 'Username atau password salah', $username, 'guest');
            $e = 'Username atau password salah!';
        }
    } catch (Throwable $exception) {
        error_log('[Page Builder] Login error: ' . $exception->getMessage());
        $e = 'Login belum tersedia. Pastikan database dan tabel autentikasi sudah disiapkan.';
    }

    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#073b32">
    <title>Masuk — Web MQ An-Nuur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            font-family: "Plus Jakarta Sans", sans-serif;
            color: #17352f;
            background: #f3f7f5;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }
        * { box-sizing: border-box; }
        body {
            min-width: 320px;
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            background:
                radial-gradient(ellipse at 12% 8%, rgba(193, 226, 211, .55), transparent 35%),
                radial-gradient(ellipse at 92% 90%, rgba(218, 234, 223, .7), transparent 32%),
                #f3f7f5;
        }
        .login-shell { width: min(100%, 440px); }
        .brand {
            margin: 0 0 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .brand-mark {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            color: #fff;
            background: #0c5949;
            border-radius: 15px;
            box-shadow: 0 8px 18px rgba(12, 89, 73, .2);
        }
        .brand-mark svg { width: 25px; height: 25px; }
        .brand-name { margin: 0; font-size: 17px; font-weight: 800; letter-spacing: -.035em; }
        .brand-caption { margin: 3px 0 0; color: #71837d; font-size: 11px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase; }
        .card {
            padding: 38px 40px 32px;
            background: rgba(255, 255, 255, .96);
            border: 1px solid #e4ece7;
            border-radius: 22px;
            box-shadow: 0 22px 60px rgba(31, 65, 51, .09), 0 2px 8px rgba(31, 65, 51, .04);
        }
        .card h1 { margin: 0; color: #17352f; font-size: 25px; font-weight: 800; letter-spacing: -.045em; }
        .intro { margin: 8px 0 28px; color: #74847e; font-size: 14px; line-height: 1.6; }
        .field { margin-top: 18px; }
        label { display: block; margin-bottom: 8px; color: #344d45; font-size: 12px; font-weight: 700; }
        .input-wrap { position: relative; }
        .input-wrap svg {
            position: absolute;
            top: 50%;
            left: 14px;
            width: 18px;
            height: 18px;
            color: #82928b;
            transform: translateY(-50%);
            pointer-events: none;
        }
        input {
            width: 100%;
            height: 50px;
            padding: 0 14px 0 43px;
            color: #17352f;
            background: #fbfcfb;
            border: 1px solid #dce6e0;
            border-radius: 11px;
            outline: none;
            font: inherit;
            font-size: 14px;
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        input::placeholder { color: #a3afa9; }
        input:focus { background: #fff; border-color: #168265; box-shadow: 0 0 0 4px rgba(22, 130, 101, .12); }
        .error {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin: 0 0 20px;
            padding: 12px 13px;
            color: #9d3030;
            background: #fff4f2;
            border: 1px solid #f2d5d1;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.55;
        }
        .error svg { width: 17px; height: 17px; flex: 0 0 auto; margin-top: 1px; }
        .submit {
            width: 100%;
            min-height: 50px;
            margin-top: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            color: #fff;
            background: #0c7258;
            border: 0;
            border-radius: 11px;
            box-shadow: 0 7px 14px rgba(12, 114, 88, .18);
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            transition: background .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .submit:hover { background: #095e49; box-shadow: 0 9px 18px rgba(12, 114, 88, .23); transform: translateY(-1px); }
        .submit:focus-visible { outline: 3px solid rgba(22, 130, 101, .35); outline-offset: 3px; }
        .submit svg { width: 17px; height: 17px; }
        .card-footer { margin: 24px 0 0; color: #8a9992; text-align: center; font-size: 11px; }
        @media (max-width: 480px) {
            body { padding: 24px 16px; }
            .brand { margin-bottom: 18px; }
            .card { padding: 30px 23px 26px; border-radius: 18px; }
            .card h1 { font-size: 23px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <header class="brand">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/>
                    <path d="M4 5.5v16M8 7h8M8 11h8"/>
                </svg>
            </div>
            <div>
                <p class="brand-name">Web MQ An-Nuur</p>
                <p class="brand-caption">Page Builder &amp; CMS</p>
            </div>
        </header>

        <section class="card" aria-labelledby="login-title">
            <h1 id="login-title">Selamat datang</h1>
            <p class="intro">Masuk dengan akun Super Admin atau Editor untuk mengelola website.</p>

            <?php if (isset($e)): ?>
                <div class="error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                    </svg>
                    <span><?= htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="<?= htmlspecialchars(pbBasePath() . '/login.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>
                        </svg>
                        <input type="text" id="username" name="username" autocomplete="username" required autofocus placeholder="Masukkan username">
                    </div>
                </div>
                <div class="field">
                    <label for="password">Kata sandi</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>
                        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="Masukkan kata sandi">
                    </div>
                </div>
                <button type="submit" name="login" value="1" id="btnSubmit" class="submit">
                    <span>Masuk ke aplikasi</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14m-7-7 7 7-7 7"/>
                    </svg>
                </button>
            </form>
        </section>
        <p class="card-footer">Akses khusus Super Admin & Editor</p>
    </main>
</body>
</html>
