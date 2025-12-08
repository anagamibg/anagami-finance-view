@if (databackd_module('databackd_teams')) {{-- AFV (S8): package module --}}
<x-sub-menu-item :link="route('databackd.teams.specialists.index')" :text="__('databackd-teams-specialists.menu')" />
@endif
