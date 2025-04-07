<?php

return [
    'close' => 'Close',
    'counterparty_side' => 'Counterparty type',
    'source_rate_limited' => 'Too many attempts to download data. Try again in :seconds sec.',

    // Layout (skip link before the sidebar/topbar)
    'skip_to_content' => 'Skip to main content',
    'open_menu' => 'Open menu',

    // Spreadsheet / CSV import limits (FinEntryImporter)
    'import' => [
        'too_many_sheets' => 'The uploaded file has too many sheets.',
        'too_large_cells' => 'The uploaded file is too large (too many rows or columns).',
        'too_large_rows' => 'The uploaded file is too large (too many rows).',
    ],

    // Google Sheets / CSV URL reader messages that are not in databackd-data-sources.errors
    'sheets' => [
        'redirect_blocked' => 'The source redirects to a disallowed address.',
        'url_not_allowed' => 'The address is not allowed. Only https addresses of Google Sheets (docs.google.com) are supported.',
        'too_large' => 'The file is too large (maximum :max MB).',
        'too_many_redirects' => 'The address redirects too many times.',
        'too_many_rows' => 'The file has too many rows (maximum :max).',
    ],
];
