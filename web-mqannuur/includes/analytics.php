<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

function pbAnalyticsIp(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
}

function pbTrackVisit(string $path, string $title = ''): void
{
    static $tracked = false;
    if ($tracked) {
        return;
    }
    $tracked = true;

    if (PHP_SAPI === 'cli') {
        return;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return;
    }
    if (!empty($_GET['preview']) || isset($_GET['theme'])) {
        return;
    }
    $status = http_response_code();
    if (is_int($status) && $status >= 400) {
        return;
    }
    if (function_exists('authUser') && authUser()) {
        return;
    }

    $path = trim($path);
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    $path = substr(preg_replace('/[\x00-\x20]+/', '', $path) ?? '/', 0, 255);
    $title = trim(strip_tags($title));
    if (function_exists('mb_substr')) {
        $title = mb_substr($title, 0, 255, 'UTF-8');
    } else {
        $title = substr($title, 0, 255);
    }

    $ip = pbAnalyticsIp();
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (function_exists('mb_substr')) {
        $userAgent = mb_substr($userAgent, 0, 255, 'UTF-8');
    } else {
        $userAgent = substr($userAgent, 0, 255);
    }
    $visitorHash = hash_hmac('sha256', $ip . '|' . $userAgent, 'webmq-analytics-v1');

    try {
        $statement = getDbConnection()->prepare(
            'INSERT INTO visits (visit_date, path, page_title, visitor_ip, visitor_hash, user_agent) VALUES (:visit_date, :path, :page_title, :visitor_ip, :visitor_hash, :user_agent)'
        );
        $statement->execute([
            'visit_date' => date('Y-m-d'),
            'path' => $path,
            'page_title' => $title,
            'visitor_ip' => $ip,
            'visitor_hash' => $visitorHash,
            'user_agent' => $userAgent,
        ]);
    } catch (Throwable $exception) {
        error_log('[Page Builder] Analytics tracking error: ' . $exception->getMessage());
    }
}

function pbAnalyticsData(PDO $pdo, int $days = 30, int $topLimit = 10, int $recentLimit = 20): array
{
    $days = max(1, min(365, $days));
    $topLimit = max(1, min(50, $topLimit));
    $recentLimit = max(1, min(100, $recentLimit));
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $todayStatement = $pdo->query('SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM visits WHERE visit_date = CURDATE()');
    $today = $todayStatement->fetch() ?: ['views' => 0, 'visitors' => 0];

    $rangeStatement = $pdo->prepare('SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM visits WHERE visit_date >= :from');
    $rangeStatement->execute(['from' => $from]);
    $range = $rangeStatement->fetch() ?: ['views' => 0, 'visitors' => 0];

    $seriesStatement = $pdo->prepare('SELECT visit_date, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM visits WHERE visit_date >= :from GROUP BY visit_date ORDER BY visit_date ASC');
    $seriesStatement->execute(['from' => $from]);
    $seriesMap = [];
    foreach ($seriesStatement->fetchAll() as $row) {
        $seriesMap[(string)$row['visit_date']] = [
            'views' => (int)$row['views'],
            'visitors' => (int)$row['visitors'],
        ];
    }
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $series[] = [
            'date' => $date,
            'views' => (int)($seriesMap[$date]['views'] ?? 0),
            'visitors' => (int)($seriesMap[$date]['visitors'] ?? 0),
        ];
    }

    $topStatement = $pdo->prepare('SELECT path, MAX(page_title) AS page_title, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, MAX(created_at) AS last_visit FROM visits WHERE visit_date >= :from GROUP BY path ORDER BY views DESC, path ASC LIMIT ' . $topLimit);
    $topStatement->execute(['from' => $from]);
    $topPages = $topStatement->fetchAll();

    $recentStatement = $pdo->prepare('SELECT path, page_title, visitor_ip, created_at FROM visits ORDER BY created_at DESC, id DESC LIMIT ' . $recentLimit);
    $recentStatement->execute();
    $recent = $recentStatement->fetchAll();

    return [
        'days' => $days,
        'from' => $from,
        'today' => ['views' => (int)($today['views'] ?? 0), 'visitors' => (int)($today['visitors'] ?? 0)],
        'range' => ['views' => (int)($range['views'] ?? 0), 'visitors' => (int)($range['visitors'] ?? 0)],
        'series' => $series,
        'top_pages' => $topPages,
        'recent' => $recent,
    ];
}

