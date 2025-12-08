@extends('layouts.app')

@section('content')
    @php
        $r = $teamRequest;
        $sizeLabel = trans_choice('databackd-teams-requests.people', $r->headcount, ['count' => $r->headcount]) . ($r->team_size === '50' ? '+' : '');
        $engagementLabel = $r->engagement === 'full_time'
            ? __('databackd-teams-requests.full_time')
            : __('databackd-teams-requests.hours_week_person', ['hours' => (int)$r->hours_per_week]);
        $dateFormat = company()->date_format;
        $none = __('databackd-teams-requests.no_value');
        // AFV (S12): after the proposal the price is the offered one, before it only an estimate
        $priceText = fn($amount) => $amount === null ? $none : ($isOffer ? '' : '≈ ') . number_format($amount, $isOffer ? 2 : 0, ',', ' ') . ' €';
    @endphp

    @php
        $toneOf = ['new' => 'is-info', 'reviewing' => 'is-warning', 'proposed' => 'is-cash', 'active' => 'is-good', 'paused' => '', 'completed' => '', 'rejected' => 'is-bad'];
    @endphp

    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper dbk-teams">

        <!-- ACTION BAR START -->
        <x-dbk.page-header :title="$r->title">
            <div class="dbk-teams-meta">
                @include('databackd.teams.requests._status', ['status' => $r->status])
                <span>#{{ $r->id }}</span>
                @if ($r->client)
                    <span><i class="fa fa-building mr-1"></i>{{ $r->client->name }}</span>
                @endif
            </div>
            <x-slot name="actions">
                <x-forms.link-secondary :link="route('databackd.teams.requests.index')" icon="arrow-left">
                    @lang('databackd-teams-requests.back')
                </x-forms.link-secondary>

                @if ($canManage)
                    @if ($r->project_id && $r->project && in_array('projects', user_modules())) {{-- audit 2026-10-30 (G11): + projects module --}}
                        <x-forms.link-secondary :link="route('projects.show', $r->project_id)" icon="project-diagram">
                            @lang('databackd-teams-requests.show.open_project')
                        </x-forms.link-secondary>
                    @endif

                    @if ($canCreateProject)
                        <x-forms.button-secondary id="create-project" icon="project-diagram">
                            @lang('databackd-teams-requests.project.create')
                        </x-forms.button-secondary>
                    @endif

                    @if ($statusOptions)
                        <div class="dropdown">
                            <button class="btn btn-primary f-14 dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-exchange-alt mr-1"></i>@lang('databackd-teams-requests.status.change')
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                {{-- AFV (S12): only the allowed next statuses (TeamRequest::TRANSITIONS) --}}
                                @foreach ($statusOptions as $option)
                                    <a class="dropdown-item change-status" href="javascript:;" data-status="{{ $option }}"
                                        data-label="{{ __('databackd-teams-common.team_status.' . $option) }}">
                                        @include('databackd.teams.requests._status', ['status' => $option])
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </x-slot>
        </x-dbk.page-header>
        <!-- ACTION BAR END -->

        @if ($isClient)
            {{-- AFV (S12): what happens next, and the client's own actions (accept / decline the proposal, withdraw) --}}
            <div class="dbk-teams-next is-{{ $r->status }}" role="status">
                <span class="dbk-teams-next-icon" aria-hidden="true"><i class="fa fa-{{ ['new' => 'inbox', 'reviewing' => 'search', 'proposed' => 'file-signature', 'active' => 'users', 'paused' => 'pause', 'completed' => 'flag-checkered', 'rejected' => 'times'][$r->status] ?? 'info' }}"></i></span>
                <div class="dbk-teams-next-body">
                    <strong>{{ __('databackd-teams-requests.next.' . $r->status . '_title') }}</strong>
                    <p>{{ __('databackd-teams-requests.next.' . $r->status . '_text', ['price' => $priceText($r->monthly_price_estimate)]) }}</p>
                    <div class="dbk-teams-next-actions">
                        @if (in_array('active', $clientActions, true))
                            <button type="button" class="btn btn-primary f-14 client-action" data-status="active" data-action="accept">
                                <i class="fa fa-check mr-1"></i>@lang('databackd-teams-requests.client_action.accept')
                            </button>
                        @endif
                        @if ($r->status === 'active')
                            <a href="{{ route('databackd.teams.analysis.create', ['team_request_id' => $r->id]) }}" class="btn btn-primary f-14">
                                <i class="fa fa-plus mr-1"></i>@lang('databackd-teams-requests.next.new_analysis')
                            </a>
                        @endif
                        @if (in_array($r->status, ['completed', 'rejected'], true))
                            <a href="{{ route('databackd.teams.requests.create') }}" class="btn btn-secondary f-14">
                                <i class="fa fa-plus mr-1"></i>@lang('databackd-teams-requests.add')
                            </a>
                        @endif
                        @if (in_array('rejected', $clientActions, true))
                            <button type="button" class="btn btn-secondary f-14 client-action" data-status="rejected"
                                data-action="{{ $r->status === 'proposed' ? 'decline' : 'withdraw' }}">
                                <i class="fa fa-times mr-1"></i>@lang('databackd-teams-requests.client_action.' . ($r->status === 'proposed' ? 'decline' : 'withdraw'))
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- STAT CARDS START -->
        <div class="dbk-kpi-grid">
            <x-dbk.kpi :label="$isOffer ? __('databackd-teams-requests.show.price_offer') : __('databackd-teams-requests.show.price')" :value="$priceText($r->monthly_price_estimate)" icon="euro-sign" tone="revenue" />
            <x-dbk.kpi :label="__('databackd-teams-requests.show.size')" :value="$sizeLabel" icon="users" tone="profit" />
            {{-- AFV (S12): short value - "на човек" was cut off on the phone; the full text is in the details --}}
            <x-dbk.kpi :label="__('databackd-teams-requests.show.engagement')" :value="$r->engagement === 'full_time' ? __('databackd-teams-requests.full_time') : __('databackd-teams-requests.hours_week', ['hours' => (int)$r->hours_per_week])" icon="clock" />
            <x-dbk.kpi :label="__('databackd-teams-requests.show.duration')" :value="trans_choice('databackd-teams-requests.months_count', (int)$r->duration_months, ['count' => (int)$r->duration_months])" icon="calendar-alt" tone="cash" />
        </div>
        <!-- STAT CARDS END -->

        <div class="dbk-teams-layout">
            <!-- LEFT START -->
            <div>

                <!-- REQUEST DATA START -->
                <x-dbk.card :title="__('databackd-teams-requests.show.request_data')">
                    <dl class="dbk-dl">
                        <dt>@lang('databackd-teams-requests.show.client')</dt>
                        <dd>
                            @if ($r->client)
                                {{ $r->client->name }}
                                @if ($r->client->clientDetails?->company_name)
                                    <span class="dbk-text-muted">({{ $r->client->clientDetails->company_name }})</span>
                                @endif
                            @else
                                {{ $none }}
                            @endif
                        </dd>

                        <dt>@lang('databackd-teams-requests.show.size')</dt>
                        <dd>{{ $sizeLabel }}</dd>

                        <dt>@lang('databackd-teams-requests.show.roles')</dt>
                        <dd>
                            <span class="dbk-teams-roles-chips">
                                @forelse ($rolesRows as $role => $count)
                                    <span class="dbk-chip">{{ $count }} × {{ __('databackd-teams-common.role.' . $role) }}</span>
                                @empty
                                    {{ $none }}
                                @endforelse
                            </span>
                        </dd>

                        <dt>@lang('databackd-teams-requests.show.engagement')</dt>
                        <dd>{{ $engagementLabel }}</dd>

                        <dt>@lang('databackd-teams-requests.show.start_date')</dt>
                        <dd class="dbk-num">{{ $r->start_date?->translatedFormat($dateFormat) ?? $none }}</dd>

                        <dt>@lang('databackd-teams-requests.show.end_date')</dt>
                        <dd class="dbk-num">{{ $endDate?->translatedFormat($dateFormat) ?? $none }}</dd>

                        <dt>@lang('databackd-teams-requests.show.price_total')</dt>
                        <dd class="dbk-num">{{ $priceText($totalPrice) }}</dd>

                        <dt>@lang('databackd-teams-requests.show.created_at')</dt>
                        <dd>
                            {{ $r->created_at?->timezone(company()->timezone)->translatedFormat($dateFormat . ' H:i') }}
                            @if ($creator)
                                <span class="dbk-text-muted">&middot; {{ $creator->name }}</span>
                            @endif
                        </dd>

                        @if ($r->project_id && $r->project)
                            <dt>@lang('databackd-teams-requests.show.project')</dt>
                            <dd>
                                {{-- audit 2026-10-30 (G11): ProjectController::show needs the projects module and view_projects = all or membership --}}
                                @if (in_array('projects', user_modules()) && !$isClient && ($canManage || user()->permission('view_projects') == 'all' || $r->project->members->pluck('user_id')->contains(user()->id)))
                                    <a href="{{ route('projects.show', $r->project_id) }}">{{ $r->project->project_name }}</a>
                                @else
                                    {{ $r->project->project_name }}
                                @endif
                            </dd>
                        @endif
                    </dl>

                    <hr class="dbk-teams-divider">
                    <h4 class="dbk-teams-subhead">@lang('databackd-teams-requests.show.description')</h4>
                    <p class="dbk-teams-pre">{{ $r->description }}</p>
                </x-dbk.card>
                <!-- REQUEST DATA END -->

                <!-- TIMELINE START -->
                <x-dbk.card :title="__('databackd-teams-requests.show.timeline')">
                    @if ($logs->isEmpty())
                        <x-dbk.empty icon="clock-history" size="sm" :text="__('databackd-teams-requests.show.timeline_empty')" />
                    @else
                        <ol class="dbk-teams-timeline">
                            @foreach ($logs as $log)
                                <li @class(['is-current' => $loop->last && $log->to_status === $r->status])>
                                    <span class="dbk-teams-dot {{ $toneOf[$log->to_status] ?? '' }}" aria-hidden="true"></span>
                                    <div class="dbk-teams-tl-head">
                                        @include('databackd.teams.requests._status', ['status' => $log->to_status])
                                        @if ($log->from_status)
                                            <span class="dbk-teams-tl-from">&larr; {{ __('databackd-teams-common.team_status.' . $log->from_status) }}</span>
                                        @else
                                            <span class="dbk-teams-tl-from">{{ __('databackd-teams-requests.show.timeline_created') }}</span>
                                        @endif
                                    </div>
                                    <div class="dbk-teams-tl-meta">
                                        <span class="dbk-num"><i class="far fa-calendar-alt"></i>{{ $log->created_at?->timezone(company()->timezone)->translatedFormat($dateFormat . ' H:i') }}</span>
                                        <span>&middot;</span>
                                        <span><i class="far fa-user"></i>{{ $log->user?->name ?? __('databackd-teams-requests.show.timeline_system') }}</span>
                                    </div>
                                    @if ($log->note)
                                        <div class="dbk-teams-note">{{ $log->note }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-dbk.card>
                <!-- TIMELINE END -->
            </div>
            <!-- LEFT END -->

            <!-- RIGHT START -->
            <div>

                <!-- SPECIALISTS START -->
                <x-dbk.card :title="__('databackd-teams-requests.show.specialists')"
                    :subtitle="__('databackd-teams-requests.show.specialists_count', ['assigned' => $members->count(), 'total' => $r->headcount])">

                    @if ($members->isEmpty())
                        <x-dbk.empty icon="person-plus" size="sm" :text="__('databackd-teams-requests.show.specialists_empty')" />
                    @else
                        <div class="dbk-teams-members">
                            @foreach ($members as $member)
                                @php
                                    $user = $member->specialist?->user;
                                    $percent = $load[$member->specialist_profile_id]['percent'] ?? 0;
                                    $tone = $percent > 100 ? 'is-bad' : ($percent >= 70 ? 'is-warning' : 'is-good');
                                @endphp
                                <div class="dbk-teams-member">
                                    <img src="{{ $user?->image_url ?? asset('img/gravatar.png') }}" alt="{{ $user?->name }}" class="dbk-teams-avatar is-md">
                                    <div class="dbk-teams-member-main">
                                        <div class="dbk-teams-member-name">{{ $user?->name ?? $none }}</div>
                                        <div class="dbk-teams-member-role">
                                            @if ($member->role)<span>{{ __('databackd-teams-common.role.' . $member->role) }}</span>@endif
                                            <span class="dbk-teams-member-hours">{{ $member->hours_per_week }} {{ __('databackd-teams-requests.show.hours_week_short') }}</span>
                                        </div>
                                        @unless ($isClient)
                                            <div class="dbk-teams-member-load" title="{{ __('databackd-teams-requests.show.utilisation') }}">
                                                <div class="dbk-progress" role="progressbar" aria-valuenow="{{ min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100"><span class="{{ $tone }}" style="width: {{ min(100, $percent) }}%"></span></div>
                                                <span class="dbk-teams-load-pct dbk-text-{{ $percent > 100 ? 'bad' : ($percent >= 70 ? 'warning' : 'good') }}">{{ __('databackd-teams-requests.assign.busy', ['percent' => $percent]) }}</span>
                                            </div>
                                        @endunless
                                    </div>
                                    @if ($canManage)
                                        <a href="javascript:;" class="remove-member" data-profile-id="{{ $member->specialist_profile_id }}"
                                            title="{{ __('databackd-teams-requests.assign.remove') }}" aria-label="{{ __('databackd-teams-requests.assign.remove') }}"><i class="fa fa-times"></i></a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($canManage)
                        <hr class="dbk-teams-divider">
                        <h4 class="dbk-teams-subhead">@lang('databackd-teams-requests.assign.title')</h4>

                        @if ($specialistOptions->isEmpty())
                            <p class="dbk-teams-muted mb-0">@lang('databackd-teams-requests.assign.no_specialists')</p>
                        @else
                            <x-form id="assign-form" method="POST">
                                <input type="hidden" name="action" value="add">
                                <div class="row">
                                    <div class="col-12">
                                        <x-forms.select fieldId="assign_specialist" fieldName="specialist_profile_id"
                                            :fieldLabel="__('databackd-teams-requests.assign.specialist')" search="true">
                                            <option value="">{{ __('databackd-teams-requests.assign.select') }}</option>
                                            @foreach ($specialistOptions as $option)
                                                <option value="{{ $option['id'] }}" data-role="{{ $option['role'] }}"
                                                    data-subtext="{{ __('databackd-teams-common.role.' . $option['role']) }} · {{ __('databackd-teams-requests.assign.busy', ['percent' => $option['percent']]) }}{{ $option['percent'] > 100 ? ' (' . __('databackd-teams-requests.assign.over_capacity') . ')' : '' }}">
                                                    {{ $option['name'] }}
                                                </option>
                                            @endforeach
                                        </x-forms.select>
                                    </div>
                                    <div class="col-sm-7">
                                        <x-forms.select fieldId="assign_role" fieldName="role"
                                            :fieldLabel="__('databackd-teams-requests.assign.role')">
                                            @foreach ($roleKeys as $roleKey)
                                                <option value="{{ $roleKey }}">{{ __('databackd-teams-common.role.' . $roleKey) }}</option>
                                            @endforeach
                                        </x-forms.select>
                                    </div>
                                    <div class="col-sm-5">
                                        <x-forms.number :fieldLabel="__('databackd-teams-requests.assign.hours')" fieldName="hours_per_week"
                                            fieldId="assign_hours" :fieldValue="$r->engagement === 'full_time' ? 40 : (int)$r->hours_per_week"
                                            minValue="1" maxValue="60" />
                                    </div>
                                </div>
                                <x-forms.button-primary id="assign-submit" icon="user-plus" class="mt-1">
                                    @lang('databackd-teams-requests.assign.submit')
                                </x-forms.button-primary>
                            </x-form>
                        @endif
                    @endif
                </x-dbk.card>
                <!-- SPECIALISTS END -->

                <!-- HOURS START -->
                @if (in_array('timelogs', user_modules()) && !($isClient && $timeLogs === null)) {{-- audit 2026-10-30 (G04): + timelogs --}}
                <x-dbk.card :title="__('databackd-teams-requests.show.hours_worked_month', ['month' => $timeMonthLabel])">
                    @if ($timeLogs === null)
                        <p class="dbk-teams-hours-total is-empty">{{ $none }}</p>
                        <p class="dbk-teams-muted mb-0">@lang('databackd-teams-requests.show.no_project')</p>
                    @else
                        @php
                            $planned = round($assignedHours * 4.33);
                            $width = $planned > 0 ? min(100, round($timeLogs['total'] / $planned * 100)) : 0;
                        @endphp
                        <p class="dbk-teams-hours-total">
                            {{ number_format($timeLogs['total'], 1, ',', ' ') }} <small>{{ __('databackd-teams-requests.show.hours_unit') }}</small>
                        </p>
                        @if ($planned > 0)
                            <div class="dbk-progress mb-1"><span style="width: {{ $width }}%;"></span></div>
                            <p class="dbk-teams-muted mb-3">{{ __('databackd-teams-requests.show.hours_planned') }}: {{ $planned }} {{ __('databackd-teams-requests.show.hours_unit') }}</p>
                        @endif
                        @foreach ($timeLogs['users'] as $row)
                            <div class="dbk-teams-hours-row">
                                <span>{{ $row['name'] }}</span>
                                <span>{{ number_format($row['hours'], 1, ',', ' ') }} {{ __('databackd-teams-requests.show.hours_unit') }}</span>
                            </div>
                        @endforeach
                        <p class="dbk-teams-muted mt-2 mb-0">@lang('databackd-teams-requests.show.hours_worked_hint')</p>
                    @endif
                </x-dbk.card>
                @endif
                <!-- HOURS END -->
            </div>
            <!-- RIGHT END -->
        </div>
    </div>
    <!-- CONTENT WRAPPER END -->
@endsection

@if ($isClient && $clientActions)
    @push('scripts')
        <script>
            $(document).ready(function() {
                const statusUrl = @json(route('databackd.teams.requests.status', $r->id));
                // the client answers the version of the request shown on this page (offer price, status)
                const seenVersion = @json(optional($r->updated_at)->timestamp);
                @php
                    $clientTexts = collect(['accept', 'decline', 'withdraw'])->mapWithKeys(fn($k) => [$k => [
                        'title' => __('databackd-teams-requests.client_action.' . $k . '_title'),
                        'text' => __('databackd-teams-requests.client_action.' . $k . '_text'),
                        'button' => __('databackd-teams-requests.client_action.' . $k),
                    ]]);
                @endphp
                const texts = @json($clientTexts);

                $('.client-action').on('click', function() {
                    const status = $(this).data('status');
                    const t = texts[$(this).data('action')];

                    Swal.fire({
                        title: t.title,
                        text: t.text,
                        icon: status === 'active' ? 'question' : 'warning',
                        input: 'textarea',
                        inputLabel: @json(__('databackd-teams-requests.client_action.note')),
                        inputAttributes: {maxlength: 1000},
                        showCancelButton: true,
                        focusConfirm: false,
                        confirmButtonText: t.button,
                        cancelButtonText: @json(__('databackd-teams-requests.status.cancel')),
                        customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
                        showClass: {popup: 'swal2-noanimation', backdrop: 'swal2-noanimation'},
                        buttonsStyling: false
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        $.easyAjax({
                            type: 'POST',
                            url: statusUrl,
                            blockUI: true,
                            data: {'_token': @json(csrf_token()), status: status, note: result.value || '', seen_version: seenVersion},
                            success: function(response) {
                                if (response.status === 'success') {
                                    window.location.reload();
                                }
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endif

@if ($canManage)
    @push('scripts')
        <script>
            $(document).ready(function() {
                const statusUrl = @json(route('databackd.teams.requests.status', $r->id));
                const currentPrice = @json($r->monthly_price_estimate);
                const assignUrl = @json(route('databackd.teams.requests.assign', $r->id));
                const projectUrl = @json(route('databackd.teams.requests.create_project', $r->id));
                const swalClasses = {
                    customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
                    showClass: {popup: 'swal2-noanimation', backdrop: 'swal2-noanimation'},
                    buttonsStyling: false
                };

                const post = (url, data, onSuccess) => {
                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        blockUI: true,
                        data: Object.assign({'_token': @json(csrf_token())}, data),
                        success: function(response) {
                            if (response.status !== 'success') {
                                return;
                            }

                            if (response.url) {
                                window.location.href = response.url;
                            } else {
                                window.location.reload();
                            }
                        }
                    });
                };

                // Change status with an optional note
                $('.change-status').on('click', function() {
                    const status = $(this).data('status');
                    const label = $(this).data('label');

                    // AFV (S12): sending the proposal also fixes the monthly price the client accepts
                    const withPrice = status === 'proposed';
                    const $html = $('<div class="text-left">');

                    if (withPrice) {
                        $html.append(
                            $('<label class="f-14 text-dark-grey d-block" for="swal-offer-price">').text(@json(__('databackd-teams-requests.status.offer_price'))),
                            $('<input type="number" min="0" step="0.01" id="swal-offer-price" class="form-control mb-1">').val(currentPrice !== null ? currentPrice : ''),
                            $('<small class="text-muted d-block mb-3">').text(@json(__('databackd-teams-requests.status.offer_price_help')))
                        );
                    }

                    $html.append(
                        $('<label class="f-14 text-dark-grey d-block" for="swal-status-note">').text(@json(__('databackd-teams-requests.status.note'))),
                        $('<textarea id="swal-status-note" class="form-control" rows="3" maxlength="1000">').attr('placeholder', @json(__('databackd-teams-requests.status.note_placeholder')))
                    );

                    Swal.fire(Object.assign({
                        title: @json(__('databackd-teams-requests.status.change_to')).replace(':status', label),
                        html: $html[0],
                        showCancelButton: true,
                        focusConfirm: false,
                        confirmButtonText: @json(__('databackd-teams-requests.status.confirm')),
                        cancelButtonText: @json(__('databackd-teams-requests.status.cancel')),
                        preConfirm: () => ({
                            note: $('#swal-status-note').val() || '',
                            offer_price: withPrice ? ($('#swal-offer-price').val() || '') : ''
                        })
                    }, swalClasses)).then((result) => {
                        if (result.isConfirmed) {
                            post(statusUrl, {status: status, note: result.value.note, offer_price: result.value.offer_price});
                        }
                    });
                });

                // Create the project of an active request
                $('#create-project').on('click', function() {
                    Swal.fire(Object.assign({
                        title: @json(__('databackd-teams-requests.project.confirm_title')),
                        text: @json(__('databackd-teams-requests.project.confirm_text')),
                        icon: 'question',
                        showCancelButton: true,
                        focusConfirm: false,
                        confirmButtonText: @json(__('databackd-teams-requests.project.confirm_button')),
                        cancelButtonText: @json(__('databackd-teams-requests.status.cancel'))
                    }, swalClasses)).then((result) => {
                        if (result.isConfirmed) {
                            post(projectUrl, {});
                        }
                    });
                });

                // Pick the role of the chosen specialist
                $('#assign_specialist').on('change', function() {
                    const role = $(this).find('option:selected').data('role');

                    if (role) {
                        $('#assign_role').selectpicker('val', role);
                    }
                });

                $('#assign-submit').on('click', function() {
                    $.easyAjax({
                        url: assignUrl,
                        container: '#assign-form',
                        type: 'POST',
                        disableButton: true,
                        blockUI: true,
                        buttonSelector: '#assign-submit',
                        data: $('#assign-form').serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                window.location.reload();
                            }
                        }
                    });
                });

                $('.remove-member').on('click', function() {
                    const profileId = $(this).data('profile-id');

                    Swal.fire(Object.assign({
                        title: @json(__('databackd-teams-requests.assign.remove_confirm_title')),
                        text: @json(__('databackd-teams-requests.assign.remove_confirm_text')),
                        icon: 'warning',
                        showCancelButton: true,
                        focusConfirm: false,
                        confirmButtonText: @json(__('databackd-teams-requests.assign.remove_confirm_button')),
                        cancelButtonText: @json(__('databackd-teams-requests.assign.cancel'))
                    }, swalClasses)).then((result) => {
                        if (result.isConfirmed) {
                            post(assignUrl, {action: 'remove', specialist_profile_id: profileId});
                        }
                    });
                });
            });
        </script>
    @endpush
@endif
