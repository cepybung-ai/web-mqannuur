<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

if (($_SESSION['admin_logged_in'] ?? false) !== true) {
    header('Location: ' . pbBasePath() . '/login.php');
    exit;
}

function pbCurrentRole(): string
{
    $user = authUser();
    return is_array($user) ? pbNormalizeRole($user['role'] ?? 'editor') : 'editor';
}

function hasRole($role): bool
{
    $user = authUser();
    if (!is_array($user)) {
        return false;
    }
    $current = pbNormalizeRole($user['role'] ?? 'editor');
    $allowed = is_array($role) ? $role : [$role];
    foreach ($allowed as $candidate) {
        $candidate = strtolower(trim((string)$candidate));
        if (in_array($candidate, ['super_admin', 'admin', 'operator'], true)) {
            $candidate = 'super_admin';
        } elseif ($candidate !== 'editor') {
            continue;
        }
        if ($candidate === $current) {
            return true;
        }
    }
    return false;
}

function checkRole($allowed_roles): void
{
    if (hasRole($allowed_roles)) {
        return;
    }
    if (!isset($_SESSION['webmq_admin_flash']) || !is_array($_SESSION['webmq_admin_flash'])) {
        $_SESSION['webmq_admin_flash'] = [];
    }
    $_SESSION['webmq_admin_flash'][] = [
        'type' => 'error',
        'message' => 'Akses ditolak. Anda tidak memiliki izin untuk membuka halaman tersebut.',
    ];
    header('Location: ' . pbBasePath() . '/dashboard.php', true, 303);
    exit;
}
