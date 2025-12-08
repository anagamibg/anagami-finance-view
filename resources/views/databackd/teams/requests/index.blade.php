@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush


@section('filter-section')

    <x-filters.filter-box>
        <!-- STATUS START -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-requests.filters.status')</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter-status">
                    <option value="all">@lang('databackd-teams-requests.filters.all')</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ __('databackd-teams-common.team_status.' . $status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <!-- STATUS END -->

        @unless ($isClient)
            <!-- CLIENT START -->
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-requests.filters.client')</p>
                <div class="select-status">
                    <select class="form-control select-picker" id="filter-client" data-live-search="true" data-size="8">
                        <option value="all">@lang('databackd-teams-requests.filters.all')</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}@if ($client->company_name) ({{ $client->company_name }})@endif</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <!-- CLIENT END -->
        @endunless

        <!-- SIZE START -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('databackd-teams-requests.filters.size')</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter-size">
                    <option value="all">@lang('databackd-teams-requests.filters.all')</option>
                    @foreach ($sizes as $size)
                        <option value="{{ $size }}">{{ $size === '50' ? __('databackd-teams-requests.filters.size_50') : __('databackd-teams-common.team_size.' . $size) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <!-- SIZE END -->

        <!-- SEARCH START -->
        <div class="task-search d-flex py-1 px-lg-3 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey">
                            <i class="fa fa-search f-13 text-dark-grey"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                        placeholder="@lang('databackd-teams-requests.filters.search')">
                </div>
            </form>
        </div>
        <!-- SEARCH END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->
    </x-filters.filter-box>

@endsection

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper dbk-teams">

        <x-dbk.page-header :title="$pageTitle" :subtitle="$isClient ? __('databackd-teams-requests.subtitle') : __('databackd-teams-requests.subtitle_admin')">
            <x-slot name="actions">
                <x-forms.link-primary :link="route('databackd.teams.requests.create')" icon="plus">
                    @lang('databackd-teams-requests.add')
                </x-forms.link-primary>
            </x-slot>
        </x-dbk.page-header>

        <!-- STAT CARDS START -->
        <div class="dbk-kpi-grid">
            <x-dbk.kpi :label="__('databackd-teams-requests.cards.total')" :value="number_format($totalRequests, 0, ',', ' ')" icon="users" tone="profit" />
            <x-dbk.kpi :label="__('databackd-teams-requests.cards.active')" :value="number_format($activeRequests, 0, ',', ' ')" icon="check-circle" tone="revenue" />
            <x-dbk.kpi :label="__('databackd-teams-requests.cards.pending')" :value="number_format($pendingRequests, 0, ',', ' ')" icon="hourglass-half" />
            <x-dbk.kpi :label="__('databackd-teams-requests.cards.value')" :value="number_format($activeValue, 0, ',', ' ') . ' €'" icon="euro-sign" tone="cash" />
        </div>
        <!-- STAT CARDS END -->

        @if ($totalRequests == 0)
            <div class="dbk-card">
                <x-dbk.empty icon="people" :title="__('databackd-teams-requests.empty.title')" :text="__('databackd-teams-requests.empty.text')">
                    <x-forms.link-primary :link="route('databackd.teams.requests.create')" icon="plus">
                        @lang('databackd-teams-requests.add')
                    </x-forms.link-primary>
                </x-dbk.empty>
            </div>
        @else
            <!-- Table Box Start -->
            <div class="dbk-card dbk-teams-table-card">
                <div class="d-flex flex-column w-tables table-responsive">
                    {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}
                </div>
            </div>
            <!-- Table Box End -->
        @endif
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@if ($totalRequests > 0)
    @push('scripts')
        @include('sections.datatable_js')

        <script>
            $('#team-requests-table').on('preXhr.dt', function(e, settings, data) {
                data['status'] = $('#filter-status').val();
                data['client'] = $('#filter-client').length ? $('#filter-client').val() : 'all';
                data['size'] = $('#filter-size').val();
                data['searchText'] = $('#search-text-field').val();
            });

            const showTable = () => {
                window.LaravelDataTables["team-requests-table"].draw(false);
            }

            const toggleReset = () => {
                var active = $('#filter-status').val() != 'all' || $('#filter-size').val() != 'all'
                    || ($('#filter-client').length && $('#filter-client').val() != 'all')
                    || $('#search-text-field').val() != '';
                $('#reset-filters').toggleClass('d-none', !active);
            }

            $('#filter-status, #filter-client, #filter-size').on('change', function() {
                toggleReset();
                showTable();
            });

            $('#search-text-field').on('keyup', function() {
                toggleReset();
                showTable();
            });

            $('#reset-filters').click(function() {
                $('#filter-form')[0].reset();
                $('.filter-box .select-picker').selectpicker('refresh');
                $('#reset-filters').addClass('d-none');
                showTable();
            });

            @if ($canManage)
                $('body').on('click', '.delete-team-request', function() {
                    var id = $(this).data('request-id');

                    Swal.fire({
                        title: @json(__('databackd-teams-requests.delete.title')),
                        text: @json(__('databackd-teams-requests.delete.text')),
                        icon: 'warning',
                        showCancelButton: true,
                        focusConfirm: false,
                        confirmButtonText: @json(__('databackd-teams-requests.delete.confirm')),
                        cancelButtonText: @json(__('databackd-teams-requests.assign.cancel')),
                        customClass: {
                            confirmButton: 'btn btn-primary mr-3',
                            cancelButton: 'btn btn-secondary'
                        },
                        showClass: {
                            popup: 'swal2-noanimation',
                            backdrop: 'swal2-noanimation'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            var url = @json(route('databackd.teams.requests.destroy', ':id')).replace(':id', id);

                            $.easyAjax({
                                type: 'POST',
                                url: url,
                                blockUI: true,
                                data: {
                                    '_token': @json(csrf_token()),
                                    '_method': 'DELETE'
                                },
                                success: function(response) {
                                    if (response.status == 'success') {
                                        window.location.reload();
                                    }
                                }
                            });
                        }
                    });
                });
            @endif
        </script>
    @endpush
@endif
