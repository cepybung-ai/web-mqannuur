<?php
declare(strict_types=1);

/** Shared helpers for the standalone web-mqannuur project. */
if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/** Open a UTF-8 PDO connection using environment-based credentials. */
function pbDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'web_mqannuur';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '';
    $socket = getenv('DB_SOCKET') ?: '';
    $dsn = $socket !== ''
        ? "mysql:unix_socket={$socket};dbname={$database};charset=utf8mb4"
        : "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    $sslCa = getenv('DB_SSL_CA') ?: '';
    $isLocalHost = in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true) || $socket !== '';
    if ($sslCa === '' && !$isLocalHost && is_file(__DIR__ . '/database/ca.pem')) {
        $sslCa = __DIR__ . '/database/ca.pem';
    }
    if ($socket === '' && $sslCa !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    }
    $sslVerify = getenv('DB_SSL_VERIFY');
    if ($socket === '' && $sslVerify !== false && filter_var($sslVerify, FILTER_VALIDATE_BOOLEAN) === false
        && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, $username, $password, $options);
    return $pdo;
}

/** Redirect page requests to the session login when no admin is authenticated. */
function pbRequireAdminPage(): void
{
    if (($_SESSION['admin_logged_in'] ?? null) !== true) {
        header('Location: login.php', true, 302);
        exit;
    }
}

function pbJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function pbBasePath(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = preg_replace('~/page-builder$~', '', rtrim($scriptDir, '/')) ?? '';
    return $base === '' ? '' : $base;
}

function pbValidSlug($slug): bool
{
    return is_string($slug) && strlen($slug) <= 100 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

function pbTitleFromSlug(string $slug): string
{
    return ucwords(str_replace('-', ' ', $slug));
}

/** Accept only HTTP(S), safe relative paths, and optionally embedded raster images. */
function pbSafeUrl(string $url, bool $allowImageData = false): ?string
{
    $url = trim($url);
    $decodedUrl = rawurldecode($url);
    if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $decodedUrl)) {
        return null;
    }

    if ($allowImageData && preg_match('~^data:image/(?:png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$~i', $url)) {
        return $url;
    }

    if (preg_match('~^(?:https?://|mailto:|tel:)~i', $url)) {
        return $url;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $decodedUrl) || str_starts_with($url, '//')) {
        return null;
    }

    return $url;
}

/** Remove CSS constructs that can escape a style block or execute active URLs. */
function pbSanitizeCss(string $css): string
{
    $css = substr($css, 0, 2_000_000);
    $css = preg_replace('~</?style\b[^>]*>|</?script\b[^>]*>|<!--|-->~i', '', $css) ?? '';
    $css = preg_replace('~@import\b[^;]*(?:;|$)~i', '', $css) ?? '';
    $css = preg_replace('~expression\s*\([^)]*\)|-moz-binding\s*:[^;}]*(?:;|$)|\bbehavior\s*:[^;}]*(?:;|$)~i', '', $css) ?? '';
    $css = preg_replace('~(?:javascript|vbscript)\s*:~i', '', $css) ?? '';
    $css = preg_replace_callback('~url\s*\((.*?)\)~is', static function (array $match): string {
        $url = trim($match[1], " \t\n\r\0\x0B\"'");
        $safe = pbSafeUrl($url, true);
        if ($safe === null || preg_match('~^(?:mailto:|tel:)~i', $safe)) {
            return 'none';
        }
        $safe = str_replace(['\\', '"'], ['\\\\', '\\"'], $safe);
        return 'url("' . $safe . '")';
    }, $css) ?? '';

    return $css;
}

function pbAllowedHtmlTags(): array
{
    return [
        'section', 'div', 'main', 'header', 'footer', 'article', 'aside', 'nav',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'small', 'strong', 'b',
        'em', 'i', 'u', 's', 'br', 'hr', 'a', 'img', 'button', 'ul', 'ol', 'li',
        'blockquote', 'figure', 'figcaption', 'details', 'summary', 'video', 'source',
        'form', 'label', 'input', 'textarea', 'select', 'option',
        'table', 'thead', 'tbody', 'tr', 'th', 'td'
    ];
}

/** Keep a small safe attribute set; inline styles are provided through content_css. */
function pbSanitizeElementAttributes(DOMElement $element): void
{
    $allowed = [
        'class', 'id', 'title', 'alt', 'width', 'height', 'href', 'src', 'poster', 'target',
        'rel', 'role', 'type', 'loading', 'decoding', 'controls', 'autoplay', 'muted',
        'loop', 'playsinline', 'preload', 'open', 'action', 'method', 'name', 'value',
        'placeholder', 'required', 'rows', 'cols', 'maxlength', 'minlength', 'autocomplete',
        'for', 'tabindex'
    ];
    $attributes = [];
    foreach ($element->attributes as $attribute) {
        $attributes[] = [$attribute->name, $attribute->value];
    }

    foreach ($attributes as [$name, $value]) {
        $name = strtolower($name);
        if (str_starts_with($name, 'on')) {
            $element->removeAttribute($name);
            continue;
        }
        if ($name === 'style') {
            $element->setAttribute('style', pbSanitizeCss($value));
            continue;
        }
        if (!in_array($name, $allowed, true) && !preg_match('/^(?:aria|data)-[a-z0-9_.:-]+$/', $name)) {
            $element->removeAttribute($name);
            continue;
        }
        if ($name === 'href' || $name === 'src' || $name === 'poster') {
            $safe = pbSafeUrl($value, $name === 'src' || $name === 'poster');
            if ($safe === null) {
                $element->removeAttribute($name);
            } else {
                $element->setAttribute($name, $safe);
            }
        }
        if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) {
            $element->removeAttribute($name);
        }
        if ($name === 'action') {
            $action = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!in_array($action, ['api.php?action=submit_form', './api.php?action=submit_form'], true)) {
                $element->removeAttribute($name);
            }
        }
        if ($name === 'method' && strtolower($value) !== 'post') {
            $element->setAttribute($name, 'post');
        }
        $tagName = strtolower($element->tagName);
        if ($name === 'type' && $tagName === 'input'
            && !in_array(strtolower($value), ['text', 'email', 'hidden', 'tel'], true)) {
            $element->setAttribute($name, 'text');
        }
        if (in_array($name, ['rows', 'cols', 'maxlength', 'minlength', 'tabindex'], true)
            && !preg_match('/^-?\d{1,5}$/', $value)) {
            $element->removeAttribute($name);
        }
        if (($name === 'width' || $name === 'height') && !preg_match('/^\d{1,4}$/', $value)) {
            $element->removeAttribute($name);
        }
        if ($name === 'type' && $tagName === 'button' && $value !== 'button') {
            $element->setAttribute('type', 'button');
        }
    }

    $tagName = strtolower($element->tagName);
    if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
        $element->setAttribute('rel', 'noopener noreferrer');
    }
    if ($tagName === 'button') {
        $element->setAttribute('type', 'button');
    }
}

