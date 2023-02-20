@if(!\App\Models\SmtpSetting::isVerified() && app()->environment('codecanyon'))
    <div class="col-lg-12 mt-2">
        <x-alert type="danger" icon="info-circle">
            {{ __('ui-misc.common.smtpNotConfigured') }}
        </x-alert>
    </div>
@endif
