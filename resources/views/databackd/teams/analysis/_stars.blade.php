@if ($rating)
    <span class="an-stars" title="{{ $rating }}/5">@for ($i = 1; $i <= 5; $i++)<i class="fa fa-star {{ $i <= $rating ? '' : 'off' }}"></i>@endfor</span>
@endif