function pbAnalyticsChartSvg(array $series, int $height = 180, bool $showLabels = true): string
{
    if ($series === []) {
        return '<div class="admin-analytics-empty">Belum ada data kunjungan.</div>';
    }

    $width = 680;
    $padTop = 14;
    $padBottom = $showLabels ? 30 : 10;
    $padX = 26;
    $plotWidth = $width - ($padX * 2);
    $plotHeight = max(20, $height - $padTop - $padBottom);
    $maxValue = 1;
    foreach ($series as $point) {
        $maxValue = max($maxValue, (int)($point['views'] ?? 0), (int)($point['visitors'] ?? 0));
    }
    $count = count($series);
    $viewPoints = [];
    $visitorPoints = [];
    $areaPoints = [];
    foreach (array_values($series) as $index => $point) {
        $x = $count > 1 ? $padX + (($plotWidth / ($count - 1)) * $index) : $padX + ($plotWidth / 2);
        $viewY = $padTop + $plotHeight - (($plotHeight / $maxValue) * (int)($point['views'] ?? 0));
        $visitorY = $padTop + $plotHeight - (($plotHeight / $maxValue) * (int)($point['visitors'] ?? 0));
        $viewPoints[] = round($x, 1) . ',' . round($viewY, 1);
        $visitorPoints[] = round($x, 1) . ',' . round($visitorY, 1);
        $areaPoints[] = round($x, 1) . ',' . round($viewY, 1);
    }
    $baseline = $padTop + $plotHeight;
    $area = $padX . ',' . $baseline . ' ' . implode(' ', $areaPoints) . ' ' . ($padX + $plotWidth) . ',' . $baseline;

    $labelIndexes = [];
    if ($showLabels && $count > 0) {
        $slots = min(6, $count);
        for ($i = 0; $i < $slots; $i++) {
            $labelIndexes[] = (int)round((($count - 1) / max(1, $slots - 1)) * $i);
        }
        $labelIndexes = array_values(array_unique($labelIndexes));
    }

    $svg = '<svg class="admin-analytics-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Grafik kunjungan dan unique visitor">';
    $svg .= '<title>Grafik kunjungan dan unique visitor</title>';
    for ($i = 0; $i <= 3; $i++) {
        $gridY = $padTop + (($plotHeight / 3) * $i);
        $svg .= '<line x1="' . $padX . '" y1="' . round($gridY, 1) . '" x2="' . ($width - $padX) . '" y2="' . round($gridY, 1) . '" stroke="#e8edf5" stroke-width="1" />';
    }
    $svg .= '<polygon points="' . pbE($area) . '" fill="rgba(99,102,241,.12)" />';
    $svg .= '<polyline points="' . pbE(implode(' ', $viewPoints)) . '" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />';
    $svg .= '<polyline points="' . pbE(implode(' ', $visitorPoints)) . '" fill="none" stroke="#10b981" stroke-width="2" stroke-dasharray="5 4" stroke-linecap="round" stroke-linejoin="round" />';
    foreach ($labelIndexes as $index) {
        if (!isset($series[$index])) {
            continue;
        }
        $x = $count > 1 ? $padX + (($plotWidth / ($count - 1)) * $index) : $padX + ($plotWidth / 2);
        $svg .= '<text x="' . round($x, 1) . '" y="' . ($height - 8) . '" text-anchor="middle" fill="#94a3b8" font-size="10" font-family="Inter, sans-serif">' . pbE(date('d M', strtotime((string)$series[$index]['date']))) . '</text>';
    }
    $svg .= '</svg>';
    return $svg;
}
