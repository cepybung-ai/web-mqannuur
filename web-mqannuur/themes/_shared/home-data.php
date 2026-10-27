<?php
declare(strict_types=1);

$themeLatestPosts = [];
$themeEvents = [];
$themeHomeHtml = pbThemeHomeHtml((string)($pageHtml ?? ''));
try {
    $pdo = getDbConnection();
    $themeLatestPosts = $pdo->query("SELECT title, slug, excerpt, featured_image, category, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 6")->fetchAll();
    $themeEvents = $pdo->query("SELECT title, slug, excerpt, location, event_date, event_end_date FROM events WHERE status = 'published' ORDER BY event_date DESC, id DESC LIMIT 6")->fetchAll();
} catch (Throwable $exception) {
    error_log('[Page Builder] Theme home data error: ' . $exception->getMessage());
}
