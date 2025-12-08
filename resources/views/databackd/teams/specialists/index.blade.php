@extends('layouts.app')

@section('filter-section')
    <x-filters.filter-box>
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-specialists.filter_role')</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter-role" data-size="8">
                    <option value="all">@lang('databackd-teams-specialists.filter_all')</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">@lang('databackd-teams-common.role.' . $role)</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-specialists.filter_seniority')</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter-seniority" data-size="8">
                    <option value="all">@lang('databackd-teams-specialists.filter_all')</option>
                    @foreach ($seniorities as $seniority)
                        <option value="{{ $seniority }}">@lang('databackd-teams-common.seniority.' . $seniority)</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-specialists.filter_availability')</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter-availability" data-size="8">
                    <option value="all">@lang('databackd-teams-specialists.filter_all')</option>
                    @foreach (['available', 'busy', 'unavailable'] as $state)
                        <option value="{{ $state }}">@lang('databackd-teams-specialists.availability.' . $state)</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('databackd-teams-specialists.filter_reset')
            </x-forms.button-secondary>
        </div>
    </x-filters.filter-box>
@endsection

@section('content')
    <div class="content-wrapper dbk-teams">
        <x-dbk.page-header :title="$pageTitle" :subtitle="__('databackd-teams-specialists.subtitle')">
            <x-slot name="actions">
                <span class="dbk-page-meta" id="specialists-count" aria-live="polite"></span>
                @if ($canAdd)
                    <x-forms.button-primary class="add-specialist" icon="user-plus">@lang('databackd-teams-specialists.add')</x-forms.button-primary>
                @endif
            </x-slot>
        </x-dbk.page-header>

        @if ($specialists->isEmpty())
            <div class="dbk-card">
                <x-dbk.empty icon="people" :title="__('databackd-teams-specialists.empty')" :text="$canEdit ? __('databackd-teams-specialists.empty_text') : null">
                    @if ($canAdd)
                        <x-forms.button-primary class="add-specialist" icon="user-plus">@lang('databackd-teams-specialists.add')</x-forms.button-primary>
                    @endif
                </x-dbk.empty>
            </div>
        @else
            <div class="dbk-teams-spec-grid" id="specialists-grid">
                @foreach ($specialists as $profile)
                    @php
                        $user = $profile->user;
                        $load = $utilisation[$profile->id];
                        $over = $load['pct'] > 100;
                        $barTone = $over ? 'is-bad' : ($load['pct'] >= 85 ? 'is-warning' : ($load['pct'] >= 60 ? '' : 'is-good'));
                        $stateBadge = ['available' => 'is-good', 'busy' => 'is-bad', 'unavailable' => ''][$load['status']];
                        $designation = optional(optional($user->employeeDetail)->designation)->name;
                    @endphp
                    <div class="dbk-teams-spec-col specialist-col"
                         data-role="{{ $profile->role }}" data-seniority="{{ $profile->seniority }}"
                         data-availability="{{ $load['status'] }}">
                        <div class="dbk-card dbk-teams-spec">
                            <div class="dbk-card-body">
                                @if ($canEdit)
                                    <button type="button" class="btn dbk-teams-spec-edit edit-specialist"
                                            data-id="{{ $profile->id }}" title="@lang('databackd-teams-specialists.edit')" aria-label="@lang('databackd-teams-specialists.edit')">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                @endif

                                <div class="dbk-teams-spec-head">
                                    <img src="{{ $user->image_url }}" class="dbk-teams-avatar is-lg" alt="{{ $user->name }}">
                                    <div class="dbk-teams-spec-who">
                                        <div class="dbk-teams-spec-name" title="{{ $user->name }}">{{ $user->name }}</div>
                                        @if ($designation)
                                            <div class="dbk-teams-spec-designation">{{ $designation }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="dbk-teams-spec-tags">
                                    <span class="dbk-badge is-info no-dot">@lang('databackd-teams-common.role.' . $profile->role)</span>
                                    <span class="dbk-badge no-dot">@lang('databackd-teams-common.seniority.' . $profile->seniority)</span>
                                    <span class="dbk-badge {{ $stateBadge }}">@lang('databackd-teams-specialists.availability.' . $load['status'])</span>
                                </div>

                                <div class="dbk-teams-spec-stats">
                                    <div>
                                        <div class="dbk-teams-spec-label">@lang('databackd-teams-specialists.hourly_rate')</div>
                                        <div class="dbk-teams-spec-value">
                                            {{-- AFV (S12): the rates are in EUR (PriceEstimator, edit form), not in the company currency --}}
                                            @lang('databackd-teams-specialists.per_hour', ['amount' => number_format($profile->hourly_rate, fmod((float)$profile->hourly_rate, 1) == 0.0 ? 0 : 2, ',', ' ') . ' €'])
                                        </div>
                                    </div>
                                    <div>
                                        <div class="dbk-teams-spec-label">@lang('databackd-teams-specialists.capacity')</div>
                                        <div class="dbk-teams-spec-value">
                                            @lang('databackd-teams-specialists.hours_week', ['hours' => $profile->weekly_capacity_hours])
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="dbk-teams-util-head">
                                        <span>@lang('databackd-teams-specialists.utilisation')</span>
                                        <strong @class(['is-bad' => $over])>{{ number_format($load['pct'], 0, ',', ' ') }} %</strong>
                                    </div>
                                    <div class="dbk-progress" role="progressbar"
                                         aria-valuenow="{{ min(100, $load['pct']) }}" aria-valuemin="0" aria-valuemax="100">
                                        <span class="{{ $barTone }}" style="width: {{ min(100, $load['pct']) }}%"></span>
                                    </div>
                                    <div class="dbk-teams-util-hint">
                                        @lang('databackd-teams-specialists.utilisation_hint', ['hours' => $load['hours'], 'capacity' => $load['capacity'], 'team' => $load['team_hours'], 'analysis' => $load['analysis_count']])
                                    </div>
                                </div>

                                <div>
                                    <div class="dbk-teams-spec-label mb-1">@lang('databackd-teams-specialists.skills')</div>
                                    <div class="dbk-teams-spec-skills">
                                        @forelse ($profile->skills ?? [] as $skill)
                                            <span class="dbk-chip">{{ $skill }}</span>
                                        @empty
                                            <span class="dbk-teams-muted">@lang('databackd-teams-specialists.no_skills')</span>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($profile->languages)
                                    <div class="dbk-teams-spec-lang">
                                        <i class="fa fa-language"></i>{{ $profile->languages }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="dbk-card d-none mt-3" id="specialists-empty">
                <x-dbk.empty icon="search" :title="__('databackd-teams-specialists.empty_filtered')" />
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        var specialistsTotal = $('.specialist-col').length;
        var countText = @json(__('databackd-teams-specialists.showing', ['shown' => ':shown', 'total' => ':total']));

        function applyFilters() {
            var role = $('#filter-role').val();
            var seniority = $('#filter-seniority').val();
            var availability = $('#filter-availability').val();
            var shown = 0;

            $('.specialist-col').each(function () {
                var $col = $(this);
                var match = (role === 'all' || $col.data('role') === role)
                    && (seniority === 'all' || $col.data('seniority') === seniority)
                    && (availability === 'all' || $col.data('availability') === availability);

                $col.toggleClass('d-none', !match);
                if (match) {
                    shown++;
                }
            });

            $('#specialists-empty').toggleClass('d-none', shown > 0 || specialistsTotal === 0);
            $('#specialists-count').text(specialistsTotal ? countText.replace(':shown', shown).replace(':total', specialistsTotal) : '');
            $('#reset-filters').toggleClass('d-none', role === 'all' && seniority === 'all' && availability === 'all');
        }

        $('#filter-role, #filter-seniority, #filter-availability').on('change', applyFilters);

        $('#reset-filters').on('click', function () {
            $('#filter-role, #filter-seniority, #filter-availability').val('all').selectpicker('refresh');
            applyFilters();
        });

        $('body').on('click', '.add-specialist', function () {
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, @json(route('databackd.teams.specialists.edit', 0)));
        });

        $('body').on('click', '.edit-specialist', function () {
            var url = "{{ route('databackd.teams.specialists.edit', ':id') }}".replace(':id', $(this).data('id'));
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        applyFilters();
    </script>
@endpush
