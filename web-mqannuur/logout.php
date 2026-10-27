<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$user = authUser();
if (is_array($user)) {
    logActivity(
        'logout',
        'auth',
        (int)($user['id'] ?? 0),
        'Logout dari CMS',
        (string)($user['username'] ?? ''),
        pbNormalizeRole($user['role'] ?? 'editor')
    );
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: ' . pbBasePath() . '/login.php');
exit;
