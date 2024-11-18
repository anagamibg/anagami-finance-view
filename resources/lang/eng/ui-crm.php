<?php

// AFV (upgrade 6.0.19): CRM texts (clients, deals, deal emails, lead forms) that have no upstream key.
return [
    'clients' => [
        'maxClientsReached' => 'You have reached the maximum number of clients allowed in your plan.',
    ],
    'dealEmails' => [
        'ccNotAllowed' => 'A copy can only be sent to the deal contact or to users of your company: :emails',
        'ccTooMany' => 'You can add up to :max addresses in copy.',
        'toMustBeContact' => 'The email can only be sent to the email address of the deal contact.',
    ],
    'emailTemplates' => [
        'add' => 'Add email template',
        'title' => [
            'add' => 'Add Email Template',
            'edit' => 'Edit Email Template',
        ],
    ],
    'leadForms' => [
        'title' => [
            'edit' => 'Edit Lead Form',
        ],
    ],
];
