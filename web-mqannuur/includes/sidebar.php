<?php
declare(strict_types=1);

$adminSiteName = $adminSiteName ?? 'Web MQ An-Nuur';
$adminSiteTagline = $adminSiteTagline ?? 'Central CMS';
$adminName = $adminName ?? 'Super Admin';
$adminRole = $adminRole ?? 'Super Admin';
$adminInitial = $adminInitial ?? 'A';
$activeNav = $activeNav ?? 'dashboard';
$adminNavigation = $adminNavigation ?? pbAdminFilteredNavigation();
$visibleNavigation = [];
foreach ($adminNavigation as $groupLabel => $items) {
    $visibleItems = array_values(array_filter(
        $items,
        static fn(array $item): bool => hasRole($item['roles'] ?? ['super_admin'])
    ));
    if ($visibleItems !== []) {
        $visibleNavigation[$groupLabel] = $visibleItems;
    }
}
$adminNavigation = $visibleNavigation;
$adminLogoUrl = $adminLogoUrl ?? null;
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Navigasi admin">
    <div class="admin-brand">
        <span class="admin-brand-mark" aria-hidden="true">
            <?php if ($adminLogoUrl !== null): ?>
                <img src="<?= pbE($adminLogoUrl) ?>" alt="">
            <?php else: ?>
                <i class="fa-solid fa-cubes-stacked"></i>
            <?php endif; ?>
        </span>
        <span class="admin-brand-text">
            <strong><?= pbE($adminSiteName) ?></strong>
            <small><?= pbE($adminSiteTagline) ?></small>
        </span>
    </div>
    <nav class="admin-nav" aria-label="Menu utama">
        <?php foreach ($adminNavigation as $groupLabel => $items): ?>
            <p class="admin-nav-label"><?= pbE($groupLabel) ?></p>
            <?php foreach ($items as $item): ?>
                <a class="<?= $activeNav === $item['key'] ? 'active' : '' ?>" href="<?= pbE($item['href']) ?>"<?= $activeNav === $item['key'] ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= pbE($item['icon']) ?>" aria-hidden="true"></i><span><?= pbE($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <p class="admin-nav-label">Akun</p>
        <a class="logout" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Logout</span></a>
    </nav>
    <div class="admin-sidebar-foot">
        <div class="admin-user">
            <span class="admin-avatar" aria-hidden="true"><?= pbE($adminInitial) ?></span>
            <span class="admin-user-text"><strong><?= pbE($adminName) ?></strong><small><?= pbE($adminRole) ?></small></span>
        </div>
    </div>
</aside>
<div class="admin-overlay" id="adminOverlay"></div>
