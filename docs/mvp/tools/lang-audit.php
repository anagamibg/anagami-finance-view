<?php

/**
 * Lang audit (S7): compares resources/lang/eng and resources/lang/bg and flags wording/case problems.
 *
 *   php docs/mvp/tools/lang-audit.php [--file=<substring>] [--type=<type>[,<type>]] [--json=<path>]
 *                                     [--list] [--limit=<n>] [--strict] [--with-superadmin] [--lang-dir=<dir>]
 *
 *   --file             only files whose name contains the substring (e.g. databackd-data, app.php)
 *   --type             only these issue types (see TYPES below)
 *   --json             write the full result (summary + every issue) to this path (default: nothing written)
 *   --list             print the issues, not only the per-file table
 *   --limit            max issues printed with --list (default 200)
 *   --strict           exit code 1 if there is any critical issue
 *   --with-superadmin  include superadmin.php (optional area, skipped by default)
 *
 * Issue types (severity):
 *   missing_in_bg, missing_in_eng, missing_file_bg, missing_file_eng   critical
 *   placeholder_mismatch   :name / {0} / %s placeholders differ between eng and bg      critical
 *   banned_term            literal-translation term from the BANNED glossary in bg       critical
 *   bg_latin               bg value contains Latin words that are not known terms        warn (critical in menu/title keys)
 *   bg_lowercase_start     bg value starts with a lowercase letter                      warn (critical for label-like values in menu/title keys)
 *   bg_title_case          bg label with Capitalised words after the first               warn
 *   eng_case_heading       eng menu/title value is not Title Case                        info
 *   eng_case_label         eng button/label/message value written in Title Case          info
 *
 * Bulgarian rule: capital letter only at the start of menus, titles, buttons, labels, tabs and columns;
 * proper names and abbreviations stay as they are (ОПР, ДДС, ЕИК, API, Excel, Google Sheets).
 * English rule: Title Case for menus and headings, sentence case for buttons and messages.
 */

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

$root = dirname(__DIR__, 3);
$langDir = $opts['lang-dir'] ?? $root . '/resources/lang';
$limit = (int) ($opts['limit'] ?? 200);
$onlyFile = $opts['file'] ?? null;
$onlyTypes = isset($opts['type']) ? explode(',', $opts['type']) : null;
$withSuper = isset($opts['with-superadmin']);

const CRITICAL_TYPES = ['missing_in_bg', 'missing_in_eng', 'missing_file_bg', 'missing_file_eng', 'placeholder_mismatch', 'banned_term'];

/** Latin words that are allowed inside Bulgarian text (compared case-insensitively). All-caps tokens of up to 8 chars are always allowed. */
const LATIN_OK = [
    'ctrl', 'excel', 'csv', 'google', 'sheets', 'pdf', 'api', 'url', 'html', 'json', 'xml', 'sms', 'smtp', 'imap', 'iban', 'swift', 'bic',
    'worksuite', 'databackd', 'stripe', 'paypal', 'razorpay', 'mollie', 'paystack', 'square', 'authorize', 'payfast', 'flutterwave',
    'telegram', 'slack', 'pusher', 'whatsapp', 'facebook', 'linkedin', 'twitter', 'zoom', 'oauth', 'gmail', 'outlook', 'mailgun',
    'camt', 'mt940', 'mt', 'ecb', 'ebitda', 'kpi', 'roe', 'roa', 'roi', 'dso', 'dpo', 'dio', 'qr', 'ssl', 'tls', 'dns', 'ftp', 'sftp',
    'ok', 'id', 'ip', 'php', 'js', 'css', 'gdpr', 'jpg', 'jpeg', 'png', 'svg', 'gif', 'zip', 'docx', 'xlsx', 'xls', 'ods', 'ical', 'ics',
    'e-mail', 'email', 'wi-fi', 'wifi', 'sdk', 'cron', 'bearer', 'token', 'webhook', 'wordpress', 'youtube', 'dropbox', 'aws', 's3',
    'microinvest', 'ajur', 'plus', 'minus', 'salesforce', 'quickbooks', 'xero', 'authorization', 'content-type', 'application', 'accept', 'rest',
    'digitalocean', 'wasabi', 'minio', 'sku', 'rtl', 'gst', 'in', 'gitlab', 'miro', 'recaptcha', 'authenticator', 'maps', 'apple', 'android', 'ios',
    'play', 'store', 'testflight', 'onesignal', 'beams', 'cvv', 'ses', 'env', 'envato', 'codecanyon', 'laravel', 'whatsapp',
    'amount', 'x', 'n', 'm', 'a', 'b', 'c', 'd', 'e', 'f', 'u', 'v',
];

