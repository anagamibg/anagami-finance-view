<?php

// AFV (upgrade 6.0.19): app shell texts (notification bell, layout) that have no upstream key.
return [
    'notifications' => [
        // notifications/notification_fallback.blade.php: a notification type without its own bell view
        'generic' => 'Имате ново известие.',
        'types' => [
            'employee_document_expiry_alert' => 'Изтичащ документ на служител',
            'estimate_request_accepted' => 'Заявката за оферта е приета',
            'estimate_request_rejected' => 'Заявката за оферта е отхвърлена',
            'event_completed' => 'Събитието е приключило',
            'event_invite_mention' => 'Споменаха Ви в събитие',
            'immigration_expiry_alert' => 'Изтичащ имиграционен документ',
            'invoice_reminder' => 'Напомняне за фактура',
            'late_clock_in' => 'Закъснение при отчитане на присъствие',
            'late_clock_in_manager_admin' => 'Закъснение на служител при отчитане на присъствие',
            'lead_imported' => 'Потенциалните клиенти са импортирани',
            'mention_ticket_agent' => 'Споменаха Ви в заявка за поддръжка',
            'new_expense_recurring_admin' => 'Нов повтарящ се разход',
            'new_holiday' => 'Нов празник',
            'new_issue' => 'Нов проблем',
            'new_project_note' => 'Нова бележка към проект',
            'new_proposal' => 'Ново предложение',
            'new_ticket_note' => 'Нова бележка към заявка за поддръжка',
            'payfast_payment_failed' => 'Неуспешно плащане през PayFast',
            'payfast_subscription_suspended' => 'Абонаментът през PayFast е спрян',
            'project_note_updated' => 'Бележката към проекта е обновена',
            'task_comment_admin' => 'Нов коментар към задача',
        ],
    ],
];
