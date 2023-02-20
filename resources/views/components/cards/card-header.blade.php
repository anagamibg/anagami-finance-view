<div class="card-header bg-white d-flex justify-content-between align-items-center dbk-layout-card-header">
    <h4 class="mb-0 dbk-layout-card-title">{{ $slot }}</h4>

    @if(isset($action) && trim((string) $action) !== '')
        <div class="dbk-layout-card-actions">{!! $action !!}</div>
    @endif

</div>
