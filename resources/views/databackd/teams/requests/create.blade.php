@extends('layouts.app')

@section('content')
    <div class="content-wrapper dbk-teams">
        <x-dbk.page-header :title="$pageTitle" />

        <x-form id="save-team-request-form" method="POST">
            <div class="dbk-teams-layout is-8-4">

                <!-- LEFT: FORM START -->
                <div>

                    <x-dbk.card :title="__('databackd-teams-requests.form.section_basic')" body-class="pb-0">
                        <div class="row">
                            <div class="{{ $isClient ? 'col-md-12' : 'col-md-8' }}">
                                <x-forms.text :fieldLabel="__('databackd-teams-requests.form.title')" fieldName="title" fieldId="request_title"
                                    fieldRequired="true"
                                    :fieldPlaceholder="__('databackd-teams-requests.form.title_placeholder')" />
                            </div>

                            @unless ($isClient)
                                <div class="col-md-4">
                                    <x-forms.select fieldId="request_client_id" fieldName="client_id"
                                        :fieldLabel="__('databackd-teams-requests.form.client')" fieldRequired="true" search="true">
                                        <option value="">{{ __('databackd-teams-requests.form.client_placeholder') }}</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }}@if ($client->company_name) ({{ $client->company_name }})@endif</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                            @endunless
                        </div>
                    </x-dbk.card>

                    <!-- TEAM SIZE START -->
                    <x-dbk.card :title="__('databackd-teams-requests.form.size_title')" :subtitle="__('databackd-teams-requests.form.size_help')">
                        <div class="form-group mb-0">
                        <input type="hidden" name="team_size" id="team_size" value="{{ $defaultSize }}">
                        <div class="dbk-teams-size-grid" role="group" aria-label="{{ __('databackd-teams-requests.form.size_title') }}">
                            @php
                                $sizeIcons = ['1' => 'user', '2' => 'user-friends', '5' => 'users', '10' => 'people-carry', '20' => 'building', '50' => 'city'];
                            @endphp
                            @foreach ($sizes as $size)
                                <div class="size-card dbk-teams-size-card {{ $size === $defaultSize ? 'active' : '' }}" data-size="{{ $size }}"
                                    role="button" tabindex="0" aria-pressed="{{ $size === $defaultSize ? 'true' : 'false' }}">
                                    <span class="size-icon" aria-hidden="true"><i class="fa fa-{{ $sizeIcons[$size] }}"></i></span>
                                    <div class="size-num">{{ $size === '50' ? '50+' : $size }}</div>
                                    <div class="size-unit">{{ $size === '1' ? __('databackd-teams-requests.form.unit_one') : __('databackd-teams-requests.form.unit_many') }}</div>
                                </div>
                            @endforeach
                            <div class="size-card dbk-teams-size-card" data-size="custom" aria-pressed="false">
                                <span class="size-icon" aria-hidden="true"><i class="fa fa-plus"></i></span>
                                <input type="number" min="1" max="1000" name="custom_size" id="custom_size" inputmode="numeric"
                                    class="form-control height-35 custom-input" placeholder="#"
                                    aria-label="{{ __('databackd-teams-requests.form.size_custom') }}">
                                <div class="size-unit">{{ __('databackd-teams-requests.form.size_custom') }}</div>
                            </div>
                        </div>
                        </div>
                    </x-dbk.card>
                    <!-- TEAM SIZE END -->

                    <!-- ROLES START -->
                    <x-dbk.card :title="__('databackd-teams-requests.form.roles_title')" :subtitle="__('databackd-teams-requests.form.roles_help')" class="dbk-teams-roles">
                        {{-- AFV (S12): form-group with id "roles" so that the validation errors of the roles are shown here --}}
                        <div class="form-group mb-0" id="roles">
                        <div id="roles-rows"></div>
                        <div id="roles-hidden"></div>

                        <a href="javascript:;" id="add-role" class="dbk-teams-add-role">
                            <i class="fa fa-plus-circle"></i>{{ __('databackd-teams-requests.form.add_role') }}
                        </a>

                        <div id="roles-counter" class="roles-counter ok" aria-live="polite">
                            <span class="counter-main" id="counter-main"></span>
                            <span id="counter-text"></span>
                        </div>
                        </div>
                    </x-dbk.card>
                    <!-- ROLES END -->

                    <!-- ENGAGEMENT START -->
                    <x-dbk.card :title="__('databackd-teams-requests.form.engagement_title')" body-class="pb-0">
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <input type="hidden" name="engagement" id="engagement" value="hours_per_week">
                                <div class="btn-group engagement-toggle dbk-teams-engagement" role="group">
                                    <button type="button" class="btn active" data-engagement="hours_per_week" aria-pressed="true">
                                        <i class="fa fa-clock mr-1"></i>{{ __('databackd-teams-requests.form.engagement_hours') }}
                                    </button>
                                    <button type="button" class="btn" data-engagement="full_time" aria-pressed="false">
                                        <i class="fa fa-briefcase mr-1"></i>{{ __('databackd-teams-requests.form.engagement_full') }}
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-4" id="hours-wrap">
                                <x-forms.number :fieldLabel="__('databackd-teams-requests.form.hours_per_week')" fieldName="hours_per_week"
                                    fieldId="hours_per_week" fieldValue="10" minValue="1" maxValue="40" fieldRequired="true" />
                            </div>
                            <div class="col-md-8 d-flex align-items-end pb-2">
                                <small class="dbk-form-hint" id="hours-help">{{ __('databackd-teams-requests.form.hours_help') }}</small>
                                <small class="dbk-form-hint d-none" id="full-time-help">{{ __('databackd-teams-requests.form.full_time_help') }}</small>
                            </div>

                            <div class="col-md-4">
                                <x-forms.datepicker fieldId="start_date" fieldRequired="true" fieldName="start_date"
                                    :fieldLabel="__('databackd-teams-requests.form.start_date')"
                                    :fieldPlaceholder="__('placeholders.date')" :fieldValue="$minStart" />
                            </div>

                            <div class="col-md-4">
                                <x-forms.number :fieldLabel="__('databackd-teams-requests.form.duration')" fieldName="duration_months"
                                    fieldId="duration_months" fieldValue="6" minValue="1" maxValue="60" fieldRequired="true" />
                            </div>

                            <div class="col-md-12">
                                <x-forms.textarea :fieldLabel="__('databackd-teams-requests.form.description')" fieldName="description"
                                    fieldId="request_description" fieldRequired="true"
                                    :fieldPlaceholder="__('databackd-teams-requests.form.description_placeholder')" />
                            </div>
                        </div>
                    </x-dbk.card>
                    <!-- ENGAGEMENT END -->
                </div>
                <!-- LEFT: FORM END -->

                <!-- RIGHT: PRICE START -->
                <div class="dbk-teams-aside">
                    <div class="dbk-card dbk-teams-price" id="price-box">
                        <div class="dbk-card-body">
                            <p class="dbk-teams-price-label">
                                <span class="dbk-teams-price-icon" aria-hidden="true"><i class="fa fa-euro-sign"></i></span>
                                {{ __('databackd-teams-requests.form.estimate_title') }}
                            </p>
                            <div class="price-value" aria-live="polite">≈ <span id="est-price">0</span> €<small>{{ __('databackd-teams-requests.form.estimate_per_month') }}</small></div>
                            <div class="dbk-teams-muted mt-1" id="est-basis"></div>
                            <ul class="price-breakdown" id="est-breakdown"></ul>
                            <div class="dbk-teams-price-total" id="est-total"></div>
                            <p class="dbk-teams-price-note">{{ __('databackd-teams-requests.form.estimate_note') }}</p>

                            <x-forms.button-primary id="save-team-request" class="w-100" icon="paper-plane">
                                {{ __('databackd-teams-requests.form.submit') }}
                            </x-forms.button-primary>
                            <x-forms.button-cancel :link="route('databackd.teams.requests.index')" class="border-0 w-100 mt-2">
                                @lang('app.cancel')
                            </x-forms.button-cancel>
                        </div>
                    </div>

                    {{-- AFV (S12): what the client gets and what happens after the request --}}
                    <div class="dbk-card dbk-teams-howto">
                        <div class="dbk-card-body">
                            <h3 class="dbk-card-title">{{ __('databackd-teams-requests.howto.title') }}</h3>
                            <ol class="dbk-teams-steps">
                                @foreach (['send', 'offer', 'accept', 'work'] as $step)
                                    <li>
                                        <strong>{{ __('databackd-teams-requests.howto.' . $step . '_title') }}</strong>
                                        <span>{{ __('databackd-teams-requests.howto.' . $step . '_text') }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
                <!-- RIGHT: PRICE END -->

            </div>
        </x-form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const roleLabels = @json(collect($roleKeys)->mapWithKeys(fn($r) => [$r => __('databackd-teams-common.role.' . $r)]));
            const defaultRoles = @json($defaultRoles);
            const txt = {
                counter: @json(__('databackd-teams-requests.form.assigned_counter')),
                ok: @json(__('databackd-teams-requests.form.counter_ok')),
                less: @json(__('databackd-teams-requests.form.counter_less')),
                more: @json(__('databackd-teams-requests.form.counter_more')),
                basis: @json(__('databackd-teams-requests.form.estimate_basis')),
                totalPeriod: @json(__('databackd-teams-requests.form.estimate_total_period')),
                loading: @json(__('databackd-teams-requests.form.estimate_loading')),
                removeRole: @json(__('databackd-teams-requests.form.remove_role')),
                role: @json(__('databackd-teams-requests.form.role')),
                count: @json(__('databackd-teams-requests.form.count')),
                less1: @json(__('databackd-teams-requests.form.count_down')),
                more1: @json(__('databackd-teams-requests.form.count_up')),
                fullTime: @json(__('databackd-teams-requests.full_time')),
                hoursWeek: @json(__('databackd-teams-requests.hours_week'))
            };
            const estimateUrl = @json(route('databackd.teams.requests.estimate'));
            const storeUrl = @json(route('databackd.teams.requests.store'));
            let estimateTimer = null;
            let estimateXhr = null;

            datepicker('#start_date', {
                position: 'bl',
                ...datepickerConfig
            });

            // AFV (S12): thousands always separated (Intl bg-BG leaves 4-digit numbers ungrouped: "3031")
            const fmt = (n) => String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

            // ---------- team size ----------
            const headcount = () => {
                const size = $('#team_size').val();
                return size === 'custom' ? (parseInt($('#custom_size').val(), 10) || 0) : parseInt(size, 10);
            };

            const setSize = (size) => {
                $('.size-card').removeClass('active').attr('aria-pressed', 'false');
                $('.size-card[data-size="' + size + '"]').addClass('active').attr('aria-pressed', 'true');
                $('#team_size').val(size);

                if (size !== 'custom') {
                    $('#custom_size').val('');
                }

                redistribute(headcount());
                refresh();
            };

            $('.size-card').on('click', function() {
                const size = String($(this).data('size'));
                setSize(size);

                if (size === 'custom') {
                    $('#custom_size').trigger('focus');
                }
            });

            // keyboard: Enter / Space choose a size card
            $('.size-card[role="button"]').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            $('#custom_size').on('click focus', function(e) {
                e.stopPropagation();

                if ($('#team_size').val() !== 'custom') {
                    $('#team_size').val('custom');
                    $('.size-card').removeClass('active').attr('aria-pressed', 'false');
                    $(this).closest('.size-card').addClass('active').attr('aria-pressed', 'true');
                }
            }).on('input', function() {
                redistribute(headcount());
                refresh();
            });

            // ---------- roles ----------
            const rowCounts = () => $('#roles-rows .role-row').map(function() {
                return parseInt($(this).find('.role-count').val(), 10) || 0;
            }).get();

            // AFV (S12): "select.role-select" - bootstrap-select copies the classes of the <select> to its wrapper
            // <div>, so ".role-select" found the div (val() = "") and no role was ever counted or sent
            const usedRoles = () => $('#roles-rows select.role-select').map(function() {
                return $(this).val();
            }).get();

            const addRow = (role, count) => {
                const options = Object.keys(roleLabels).map(function(key) {
                    return '<option value="' + key + '"' + (key === role ? ' selected' : '') + '>' + roleLabels[key] + '</option>';
                }).join('');

                const $row = $(
                    '<div class="role-row">' +
                    '<div class="role-select-wrap"><select class="form-control select-picker role-select" data-size="8" aria-label="' + txt.role + '">' + options + '</select></div>' +
                    '<div class="stepper">' +
                    '<button type="button" class="btn step-down" aria-label="' + txt.less1 + '"><i class="fa fa-minus f-12"></i></button>' +
                    '<input type="number" min="0" max="1000" inputmode="numeric" class="form-control height-35 f-14 role-count" value="' + count + '" aria-label="' + txt.count + '">' +
                    '<button type="button" class="btn step-up" aria-label="' + txt.more1 + '"><i class="fa fa-plus f-12"></i></button>' +
                    '</div>' +
                    '<a href="javascript:;" role="button" class="remove-role" title="' + txt.removeRole + '" aria-label="' + txt.removeRole + '"><i class="fa fa-times"></i></a>' +
                    '</div>'
                );

                $('#roles-rows').append($row);
                $row.find('.select-picker').selectpicker();
            };

            // Scale the counts to a new team size (largest remainder), so the sum stays equal to it
            const redistribute = (total) => {
                const counts = rowCounts();
                const rows = $('#roles-rows .role-row');

                if (!rows.length || total < 1) {
                    return;
                }

                const sum = counts.reduce((a, b) => a + b, 0);
                let result;

                if (sum === 0) {
                    result = counts.map((c, i) => i === 0 ? total : 0);
                } else {
                    const exact = counts.map((c) => c * total / sum);
                    result = exact.map(Math.floor);
                    let left = total - result.reduce((a, b) => a + b, 0);
                    const order = exact.map((v, i) => [v - Math.floor(v), i]).sort((a, b) => b[0] - a[0]);

                    for (let k = 0; k < order.length && left > 0; k++, left--) {
                        result[order[k][1]]++;
                    }
                }

                rows.each(function(i) {
                    $(this).find('.role-count').val(result[i]);
                });

                // rows left with 0 people are dropped (the first one is kept)
                rows.each(function(i) {
                    if (i > 0 && result[i] === 0) {
                        $(this).remove();
                    }
                });
            };

            const collectRoles = () => {
                const roles = {};

                $('#roles-rows .role-row').each(function() {
                    const role = $(this).find('select.role-select').val();
                    const count = parseInt($(this).find('.role-count').val(), 10) || 0;

                    if (role && count > 0) {
                        roles[role] = (roles[role] || 0) + count;
                    }
                });

                return roles;
            };

            const syncHidden = (roles) => {
                const $box = $('#roles-hidden').empty();

                Object.keys(roles).forEach(function(role) {
                    $box.append($('<input type="hidden">').attr('name', 'roles[' + role + ']').val(roles[role]));
                });
            };

            const updateCounter = (roles) => {
                const total = headcount();
                const assigned = Object.values(roles).reduce((a, b) => a + b, 0);
                const diff = total - assigned;
                const $c = $('#roles-counter').removeClass('ok warn bad');

                $('#counter-main').text(txt.counter.replace(':assigned', assigned).replace(':total', total));

                if (diff === 0 && total > 0) {
                    $c.addClass('ok');
                    $('#counter-text').text(txt.ok);
                } else if (diff > 0) {
                    $c.addClass('warn');
                    $('#counter-text').text(txt.less.replace(':diff', diff));
                } else {
                    $c.addClass('bad');
                    $('#counter-text').text(txt.more.replace(':diff', Math.abs(diff)));
                }
            };

            $('#add-role').on('click', function() {
                const used = usedRoles();
                const free = Object.keys(roleLabels).filter((r) => used.indexOf(r) === -1);
                const total = headcount();
                const assigned = rowCounts().reduce((a, b) => a + b, 0);

                addRow(free.length ? free[0] : Object.keys(roleLabels)[0], Math.max(0, total - assigned) || 1);
                refresh();
            });

            $('#roles-rows').on('click', '.step-up', function() {
                const $i = $(this).siblings('.role-count');
                $i.val((parseInt($i.val(), 10) || 0) + 1);
                refresh();
            }).on('click', '.step-down', function() {
                const $i = $(this).siblings('.role-count');
                $i.val(Math.max(0, (parseInt($i.val(), 10) || 0) - 1));
                refresh();
            }).on('click', '.remove-role', function() {
                if ($('#roles-rows .role-row').length > 1) {
                    $(this).closest('.role-row').remove();
                    refresh();
                }
            }).on('input change', '.role-count, select.role-select', refresh);

            // ---------- engagement ----------
            const setEngagement = (value) => {
                $('#engagement').val(value);
                $('.engagement-toggle .btn').removeClass('active').attr('aria-pressed', 'false');
                $('.engagement-toggle .btn[data-engagement="' + value + '"]').addClass('active').attr('aria-pressed', 'true');
                $('#hours-wrap, #hours-help').toggleClass('d-none', value === 'full_time');
                $('#full-time-help').toggleClass('d-none', value !== 'full_time');
                refresh();
            };

            $('.engagement-toggle .btn').on('click', function() {
                setEngagement($(this).data('engagement'));
            });

            $('#hours_per_week, #duration_months').on('input change', refresh);

            // ---------- live price ----------
            function loadEstimate(roles) {
                const $box = $('#price-box').addClass('loading');

                if (estimateXhr) {
                    estimateXhr.abort();
                }

                estimateXhr = $.ajax({
                    url: estimateUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        roles: roles,
                        engagement: $('#engagement').val(),
                        hours_per_week: $('#hours_per_week').val(),
                        duration_months: $('#duration_months').val()
                    },
                    success: function(res) {
                        $('#est-price').text(res.formatted);

                        const hours = $('#engagement').val() === 'full_time' ? txt.fullTime : txt.hoursWeek.replace(':hours', res.hours);
                        $('#est-basis').text(res.people_label + ' × ' + hours);

                        const $list = $('#est-breakdown').empty();
                        res.breakdown.forEach(function(item) {
                            $list.append($('<li>').append($('<span>').text(item.count + ' × ' + item.role), $('<span>').text(fmt(item.amount) + ' €')));
                        });

                        const months = parseInt($('#duration_months').val(), 10) || 1;
                        $('#est-total').text(res.people > 0 ? txt.totalPeriod.replace(':months', months).replace(':price', res.total_formatted) : '');
                        $box.removeClass('loading');
                    },
                    error: function(xhr, status) {
                        if (status !== 'abort') {
                            $box.removeClass('loading');
                        }
                    }
                });
            }

            function refresh() {
                const roles = collectRoles();
                syncHidden(roles);
                updateCounter(roles);

                clearTimeout(estimateTimer);
                estimateTimer = setTimeout(function() {
                    loadEstimate(roles);
                }, 300);
            }

            // ---------- submit ----------
            $('#save-team-request').on('click', function() {
                syncHidden(collectRoles());

                $.easyAjax({
                    url: storeUrl,
                    container: '#save-team-request-form',
                    type: 'POST',
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: '#save-team-request',
                    data: $('#save-team-request-form').serialize(),
                    success: function(response) {
                        if (response.status === 'success' && response.url) {
                            window.location.href = response.url;
                        }
                    }
                });
            });

            // ---------- init ----------
            Object.keys(defaultRoles).forEach(function(role) {
                addRow(role, defaultRoles[role]);
            });

            refresh();
        });
    </script>
@endpush
