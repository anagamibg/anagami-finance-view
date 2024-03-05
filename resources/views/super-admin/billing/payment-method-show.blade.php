<style>
    .stripe-button-el {
        display: none;
    }

    .displayNone {
        display: none;
    }

    .checkbox-inline, .radio-inline {
        vertical-align: top !important;
    }

    .box-height {
        height: 78px;
    }

    .button-center {
        display: flex;
        justify-content: center;
    }
    .paymentMethods{display: none; transition: 0.3s;}
    .paymentMethods.show{display: block;}

    .stripePaymentForm{display: none; transition: 0.3s;}
    .stripePaymentForm.show{display: block;}

    .authorizePaymentForm{display: none; transition: 0.3s;}
    .authorizePaymentForm.show{display: block;}

    .worldpayPaymentForm{display: none; transition: 0.3s;}
    .worldpayPaymentForm.show{display: block;}

    div#card-element {
        width: 100%;
        color: #4a5568;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        line-height: 1.25;
        border-width: 1px;
        border-radius: 0.25rem;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        border-style: solid;
        border-color: #e2e8f0;
    }

    .paystack-form {
        display: inline-block;
        position: relative;
    }

    .payment-type button {
        margin: 5px;
        float: none;
        width: 170px;
    }

    #offlineBox label {
        font-size: 18px;
        font-weight: 500;
    }