function pbSanitizeHtmlChildren(DOMNode $parent): void
{
    $allowed = pbAllowedHtmlTags();
    $dropEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'link', 'meta', 'base'];
    $children = [];
    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child->parentNode !== $parent) {
            continue;
        }
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
            $parent->removeChild($child);
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->tagName);
        if (in_array($tag, $dropEntirely, true)) {
            $parent->removeChild($child);
            continue;
        }

        pbSanitizeHtmlChildren($child);
        if (!in_array($tag, $allowed, true)) {
            while ($child->firstChild) {
                $parent->insertBefore($child->firstChild, $child);
            }
            $parent->removeChild($child);
            continue;
        }

        pbSanitizeElementAttributes($child);
    }
}

/** Sanitize builder HTML before it is stored or rendered publicly. Requires PHP DOM. */
function pbSanitizeHtml(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('Ekstensi PHP DOM diperlukan untuk membersihkan HTML.');
    }
    $html = substr($html, 0, 2_000_000);
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body><div id="pb-sanitize-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="pb-sanitize-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }
        pbSanitizeHtmlChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return $result;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** Remove executable component types and event attributes from saved GrapesJS JSON. */
function pbSanitizeComponents($value, int $depth = 0)
{
    if ($depth > 64 || !is_array($value)) {
        return null;
    }
    $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        $clean = [];
        foreach ($value as $item) {
            $item = pbSanitizeComponents($item, $depth + 1);
            if ($item !== null) {
                $clean[] = $item;
            }
        }
        return $clean;
    }

    $tag = strtolower((string)($value['tagName'] ?? ''));
    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'select'], true)) {
        return null;
    }

    $clean = [];
    foreach ($value as $key => $item) {
        $key = (string)$key;
        if (preg_match('/^on/i', $key) || in_array(strtolower($key), ['script', 'script-export'], true)) {
            continue;
        }
        if ($key === 'attributes' && is_array($item)) {
            $attributes = [];
            foreach ($item as $attribute => $attributeValue) {
                $attribute = strtolower((string)$attribute);
                if (str_starts_with($attribute, 'on') || $attribute === 'style') {
                    continue;
                }
                if (!in_array($attribute, ['class', 'id', 'title', 'alt', 'width', 'height', 'href', 'src', 'target', 'rel', 'role', 'type', 'loading', 'decoding', 'action', 'method', 'name', 'value', 'placeholder', 'required', 'rows', 'cols', 'maxlength', 'minlength', 'autocomplete', 'for', 'tabindex'], true)
                    && !preg_match('/^(?:aria|data)-[a-z0-9_.:-]+$/', $attribute)) {
                    continue;
                }
                if ($attribute === 'action' && !in_array($attributeValue, ['api.php?action=submit_form', './api.php?action=submit_form'], true)) {
                    continue;
                }
                if ($attribute === 'method' && strtolower((string)$attributeValue) !== 'post') {
                    $attributeValue = 'post';
                }
                if (($attribute === 'href' || $attribute === 'src') && is_string($attributeValue)) {
                    $attributeValue = pbSafeUrl($attributeValue, $attribute === 'src');
                    if ($attributeValue === null) {
                        continue;
                    }
                }
                $attributes[$attribute] = $attributeValue;
            }
            $clean[$key] = $attributes;
        } elseif ($key === 'style' && is_array($item)) {
            $styles = [];
            foreach ($item as $property => $styleValue) {
                $property = strtolower((string)$property);
                if (!preg_match('/^(?:--[a-z0-9_-]+|[a-z][a-z0-9-]*)$/', $property) || in_array($property, ['behavior', '-moz-binding'], true)) {
                    continue;
                }
                if (is_scalar($styleValue) && !preg_match('~(?:expression\s*\(|javascript\s*:|vbscript\s*:|</style|url\s*\()~i', (string)$styleValue)) {
                    $styles[$property] = $styleValue;
                }
            }
            $clean[$key] = $styles;
        } elseif ($key === 'content' && is_string($item)) {
            $clean[$key] = pbSanitizeHtml($item);
        } elseif (is_array($item)) {
            $nested = pbSanitizeComponents($item, $depth + 1);
            if ($nested !== null) {
                $clean[$key] = $nested;
            }
        } else {
            $clean[$key] = $item;
        }
    }
    return $clean;
}

function pbRequireAdminJson(): void
{
    if (($_SESSION['admin_logged_in'] ?? null) !== true) {
        pbJsonResponse(['success' => false, 'message' => 'Sesi admin berakhir. Silakan login kembali.'], 401);
    }
}
