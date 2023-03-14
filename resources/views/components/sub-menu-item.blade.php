<!-- COLLAPSE - INFORMATION -->
@if ($permission)
    @php
        // AFV ui: server-side active state - exact URL match; AFV items also match their sub-pages
        // (show/edit/...) and the extra paths in data-dbk-match (space separated, relative to the app root,
        // e.g. "account/databackd/planning/budget-editor"). The sidebar script refines it by the longest path prefix.
        $dbkSubActive = !empty($link) && rtrim(strtok((string) $link, '?#'), '/') === rtrim(url()->current(), '/');
        if (!$dbkSubActive && !empty($link)) {
            $dbkHere = trim(request()->path(), '/');
            $dbkLinkPath = trim(str_replace(url('/'), '', strtok((string) $link, '?#')), '/');
            $dbkPrefixes = str_contains($dbkLinkPath, 'databackd/') ? [$dbkLinkPath] : [];
            foreach (preg_split('/\s+/', trim((string) $attributes->get('data-dbk-match', ''))) as $dbkPrefix) {
                if ($dbkPrefix !== '') {
                    $dbkPrefixes[] = trim($dbkPrefix, '/');
                }
            }
            foreach ($dbkPrefixes as $dbkPrefix) {
                if ($dbkHere === $dbkPrefix || str_starts_with($dbkHere, $dbkPrefix . '/')) {
                    $dbkSubActive = true;
                    break;
                }
            }
        }
    @endphp
    <a {{ $attributes->class(['f-14 text-lightest dbk-sb-sub', 'active dbk-sb-current' => $dbkSubActive]) }} href="{{ $link }}" title="{{ $text }}" @if ($dbkSubActive) aria-current="page" @endif>{{ $text }}
        @if ($addon)
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor"
                 class="bi bi-gift ml-1 text-yellow" viewBox="0 0 16 16" data-toggle="tooltip"
                 data-original-title="{{ __('ui-shared.addon_tooltip') }}">
                <path
                    d="M3 2.5a2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 1 5 0v.006c0 .07 0 .27-.038.494H15a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 14.5V7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2.038A2.968 2.968 0 0 1 3 2.506V2.5zm1.068.5H7v-.5a1.5 1.5 0 1 0-3 0c0 .085.002.274.045.43a.522.522 0 0 0 .023.07zM9 3h2.932a.56.56 0 0 0 .023-.07c.043-.156.045-.345.045-.43a1.5 1.5 0 0 0-3 0V3zM1 4v2h6V4H1zm8 0v2h6V4H9zm5 3H9v8h4.5a.5.5 0 0 0 .5-.5V7zm-7 8V7H2v7.5a.5.5 0 0 0 .5.5H7z"/>
            </svg>
        @endif
    </a>
@endif
