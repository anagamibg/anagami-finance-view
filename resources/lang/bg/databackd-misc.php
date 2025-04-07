<?php

return [
    'close' => 'Затвори',
    'counterparty_side' => 'Вид контрагент',
    'source_rate_limited' => 'Твърде много опити за изтегляне на данни. Опитайте отново след :seconds сек.',

    // Layout (skip link before the sidebar/topbar)
    'skip_to_content' => 'Към основното съдържание',
    'open_menu' => 'Отвори менюто',

    // Spreadsheet / CSV import limits (FinEntryImporter)
    'import' => [
        'too_many_sheets' => 'Каченият файл съдържа твърде много листове.',
        'too_large_cells' => 'Каченият файл е твърде голям (твърде много редове или колони).',
        'too_large_rows' => 'Каченият файл е твърде голям (твърде много редове).',
    ],

    // Google Sheets / CSV URL reader messages that are not in databackd-data-sources.errors
    'sheets' => [
        'redirect_blocked' => 'Източникът пренасочва към непозволен адрес.',
        'url_not_allowed' => 'Адресът не е разрешен. Поддържат се само https адреси на Google Sheets (docs.google.com).',
        'too_large' => 'Файлът е твърде голям (максимум :max MB).',
        'too_many_redirects' => 'Адресът пренасочва твърде много пъти.',
        'too_many_rows' => 'Файлът има твърде много редове (максимум :max).',
    ],
];
