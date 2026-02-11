<?php

/**
 * Hard-coded text finder (S7): visible English text that is not wrapped in __() / @lang / trans().
 *
 *   php docs/mvp/tools/hardcoded-text.php [--pkg=P7,P8,...] [--kind=text,attr,js,title,reply] [--list] [--limit=<n>] [--json=<path>] [--strict]
 *
 * Kinds:
 *   text   text node between Blade/HTML tags containing Latin words
 *   attr   placeholder / title / alt / aria-label / data-*title attribute with Latin text
 *   js     string literal on a SweetAlert / toastr / alert / title: / text: / *ButtonText line (inline <script> and resources/js)
 *   title  `pageTitle = '...'` (or `$this->pageTitle = "..."`) with a literal, and any pageTitle assigned in __construct
 *   reply  Reply::success|error|redirect('literal message')
 *
 * Package map (see docs/mvp/phase2/session-7-translation.md): P7 AFV views + AFV controllers, P8 core feature views,
 * P9 layouts/sections/components/pdf/emails/notifications, P10 JS. vendor, node_modules, compiled public/js are skipped.
 * Heuristic: expect some false positives (code identifiers, brand names); use --list to review.
 */

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$root = dirname(__DIR__, 3);
$limit = (int) ($opts['limit'] ?? 300);
$onlyPkg = isset($opts['pkg']) ? explode(',', $opts['pkg']) : null;
$onlyKind = isset($opts['kind']) ? explode(',', $opts['kind']) : null;

/** Latin tokens that are fine in visible text. */
$ok = ['excel', 'csv', 'pdf', 'api', 'url', 'html', 'json', 'xml', 'sms', 'iban', 'swift', 'bic', 'google', 'sheets', 'databackd', 'worksuite',
    'ok', 'id', 'ip', 'php', 'email', 'e-mail', 'stripe', 'paypal', 'zoom', 'slack', 'telegram', 'whatsapp', 'facebook', 'twitter', 'linkedin',
    'eur', 'bgn', 'usd', 'gbp', 'kpi', 'ebitda', 'vat', 'qr', 'jpg', 'png', 'svg', 'gif', 'zip', 'xlsx', 'docx', 'camt', 'mt940', 'gdpr', 'html5'];

$pkgOf = function (string $rel): string {
    if (preg_match('#^resources/js/#', $rel)) {
        return 'P10';
    }
    if (preg_match('#^(resources/views/databackd/|app/Http/Controllers/Databackd/)#', $rel)) {
        return 'P7';
    }
    if (preg_match('#^resources/views/(dashboard|clients|invoices|payments|credit-notes|expenses|bank-account|products|contracts|projects|tasks|employees|reports)/#', $rel)
        && ! preg_match('#/pdf/#', $rel)) {
        return 'P8';
    }
    if (preg_match('#^(resources/views/(layouts|sections|components|emails|mail|notifications)/|app/Notifications/)#', $rel) || preg_match('#/pdf/#', $rel)) {
        return 'P9';
    }
    if (preg_match('#^resources/views/super-admin/#', $rel)) {
        return 'SA';
    }
    return 'other';
};

$hasLatinWords = function (string $s) use ($ok): bool {
    $s = preg_replace('/&[a-z]+;|&#\d+;/i', ' ', $s);
    preg_match_all('/\b[A-Za-z][A-Za-z\'\-]{2,}\b/', $s, $m);
    foreach ($m[0] as $w) {
        if (! in_array(strtolower($w), $GLOBALS['ok'], true)) {
            return true;
        }
    }
    return false;
};
$GLOBALS['ok'] = $ok;

$found = [];
$push = function (string $kind, string $rel, int $line, string $snippet) use (&$found, $pkgOf, $onlyPkg, $onlyKind) {
    $pkg = $pkgOf($rel);
    if (($onlyPkg && ! in_array($pkg, $onlyPkg, true)) || ($onlyKind && ! in_array($kind, $onlyKind, true))) {
        return;
    }
    $found[] = ['pkg' => $pkg, 'kind' => $kind, 'file' => $rel, 'line' => $line, 'text' => trim(preg_replace('/\s+/', ' ', $snippet))];
};