</style>
<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">
        @if($free)
            @lang('superadmin.packages.choosePlan')
        @else
            @lang('superadmin.choosePaymentMethod')
        @endif
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <div class="form-body">
        @if(!$free)
            <div class="row paymentMethods show">

                <div class="col-12 col-sm-12 mt-40" id="onlineBox">
                    @if(($paymentGatewatActive))
                        <div class="payment-type">

                            @if($stripeSettings->paypal_status == 'active')
                                <button class="btn-light border rounded f-15 btn px-4 py-3 paypalPayment" type="submit" >
                                    <img style="height: 15px;"  src="{{ asset('img/paypal.png') }}">
                                    @lang('app.paypal')
                                </button>
                            @endif

                            @if($stripeSettings->stripe_status == 'active')
                                <button class="btn-light border rounded f-15 btn px-4 py-3 stripePay" type="submit" >
                                    <img style="height: 15px;"  src="{{ asset('img/stripe.png') }}">
                                    @lang('app.stripe')
                                </button>
                            @endif

                            @if($stripeSettings->razorpay_status == 'active')
                                <button class="btn-light border rounded f-15 btn px-4 py-3" type="submit" onclick="razorpaySubscription();">
                                    <img style="height: 15px;" src="{{ asset('img/razorpay.png') }}">
                                    @lang('app.razorpay')
                                </button>
                            @endif

                            @if($stripeSettings->paystack_status == 'active')
                                <form id="paystack-form" action="{{ route('billing.paystack') }}"
                                      class="paystack-form d-inline" method="POST">
                                    <input type="hidden" id="name" name="name" value="{{ $user->name }}">
                                    <input type="hidden" id="paystackEmail" name="paystackEmail"
                                           value="{{ company()->company_email }}">
                                    <input type="hidden" name="plan_id" value="{{ $package->id }}">
                                    <input type="hidden" name="type" value="{{ $type }}">
                                    <input type="hidden" name="package_type" value="lifetime">

                                    @csrf
                                    <button class="btn-light border rounded f-15 btn px-4 py-3" type="submit" id="card-button">
                                        <img id="company-logo-img" style="height: 15px" src="{{ asset('img/paystack.jpg') }}">
                                        @lang('app.paystack')
                                    </button>
                                </form>
                            @endif

                            @if($stripeSettings->mollie_status == 'active')
                                <form id="mollie-form" action="{{ route('billing.mollie') }}" class="mollie-form d-inline" method="POST">
                                    <input type="hidden" id="name" name="name" value="{{ $user->name }}">
                                    <input type="hidden" id="mollieEmail" name="mollieEmail" value="{{ $user->email }}">
                                    <input type="hidden" name="plan_id" value="{{ $package->id }}">
                                    <input type="hidden" name="type" value="{{ $type }}">
                                    {{ csrf_field() }}
                                    <button class="btn-light border rounded f-15 btn px-4 py-3 molliePay" type="submit">
                                            <img style="height: 20px;"  src="{{ asset('img/mollie.png') }}"> @lang('app.mollie')
                                    </button>
                                </form>

                            @endif

                            @if($stripeSettings->payfast_status == 'active')
                                {!! $payFastHtml !!}
                            @endif

                            @if(($stripeSettings->worldpay_status ?? 'deactive') == 'active' && !empty($stripeSettings->worldpay_checkout_id) && $worldpayAmount > 0)
                                <button class="btn-light border rounded f-15 btn px-4 py-3 worldpayPay" type="button">
                                    <img style="height: 15px;" src="{{ asset('img/worldpay.svg') }}">
                                    @lang('app.worldpay')
                                </button>
                            @endif

                            @if($stripeSettings->authorize_api_login_id != null && $stripeSettings->authorize_transaction_key != null  && $stripeSettings->authorize_status == 'active')
                                <button class="btn-light border rounded f-15 btn px-4 py-3 authroizePay" type="submit"
                                        data-toggle="modal" data-target="#authorizeModal" data-placement="top"
                                        id="card-button" title="Choose Plan">
                                    <img id="company-logo-img" style="height: 15px"
                                         src="{{ asset('img/authorize.jpg') }}">
                                    @lang('app.authorize')
                                </button>
                            @endif

                            @if($methods->count() > 0)
                                <button class="btn-light border rounded f-15 btn px-4 py-3" type="button" onclick="showButton('offline')">
                                    @lang('modules.invoices.payOffline')
                                </button>
                            @endif

                        </div>
                    @endif
                </div>
                <div class="col-12 col-sm-12 mt-40">
                    @if($methods->count() > 0)
                        <div class="form-group my-3 @if(($paymentGatewatActive)) d-none @endif" id="offlineBox">
                            <div class="my-3">
                                @if($paymentGatewatActive)
                                    <button class="btn-light border rounded f-15 btn px-4 py-3" type="button" onclick="showButton('online')">
                                        <i class="fa fa-globe"></i>
                                        @lang('superadmin.payOnline')
                                    </button>
                                @endif
                            </div>

                                @foreach($methods as $key => $method)
                                    <div class='card border mb-3'>
                                        <div class="card-header bg-white border-0  d-flex justify-content-between p-20">
                                            <x-forms.radio :fieldId="'offline'.$key"
                                                        :fieldLabel="$method->name"
                                                        fieldName="offlineMethod" :checked="$key == 0"
                                                        :fieldValue="$method->id" />

                                        </div>

                                        <div class="card-body pt-0">
                                            {!! nl2br($method->description) !!}
                                        </div>
                                    </div>
                                @endforeach

                            <div class="row">
                                <div class="col-md-12 " id="methodDetail">
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="row stripePaymentForm">
                @if($stripeSettings->stripe_status == 'active')
                    <div class="col-sm-12">
                        <form id="stripe-payment-form" action="{{ route('billing.stripe') }}" method="POST">
                        {{-- <form id="stripe-payment-form" action="{{ route('billing.stripeNew',[$company->id]) }}" method="POST"> --}}

                            <input type="hidden" id="name" name="name" value="{{ $user->name }}">
                            <input type="hidden" id="stripeEmail" name="stripeEmail" value="{{ user()->email }}">
                            <input type="hidden" name="plan_id" value="{{ $package->id }}">
                            <input type="hidden" name="type" value="{{ $type }}">
                            {{ csrf_field() }}
                            <div class="form-body">
                                <div class="row" id="addressDetail">
                                    <div class="col-lg-12 col-md-12">
                                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.name')"
                                                      fieldName="clientName"
                                                      fieldId="clientName"
                                                      :fieldPlaceholder="__('modules.stripeCustomerAddress.name')"
                                                      fieldValue="" :fieldRequired="true"/>
                                    </div>
                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.city')"
                                                      fieldName="city"
                                                      fieldId="city"
                                                      :fieldPlaceholder="__('modules.stripeCustomerAddress.city')"
                                                      fieldValue="" :fieldRequired="true"/>
                                    </div>
                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.state')"
                                                      fieldName="state"
                                                      fieldId="state"
                                                      :fieldPlaceholder="__('modules.stripeCustomerAddress.state')"
                                                      fieldValue="" :fieldRequired="true"/>
                                    </div>
                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.select fieldId="country"
                                                        :fieldLabel="__('modules.stripeCustomerAddress.country')"
                                                        fieldName="country" search="true" :fieldRequired="true">
                                            @foreach($countries as $country)
                                                <option value="{{ $country->iso }}">{{ $country->nicename }}</option>
                                            @endforeach
                                        </x-forms.select>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2"
                                                              :fieldLabel="__('modules.stripeCustomerAddress.line1')"
                                                              fieldName="line1" fieldId="line1"
                                                              :fieldPlaceholder="__('modules.stripeCustomerAddress.line1')"
                                                              fieldValue="" :fieldRequired="true">
                                            </x-forms.textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>
                @endif
            </div>
            <div class="authorizePaymentForm">
                <div id="alert"></div>
                @if($stripeSettings->authorize_status == 'active')
                    <div class="m-l-10">
                        <form id="authorize-form">

                            <input type="hidden" id="name" name="name" value="{{ $user->name }}">
                            <input type="hidden" id="email" name="email" value="{{ $user->email }}">
                            <input type="hidden" name="plan_id" value="{{ $package->id }}">
                            <input type="hidden" name="type" value="{{ $type }}">
                            <input type="hidden" name="package_type" value="{{ $package->package_type }}">

                            {{ csrf_field() }}
                            <div class="form-body">
                                <div class="row">
                                    <div class="col-lg-12 col-md-12">
                                        <x-forms.text :fieldLabel="__('modules.stripeCustomerAddress.name')"
                                                      fieldName="owner"
                                                      fieldId="owner"
                                                      :fieldPlaceholder="__('modules.stripeCustomerAddress.name')"
                                                      fieldValue="{{ user()->name ?? '' }}" :fieldRequired="true"/>
                                    </div>
                                    <div class="col-lg-12 col-md-12">
                                        <x-forms.text :fieldLabel="__('modules.authorize.cardNumber')"
                                                      fieldName="card_number"
                                                      fieldId="card_number"
                                                      :fieldPlaceholder="__('modules.authorize.cardNumber')"
                                                      fieldValue="" :fieldRequired="true"/>
                                    </div>
                                    @php
                                        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
                                    @endphp

                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.select fieldId="expiration_month"
                                                        :fieldLabel="__('modules.authorize.expMonth')"
                                                        fieldName="expiration_month" fieldRequired="true">
                                            @foreach($months as $key => $month)
                                                <option value="{{ $key }}">{{ $month }}</option>
                                            @endforeach
                                        </x-forms.select>
                                    </div>
                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.select fieldId="expiration_year"
                                                        :fieldLabel="__('modules.authorize.expYear')"
                                                        fieldName="expiration_year" fieldRequired="true">
                                            @for ($i = 0; $i < 15; $i++)
                                                <option value="{{ date('Y') + $i }}">{{ date('Y') + $i }}</option>
                                            @endfor
                                        </x-forms.select>
                                    </div>

                                    <div class="col-lg-4 col-md-4">
                                        <x-forms.number :fieldLabel="__('modules.authorize.cvv')" fieldName="cvv"
                                                        fieldId="cvv" :fieldPlaceholder="__('modules.authorize.cvv')"
                                                        fieldValue="" :fieldRequired="true"/>
                                    </div>
                                </div>
                            </div>
                            {{--                            <div class="flex flex-wrap mt-6" style="margin-top: 15px; text-align: center">--}}
                            {{--                                <button type="button" id="authorize-button" class="btn btn-success inline-block align-middle text-center select-none border font-bold whitespace-no-wrap py-2 px-4 rounded text-base leading-normal no-underline text-gray-100 bg-blue-500 hover:bg-blue-700">--}}
                            {{--                                    <img height="15px" id="company-logo-img" src="{{ asset('img/authorize.jpg') }}"> {{ __('Pay') }}--}}
                            {{--                                </button>--}}
                            {{--                            </div>--}}
                        </form>

                    </div>
                @endif
            </div>
            @if(($stripeSettings->worldpay_status ?? 'deactive') == 'active' && !empty($stripeSettings->worldpay_checkout_id) && $worldpayAmount > 0)
                {{-- AFV (upgrade 6.0.19): device-data frame title and expiry placeholder through __() (S7) --}}
                <div class="worldpayPaymentForm">
                    <div id="worldpay-error" class="alert alert-danger d-none"></div>
                    <p class="f-14 text-dark-grey mb-3">
                        @lang('modules.worldpay.amountDue'):
                        <strong>{{ $worldpayCurrency }} {{ number_format((float) $worldpayAmount, 2) }}</strong>
                    </p>
                    <form id="worldpay-card-form" action="javascript:;" method="POST">
                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-1">@lang('modules.authorize.cardNumber')</label>
                            <section id="worldpay-card-pan" class="border-grey" style="height: 40px;"></section>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="f-14 text-dark-grey mb-1">@lang('modules.authorize.expMonth')</label>
                                    <section id="worldpay-card-expiry" class="border-grey" style="height: 40px;"></section>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="f-14 text-dark-grey mb-1">@lang('modules.authorize.cvv')</label>
                                    <section id="worldpay-card-cvv" class="border-grey" style="height: 40px;"></section>
                                </div>
                            </div>
                        </div>
                        @if ($worldpayStoreCard)
                            <div class="form-group">
                                <label class="f-14 text-dark-grey">
                                    <input type="checkbox" id="worldpay-agreement" value="1">
                                    @lang('modules.worldpay.storeCardForSubscription')
                                </label>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary rounded f-14" id="worldpay-pay">@lang('modules.invoices.payNow')</button>
                    </form>
                    <iframe name="worldpay-ddc-frame" id="worldpay-ddc-frame" title="{{ __('ui-billing.worldpay.device_data_frame') }}" style="display:none;width:0;height:0;border:0;"></iframe>
                    <form id="worldpay-ddc-form" method="POST" target="worldpay-ddc-frame" action="about:blank">
                        <input type="hidden" name="JWT" value="">
                        <input type="hidden" name="Bin" value="">
                    </form>
                    <div id="worldpay-challenge-wrap" class="d-none mt-3">
                        <p class="f-14 text-dark-grey">@lang('modules.worldpay.challengeHelp')</p>
                        <iframe name="worldpay-challenge-frame" id="worldpay-challenge-frame" title="@lang('modules.worldpay.challengeHelp')" style="width:100%;height:420px;border:1px solid #e8e8e8;"></iframe>
                        <form id="worldpay-challenge-form" method="POST" target="worldpay-challenge-frame" action="about:blank">
                            <input type="hidden" name="JWT" value="">
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="row">
                <div class="col-sm-12">

                    @lang($package->default == 'yes' ? 'superadmin.choseDefaultPlan' : 'superadmin.choseFreePlan')
                </div>
            </div>
        @endif
    </div>
