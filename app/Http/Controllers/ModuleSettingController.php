<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\ModuleSetting;
use Illuminate\Http\Request;

class ModuleSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.moduleSettings';
        $this->activeSettingMenu = 'module_settings';
        $this->middleware(function ($request, $next) {
            abort_403(!(user()->permission('manage_module_setting') == 'all'));
            return $next($request);
        });
    }

    public function index()
    {

        $tab = request('tab');

        // AFV (S8): the databackd_* modules are controlled by the package only, not by the company admin
        $base = ModuleSetting::where('module_name', '<>', 'settings')->where('module_name', 'not like', 'databackd\\_%')->where('is_allowed', 1);

        $this->modulesData = match ($tab) {
            'employee' => $base->where('type', 'employee')->get(),
            'client' => $base->where('type', 'client')->get(),
            default => $base->where('type', 'admin')->get(),
        };

        $this->view = 'module-settings.ajax.modules';
        $this->activeTab = $tab ?: 'admin';

        if (request()->ajax()) {
            $html = view($this->view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle, 'activeTab' => $this->activeTab]);
        }

        return view('module-settings.index', $this->data);
    }

    public function update(Request $request, $id)
    {
        $setting = ModuleSetting::findOrFail($id);
        $setting->status = $request->status;
        $setting->save();

        return Reply::success(__('messages.updateSuccess'));
    }

}