$lineOf = fn (string $src, int $offset) => substr_count(substr($src, 0, $offset), "\n") + 1;

/** Replace matched text by spaces of the same newline structure so offsets/lines survive. */
$blank = fn (string $re) => fn (array $m) => preg_replace('/[^\n]/u', ' ', $m[0]);

$scanJsText = function (string $js, string $rel, int $baseLine) use ($push, $hasLatinWords) {
    $lines = explode("\n", $js);
    foreach ($lines as $i => $ln) {
        if (! preg_match('/(Swal\.fire|swal\(|Swal\b|toastr\.|alert\(|confirm\(|title\s*:|text\s*:|html\s*:|ButtonText|placeholder|\.text\(|\.html\(|showAlert|message\s*:)/i', $ln)) {
            continue;
        }
        if (preg_match_all('/([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1/u', $ln, $m)) {
            foreach ($m[2] as $str) {
                $str = trim(preg_replace('/<[^>]*>/', ' ', $str));
                if (strlen($str) < 4 || str_contains($str, '__(') || str_contains($str, '{{') || preg_match('~^[\w./#:\-\[\]=,{}()@$%&?]+$~', $str) && ! preg_match('/\s/', $str)) {
                    continue;
                }
                if (preg_match('/[A-Za-z]{3,}\s+[A-Za-z]{2,}|^[A-Z][a-z]{3,}/', $str) && $hasLatinWords($str)) {
                    $push('js', $rel, $baseLine + $i, $ln);
                    break;
                }
            }
        }
    }
};

// ---------------- Blade views ----------------
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }
    $rel = ltrim(str_replace($root, '', $f->getPathname()), '/');
    if (str_contains($rel, '/vendor/')) {
        continue;
    }
    $src = file_get_contents($f->getPathname());

    // inline <script> => JS scan, then blank it
    if (preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $src, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[1] as $blockInfo) {
            $scanJsText($blockInfo[0], $rel, $lineOf($src, $blockInfo[1]));
        }
    }
    $clean = preg_replace_callback('#<script\b.*?</script>|<style\b.*?</style>|\{\{--.*?--\}\}|<!--.*?-->|@php.*?@endphp|@verbatim.*?@endverbatim#is', fn ($m) => preg_replace('/[^\n]/u', ' ', $m[0]), $src);
    // attributes first (before {{ }} are removed they decide whether the attr is dynamic)
    if (preg_match_all('/(?<![:@\w-])(placeholder|title|alt|aria-label|data-original-title|data-title|data-confirm|data-bs-title)\s*=\s*"([^"]*)"/i', $clean, $am, PREG_OFFSET_CAPTURE)) {
        foreach ($am[2] as $idx => $val) {
            $v = $val[0];
            if (str_contains($v, '{{') || str_contains($v, '{!!') || str_contains($v, '@') || str_contains($v, '__(') || str_contains($v, 'trans(') || str_contains($v, '$')) {
                continue;
            }
            if ($hasLatinWords($v) && preg_match('/\s|^[A-Z][a-z]/', $v)) {
                $push('attr', $rel, $lineOf($clean, $val[1]), $am[1][$idx][0] . '="' . $v . '"');
            }
        }
    }
    $clean = preg_replace_callback('#\{!!.*?!!\}|\{\{.*?\}\}#s', fn ($m) => preg_replace('/[^\n]/u', ' ', $m[0]), $clean);
    $clean = preg_replace_callback('/(?<![\w.@])@[a-zA-Z]+(?:\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', fn ($m) => preg_replace('/[^\n]/u', ' ', $m[0]), $clean);
    // text nodes
    if (preg_match_all('/>([^<>]+)</u', $clean, $tm, PREG_OFFSET_CAPTURE)) {
        foreach ($tm[1] as $t) {
            $text = trim($t[0]);
            if ($text === '' || ! preg_match('/[A-Za-z]{3,}/', $text) || ! $hasLatinWords($text)) {
                continue;
            }
            if (preg_match('/^[\s\W\d]*$/u', $text)) {
                continue;
            }
            $push('text', $rel, $lineOf($clean, $t[1] + (strlen($t[0]) - strlen(ltrim($t[0])))), $text);
        }
    }
}