</div>
<div class="modal-footer">
    @if($stripeSettings->stripe_status == 'active')
        <div id="stripeButton">
            <x-forms.button-primary id="save-stripe-detail" class="d-none">@lang('app.save') <i
                    class="fa fa-arrow-right pl-1"></i></x-forms.button-primary>
        </div>

    @endif
    @if($stripeSettings->authorize_status == 'active')
        <div id="authorizeButton">
            <x-forms.button-primary id="authorize-button" class="d-none">@lang('app.save') <i
                    class="fa fa-arrow-right pl-1"></i></x-forms.button-primary>
        </div>

        @endif
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>

        @if(count($methods) > 0 && !$free)
            <div id="offlineButton">
                @php
                    $class = 'd-none';
                    if(!$paymentGatewatActive) $class = '';
                @endphp
                <x-forms.button-primary type="button" class="{{ $class }}" @endif id="save-offline" onclick="selectOffline('{{ $package->id }}')">@lang('app.select')</x-forms.button-primary>
            </div>
        @endif
        @if($free)
            <button type="button" class="btn btn-success waves-effect" onclick="selectFreePlan();" data-dismiss="modal">@lang('messages.confirm')</button>
        @endif
    </div>
</div>

<script>
    $(".select-picker").selectpicker();

    function selectFreePlan() {
        var plan_id = '{{ $package->id }}';
        $.easyAjax({
            url: '{{ route('billing.free-plan') }}',
            type: "POST",
            redirect: true,
            blockUI: true,
            data: {
                'package_id': plan_id,
                'type': '{{ $type }}',
                '_token': '{{ csrf_token() }}'
            }
        })
    }
