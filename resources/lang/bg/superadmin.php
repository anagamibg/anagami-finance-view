<?php

// AFV (upgrade 6.0.19): partial Bulgarian superadmin file — only the texts that company users see
// (package limits, CUS_000329) and the new 6.0.19 keys. Every other superadmin.* key falls back to eng
// (fallback_locale), as before; lang-audit skips superadmin.php unless --with-superadmin is given.
return [
    'addPackage' => 'Добави пакет', // databackd: the super admin package list
    'maxEmployeesLimitReached' => 'Достигнахте максималния брой служители, разрешен от Вашия план. Надградете плана, за да добавяте още служители.',
    'maxClientsLimitReached' => 'Достигнахте максималния брой клиенти, разрешен от Вашия план. Надградете плана, за да добавяте още клиенти.',
    'planRenew' => [
        'subject' => 'Абонаментът е подновен',
        'text' => ':company поднови абонамента за :package.',
    ],
];
