{{-- AFV chart container: <x-dbk.chart id="chart-revenue" size="lg" /> then dbkChart.bar('chart-revenue', {...}) (ui-guidelines §6).
     size: xs|sm|lg|xl|null (300 px). --}}
@props(['id', 'size' => null, 'label' => null])

<div {{ $attributes->merge(['class' => 'dbk-chart' . ($size ? ' dbk-chart--' . $size : '')]) }}>
    <canvas id="{{ $id }}" role="img" @if ($label) aria-label="{{ $label }}" @endif></canvas>
</div>
