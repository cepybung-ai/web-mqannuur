<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/analytics.php';

$adminSettings = isset($adminSettings) && is_array($adminSettings) ? $adminSettings : pbSettings();
$adminSiteName = trim((string)($adminSettings['site_name'] ?? '')) ?: 'Web MQ An-Nuur';
$adminSiteTagline = trim((string)($adminSettings['site_tagline'] ?? '')) ?: 'Central CMS';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav = $activeNav ?? 'dashboard';
$adminExtraCss = $adminExtraCss ?? '';
$adminName = $adminName ?? 'Super Admin';
$adminRole = $adminRole ?? 'Super Admin';
$adminInitial = $adminInitial ?? 'A';
$adminFlashes = $adminFlashes ?? [];
$adminNavigation = $adminNavigation ?? pbAdminFilteredNavigation();
$adminLogo = trim((string)($adminSettings['site_logo'] ?? ''));
$adminLogoUrl = $adminLogo !== '' ? pbSafeUrl($adminLogo) : null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title><?= pbE($pageTitle) ?> — <?= pbE($adminSiteName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <?php if ($adminExtraCss !== ''): ?>
        <style><?= $adminExtraCss ?></style>
    <?php endif; ?>
</head>
<body>
<div class="admin-shell">
