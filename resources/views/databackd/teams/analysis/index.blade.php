@extends('layouts.app')

@section('filter-section')
    <x-filters.filter-box>
        <input type="hidden" name="view" value="{{ $view }}">

        @unless ($isClient)
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-analysis.filters.client')</p>
                <div class="select-status">
                    <select class="form-control select-picker an-filter" name="client" data-live-search="true" data-size="8">
                        <option value="">@lang('databackd-teams-analysis.filters.all')</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string)$filters['client'] === (string)$client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-analysis.filters.specialist')</p>
                <div class="select-status">
                    <select class="form-control select-picker an-filter" name="assignee" data-live-search="true" data-size="8">
                        <option value="">@lang('databackd-teams-analysis.filters.all')</option>
                        @foreach ($specialists as $specialist)
                            <option value="{{ $specialist->id }}" @selected((string)$filters['assignee'] === (string)$specialist->id)>{{ $specialist->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endunless

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-analysis.filters.type')</p>
            <div class="select-status">
                <select class="form-control select-picker an-filter" name="type" data-size="8">
                    <option value="">@lang('databackd-teams-analysis.filters.all')</option>
                    @foreach (\App\Models\Databackd\AnalysisRequest::TYPES as $type)
                        <option value="{{ $type }}" @selected($filters['type'] === $type)>@lang('databackd-teams-common.analysis_type.' . $type)</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-analysis.filters.month')</p>
            <div class="select-status">
                <select class="form-control select-picker an-filter" name="month" data-size="8">
                    <option value="">@lang('databackd-teams-analysis.filters.all')</option>
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}" @selected($filters['month'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (array_filter($filters))
            <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
                <a href="{{ route('databackd.teams.analysis.index', ['view' => $view]) }}" class="btn btn-secondary btn-xs rounded f-14 px-2 py-1">
                    <i class="fa fa-times-circle mr-1"></i> @lang('databackd-teams-analysis.filters.clear')
                </a>
            </div>
        @endif
    </x-filters.filter-box>
@endsection

@section('content')
    @php
        $statusTone = ['new' => 'is-info', 'in_progress' => 'is-warning', 'review' => 'dbk-teams-ast-review', 'delivered' => 'is-good', 'closed' => ''];
    @endphp
    <div class="content-wrapper dbk-teams">

        <x-dbk.page-header :title="$pageTitle" :subtitle="$isClient ? __('databackd-teams-analysis.subtitle_client') : __('databackd-teams-analysis.subtitle')">
            <x-slot name="actions">
                <div class="dbk-segmented an-view-toggle" role="group">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban', 'page' => null]) }}" @class(['active' => $view === 'kanban']) @if ($view === 'kanban') aria-current="page" @endif>
                        <i class="fa fa-columns mr-1"></i> @lang('databackd-teams-analysis.view.kanban')
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}" @class(['active' => $view === 'list']) @if ($view === 'list') aria-current="page" @endif>
                        <i class="fa fa-list mr-1"></i> @lang('databackd-teams-analysis.view.list')
                    </a>
                </div>
                <x-forms.link-primary :link="route('databackd.teams.analysis.create')" icon="plus">
                    @lang('databackd-teams-analysis.new_request')
                </x-forms.link-primary>
            </x-slot>
        </x-dbk.page-header>

        <!-- KPI strip -->
        <div class="dbk-kpi-grid">
            <x-dbk.kpi :label="__('databackd-teams-analysis.kpi.open')" :value="$stats['open']" icon="folder-open" tone="profit" />
            <x-dbk.kpi :label="__('databackd-teams-analysis.kpi.overdue')" :value="$stats['overdue']" icon="exclamation-circle" :tone="$stats['overdue'] > 0 ? 'expense' : null" />
            <x-dbk.kpi :label="__('databackd-teams-analysis.kpi.delivered_month')" :value="$stats['delivered_month']" icon="check-circle" tone="revenue" />
            <x-dbk.kpi :label="__('databackd-teams-analysis.kpi.avg_rating')" :value="$stats['avg_rating'] !== null ? number_format($stats['avg_rating'], 1) . ' / 5' : '-'" icon="star" />
        </div>

        @if (!$hasAny)
            {{-- AFV (S12): first visit - explain and offer the next step instead of 5 empty columns --}}
            <div class="dbk-card">
                <x-dbk.empty icon="kanban" :title="__('databackd-teams-analysis.empty.title')" :text="$isClient ? __('databackd-teams-analysis.empty.text_client') : __('databackd-teams-analysis.empty.text')">
                    <x-forms.link-primary :link="route('databackd.teams.analysis.create')" icon="plus">
                        @lang('databackd-teams-analysis.new_request')
                    </x-forms.link-primary>
                </x-dbk.empty>
            </div>
        @elseif ($view === 'kanban')
            <div class="an-board dbk-teams-board {{ $canMove ? 'can-move' : '' }}" id="an-board">
                @foreach (\App\Models\Databackd\AnalysisRequest::STATUSES as $status)
                    @php $cards = $columns->get($status, collect()); @endphp
                    <section class="an-col an-col-{{ $status }}">
                        <div class="an-col-head">
                            <span class="dbk-teams-col-name">@lang('databackd-teams-common.analysis_status.' . $status)</span>
                            <span class="an-count" data-count-for="{{ $status }}">{{ $cards->count() }}</span>
                        </div>
                        <div class="an-list" data-status="{{ $status }}" data-empty="@lang('databackd-teams-analysis.kanban.empty')">
                            @foreach ($cards as $card)
                                @php
                                    $overdue = $card->due_date && !in_array($card->status, ['delivered', 'closed'], true) && $card->due_date->lt(today());
                                @endphp
                                <div class="an-card an-p-{{ $card->priority }}" data-id="{{ $card->id }}">
                                    <div class="dbk-teams-card-top">
                                        <span class="dbk-badge dbk-teams-prio-{{ $card->priority }}">@lang('databackd-teams-common.priority.' . $card->priority)</span>
                                        @include('databackd.teams.analysis._stars', ['rating' => $card->client_rating])
                                    </div>
                                    <a href="{{ route('databackd.teams.analysis.show', $card->id) }}" class="an-title">{{ $card->title }}</a>
                                    @unless ($isClient)
                                        <div class="an-client text-truncate"><i class="fa fa-building mr-1"></i>{{ $card->client?->name ?? '-' }}</div>
                                    @endunless
                                    <div class="dbk-teams-card-tags">
                                        <span class="an-type">@lang('databackd-teams-common.analysis_type.' . $card->type)</span>
                                        @if ($card->recurrence !== 'none')
                                            <i class="fa fa-sync-alt f-11 dbk-text-muted" title="@lang('databackd-teams-common.recurrence.' . $card->recurrence)"></i>
                                        @endif
                                    </div>
                                    <div class="an-foot">
                                        <span class="{{ $overdue ? 'an-overdue' : '' }}" title="@lang('databackd-teams-analysis.fields.due_date')">
                                            <i class="far fa-calendar-alt mr-1"></i>{{ $card->due_date?->format(company()->date_format) ?? '-' }}
                                            @if ($overdue)<i class="fa fa-exclamation-circle ml-1"></i>@endif
                                        </span>
                                        @if ($card->assignee)
                                            <img src="{{ $card->assignee->image_url }}" class="an-avatar" alt="{{ $card->assignee->name }}" title="{{ $card->assignee->name }}">
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <div class="dbk-table-wrap">
                <table class="dbk-table">
                    <thead>
                    <tr>
                        <th>@lang('databackd-teams-analysis.fields.title')</th>
                        @unless ($isClient)
                            <th>@lang('databackd-teams-analysis.fields.client')</th>
                        @endunless
                        <th>@lang('databackd-teams-analysis.fields.type')</th>
                        <th>@lang('databackd-teams-analysis.fields.priority')</th>
                        <th>@lang('databackd-teams-analysis.fields.status')</th>
                        <th>@lang('databackd-teams-analysis.fields.due_date')</th>
                        <th>@lang('databackd-teams-analysis.fields.assignee')</th>
                        <th>@lang('databackd-teams-analysis.fields.rating')</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($items as $row)
                        @php
                            $overdue = $row->due_date && !in_array($row->status, ['delivered', 'closed'], true) && $row->due_date->lt(today());
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('databackd.teams.analysis.show', $row->id) }}" class="dbk-teams-list-title">{{ $row->title }}</a>
                                @if ($row->recurrence !== 'none')
                                    <i class="fa fa-sync-alt f-11 dbk-text-muted ml-1" title="@lang('databackd-teams-common.recurrence.' . $row->recurrence)"></i>
                                @endif
                            </td>
                            @unless ($isClient)
                                <td>{{ $row->client?->name ?? '-' }}</td>
                            @endunless
                            <td><span class="an-type">@lang('databackd-teams-common.analysis_type.' . $row->type)</span></td>
                            <td><span class="dbk-badge dbk-teams-prio-{{ $row->priority }}">@lang('databackd-teams-common.priority.' . $row->priority)</span></td>
                            <td><span class="dbk-badge {{ $statusTone[$row->status] ?? '' }}">@lang('databackd-teams-common.analysis_status.' . $row->status)</span></td>
                            <td class="dbk-num dbk-teams-nowrap {{ $overdue ? 'an-overdue' : '' }}">{{ $row->due_date?->format(company()->date_format) ?? '-' }}</td>
                            <td>
                                @if ($row->assignee)
                                    <span class="dbk-teams-person"><img src="{{ $row->assignee->image_url }}" class="an-avatar" alt="">{{ $row->assignee->name }}</span>
                                @else
                                    <span class="dbk-text-muted">-</span>
                                @endif
                            </td>
                            <td>@include('databackd.teams.analysis._stars', ['rating' => $row->client_rating])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isClient ? 7 : 8 }}">
                                <x-dbk.empty icon="search" :title="__('databackd-teams-analysis.empty.filtered')">
                                    <a href="{{ route('databackd.teams.analysis.index', ['view' => $view]) }}" class="btn btn-secondary f-14">@lang('databackd-teams-analysis.filters.clear')</a>
                                </x-dbk.empty>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items && $items->hasPages())
                <div class="mt-3 d-flex justify-content-end">{{ $items->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    {{-- AFV ui: jQuery UI sortable for the Kanban drag & drop (was never loaded) --}}
    <script src="{{ asset('vendor/jquery/jquery-ui.min.js') }}"></script>
    <script>
        $(function () {
            $('.an-filter').on('change', function () {
                $('#filter-form').submit();
            });

            @if ($view === 'kanban')
            var refreshBoard = function () {
                $('.an-list').each(function () {
                    var n = $(this).children('.an-card').length;
                    $(this).toggleClass('is-empty', n === 0);
                    $('[data-count-for="' + $(this).data('status') + '"]').text(n);
                });
            };
            refreshBoard();

            @if ($canMove)
            $('.an-list').sortable({
                connectWith: '.an-list',
                items: '.an-card',
                placeholder: 'an-placeholder',
                tolerance: 'pointer',
                distance: 5,
                receive: function (event, ui) {
                    var card = ui.item;
                    var target = $(this).data('status');

                    $.easyAjax({
                        url: "{{ route('databackd.teams.analysis.move', ':id') }}".replace(':id', card.data('id')),
                        type: 'POST',
                        blockUI: false,
                        container: '#an-board',
                        data: {_token: @json(csrf_token()), status: target},
                        error: function () {
                            window.setTimeout(function () { window.location.reload(); }, 1200);
                        },
                        success: function (response) {
                            if (response.status !== 'success') {
                                window.setTimeout(function () { window.location.reload(); }, 1200);
                            }
                            refreshBoard();
                        }
                    });
                },
                stop: refreshBoard
            });
            @endif
            @endif

            init('.content-wrapper');
        });
    </script>
@endpush
