@php
    $statusClass = [
        'new' => 'is-info',
        'reviewing' => 'is-warning',
        'proposed' => 'dbk-teams-st-proposed',
        'active' => 'is-good',
        'paused' => '',
        'completed' => 'dbk-teams-st-completed',
        'rejected' => 'is-bad',
    ][$status] ?? '';
@endphp
<span class="dbk-badge {{ $statusClass }}">{{ __('databackd-teams-common.team_status.' . $status) }}</span>
