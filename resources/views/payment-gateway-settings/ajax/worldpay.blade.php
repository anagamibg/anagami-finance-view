<div class="col-xl-12 col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-20">
    @include('sections.password-autocomplete-hide')
    <input type="hidden" name="payment_method" value="worldpay">

    <div class="row">
        <div class="col-lg-12 mb-3">
            <x-forms.checkbox :fieldLabel="__('modules.payments.worldpayStatus')" fieldName="worldpay_status"
                              fieldId="worldpay_status" fieldValue="active" fieldRequired="true"
                              :checked="($credentials->worldpay_status ?? 'deactive') == 'active'"/>
        </div>
    </div>
    <div class="row @if (($credentials->worldpay_status ?? 'deactive') == 'deactive') d-none @endif" id="worldpay_details">
        <div class="col-lg-6">
            <x-forms.select fieldId="worldpay_environment" :fieldLabel="__('app.selectEnvironment')"
                            fieldName="worldpay_environment" fieldRequired="true">
                <option value="sandbox"
                        @if (($credentials->worldpay_environment ?? 'sandbox') == 'sandbox') selected @endif>@lang('app.sandbox')</option>
                <option value="live"
                        @if (($credentials->worldpay_environment ?? 'sandbox') == 'live') selected @endif>@lang('app.live')</option>
            </x-forms.select>
        </div>

        <div class="col-lg-6">
            <x-forms.text class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.payments.worldpayMerchantEntity')"
                          fieldName="worldpay_merchant_entity" fieldId="worldpay_merchant_entity"
                          :fieldValue="$credentials->worldpay_merchant_entity" fieldRequired="true"></x-forms.text>
        </div>

        <div class="col-lg-6">
            <x-forms.label class="mt-3" fieldId="worldpay_username_label" :fieldLabel="__('modules.payments.worldpayUsername')"
                           fieldRequired="true">
            </x-forms.label>
            <x-forms.input-group>
                <input type="password" name="worldpay_username" id="worldpay_username"
                       class="form-control height-35 f-14"
                       value="{{ $credentials->worldpay_username }}" autocomplete="off">
                <x-slot name="preappend">
                    <button type="button" data-toggle="tooltip" data-original-title="{{ __('messages.viewKey') }}"
                            class="btn btn-outline-secondary border-grey height-35 toggle-password"><i
                            class="fa fa-eye"></i></button>
                </x-slot>
            </x-forms.input-group>
        </div>

        <div class="col-lg-6">
            <x-forms.label class="mt-3" fieldId="worldpay_password_label" :fieldLabel="__('modules.payments.worldpayPassword')"
                           fieldRequired="true">
            </x-forms.label>
            <x-forms.input-group>
                <input type="password" name="worldpay_password" id="worldpay_password"
                       class="form-control height-35 f-14"
                       value="{{ $credentials->worldpay_password }}" autocomplete="off">
                <x-slot name="preappend">
                    <button type="button" data-toggle="tooltip" data-original-title="{{ __('messages.viewKey') }}"
                            class="btn btn-outline-secondary border-grey height-35 toggle-password"><i
                            class="fa fa-eye"></i></button>
                </x-slot>
            </x-forms.input-group>
        </div>

        <div class="col-lg-6">
            <x-forms.text class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.payments.worldpayCheckoutId')"
                          fieldName="worldpay_checkout_id" fieldId="worldpay_checkout_id"
                          :fieldValue="$credentials->worldpay_checkout_id" fieldRequired="true"></x-forms.text>
        </div>

        <div class="col-lg-6">
            <x-forms.label class="mt-3" fieldId="worldpay_webhook_secret_label" :fieldLabel="__('modules.payments.worldpayWebhookSecret')"
                           fieldRequired="true">
            </x-forms.label>
            <x-forms.input-group>
                <input type="password" name="worldpay_webhook_secret" id="worldpay_webhook_secret"
                       class="form-control height-35 f-14"
                       value="{{ $credentials->worldpay_webhook_secret }}" autocomplete="off">
                <x-slot name="preappend">
                    <button type="button" data-toggle="tooltip" data-original-title="{{ __('messages.viewKey') }}"
                            class="btn btn-outline-secondary border-grey height-35 toggle-password"><i
                            class="fa fa-eye"></i></button>
                </x-slot>
            </x-forms.input-group>
        </div>

        <div class="col-lg-12">
            <x-forms.label fieldId="" :fieldLabel="__('app.webhook')">
            </x-forms.label>
            <p class="text-bold"><span id="webhook-link-text">{{ $webhookRoute }}</span>
                <a href="javascript:;" class="btn-copy btn-secondary f-12 rounded p-1 py-2 ml-1"
                   data-clipboard-target="#webhook-link-text">
                    <i class="fa fa-copy mx-1"></i>@lang('app.copy')</a>
            </p>
            <p class="text-primary">(@lang('messages.addWorldpayWebhookUrl'))</p>
        </div>
    </div>
</div>
<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <div class="d-flex">
            <x-forms.button-primary class="mr-3 w-100" icon="check" id="save_worldpay_data">@lang('app.save')
            </x-forms.button-primary>
        </div>
    </x-setting-form-actions>
</div>
<script src="{{ asset('vendor/jquery/clipboard.min.js') }}"></script>
<script>
    var clipboard = new ClipboardJS('.btn-copy');

    clipboard.on('success', function (e) {
        Swal.fire({
            icon: 'success',
            text: '@lang("app.webhookUrlCopied")',
            toast: true,
            position: 'top-end',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            customClass: {
                confirmButton: 'btn btn-primary',
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
        })
    });
</script>
