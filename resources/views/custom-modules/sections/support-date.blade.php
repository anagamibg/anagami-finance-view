@php $supportDate = \Carbon\Carbon::parse($fetchSetting->supported_until) @endphp

@if ($supportDate->isPast())
    <span>{!! __('ui-misc.modules.supportExpiredOn', ['date' => '<b>' . e($supportDate->translatedFormat('d M, Y')) . '</b>']) !!}
        @if($supportDate->isYesterday())
            {{ __('ui-misc.common.yesterday') }}
        @endif
    </span>
    <br>
@else
    <span >{!! __('ui-misc.modules.supportExpiresOn', ['date' => '<b>' . e($supportDate->translatedFormat('d M, Y')) . '</b>']) !!}
        @if($supportDate->isToday())
            {{ __('ui-misc.common.today') }}
        @elseif($supportDate->isTomorrow())
            {{ __('ui-misc.common.tomorrow') }}
        @endif
    </span>
    @if($supportDate->diffInDays() < 90)
        <div class="h-mt2 mt-2">
            <p class="t-body -size-m -color-mid">
                <a class="img-lightbox"
                   data-image-url="{{ asset('img/Support_Extension_Cost.jpg') }}"
                   href="javascript:;">{{ __('ui-misc.modules.howMuchSave') }}
                </a>
            </p>
        </div>
    @endif
@endif



