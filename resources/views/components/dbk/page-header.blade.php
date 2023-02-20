{{-- AFV page header: title + subtitle on the left, actions on the right (ui-guidelines §4).
     <x-dbk.page-header :title="__('...')" :subtitle="__('...')"><x-slot name="actions">…buttons…</x-slot></x-dbk.page-header> --}}
@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'dbk-page-header']) }}>
    <div class="dbk-page-heading">
        @if ($title)
            <h1 class="dbk-page-title">{{ $title }}</h1>
        @endif
        @if ($subtitle)
            <p class="dbk-page-subtitle">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="dbk-page-actions">{{ $actions }}</div>
    @endisset
</div>
