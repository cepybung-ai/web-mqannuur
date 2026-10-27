<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
header('Cache-Control: no-store, private');

if (($_SESSION['admin_logged_in'] ?? null) !== true) {
    header('Location: login.php', true, 302);
    exit;
}
