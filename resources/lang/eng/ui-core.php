<?php

// S7 (P8): texts for the core screens (clients, invoices, payments, expenses, projects, tasks, employees, reports)
// that were hard-coded in views and controllers.
return [
    'titles' => [
        'department_hierarchy' => 'Department Hierarchy',
        'designation_hierarchy' => 'Designation Hierarchy',
    ],

    'common' => [
        'added_by_client' => '(Added by: :name)',
        'error' => 'Error',
    ],

    'messages' => [
        'php_zip_missing' => '<b>PHP-ZIP</b> extension is missing on your server. Please install the extension.',
        'installed_successfully' => 'Installed successfully.',
        'smtp_not_configured' => 'Please configure the SMTP details. Go to Settings -> Notification settings to set up SMTP.',
        'smtp_not_configured_employee' => 'Please configure the SMTP details to add an employee. Go to Settings -> Notification settings to set up SMTP.',
        'smtp_not_configured_project' => 'Please configure the SMTP details to add a project. Go to Settings -> Notification settings to set up SMTP.',
        'insert_error' => 'An error occurred while saving the data. Please try again or contact support.',
        'mollie_webhook_local' => 'The Mollie webhook works only on a live server. You can try ngrok. It will not work on localhost.',
        'transaction_failed' => 'The transaction failed.',
        'test_notification_sent' => 'Test notification sent.',
        'account_created' => 'The account was created successfully. You will be redirected to the dashboard shortly.',
        'webhook_alert_removed' => 'The webhook alert was removed permanently.',
        'select_comment' => 'Please select at least one comment.',
        'select_file' => 'Please select at least one file.',
        'transaction_id_duplicated' => 'Transaction ID ":id" is duplicated in this batch. Please use unique transaction IDs.',
        'transaction_id_exists' => 'Transaction ID ":id" already exists. Please use a unique transaction ID.',
        'attendance_disabled' => 'The attendance feature is currently disabled.',
        'invalid_date_time' => 'Invalid date or time format.',
    ],

    'dashboard' => [
        'todo_list' => 'To-do list',
        'very_important' => 'Very important',
        'update_modules_note' => '{1} Please update and reactivate the module :modules so that it works properly.|[2,*] Please update and reactivate the modules :modules so that they work properly.',
        'update_custom_modules' => 'Update custom modules',
    ],

    'finance' => [
        'tax_deducted' => ':tax (deducted)',
        'discount_exceeds_total' => 'The discount cannot be more than the total amount.',
    ],

    'bank' => [
        'sort_code' => 'Sort code',
        'enter_sort_code' => 'Enter sort code',
    ],

    'projects' => [
        'projects_data' => 'Project data',
        'rating_poor' => 'Poor',
        'rating_fair' => 'Fair',
        'rating_good' => 'Good',
        'rating_excellent' => 'Excellent',
        'milestone_cannot_be_linked' => 'The milestone cannot be linked.',
        'serial_no' => 'No.',
    ],

    'tasks' => [
        'view_all_members' => 'View all (:count members)',
        'people_assigned' => '{0} No people assigned|{1} :count person assigned|[2,*] :count people assigned',
        'approval_message' => 'Approval Message',
        'duplicate_files' => 'Duplicate files',
        'duplicate_sub_tasks' => 'Duplicate subtasks',
        'duplicate_comments' => 'Duplicate comments',
        'duplicate_timesheet' => 'Duplicate timesheet',
        'duplicate_notes' => 'Duplicate notes',
        'duplicate_history' => 'Duplicate history',
    ],

    'reports' => [
        'monthly_values_by_stage' => 'Monthly Values by Stage',
    ],
];
