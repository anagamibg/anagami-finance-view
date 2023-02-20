{{-- AFV KPI card (ui-guidelines §5).
     label, value (already formatted), icon (FA name without "fa-" or "bi-…"), tone: revenue|expense|profit|cash|null,
     change (number|null), change-unit ('%' or 'pp' label text), good: 'up'|'down' (which direction is good),
     hint (comparison text under the value), info (popover text), spark-id (canvas id for dbkChart.sparkline), value-id --}}
@props([
    'label', 'value', 'icon' => null, 'tone' => null, 'change' => null, 'changeUnit' => '%', 'good' => 'up',
    'hint' => null, 'info' => null, 'sparkId' => null, 'valueId' => null, 'decimals' => 1,
])

@php
    $hasChange = $change !== null && $change !== '';
    $changeValue = $hasChange ? (float) $change : null;
    $state = !$hasChange || $changeValue == 0 ? 'neutral' : ((($changeValue > 0) === ($good === 'up')) ? 'good' : 'bad');
    $arrow = !$hasChange || $changeValue == 0 ? 'bi-dash' : ($changeValue > 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short');
    $iconClass = $icon ? (str_starts_with($icon, 'bi-') ? 'bi ' . $icon : 'fa fa-' . $icon) : null;
@endphp

<div {{ $attributes->merge(['class' => 'dbk-kpi']) }}>
    <div class="dbk-kpi-top">
        <p class="dbk-kpi-label">{{ $label }}
            @if ($info)
                {{-- a11y: reachable with the keyboard, the popover also opens on focus --}}
                <i class="fa fa-question-circle ml-1" data-toggle="popover" data-placement="top" data-content="{{ $info }}" data-html="true" data-trigger="hover focus" tabindex="0" role="img" aria-label="{{ strip_tags($info) }}"></i>
            @endif
        </p>
        @if ($iconClass)
            <span @class(['dbk-kpi-icon', 'is-' . $tone => $tone]) aria-hidden="true"><i class="{{ $iconClass }}"></i></span>
        @endif
    </div>
    <div class="dbk-kpi-value" @if ($valueId) id="{{ $valueId }}" @endif title="{{ $value }}">{{ $value }}</div>
    @if ($hasChange || $hint)
        <div class="dbk-kpi-foot">
            @if ($hasChange)
                <span class="dbk-delta is-{{ $state }}"><i class="bi {{ $arrow }}" aria-hidden="true"></i>{{ ($changeValue > 0 ? '+' : ($changeValue < 0 ? '−' : '')) . number_format(abs($changeValue), $decimals, ',', ' ') . ' ' . $changeUnit }}</span>
            @endif
            @if ($hint)
                <span>{{ $hint }}</span>
            @endif
        </div>
    @endif
    @if ($sparkId)
        <div class="dbk-kpi-spark"><canvas id="{{ $sparkId }}"></canvas></div>
    @endif
    {{ $slot }}
</div>