// ---------------- resources/js ----------------
$jsDir = $root . '/resources/js';
if (is_dir($jsDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jsDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = ltrim(str_replace($root, '', $f->getPathname()), '/');
        if (! preg_match('/\.js$/', $f->getFilename()) || str_contains($rel, '/vendor/') || preg_match('/\.min\.js$/', $rel) || str_contains($rel, 'node_modules')) {
            continue;
        }
        $scanJsText(file_get_contents($f->getPathname()), $rel, 1);
    }
}

// ---------------- PHP: pageTitle and Reply messages ----------------
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $rel = ltrim(str_replace($root, '', $f->getPathname()), '/');
    $src = file_get_contents($f->getPathname());
    $inCtor = false;
    $depth = 0;
    foreach (explode("\n", $src) as $i => $ln) {
        if (preg_match('/function\s+__construct\s*\(/', $ln)) {
            $inCtor = true;
            $depth = 0;
        }
        if ($inCtor) {
            $depth += substr_count($ln, '{') - substr_count($ln, '}');
        }
        if (preg_match('/pageTitle\s*=\s*([\'"])(.+?)\1\s*;/', $ln, $m) && ! str_contains($ln, '__(') && ! preg_match('/^[a-z][\w-]*(\.[\w-]+)+$/', $m[2]) && $hasLatinWords($m[2])) {
            $push('title', $rel, $i + 1, $ln);
        } elseif ($inCtor && preg_match('/^( {8}|\t\t)\S.*pageTitle\s*=/', $ln) && preg_match('#Controllers/Databackd/#', $rel)) {
            $push('title', $rel, $i + 1, '[in __construct, locale not applied yet] ' . $ln);
        }
        if ($inCtor && $depth <= 0 && str_contains($ln, '}')) {
            $inCtor = false;
        }
        if (preg_match('/Reply::(?:success|error|redirect|warning)\(\s*([\'"])([^\'"]{4,})\1/', $ln, $m) && $hasLatinWords($m[2]) && preg_match('/\s/', $m[2])) {
            $push('reply', $rel, $i + 1, $ln);
        }
    }
}

usort($found, fn ($a, $b) => [$a['pkg'], $a['file'], $a['line']] <=> [$b['pkg'], $b['file'], $b['line']]);

$byPkg = [];
$byFile = [];
foreach ($found as $x) {
    $byPkg[$x['pkg']][$x['kind']] = ($byPkg[$x['pkg']][$x['kind']] ?? 0) + 1;
    $byFile[$x['pkg']][$x['file']] = ($byFile[$x['pkg']][$x['file']] ?? 0) + 1;
}
ksort($byPkg);

if (isset($opts['json']) && $opts['json'] !== true) {
    file_put_contents($opts['json'], json_encode(['by_package' => $byPkg, 'by_file' => $byFile, 'items' => $found], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "JSON: {$opts['json']}\n";
}

$kinds = ['text', 'attr', 'js', 'title', 'reply'];
printf("%-8s", 'package');
foreach ($kinds as $k) {
    printf('%8s', $k);
}
printf("%8s\n", 'total');
foreach ($byPkg as $p => $row) {
    printf('%-8s', $p);
    foreach ($kinds as $k) {
        printf('%8s', $row[$k] ?? '.');
    }
    printf("%8d\n", array_sum($row));
}
printf("TOTAL: %d in %d files\n", count($found), array_sum(array_map('count', $byFile)));

if (isset($opts['list'])) {
    echo "\n";
    foreach (array_slice($found, 0, $limit) as $x) {
        printf("[%s/%s] %s:%d  %s\n", $x['pkg'], $x['kind'], $x['file'], $x['line'], mb_strimwidth($x['text'], 0, 150, '…'));
    }
    if (count($found) > $limit) {
        printf("... %d more\n", count($found) - $limit);
    }
}

exit(isset($opts['strict']) && count($found) ? 1 : 0);
