<?php

// AFV (upgrade 6.0.19): CRM texts (clients, deals, deal emails, lead forms) that have no upstream key.
return [
    'clients' => [
        'maxClientsReached' => 'Достигнахте максималния брой клиенти, разрешен във Вашия план.',
    ],
    'dealEmails' => [
        'ccNotAllowed' => 'Копие може да се изпрати само до контакта по сделката или до потребители на Вашата компания: :emails',
        'ccTooMany' => 'Можете да добавите до :max адреса в копие.',
        'toMustBeContact' => 'Имейлът може да се изпрати само до имейл адреса на контакта по сделката.',
    ],
    'emailTemplates' => [
        'add' => 'Добави шаблон за имейл',
        'title' => [
            'add' => 'Нов шаблон за имейл',
            'edit' => 'Редакция на шаблон за имейл',
        ],
    ],
    'leadForms' => [
        'title' => [
            'edit' => 'Редакция на формуляр за потенциални клиенти',
        ],
    ],
];
