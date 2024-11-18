<?php

// AFV (upgrade 6.0.19): app shell texts (notification bell, layout) that have no upstream key.
return [
    'notifications' => [
        // notifications/notification_fallback.blade.php: a notification type without its own bell view
        'generic' => 'You have a new notification.',
        'types' => [
            'employee_document_expiry_alert' => 'Employee document expiring',
            'estimate_request_accepted' => 'Estimate request accepted',
            'estimate_request_rejected' => 'Estimate request rejected',
            'event_completed' => 'Event completed',
            'event_invite_mention' => 'You were mentioned in an event',
            'immigration_expiry_alert' => 'Immigration document expiring',
            'invoice_reminder' => 'Invoice reminder',
            'late_clock_in' => 'Late clock-in',
            'late_clock_in_manager_admin' => 'Late clock-in of an employee',
            'lead_imported' => 'Leads imported',
            'mention_ticket_agent' => 'You were mentioned in a ticket',
            'new_expense_recurring_admin' => 'New recurring expense',
            'new_holiday' => 'New holiday',
            'new_issue' => 'New issue',
            'new_project_note' => 'New project note',
            'new_proposal' => 'New proposal',
            'new_ticket_note' => 'New ticket note',
            'payfast_payment_failed' => 'PayFast payment failed',
            'payfast_subscription_suspended' => 'PayFast subscription suspended',
            'project_note_updated' => 'Project note updated',
            'task_comment_admin' => 'New task comment',
        ],
    ],
];