/** Literal / wrong Bulgarian terms => preferred term. Keep in sync with docs/mvp/features/07-translation.md. */
const BANNED = [
    'Помогне' => 'Помощ',
    'Обозначаване' => 'Длъжности',
    'Разноски' => 'Разходи',
    'Кредитна бележка' => 'Кредитни известия',
    'Частно табло за управление' => 'Лично табло',
    'Разширено табло за управление' => 'Разширено табло',
    'Доклади' => 'Справки',
];

$files = function (string $dir): array {
    $out = [];
    foreach (glob($dir . '/*.php') ?: [] as $f) {
        $out[basename($f)] = $f;
    }
    ksort($out);
    return $out;
};

$flatten = function (array $arr, string $prefix = '') use (&$flatten): array {
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += $flatten($v, $key);
        } elseif (is_string($v)) {
            $out[$key] = $v;
        } elseif ($v !== null) {
            $out[$key] = (string) $v;
        }
    }
    return $out;
};

$load = function (string $path): array {
    try {
        $r = include $path;
    } catch (Throwable $e) {
        fwrite(STDERR, "cannot load $path: " . $e->getMessage() . "\n");
        return [];
    }
    return is_array($r) ? $r : [];
};

$engFiles = $files($langDir . '/eng');
$bgFiles = $files($langDir . '/bg');
if (! $withSuper) {
    unset($engFiles['superadmin.php'], $bgFiles['superadmin.php']);
}
$names = array_unique(array_merge(array_keys($engFiles), array_keys($bgFiles)));
sort($names);

$headingKey = fn (string $k) => (bool) preg_match('/(^|\.)(menu|title|titles|heading|header|headers|tab|tabs|breadcrumb|page_title|menu_sections|menu_groups|columns?)(\.|_|$)|_title$|^title/i', $k);

$stripForText = function (string $s): string {
    $s = preg_replace('/<[^>]*>/u', '', $s);                           // HTML tags
    $s = preg_replace('/&[a-z]+;|&#\d+;/i', ' ', $s);                 // entities
    $s = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/u', '', $s);          // :placeholders
    $s = preg_replace('/\{[^}]*\}|\[\d+(?:,\s*[\d*]+)?\]|%[sd]|%\d\$[sd]/u', '', $s); // {0}, [1,*], %s
    return $s;
};
$placeholders = function (string $s): array {
    preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*|\{[^}]*\}|%[sd]|%\d\$[sd]/u', $s, $m);
    $p = $m[0];
    sort($p);
    return $p;
};
$firstLetter = function (string $s) use ($stripForText): ?string {
    if (preg_match('/^(?:<[^>]*>|\s)*(?::[a-zA-Z_]|\{|%[sd])/u', $s)) {
        return null; // starts with a placeholder: the sentence continues the substituted value
    }
    $t = $stripForText($s);
    if (preg_match('/\p{L}/u', $t, $m, PREG_OFFSET_CAPTURE)) {
        return $m[0][0];
    }
    return null;
};
$labelLike = fn (string $s) => mb_strlen($s) <= 60 && ! preg_match('/[.?!;:]\s*$|[.?!]\s+\S|,/u', $s);
$isUpperAcronym = fn (string $w) => mb_strtoupper($w) === $w && mb_strlen($w) >= 1;

$issues = [];
$add = function (string $type, string $file, string $key, ?string $eng, ?string $bg, ?string $note = null) use (&$issues, $onlyTypes, $onlyFile) {
    if ($onlyTypes && ! in_array($type, $onlyTypes, true)) {
        return;
    }
    if ($onlyFile && ! str_contains($file, $onlyFile)) {
        return;
    }
    $issues[] = ['type' => $type, 'file' => $file, 'key' => $key, 'eng' => $eng, 'bg' => $bg, 'note' => $note];
};

