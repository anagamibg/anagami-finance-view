@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
    <style>
        input[type=radio].form-check-input  {
            height: 15px;
        }

        /* databackd: package list - name + badges | price | limits | modules summary | financial analysis (PackageDataTable) */
        #package-table td { vertical-align: top; }
        .dbk-pkg-name { font-weight: 600; font-size: var(--dbk-fs-base); color: var(--dbk-text); }
        .dbk-pkg-badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .dbk-pkg-badges .dbk-badge i { font-size: 11px; }
        .dbk-pkg-col-price { white-space: nowrap; }
        .dbk-pkg-price { line-height: 22px; white-space: nowrap; }
        .dbk-pkg-price span { font-weight: 600; color: var(--dbk-text); font-variant-numeric: tabular-nums; }
        .dbk-pkg-price small { color: var(--dbk-text-muted); }
        .dbk-pkg-col-limits { min-width: 190px; }
        .dbk-pkg-limits { display: grid; grid-template-columns: auto auto; column-gap: 14px; row-gap: 2px; margin: 0; font-size: var(--dbk-fs-sm); }
        .dbk-pkg-limits dt { font-weight: 400; color: var(--dbk-text-muted); }
        .dbk-pkg-limits dd { margin: 0; font-weight: 600; text-align: right; color: var(--dbk-text); font-variant-numeric: tabular-nums; }
        .dbk-pkg-col-modules { min-width: 260px; max-width: 440px; }
        .dbk-pkg-col-fin { min-width: 210px; }
        .dbk-pkg-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-top: 6px; }
        .dbk-pkg-chips .dbk-chip { gap: 5px; padding: 0 8px; }
        .dbk-pkg-chips .dbk-chip i { font-size: 10px; }
        .dbk-pkg-chips-label { font-size: var(--dbk-fs-xs); color: var(--dbk-text-muted); }
        .dbk-pkg-chips-stack { flex-direction: column; align-items: flex-start; margin-top: 0; }
        .dbk-pkg-on { color: var(--dbk-good); background: var(--dbk-good-soft); }
        .dbk-pkg-off { color: var(--dbk-text-muted); background: var(--dbk-neutral-soft); text-decoration: line-through; }
        .dbk-pkg-details { margin-top: 6px; }
        .dbk-pkg-details summary { cursor: pointer; font-size: var(--dbk-fs-xs); color: var(--dbk-accent-text); }
    </style>
@endpush

@section('filter-section')

    <x-filters.filter-box>
        <!-- SEARCH BY TASK START -->
        <div class="task-search d-flex py-1 pr-lg-3 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey">
                            <i class="fa fa-search f-13 text-dark-grey"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                        placeholder="@lang('app.startTyping')">
                </div>
            </form>
        </div>
        <!-- SEARCH BY TASK END -->

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
@php
$addPackagesPermission = user()->permission('add_packages');
$deletePackagesPermission = user()->permission('delete_packages');
@endphp
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        <!-- Add Task Export Buttons Start -->
        <div class="d-flex">
            <div id="table-actions" class="flex-grow-1 align-items-center">
                @if($addPackagesPermission == 'all')
                <x-forms.link-primary :link="route('superadmin.packages.create')" class="mr-3 float-left openRightModal"
                    icon="plus">
                    @lang('superadmin.addPackage')
                </x-forms.link-primary>
                @endif
            </div>
        </div>

        <!-- Add Task Export Buttons End -->
        <!-- Task Box Start -->
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">

            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}

        </div>
        <!-- Task Box End -->
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        $('#package-table').on('preXhr.dt', function(e, settings, data) {
            var searchText = $('#search-text-field').val();
            data['searchText'] = searchText;
        });

        const showTable = () => {
            window.LaravelDataTables["package-table"].draw();
        }

        $('#search-text-field')
            .on('change keyup',
                function() {
                    if ($('#search-text-field').val() != "") {
                        $('#reset-filters').removeClass('d-none');
                        showTable();
                    } else {
                        $('#reset-filters').addClass('d-none');
                        showTable();
                    }
                });

        $('#reset-filters').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('#reset-filters-2').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        @if($deletePackagesPermission == 'all')
            $('body').on('click', '.delete-table-row', function() {
                var id = $(this).data('order-id');
                Swal.fire({
                    title: "@lang('messages.sweetAlertTitle')",
                    text: "@lang('messages.recoverRecord')",
                    icon: 'warning',
                    showCancelButton: true,
                    focusConfirm: false,
                    confirmButtonText: "@lang('messages.confirmDelete')",
                    cancelButtonText: "@lang('app.cancel')",
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
                        var url = "{{ route('superadmin.packages.destroy', ':id') }}";
                        url = url.replace(':id', id);

                        var token = "{{ csrf_token() }}";

                        $.easyAjax({
                            type: 'POST',
                            url: url,
                            blockUI: true,
                            data: {
                                '_token': token,
                                '_method': 'DELETE'
                            },
                            success: function(response) {
                                if (response.status == "success") {
                                    showTable();
                                }
                            }
                        });
                    }
                });
            });
        @endif


    </script>
@endpush
