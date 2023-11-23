<style>

    .e-btn--3d.-color-primary {
        -webkit-box-shadow: 0 2px 0 #6f9a37;
        box-shadow: 0 2px 0 #6f9a37;
        position: relative;
    }

</style>


<div class="support-div mb-2">
    @if (!is_null($envatoUpdateCompanySetting->supported_until))

        @php
            $expired = Carbon\Carbon::parse($envatoUpdateCompanySetting->supported_until)->isPast();
            $support = Carbon\Carbon::parse($envatoUpdateCompanySetting->supported_until);
        @endphp

        <div @class(['alert-success' => !$expired,'alert-danger' => $expired,'alert'])>
            <div class="row">
                <div class="col-md-8">
                    <h2 class="mb-0 f-21 font-weight-normal ">
                        <strong>@if($expired) {{ __('ui-misc.update.supportExpiredHeading') }} @else {{ __('ui-misc.update.supportHeading') }} @endif
                        </strong>
                    </h2>
                </div>
                <div class="col-md-4 text-right">
                    <span class="text-center">
                         @if($expired)
                            {{ __('ui-misc.update.timeAgo', ['time' => $support->diffForHumans(now(),Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                        @else
                            {{ __('ui-misc.update.timeLeft', ['time' => $support->diffForHumans(now(),Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                        @endif
                    </span>

                    </div>
                <div class="col-md-12 mt-3">
                    <div class="item-support-extension__row1 mb-2">
                        <div class="item-support-extension__label">
                            @if($expired)
                                <p>{!! __('ui-misc.update.renewHelp', ['url' => 'https://1.envato.market/froiden']) !!}</p>
                            @elseif($support->diffInDays() < 90)
                                <p>{!! __('ui-misc.update.extraSixMonths') !!}</p>
                            @endif

                            @include('custom-modules.sections.support-date',['fetchSetting' => $envatoUpdateCompanySetting])
                        </div>
                    </div>


                    @if($expired)
                        <x-forms.link-primary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                              :link="Froiden\Envato\Helpers\FroidenApp::renewSupportUrl(config('froiden_envato.envato_item_id'))"
                                              icon="shopping-cart"
                                              data-toggle="tooltip"
                                              data-original-title="{{ __('ui-misc.update.extendTooltip') }}"
                                              target="_blank">{{ __('ui-misc.modules.renewSupportNow') }}
                        </x-forms.link-primary>
                        <x-forms.link-secondary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                                link="https://froiden.freshdesk.com/support/tickets/new"
                                                target="_blank"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('ui-misc.update.contactAuthorTooltip') }}"
                                                icon="external-link-alt">{{ __('ui-misc.update.contactAuthorSupport') }}
                        </x-forms.link-secondary>
                        <x-forms.link-secondary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                                link="https://bit.ly/worksuite-amc-offer"
                                                target="_blank"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('ui-misc.update.amcTooltip') }}"
                                                icon="external-link-alt">{{ __('ui-misc.update.checkAmc') }}
                        </x-forms.link-secondary>
                        <x-forms.link-secondary link="javascript:;"
                                                class="e-btn--3d -color-primary -size-m -width-full h-mt"
                                                onclick="getPurchaseData();"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('ui-misc.modules.refreshSupportHint') }}"
                                                icon="sync-alt">{{ __('ui-misc.common.refresh') }}
                        </x-forms.link-secondary>
                    @elseif ($support->diffInDays() < 90)
                        <x-forms.link-primary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                              :link="Froiden\Envato\Helpers\FroidenApp::extendSupportUrl(config('froiden_envato.envato_item_id'))"
                                              target="_blank"
                                              data-toggle="tooltip"
                                              data-original-title="{{ __('ui-misc.update.extendTooltip') }}"
                                              icon="shopping-cart">{{ __('ui-misc.update.extendNowSave') }}
                        </x-forms.link-primary>
                        <x-forms.link-secondary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                              link="https://froiden.freshdesk.com/support/tickets/new"
                                              target="_blank"
                                              data-toggle="tooltip"
                                              data-original-title="{{ __('ui-misc.update.contactAuthorTooltip') }}"
                                              icon="external-link-alt">{{ __('ui-misc.update.contactAuthorSupport') }}
                        </x-forms.link-secondary>
                        <x-forms.link-secondary class="mr-2 e-btn--3d -color-primary -size-m -width-full h-mt"
                                                link="https://bit.ly/worksuite-amc-offer"
                                                target="_blank"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('ui-misc.update.amcTooltip') }}"
                                                icon="external-link-alt">{{ __('ui-misc.update.checkAmc') }}
                        </x-forms.link-secondary>
                        <x-forms.link-secondary link="javascript:;"
                                                class="e-btn--3d -color-primary -size-m -width-full h-mt"
                                                onclick="getPurchaseData();"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('ui-misc.modules.refreshSupportHint') }}"
                                                icon="sync-alt">{{ __('ui-misc.common.refresh') }}
                        </x-forms.link-secondary>

                    @endif
                </div>

            </div>

        </div>
    @if(!$expired && $support->diffInDays()>91)
            @includeIf('vendor.froiden-envato.update.amc')
        @endif
    @else
        @includeIf('vendor.froiden-envato.update.amc')
    @endif
</div>