$stats = ['files' => count($names), 'keys_eng' => 0, 'keys_bg' => 0];
foreach ($names as $name) {
    $eng = isset($engFiles[$name]) ? $flatten($load($engFiles[$name])) : null;
    $bg = isset($bgFiles[$name]) ? $flatten($load($bgFiles[$name])) : null;
    if ($eng === null) {
        $add('missing_file_eng', $name, '*', null, null);
        $eng = [];
    }
    if ($bg === null) {
        $add('missing_file_bg', $name, '*', null, null);
        $bg = [];
    }
    $stats['keys_eng'] += count($eng);
    $stats['keys_bg'] += count($bg);

    foreach ($eng as $k => $v) {
        if (! array_key_exists($k, $bg) && isset($bgFiles[$name])) {
            $add('missing_in_bg', $name, $k, $v, null);
        }
    }
    foreach ($bg as $k => $v) {
        if (! array_key_exists($k, $eng) && isset($engFiles[$name])) {
            $add('missing_in_eng', $name, $k, null, $v);
        }
    }

    foreach ($eng as $k => $ev) {
        $heading = $headingKey($k);

        // ---- English ----
        $words = preg_split('/\s+/u', trim($stripForText($ev)));
        $words = array_values(array_filter($words, fn ($w) => preg_match('/\p{L}/u', $w)));
        if (count($words) >= 2 && $labelLike($ev)) {
            $small = ['a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'at', 'by', 'vs', 'per', 'with', 'from', 'as', 'is', 'into', 'via'];
            $notTitle = 0;
            $caps = 0;
            foreach (array_slice($words, 1) as $w) {
                $w = trim($w, '.,:;()"\'');
                if ($w === '' || in_array(mb_strtolower($w), $small, true) || $isUpperAcronym($w) || ! preg_match('/^\p{L}/u', $w)) {
                    continue;
                }
                if (mb_strtoupper(mb_substr($w, 0, 1)) === mb_substr($w, 0, 1)) {
                    $caps++;
                } else {
                    $notTitle++;
                }
            }
            if ($heading && $notTitle > 0 && $caps === 0) {
                $add('eng_case_heading', $name, $k, $ev, null);
            } elseif (! $heading && $caps >= 1 && $notTitle === 0 && count($words) >= 3) {
                $add('eng_case_label', $name, $k, $ev, null);
            }
        }

        // ---- Placeholders ----
        if (isset($bg[$k]) && $placeholders($ev) !== $placeholders($bg[$k])) {
            $add('placeholder_mismatch', $name, $k, $ev, $bg[$k], implode(' ', $placeholders($ev)) . ' | ' . implode(' ', $placeholders($bg[$k])));
        }
    }

    // ---- Bulgarian ----
    foreach ($bg as $k => $bv) {
        $heading = $headingKey($k);
        $ev = $eng[$k] ?? null;
        $text = $stripForText($bv);

        foreach (BANNED as $bad => $good) {
            if (mb_stripos($bv, $bad) !== false) {
                $add('banned_term', $name, $k, $ev, $bv, "$bad => $good");
            }
        }

        // Latin words that are not known terms
        preg_match_all('/[A-Za-z][A-Za-z0-9.\-]*/', $text, $m);
        $bad = [];
        foreach ($m[0] as $w) {
            $w2 = rtrim($w, '.-');
            if ($w2 === '' || in_array(strtolower($w2), LATIN_OK, true)) {
                continue;
            }
            if (strtoupper($w2) === $w2 && strlen($w2) <= 8) {
                continue;
            }
            if (preg_match('/^[a-z]{1,2}$/', $w2) && $heading === false) {
                continue;
            }
            $bad[] = $w2;
        }
        if ($bad) {
            $add('bg_latin', $name, $k, $ev, $bv, implode(', ', array_unique($bad)));
        }

        // Case
        $fl = $firstLetter($bv);
        if ($fl !== null && mb_strtolower($fl) === $fl && mb_strtoupper($fl) !== $fl) {
            $add('bg_lowercase_start', $name, $k, $ev, $bv, $labelLike($bv) ? 'label' : 'sentence');
        }
        if ($labelLike($bv)) {
            $words = preg_split('/\s+/u', trim($text));
            $caps = 0;
            foreach (array_slice($words, 1) as $w) {
                $w = trim($w, '.,:;()"\'„“«»-–—/');
                if ($w !== '' && preg_match('/^\p{Lu}\p{Ll}+/u', $w)) {
                    $caps++;
                }
            }
            if ($caps >= 1 && count($words) >= 2) {
                $add('bg_title_case', $name, $k, $ev, $bv);
            }
        }
    }
}

// Severity
$severity = function (array $i) use ($headingKey): string {
    if (in_array($i['type'], CRITICAL_TYPES, true)) {
        return 'critical';
    }
    $h = $i['key'] !== '*' && $headingKey($i['key']);
    if ($h && in_array($i['type'], ['bg_latin'], true)) {
        return 'critical';
    }
    if ($h && $i['type'] === 'bg_lowercase_start' && ($i['note'] ?? '') === 'label') {
        return 'critical';
    }
    return str_starts_with($i['type'], 'eng_case') ? 'info' : 'warn';
};
foreach ($issues as &$i) {
    $i['severity'] = $severity($i);
}
unset($i);

$summary = ['totals' => $stats, 'by_severity' => [], 'by_type' => [], 'by_file' => []];
foreach ($issues as $i) {
    $summary['by_severity'][$i['severity']] = ($summary['by_severity'][$i['severity']] ?? 0) + 1;
    $summary['by_type'][$i['type']] = ($summary['by_type'][$i['type']] ?? 0) + 1;
    $summary['by_file'][$i['file']][$i['type']] = ($summary['by_file'][$i['file']][$i['type']] ?? 0) + 1;
}
ksort($summary['by_file']);
arsort($summary['by_type']);

if (isset($opts['json']) && $opts['json'] !== true) {
    file_put_contents($opts['json'], json_encode(['summary' => $summary, 'issues' => $issues], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "JSON: {$opts['json']}\n";
}

// ---- Table ----
$types = array_keys($summary['by_type']);
printf("Files: %d, eng keys: %d, bg keys: %d\n", $stats['files'], $stats['keys_eng'], $stats['keys_bg']);
printf("Severity: %s\n\n", json_encode($summary['by_severity'], JSON_UNESCAPED_UNICODE));
$short = fn ($t) => str_replace(['missing_in_', 'missing_file_', 'placeholder_mismatch', 'banned_term', 'bg_lowercase_start', 'bg_title_case', 'bg_same_as_eng', 'bg_latin', 'eng_case_heading', 'eng_case_label'], ['miss>', 'nofile>', 'ph', 'banned', 'lower', 'Title', 'same', 'latin', 'engH', 'engL'], $t);
printf('%-46s', 'file');
foreach ($types as $t) {
    printf('%9s', $short($t));
}
echo "\n";
foreach ($summary['by_file'] as $f => $row) {
    printf('%-46s', mb_strimwidth($f, 0, 45, '~'));
    foreach ($types as $t) {
        printf('%9s', $row[$t] ?? '.');
    }
    echo "\n";
}
printf('%-46s', 'TOTAL');
foreach ($types as $t) {
    printf('%9d', $summary['by_type'][$t]);
}
echo "\n";

if (isset($opts['list'])) {
    echo "\n";
    usort($issues, fn ($a, $b) => [$a['severity'] === 'critical' ? 0 : ($a['severity'] === 'warn' ? 1 : 2), $a['file'], $a['key']] <=> [$b['severity'] === 'critical' ? 0 : ($b['severity'] === 'warn' ? 1 : 2), $b['file'], $b['key']]);
    foreach (array_slice($issues, 0, $limit) as $i) {
        printf("[%s] %s  %s :: %s\n    eng: %s\n    bg : %s%s\n", $i['severity'], $i['type'], $i['file'], $i['key'], $i['eng'] ?? '-', $i['bg'] ?? '-', $i['note'] ? "\n    note: {$i['note']}" : '');
    }
    if (count($issues) > $limit) {
        printf("... %d more (raise --limit or use --json)\n", count($issues) - $limit);
    }
}

exit(isset($opts['strict']) && ($summary['by_severity']['critical'] ?? 0) > 0 ? 1 : 0);
