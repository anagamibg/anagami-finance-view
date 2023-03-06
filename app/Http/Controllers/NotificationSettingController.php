<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\EmailNotificationSetting;
use App\Models\GlobalSetting;
use App\Models\PusherSetting;
use App\Models\PushNotificationSetting;
use App\Models\SlackSetting;
use App\Models\SmtpSetting;
use Illuminate\Contracts\Encryption\DecryptException;

class NotificationSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.notificationSettings';
        $this->activeSettingMenu = 'notification_settings';
        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_notification_setting') !== 'all' && GlobalSetting::validateSuperAdmin('manage_superadmin_notification_settings'));

            return $next($request);
        });
    }

    public function index()
    {
        $tab = request('tab');

        // AFV (audit 2026-10-30, G10): toggles of modules outside the package (or hidden by config/databackd.php) are not
        // listed; they are posted back unchanged through hidden inputs, because saving resets every setting that is not posted
        $dbkSettingModules = [
            'new-expenseadded-by-admin' => ['expenses', null], 'new-expenseadded-by-member' => ['expenses', null], 'expense-status-changed' => ['expenses', null],
            'new-support-ticket-request' => ['tickets', 'tickets'], 'new-leave-application' => ['leaves', 'leaves'], 'task-completed' => ['tasks', null],
            'task-status-updated' => ['tasks', null], 'invoice-createupdate-notification' => ['invoices', null], 'discussion-reply' => ['projects', null],
            'new-product-purchase-request' => ['orders', 'orders'], 'lead-notification' => ['leads', 'leads'], 'order-createupdate-notification' => ['orders', 'orders'],
            'follow-up-reminder' => ['leads', 'leads'], 'employee-assign-to-project' => ['projects', null], 'new-notice-published' => ['notices', 'notices'],
            'user-assign-to-task' => ['tasks', null], 'birthday-notification' => ['employees', null], 'payment-notification' => ['payments', null],
            'appreciation-notification' => ['employees', 'appreciation'], 'holiday-notification' => ['holidays', 'holidays'], 'estimate-notification' => ['estimates', 'estimates'],
            'event-notification' => ['events', 'events'], 'message-notification' => ['messages', 'messages'], 'project-mention-notification' => ['projects', null],
            'task-mention-notification' => ['tasks', null], 'shift-assign-notification' => ['attendance', 'shifts'], 'late-clock-in' => ['attendance', 'attendance'],
            'late-clock-in-manager-admin' => ['attendance', 'attendance'],
        ];
        [$this->emailSettings, $this->dbkHiddenEmailSettings] = EmailNotificationSetting::all()->partition(function ($setting) use ($dbkSettingModules) {
            [$module, $hiddenKey] = $dbkSettingModules[$setting->slug] ?? [null, null];

            return (is_null($module) || in_array($module, user_modules())) && (is_null($hiddenKey) || !databackd_hidden('menu', $hiddenKey));
        });
        $this->emailSettings = $this->emailSettings->values();
        $this->slackSettings = SlackSetting::first();
        $this->pushSettings = PushNotificationSetting::first();
        $this->pusherSettings = PusherSetting::first();

        switch ($tab) {
        case 'slack-setting':
            $this->checkedAll = $this->emailSettings->count() == $this->emailSettings->filter(function ($value) {
                    return $value->send_slack == 'yes';
            })->count();

            $this->view = 'notification-settings.ajax.slack-setting';
            break;

        case 'push-notification-setting':
            $this->checkedAll = $this->emailSettings->count() == $this->emailSettings->filter(function ($value) {
                    return $value->send_push == 'yes';
            })->count();

            $this->view = 'notification-settings.ajax.push-notification-setting';
            break;

        case 'pusher-setting':
            $this->view = 'notification-settings.ajax.pusher-setting';
            break;

        default:
            $this->checkedAll = $this->emailSettings->count() == $this->emailSettings->filter(function ($value) {
                    return $value->send_email == 'yes';
            })->count();

            $this->smtpSetting = SmtpSetting::first();

            try {
                $this->smtpSetting->mail_password;
            }catch (DecryptException $e){
                // when we get message like below set password as null or o
                // The MAC is invalid.
                // The payload is invalid.
                $this->smtpSetting->mail_password = null;
                $this->smtpSetting->save();
            }
            $this->view = 'notification-settings.ajax.email-setting';
            break;
        }

        $this->activeTab = $tab ?: 'email-setting';

        if (request()->ajax()) {
            $html = view($this->view, $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle, 'activeTab' => $this->activeTab]);
        }

        return view('notification-settings.index', $this->data);
    }

}
