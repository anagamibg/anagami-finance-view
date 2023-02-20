{{-- AFV empty state (ui-guidelines §9): icon (bootstrap-icons name without "bi-"), title, text, optional action slot.
     <x-dbk.empty icon="inbox" :title="__('...')" :text="__('...')"><a class="btn btn-primary" href="…">…</a></x-dbk.empty> --}}
@props(['icon' => 'inbox', 'title' => null, 'text' => null, 'size' => null])

<div {{ $attributes->merge(['class' => 'dbk-empty' . ($size === 'sm' ? ' dbk-empty--sm' : '')]) }}>
    <span class="dbk-empty-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
    @if ($title)
        <p class="dbk-empty-title">{{ $title }}</p>
    @endif
    @if ($text)
        <p class="dbk-empty-text">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
