@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\View;

    $notificationType = Str::snake(class_basename($notification->type));
    $primaryView = 'notifications.' . $userType . '.' . $notificationType;
    $alternateType = $userType === 'client' ? 'all' : 'client';
    $alternateView = 'notifications.' . $alternateType . '.' . $notificationType;
    $rendered = false;

    $candidateViews = [];

    if (view()->exists($primaryView)) {
        $candidateViews[] = $primaryView;
    }

    if (isWorksuiteSaas()) {
        $superadminView = 'notifications.superadmin.' . $notificationType;

        if (view()->exists($superadminView)) {
            $candidateViews[] = $superadminView;
        }
    }

    foreach ($worksuitePlugins as $item) {
        $pluginView = strtolower($item) . '::notifications.' . $notificationType;

        if (View::exists($pluginView)) {
            $candidateViews[] = $pluginView;
        }
    }

    if (view()->exists($alternateView)) {
        $candidateViews[] = $alternateView;
    }

    // AFV (audit 2026-10-30, G09): a notification of a module that is off for the receiver (package or role) would
    // link to a 403 page - it is rendered by notification_fallback instead (link to the notification list)
    $dbkNotificationModules = [
        '/^(new_task|task_|sub_task_)/' => 'tasks', '/^(new_ticket|ticket_)/' => 'tickets',
        '/^(leave_|new_leave_request|multiple_leave_application)/' => 'leaves', '/^event_/' => 'events',
        '/^(new_expense|expense_recurring_status)/' => 'expenses', '/^(new_project_|project_|new_discussion|new_rating)/' => 'projects',
        '/^(new_notice|notice_update)/' => 'notices', '/^(lead_|new_lead_created|deal_stage_updated|auto_follow_up_reminder|proposal_signed)/' => 'leads',
        '/^(new_estimate_request|estimate_declined)/' => 'estimates', '/^(new_order|order_updated|new_product_purchase_request)/' => 'orders',
        '/^contract_signed/' => 'contracts', '/^invoice_payment_received/' => 'payments', '/^(new_chat|new_mention_chat)/' => 'messages',
        '/^(new_appreciation|promotion_|birthday_reminder)/' => 'employees', '/^(shift_|request_regularis)/' => 'attendance',
        '/^time_tracker_reminder/' => 'timelogs', '/^new_customer/' => 'clients',
        '/^(analysis_request_|team_request_)/' => 'databackd_teams', '/^fin_alert_notification/' => 'databackd_analytics',
    ];

    if (!databackd_is_superadmin()) {
        foreach ($dbkNotificationModules as $dbkPattern => $dbkModule) {
            if (preg_match($dbkPattern, $notificationType)) {
                $dbkModuleOn = str_starts_with($dbkModule, 'databackd_') ? databackd_module($dbkModule) : in_array($dbkModule, user_modules());

                if (!$dbkModuleOn) {
                    $candidateViews = [];
                }

                break;
            }
        }
    }
@endphp

@foreach ($candidateViews as $candidateView)
    @if(!$rendered)
        @php ob_start(); @endphp
        @include($candidateView)
        @php
            $html = trim(ob_get_clean());

            if ($html !== '') {
                echo $html;
                $rendered = true;
            }
        @endphp
    @endif
@endforeach

@if(!$rendered)
    @include('notifications.notification_fallback')
@endif
