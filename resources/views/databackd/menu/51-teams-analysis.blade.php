@if (databackd_module('databackd_teams')) {{-- AFV (S8): package module --}}
<x-sub-menu-item :link="route('databackd.teams.analysis.index')" :text="__('databackd-teams-analysis.menu')" />
@endif
