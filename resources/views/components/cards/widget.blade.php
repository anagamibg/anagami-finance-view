<div
    {{ $attributes->merge(['class' => 'bg-white p-20 rounded border d-flex justify-content-between align-items-center dbk-layout-widget']) }}>
    <div class="d-block dbk-layout-widget-body">
        <h5 class="f-13 f-w-500 text-darkest-grey dbk-layout-widget-title">{{ $title }}
            @if (!is_null($info))
            {{-- AFV a11y: reachable with the keyboard, the popover also opens on focus --}}
            <i class="fa fa-question-circle" data-toggle="popover" data-placement="top" data-content="{{ $info }}" data-html="true" data-trigger="hover focus" tabindex="0" role="img" aria-label="{{ strip_tags($info) }}"></i>
        @endif
        </h5>
        <div class="d-flex">
            <p class="mb-0 f-13 font-weight-bold text-blue text-primary d-grid dbk-layout-widget-value"><span
                    id="{{ $widgetId }}">{{ $value }}</span>
            </p>
        </div>
    </div>
    <div class="d-block dbk-layout-widget-icon" aria-hidden="true">
        <i class="fa fa-{{ $icon }} text-lightest f-18"></i>
    </div>
</div>