</script>
@if(!$free)

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <script>
        $('#authorize-button').click(function () {
            $.easyAjax({
                url: '{{ route('billing.authorize') }}',
                type: "POST",
                data: $('#authorize-form').serialize(),
                container: '.modal-content',
                messagePosition: "inline",
                disableButton: true,
                buttonSelector: "#authorize-button",
                success: function (response) {
                    if (response.status == 'success') {
                        $('#authorize-form').remove();
                        setInterval(checkWebhook, 20000)
                    }
                }

            })
        })

        function checkWebhook() {
            $.easyAjax({
                url: '{{ route('billing.check-authorize-subscription') }}',
                type: "POST",
                data: {package_id: '{{ $package->id }}', type: '{{ $type }}', '_token': '{{ csrf_token() }}'},
                container: '.modal-content',
                success: function (response) {
                    if (response.status == 'success' && response.webhook) {
                        window.location.reload();
                    }
                }

            })
        }
    </script>
    <script>
        // $('#save-stripe-detail').on('click', function () {
        //     let packageType = '{{ $package->package_type }}'; // Pass package type from Blade to JS
        //     let url = (packageType === 'lifetime') ? '{{ route('billing.lifetime') }}' : '{{ route('billing.stripe-validate') }}';

        //     $.easyAjax({
        //         url: url,
        //         type: "POST",
        //         blockUI: true,
        //         disableButton: true,
        //         buttonSelector: "#save-stripe-detail",
        //         data: $('#stripe-payment-form').serialize(),
        //         container: '.modal-content',
        //         success: function (response) {
        //             $('.modal-footer #stripeButton').html(response.buttonView);
        //             $('#stripe-payment-form').html(response.view);
        //         }
        //     });
        // });
         $('#save-stripe-detail').on('click', function () {
            $.easyAjax({
                url: '{{ route('billing.stripe-validate') }}',
                type: "POST",
                blockUI: true,
                disableButton: true,
                buttonSelector: "#save-stripe-detail",
                data: $('#stripe-payment-form').serialize(),
                container: '.modal-content',
                success: function (response) {
                    $('.modal-footer #stripeButton').html(response.buttonView);
                    $('#stripe-payment-form').html(response.view);
                }

            });
        });


        $('.stripePay').click(function (e) {
            e.preventDefault();
            $('.paymentMethods').removeClass('show');
            $('.stripePaymentForm').addClass('show');
            $('#stripeButton #save-stripe-detail').removeClass('d-none');
            $('.modal-title').text('Enter Your Card Details');
        });

        $('.authroizePay').click(function (e) {
            e.preventDefault();
            $('.paymentMethods').removeClass('show');
            $('.authorizePaymentForm').addClass('show');
            $('#authorizeButton #authorize-button').removeClass('d-none');
            $('.modal-title').text('Enter Your Card Details');
        });

        @if(($stripeSettings->worldpay_status ?? 'deactive') == 'active' && !empty($stripeSettings->worldpay_checkout_id) && $worldpayAmount > 0)
        (function () {
            var worldpayReady = false;
            var currentReference = null;
            var waitingForDeviceData = false;
            var ddcTimer = null;
            var payUrl = "{{ route('billing.worldpay') }}";
            var deviceUrl = "{{ route('billing.worldpay.device-data') }}";
            var challengeUrl = "{{ route('billing.worldpay.challenge') }}";
            var token = "{{ csrf_token() }}";

            function showWorldpayError(message) {
                var box = document.getElementById('worldpay-error');
                box.textContent = message;
                box.classList.remove('d-none');
            }

            function deviceData() {
                return {
                    userAgentHeader: navigator.userAgent,
                    browserLanguage: navigator.language || 'en-GB',
                    browserScreenHeight: screen.height || 800,
                    browserScreenWidth: screen.width || 400,
                    browserJavaEnabled: typeof navigator.javaEnabled === 'function' ? navigator.javaEnabled() : false,
                    browserColorDepth: String(screen.colorDepth || 24),
                    timeZone: String(new Date().getTimezoneOffset())
                };
            }

            function handlePayResponse(response) {
                if (!response || response.action === 'redirect') {
                    return;
                }

                if (response.action === 'device_data') {
                    currentReference = response.reference;
                    startDeviceData(response.deviceDataCollection || {});
                    return;
                }

                if (response.action === 'challenge') {
                    currentReference = response.reference;
                    startChallenge(response.challenge || {});
                }
            }

            function postStep(url, data) {
                data._token = token;
                data.reference = currentReference;

                $.easyAjax({
                    url: url,
                    type: 'POST',
                    blockUI: true,
                    container: '.modal-content',
                    data: data,
                    success: handlePayResponse
                });
            }

            function startDeviceData(collection) {
                waitingForDeviceData = true;
                var ddcForm = document.getElementById('worldpay-ddc-form');
                ddcForm.action = collection.url;
                ddcForm.querySelector('[name=JWT]').value = collection.jwt || '';
                ddcForm.querySelector('[name=Bin]').value = collection.bin || '';
                ddcForm.submit();

                ddcTimer = setTimeout(function () {
                    if (waitingForDeviceData) {
                        waitingForDeviceData = false;
                        showWorldpayError(@json(__('modules.worldpay.deviceDataTimeout')));
                    }
                }, 12000);
            }

            function startChallenge(challenge) {
                document.getElementById('worldpay-card-form').classList.add('d-none');
                document.getElementById('worldpay-challenge-wrap').classList.remove('d-none');
                var challengeForm = document.getElementById('worldpay-challenge-form');
                challengeForm.action = challenge.url;
                challengeForm.querySelector('[name=JWT]').value = challenge.jwt || '';
                challengeForm.submit();
            }

            function initWorldpayCheckout() {
                if (typeof Worldpay === 'undefined' || !Worldpay.checkout) {
                    showWorldpayError(@json(__('modules.worldpay.unavailableBilling')));
                    return;
                }

                Worldpay.checkout.init({
                    id: @json($stripeSettings->worldpay_checkout_id),
                    form: '#worldpay-card-form',
                    fields: {
                        pan: {selector: '#worldpay-card-pan', placeholder: '4444 3333 2222 1111'},
                        expiry: {selector: '#worldpay-card-expiry', placeholder: @json(__('ui-billing.worldpay.expiry_placeholder'))},
                        cvv: {selector: '#worldpay-card-cvv', placeholder: '123'}
                    },
                    enablePanFormatting: true
                }, function (error, checkout) {
                    if (error) {
                        showWorldpayError(error.message || @json(__('modules.worldpay.errorMessage')));
                        return;
                    }

                    document.getElementById('worldpay-card-form').addEventListener('submit', function (event) {
                        event.preventDefault();
                        document.getElementById('worldpay-error').classList.add('d-none');

                        @if ($worldpayStoreCard)
                        if (!document.getElementById('worldpay-agreement').checked) {
                            showWorldpayError(@json(__('modules.worldpay.agreementRequiredSubscription')));
                            return;
                        }
                        @endif

                        checkout.generateSessionState(function (sessionError, sessionState) {
                            if (sessionError) {
                                showWorldpayError(sessionError.message || @json(__('modules.worldpay.errorMessage')));
                                return;
                            }

                            $.easyAjax({
                                url: payUrl,
                                type: 'POST',
                                blockUI: true,
                                container: '.modal-content',
                                disableButton: true,
                                buttonSelector: '#worldpay-pay',
                                data: {
                                    _token: token,
                                    plan_id: @json($package->id),
                                    type: @json($worldpayPackageType),
                                    session_href: sessionState,
                                    card_agreement: (document.getElementById('worldpay-agreement') && document.getElementById('worldpay-agreement').checked) ? 1 : 0,
                                    device_data: deviceData()
                                },
                                success: handlePayResponse
                            });
                        });
                    });
                });
            }

            window.addEventListener('message', function (event) {
                if (event.origin === window.location.origin && event.data && event.data.worldpay3ds === 'complete') {
                    if (event.data.reference === currentReference) {
                        postStep(challengeUrl, {});
                    }
                    return;
                }

                if (!waitingForDeviceData) {
                    return;
                }

                var payload = event.data;

                if (typeof payload === 'string') {
                    try {
                        payload = JSON.parse(payload);
                    } catch (error) {
                        payload = null;
                    }
                }

                var sessionId = payload && (payload.SessionId || payload.sessionId);

                if (!sessionId) {
                    return;
                }

                waitingForDeviceData = false;
                clearTimeout(ddcTimer);
                postStep(deviceUrl, {collection_reference: sessionId});
            });

            $('.worldpayPay').click(function (e) {
                e.preventDefault();
                $('.paymentMethods').removeClass('show');
                $('.worldpayPaymentForm').addClass('show');
                $('.modal-title').text(@json(__('modules.worldpay.details')));

                if (worldpayReady) {
                    return;
                }

                worldpayReady = true;
                var script = document.createElement('script');
                script.src = @json($worldpayScriptUrl);
                script.onload = initWorldpayCheckout;
                script.onerror = function () {
                    showWorldpayError(@json(__('modules.worldpay.unavailableBilling')));
                };
                document.head.appendChild(script);
            });
        })();
        @endif

        // Payment mode
        function showButton(type) {
            if (type == 'online') {
                $('#offlineBox').addClass('d-none');
                $('#onlineBox').removeClass('d-none');
            } else {
                $('#offlineBox').removeClass('d-none');
                $('#onlineBox').addClass('d-none');
                $('#offlineButton #save-offline').removeClass('d-none');
            }
        }

        // redirect on paypal payment page
        $('body').on('click', '.paypalPayment', function () {
            $.easyBlockUI('#package-select-form', 'Redirecting Please Wait...');
            var url = "{{ route('billing.paypal-payment', [$package->id, $type]) }}";
            window.location.href = url;
        });


        function selectOffline(package_id) {
            let offlineId = $("input[name=offlineMethod]:checked").val();
            $.ajaxModal(MODAL_LG, '{{ route('billing.offline-payment')}}' + '?package_id=' + package_id + '&offlineId=' + offlineId + '&type=' + '{{ $type }}');
        }

        @if($stripeSettings->razorpay_status == 'active')
        //Confirmation after transaction
        function razorpaySubscription() {
            const packageType = '{{ $package->package_type }}'; // Package type (lifetime or recurring)
            const planId = '{{ $package->id }}';
            const type = '{{ $type }}';
            const invoiceId = '{{ $package->id }}';
            const currency = '{{ $package->currency->currency_code }}';
            const companyName = '{{ $companyName }}';
            const companyLogo = '{{ $company->logo_url }}';
            const razorpayKey = '{{ $stripeSettings->razorpay_mode == 'test' ? $stripeSettings->test_razorpay_key : $stripeSettings->live_razorpay_key }}';
            const csrfToken = '{{ csrf_token() }}';

            const clientEmail = @json(optional(user()->email) ?? optional(user()->email));
            if (packageType === 'lifetime') {
                // One-time payment
                const amount = {{ number_format((float) $package->monthly_price, 2, '.', '') * 100 }};
                console.log('amount', amount);
                const options = {
                    key: razorpayKey,
                    amount: amount,
                    currency: currency,
                    name: companyName,
                    description: "Invoice Payment",
                    image: companyLogo,
                    prefill: { email: clientEmail },
                    notes: {
                        purchase_id: invoiceId,
                        type: "invoice"
                    },
                    handler: function (response) {
                        confirmRazorpayPayment(response.razorpay_payment_id, invoiceId);
                    },
                    payment: {
                        capture: "automatic",
                        capture_options: {
                            automaticexpiryperiod: 12,
                            manualexpiryperiod: 7200,
                            refund_speed: "optimum"
                        },
                    },
                    modal: {
                        ondismiss: function () {

                        }
                    }
                };

                const rzp1 = new Razorpay(options);

                rzp1.on('payment.failed', function (response) {
                    // AFV (upgrade 6.0.19): front.invoice_payment_failed records a failed payment on a COMPANY invoice
                    // (now looked up by {hash}); "invoiceId" here is the PACKAGE id - 6.0.10 wrote the failed payment to
                    // whichever company's invoice had that id, 6.0.19 answers 404. A package purchase has no company
                    // invoice, so only the gateway's error is shown.
                    Swal.fire({
                        icon: 'error',
                        text: (response.error && response.error.description) || @json(__('messages.paymentFailed')),
                        customClass: {confirmButton: 'btn btn-primary'},
                        buttonsStyling: false
                    });
                });

                rzp1.open(); // Open Razorpay modal
            } else {
                // Subscription-based payment
                $.easyAjax({
                    type: 'POST',
                    blockUI: true,
                    container: '.modal-content',
                    url: '{{ route('billing.razorpay-subscription') }}',
                    data: { plan_id: planId, type: type, _token: csrfToken },
                    success: function (response) {
                        if (response.subscriprion) {
                            razorpayPaymentCheckout(response.subscriprion);
                        } else {
                            console.error('Subscription creation failed');
                        }
                    }
                });
            }
        }



        function razorpayPaymentCheckout(subscriptionID) {
            console.log('razorpayPaymentCheckout');
            var options = {
                "key": "{{ $stripeSettings->razorpay_key }}",
                "subscription_id": subscriptionID,
                "name": "{{ global_setting()->global_app_name }}",
                "description": "{{ $package->description }}",
                "image": "{{ global_setting()->logo_url }}",
                "currency": "{{ $package->currency->currency_code }}",
                "handler": function (response) {
                    confirmRazorpayPayment(response);
                },
                "notes": {
                    "package_id": '{{ $package->id }}',
                    "package_type": '{{ $type }}',
                    "company_id": '{{ $company->id }}',
                    'webhook_hash': "{{ global_setting()->hash }}"
                },
            };

            var rzp1 = new Razorpay(options);
            rzp1.open();
        }

        //Confirmation after transaction
        function confirmRazorpayPayment(response) {
            console.log(response);
            console.log('confirmRazorpayPayment');
            var plan_id = '{{ $package->id }}';
            var type = '{{ $type }}';
            var payment_id = response.razorpay_payment_id;
            var subscription_id = response.razorpay_subscription_id;
            var razorpay_signature = response.razorpay_signature;
            console.log([plan_id, type, payment_id, subscription_id, razorpay_signature]);
            $.easyAjax({
                type: 'POST',
                blockUI: true,
                container: '.modal-content',
                url: '{{route('billing.razorpay-payment')}}',
                data: {
                    paymentId: payment_id,
                    plan_id: plan_id,
                    subscription_id: subscription_id,
                    type: type,
                    razorpay_signature: razorpay_signature,
                    _token: '{{csrf_token()}}'
                },
                redirect: true,
            })
        }
        @endif
    </script>
@endif


