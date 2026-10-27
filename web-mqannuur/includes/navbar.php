<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$adminName = $adminName ?? 'Super Admin';
$adminRole = $adminRole ?? 'Super Admin';
$adminInitial = $adminInitial ?? 'A';
$adminFlashes = $adminFlashes ?? [];
?>
<div class="admin-main">
    <header class="admin-topbar">
        <div class="admin-topbar-left">
            <button class="admin-menu-toggle" id="adminMenuToggle" type="button" aria-controls="adminSidebar" aria-expanded="false" aria-label="Buka navigasi">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div class="admin-page-title">
                <p class="admin-eyebrow">Central Admin</p>
                <h1><?= pbE($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?><p><?= pbE($pageSubtitle) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="admin-topbar-actions">
            <a class="admin-btn ghost" href="index.php" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="action-label">Lihat Situs</span>
            </a>
            <?php if (hasRole('super_admin')): ?>
                <a class="admin-btn ghost" href="admin-pages.php">
                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i><span class="action-label">Halaman</span>
                </a>
                <a class="admin-btn primary" href="editor.php?slug=home">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span class="action-label">Customize Site</span>
                </a>
            <?php endif; ?>
            <div class="admin-topbar-user">
                <span class="admin-topbar-avatar" aria-hidden="true"><?= pbE($adminInitial) ?></span>
                <span class="admin-topbar-user-text"><strong><?= pbE($adminName) ?></strong><small><?= pbE($adminRole) ?></small></span>
                <a class="admin-topbar-logout" href="logout.php" title="Logout" aria-label="Logout"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></a>
            </div>
        </div>
    </header>
    <main class="admin-content" id="adminContent">
        <?php foreach ($adminFlashes as $flash): ?>
            <?php if (is_array($flash) && isset($flash['message'])): ?>
                <?php $type = in_array($flash['type'] ?? '', ['success', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
                <div class="admin-alert <?= pbE($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
                    <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : ($type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>" aria-hidden="true"></i>
                    <span><?= pbE($flash['message']) ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
