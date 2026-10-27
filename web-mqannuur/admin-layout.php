<?php
declare(strict_types=1);

function pbAdminCsrfToken(): string
{
    if (empty($_SESSION['page_builder_csrf'])) {
        $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['page_builder_csrf'];
}

function pbAdminFlash(string $type, string $message): void
{
    $allowed = ['success', 'error', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    if (!isset($_SESSION['webmq_admin_flash']) || !is_array($_SESSION['webmq_admin_flash'])) {
        $_SESSION['webmq_admin_flash'] = [];
    }
    $_SESSION['webmq_admin_flash'][] = ['type' => $type, 'message' => $message];
}

function pbAdminTakeFlashes(): array
{
    $flashes = $_SESSION['webmq_admin_flash'] ?? [];
    unset($_SESSION['webmq_admin_flash']);
    return is_array($flashes) ? $flashes : [];
}

function pbAdminRoleLabel(string $role): string
{
    $role = strtolower($role);
    return match ($role) {
        'super_admin', 'admin', 'operator' => 'Super Admin',
        'editor' => 'Editor',
        default => ucfirst($role !== '' ? $role : 'editor'),
    };
}

function pbAdminIconIsFontAwesome(string $icon): bool
{
    return preg_match('/^(?:fa[srbldt]?|fa-(?:solid|regular|brands|light|thin|duotone))(?:\s+fa-[a-z0-9-]+)+$/', trim($icon)) === 1;
}

function pbAdminNavigation(): array
{
    return [
        'Workspace' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-chart-pie', 'roles' => ['super_admin', 'editor']],
        ],
        'Konten' => [
            ['key' => 'pages', 'label' => 'Kelola Halaman', 'href' => 'admin-pages.php', 'icon' => 'fa-file-lines', 'roles' => ['super_admin']],
            ['key' => 'posts', 'label' => 'Berita & Artikel', 'href' => 'admin-posts.php', 'icon' => 'fa-newspaper', 'roles' => ['super_admin', 'editor']],
            ['key' => 'events', 'label' => 'Agenda & Pengumuman', 'href' => 'admin-events.php', 'icon' => 'fa-calendar-days', 'roles' => ['super_admin', 'editor']],
            ['key' => 'staff', 'label' => 'Guru & Staf', 'href' => 'admin-staff.php', 'icon' => 'fa-user-tie', 'roles' => ['super_admin', 'editor']],
            ['key' => 'gallery', 'label' => 'Galeri Media', 'href' => 'admin-gallery.php', 'icon' => 'fa-images', 'roles' => ['super_admin', 'editor']],
            ['key' => 'downloads', 'label' => 'Pusat Download', 'href' => 'admin-downloads.php', 'icon' => 'fa-file-arrow-down', 'roles' => ['super_admin', 'editor']],
        ],
        'Website' => [
            ['key' => 'links', 'label' => 'Link & Menu', 'href' => 'admin-links.php', 'icon' => 'fa-link', 'roles' => ['super_admin']],
            ['key' => 'themes', 'label' => 'Tema Website', 'href' => 'admin-themes.php', 'icon' => 'fa-palette', 'roles' => ['super_admin']],
            ['key' => 'settings', 'label' => 'Identitas Situs', 'href' => 'admin-settings.php', 'icon' => 'fa-gear', 'roles' => ['super_admin']],
            ['key' => 'seo', 'label' => 'SEO Manager', 'href' => 'admin-seo.php', 'icon' => 'fa-magnifying-glass-chart', 'roles' => ['super_admin']],
            ['key' => 'analytics', 'label' => 'Analytics', 'href' => 'admin-analytics.php', 'icon' => 'fa-chart-line', 'roles' => ['super_admin']],
        ],
        'Komunikasi' => [
            ['key' => 'messages', 'label' => 'Pesan Masuk', 'href' => 'admin-messages.php', 'icon' => 'fa-envelope', 'roles' => ['super_admin', 'editor']],
        ],
        'Sistem' => [
            ['key' => 'users', 'label' => 'Kelola User', 'href' => 'admin-users.php', 'icon' => 'fa-users', 'roles' => ['super_admin']],
            ['key' => 'logs', 'label' => 'Audit Log', 'href' => 'admin-logs.php', 'icon' => 'fa-list-check', 'roles' => ['super_admin']],
            ['key' => 'backup', 'label' => 'Backup & Restore', 'href' => 'site_backup.php', 'icon' => 'fa-database', 'roles' => ['super_admin']],
        ],
    ];
}

function pbAdminSlugBase(string $value, string $fallback = 'konten'): string
{
    $source = trim($value);
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source);
        if (is_string($transliterated)) {
            $source = $transliterated;
        }
    }
    $source = strtolower($source);
    $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
    $source = trim($source, '-');
    return substr($source !== '' ? $source : $fallback, 0, 220);
}

function pbAdminUniqueSlug(PDO $pdo, string $table, string $base, int $excludeId = 0): string
{
    if (!in_array($table, ['pages', 'posts', 'events'], true)) {
        throw new InvalidArgumentException('Tabel slug tidak valid.');
    }
    $slug = $base;
    $suffix = 2;
    while (true) {
        if ($excludeId > 0) {
            $statement = $pdo->prepare('SELECT 1 FROM `' . $table . '` WHERE slug = :slug AND id <> :id LIMIT 1');
            $statement->execute(['slug' => $slug, 'id' => $excludeId]);
        } else {
            $statement = $pdo->prepare('SELECT 1 FROM `' . $table . '` WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $slug]);
        }
        if (!$statement->fetchColumn()) {
            return $slug;
        }
        $slug = substr($base, 0, 220) . '-' . $suffix++;
    }
}

function pbAdminFilteredNavigation(): array
{
    $navigation = pbAdminNavigation();
    foreach ($navigation as $group => $items) {
        $navigation[$group] = array_values(array_filter(
            $items,
            static fn(array $item): bool => hasRole($item['roles'] ?? ['super_admin'])
        ));
        if ($navigation[$group] === []) {
            unset($navigation[$group]);
        }
    }
    return $navigation;
}

function pbAdminPageStart(string $title, string $active, string $subtitle = '', string $extraCss = ''): void
{
    $user = authUser() ?: ['username' => 'admin', 'name' => 'Super Admin', 'role' => 'super_admin'];
    $adminName = trim((string)($user['name'] ?? '')) ?: (string)($user['username'] ?? 'Admin');
    $adminRole = pbAdminRoleLabel((string)($user['role'] ?? 'editor'));
    $adminInitial = strtoupper(substr($adminName, 0, 1));
    $adminFlashes = pbAdminTakeFlashes();
    $adminNavigation = pbAdminFilteredNavigation();
    $adminSettings = pbSettings();
    $pageTitle = $title;
    $pageSubtitle = $subtitle;
    $activeNav = $active;
    $adminExtraCss = $extraCss;

    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/sidebar.php';
    require __DIR__ . '/includes/navbar.php';
}

function pbAdminPageEnd(): void
{
    require __DIR__ . '/includes/footer.php';
}
