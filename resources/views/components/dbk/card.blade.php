{{-- AFV card with optional title/subtitle/actions (ui-guidelines §4).
     <x-dbk.card :title="__('...')" :subtitle="__('...')" body-class="p-0"><x-slot name="actions">…</x-slot>…</x-dbk.card> --}}
@props(['title' => null, 'subtitle' => null, 'bodyClass' => '', 'headerBorder' => false])

<div {{ $attributes->merge(['class' => 'dbk-card']) }}>
    @if ($title || isset($actions))
        <div @class(['dbk-card-header', 'has-border' => $headerBorder])>
            <div style="min-width: 0">
                @if ($title)
                    <h3 class="dbk-card-title">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="dbk-card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="dbk-card-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="dbk-card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="dbk-card-footer">{{ $footer }}</div>
    @endisset
</div>
