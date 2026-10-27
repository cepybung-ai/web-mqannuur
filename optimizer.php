<?php
declare(strict_types=1);

/** Conservative HTML/CSS optimizers used when page content is saved or rendered. */
final class PageSpeedOptimizer
{
    /** Minify CSS without changing quoted strings or calc() whitespace. */
    public static function minifyCss(string $css): string
    {
        $output = '';
        $length = strlen($css);
        $quote = null;
        $escaped = false;
        $pendingSpace = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                self::appendPendingSpace($output, $pendingSpace, $char);
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $i += 2;
                while ($i < $length && !($css[$i] === '*' && ($css[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                if ($i < $length) $i++;
                $pendingSpace = true;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            self::appendPendingSpace($output, $pendingSpace, $char);
            $output .= $char;
        }

        return trim($output);
    }

    /** Remove layout comments and inter-tag indentation while retaining inline text spacing. */
    public static function minifyHtml(string $html): string
    {
        $inlineBlocks = [];
        $html = preg_replace_callback(
            '~<(style|script)\b([^>]*)>(.*?)</\1\s*>~is',
            static function (array $match) use (&$inlineBlocks): string {
                $index = count($inlineBlocks);
                $tag = strtolower($match[1]);
                $body = $tag === 'style' ? self::minifyCss($match[3]) : trim($match[3]);
                $inlineBlocks[$index] = '<' . $tag . $match[2] . '>' . $body . '</' . $tag . '>';
                return "\x1APAGEOPT{$index}\x1A";
            },
            $html
        ) ?? $html;

        // Keep legacy IE conditional comments; remove regular editorial comments.
        $html = preg_replace_callback('~<!--[\s\S]*?-->~', static function (array $match): string {
            return preg_match('~^<!--\s*(?:\[if\b|<!\[endif\])~i', $match[0]) ? $match[0] : '';
        }, $html) ?? $html;

        $blockTags = array_fill_keys([
            'html', 'head', 'body', 'main', 'header', 'footer', 'section', 'article', 'aside', 'nav',
            'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'thead',
            'tbody', 'tfoot', 'tr', 'td', 'th', 'blockquote', 'figure', 'figcaption', 'form',
            'fieldset', 'hr', 'br'
        ], true);
        $matches = [];
        preg_match_all('/>([\t\r\n ]+)</', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($matches !== []) {
            $output = '';
            $cursor = 0;
            foreach ($matches as $match) {
                $whole = $match[0][0];
                $offset = $match[0][1];
                $before = substr($html, 0, $offset + 1);
                $after = substr($html, $offset + strlen($whole) - 1);
                preg_match_all('~</?([a-z][a-z0-9:-]*)\b[^>]*>~i', $before, $previousTags);
                preg_match('~<\s*([a-z][a-z0-9:-]*)\b~i', $after, $nextTag);
                $previous = strtolower((string)end($previousTags[1]));
                $next = strtolower((string)($nextTag[1] ?? ''));
                $output .= substr($html, $cursor, $offset - $cursor);
                $output .= isset($blockTags[$previous], $blockTags[$next]) ? '><' : '> <';
                $cursor = $offset + strlen($whole);
            }
            $html = $output . substr($html, $cursor);
        }

        foreach ($inlineBlocks as $index => $block) {
            $html = str_replace("\x1APAGEOPT{$index}\x1A", $block, $html);
        }
        return trim($html);
    }

    /** Add async decoding and lazy loading to non-hero images. */
    public static function optimizeImages(string $html): string
    {
        $protectedBlocks = [];
        $html = preg_replace_callback(
            '~<!--[\s\S]*?-->|<(script|style|pre|textarea|code)\b[^>]*>[\s\S]*?</\1\s*>~i',
            static function (array $match) use (&$protectedBlocks): string {
                $index = count($protectedBlocks);
                $protectedBlocks[$index] = $match[0];
                return "\x1AIMGSAFE{$index}\x1A";
            },
            $html
        ) ?? $html;

        $imageIndex = 0;
        $html = preg_replace_callback('~<img\b[^>]*>~i', static function (array $match) use (&$imageIndex): string {
            $isHero = $imageIndex++ === 0;
            $tag = $match[0];
            $closing = preg_match('~/\s*>$~', $tag) ? '/>' : '>';
            $body = preg_replace('~/?>$~', '', $tag) ?? $tag;
            $body = rtrim($body);

            foreach (['loading', 'decoding', 'fetchpriority'] as $attribute) {
                $body = preg_replace(
                    '~\s+' . preg_quote($attribute, '~') . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~i',
                    '',
                    $body
                ) ?? $body;
            }

            if (!$isHero) $body .= ' loading="lazy"';
            $body .= ' decoding="async"';
            if ($isHero) $body .= ' fetchpriority="high"';
            return $body . $closing;
        }, $html) ?? $html;

        foreach ($protectedBlocks as $index => $block) {
            $html = str_replace("\x1AIMGSAFE{$index}\x1A", $block, $html);
        }
        return $html;
    }

    /** Add font connection hints once when a page uses Google Fonts or Font Awesome. */
    public static function injectResourceHints(string $html): string
    {
        if (!preg_match('~(?:fonts\.googleapis\.com|fonts\.gstatic\.com|fontawesome|font-awesome)~i', $html)) {
            return $html;
        }

        $hints = '';
        if (!preg_match('~<link\b(?=[^>]*\brel\s*=\s*["\']preconnect["\'])(?=[^>]*\bhref\s*=\s*["\']https://fonts\.googleapis\.com["\'])[^>]*>~i', $html)) {
            $hints .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        }
        if (!preg_match('~<link\b(?=[^>]*\brel\s*=\s*["\']dns-prefetch["\'])(?=[^>]*\bhref\s*=\s*["\']https://fonts\.gstatic\.com["\'])[^>]*>~i', $html)) {
            $hints .= '<link rel="dns-prefetch" href="https://fonts.gstatic.com">';
        }
        if ($hints === '') return $html;

        return preg_replace('~(<head\b[^>]*>)~i', '$1' . $hints, $html, 1) ?? $html;
    }

    /** Add pending whitespace only where removing it could join CSS tokens. */
    private static function appendPendingSpace(string &$output, bool &$pending, string $next): void
    {
        if (!$pending || $output === '') {
            $pending = false;
            return;
        }
        $previous = $output[strlen($output) - 1];
        $punctuation = '{};,';
        if (strpos($punctuation, $previous) === false && strpos($punctuation, $next) === false) {
            $output .= ' ';
        }
        $pending = false;
    }
}
