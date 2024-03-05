{{-- AFV (upgrade 6.0.19): the remaining English texts (close label, device-data frame title, expiry placeholder) through __() (S7) --}}
<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('modules.worldpay.details')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    @if (empty($checkoutId) || optional($credentials)->worldpay_status !== 'active')
        <p class="text-danger mb-0">@lang('modules.worldpay.unavailable')</p>
    @else
        <p class="f-14 text-dark-grey mb-3">
            @lang('modules.worldpay.amountDue'):
            <strong>{{ $currencyCode }} {{ number_format((float) $amount, 2) }}</strong>
        </p>
        <div id="worldpay-error" class="alert alert-danger d-none"></div>
        <form id="worldpay-card-form" action="javascript:;" method="POST">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
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
            @if ($requireAgreement)
                <div class="form-group">
                    <label class="f-14 text-dark-grey">
                        <input type="checkbox" id="worldpay-agreement" value="1">
                        @lang('modules.worldpay.storeCardAgreement')
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
    @endif
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0">@lang('app.close')</x-forms.button-cancel>
</div>

@if (!empty($checkoutId) && optional($credentials)->worldpay_status === 'active')
<script src="{{ $scriptUrl }}"></script>
<script>
    (function () {
        var form = document.getElementById('worldpay-card-form');
        var payUrl = "{{ route('worldpay_public', $id) }}";
        var deviceUrl = "{{ route('worldpay.device_data', $id) }}";
        var challengeUrl = "{{ route('worldpay.challenge', $id) }}";
        var token = "{{ csrf_token() }}";
        var currentReference = null;
        var waitingForDeviceData = false;
        var ddcTimer = null;

        function showError(message) {
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
            if (!response) {
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
                return;
            }

            if (response.status === 'success') {
                window.location.reload();
            }
        }

        function postStep(url, data) {
            data._token = token;
            data.reference = currentReference;

            $.easyAjax({
                url: url,
                type: 'POST',
                blockUI: true,
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
                    showError(@json(__('modules.worldpay.deviceDataTimeout')));
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

        if (typeof Worldpay === 'undefined' || !Worldpay.checkout) {
            showError(@json(__('modules.worldpay.unavailable')));
            return;
        }

        Worldpay.checkout.init({
            id: @json($checkoutId),
            form: '#worldpay-card-form',
            fields: {
                pan: {selector: '#worldpay-card-pan', placeholder: '4444 3333 2222 1111'},
                expiry: {selector: '#worldpay-card-expiry', placeholder: @json(__('ui-billing.worldpay.expiry_placeholder'))},
                cvv: {selector: '#worldpay-card-cvv', placeholder: '123'}
            },
            enablePanFormatting: true
        }, function (error, checkout) {
            if (error) {
                showError(error.message || @json(__('modules.worldpay.errorMessage')));
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                document.getElementById('worldpay-error').classList.add('d-none');

                @if ($requireAgreement)
                if (!document.getElementById('worldpay-agreement').checked) {
                    showError(@json(__('modules.worldpay.agreementRequired')));
                    return;
                }
                @endif

                checkout.generateSessionState(function (sessionError, sessionState) {
                    if (sessionError) {
                        showError(sessionError.message || @json(__('modules.worldpay.errorMessage')));
                        return;
                    }

                    $.easyAjax({
                        url: payUrl,
                        type: 'POST',
                        blockUI: true,
                        disableButton: true,
                        buttonSelector: '#worldpay-pay',
                        data: {
                            _token: token,
                            type: @json($type),
                            session_href: sessionState,
                            card_agreement: (document.getElementById('worldpay-agreement') && document.getElementById('worldpay-agreement').checked) ? 1 : 0,
                            device_data: deviceData()
                        },
                        success: handlePayResponse
                    });
                });
            });
        });
    })();
</script>
@endif
