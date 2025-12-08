@if (databackd_module('databackd_teams')) {{-- AFV (S8): package module --}}
<x-sub-menu-item :link="route('databackd.teams.requests.index')" :text="__('databackd-teams-requests.menu')" />
@endif
