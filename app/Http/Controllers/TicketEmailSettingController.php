<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\TicketEmailSetting\UpdateRequest;
use App\Models\TicketEmailSetting;

class TicketEmailSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.emailSync';
        $this->activeSettingMenu = 'ticket_settings';
        // databackd: had no check; same rule as TicketSettingController
        $this->middleware(function ($request, $next) {
            abort_403(!(user()->permission('manage_ticket_setting') == 'all' && in_array('tickets', user_modules())));

            return $next($request);
        });
    }

    public function update(UpdateRequest $request, $id)
    {
        $emailSetting = TicketEmailSetting::findOrFail($id);
        $data = $request->all();

        if ($request->has('status')) {
            $data['status'] = 1;

        } else {
            $data['status'] = 0;
        }

        $emailSetting->update($data);
        return Reply::success(__('messages.updateSuccess'));

    }

}
