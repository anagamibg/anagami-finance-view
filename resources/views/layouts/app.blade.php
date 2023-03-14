<!doctype html>
{{-- AFV ui: real locale (dbkChart picks bg-BG/en-GB number formats from it); the app's "eng" is not a valid
     BCP 47 tag (screen readers would not pick an English voice) → "en" --}}
<html lang="{{ app()->getLocale() === 'eng' ? 'en' : str_replace('_', '-', app()->getLocale() ?: 'en') }}" data-dbk-currency="{{ strtoupper((string) (company()?->currency?->currency_code ?: 'EUR')) }}">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="icon" type="image/png" sizes="16x16" href="{{ companyOrGlobalSetting()->favicon_url }}">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('vendor/css/all.min.css') }}" defer="defer">

    <!-- Simple Line Icons -->
    <link rel="stylesheet" href="{{ asset('vendor/css/simple-line-icons.css') }}" defer="defer">

    <!-- Datepicker -->
    <link rel="stylesheet" href="{{ asset('vendor/css/datepicker.min.css') }}" defer="defer">

    <!-- TimePicker -->
    <link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-timepicker.min.css') }}" defer="defer">

    <!-- Select Plugin -->
    <link rel="stylesheet" href="{{ asset('vendor/css/select2.min.css') }}" defer="defer">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-icons.css') }}" defer="defer">

    @stack('datatable-styles')

    <!-- Template CSS -->
    {{-- databackd: cache-busting by file time so a new build is picked up after deploy --}}
    <link type="text/css" rel="stylesheet" media="all" href="{{ asset('css/main.css') }}?v={{ @filemtime(public_path('css/main.css')) }}">

    <title>{{ is_array(__($pageTitle)) ? $pageTitle : __($pageTitle) }}</title>
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="{{ companyOrGlobalSetting()->favicon_url }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    @isset($activeSettingMenu)
        <style>
            .preloader-container {
                margin-left: 510px;
                width: calc(100% - 510px)
            }

            .blur-code {
                filter: blur(3px);

            }

            .purchase-code {
                transition: filter .2s ease-out;
                margin-right: 4px;
            }
            .ql-editor {
                text-align: left;
                white-space: unset;
            }
        </style>
    @endisset

    @stack('styles')

    @includeIf('aitools::includes.quill-rephrase-styles')

    <style>
        :root {
            --fc-border-color: #E8EEF3;
            --fc-button-text-color: #99A5B5;
            --fc-button-border-color: #99A5B5;
            --fc-button-bg-color: #ffffff;
            --fc-button-active-bg-color: #171f29;
            --fc-today-bg-color: #f2f4f7;
        }

        .fc a[data-navlink] {
            color: #99a5b5;
        }

        .ql-editor p {
            line-height: 1.42;
            margin: revert;
        }

        .ql-container .ql-tooltip {
            left: 8.5px !important;
            top: -17px !important;
        }

        .table [contenteditable="true"] {
            height: 55px;
        }

        .table [contenteditable="true"]:hover::after {
            content: "{{ __('app.clickEdit') }}" !important;
        }

        .table [contenteditable="true"]:focus::after {
            content: "{{ __('app.anywhereSave') }}" !important;
        }

        .table-bordered .displayName {
            padding: 17px;
        }

        p {
            word-break: break-word;
        }
        .inactive{
            opacity: 0.7;
        }
    </style>

    {{-- Custom theme styles --}}
    @if (!user()->dark_theme)
        @include('sections.theme_css')

        @php
            // AFV a11y: white text on the company theme colour must reach 4.5:1 (WCAG AA; e.g. #ed4040 is 3.9:1).
            // The tenant setting is not changed: when it is too light, primary buttons, badges and .text-primary use the
            // same hue darkened until it passes (--dbk-primary, see resources/scss/databackd/_modules.scss).
            $dbkThemeHex = trim((string) (isset($appTheme) ? $appTheme->header_color : (isset($company) ? $company->header_color : global_setting()->header_color)));
            $dbkPrimary = null;

            if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $dbkThemeHex, $dbkHex)) {
                $dbkRgb = array_map('hexdec', str_split(strlen($dbkHex[1]) === 3 ? preg_replace('/(.)/', '$1$1', $dbkHex[1]) : $dbkHex[1], 2));
                $dbkLum = function (array $rgb): float {
                    $lin = array_map(fn ($v) => ($s = $v / 255) <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4, $rgb);

                    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
                };

                for ($i = 0; $i < 60 && 1.05 / ($dbkLum($dbkRgb) + 0.05) < 4.5; $i++) {
                    $dbkRgb = array_map(fn ($v) => (int) floor($v * 0.97), $dbkRgb);
                }

                $dbkPrimary = vsprintf('#%02x%02x%02x', $dbkRgb);
            }
        @endphp
        @if ($dbkPrimary)
            <style>:root { --dbk-primary: {{ $dbkPrimary }}; }</style>
        @endif
    @endif

    @if (file_exists(public_path() . '/css/app-custom.css'))
        <link href="{{ asset('css/app-custom.css') }}" rel="stylesheet">
    @endif

    @if (file_exists(public_path() . '/css/custom-css/theme-custom.css'))
        <link href="{{ asset('css/custom-css/theme-custom.css') }}" rel="stylesheet">

    @endif

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/modernizr.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/bootstrap-colorpicker.js') }}"></script>

    {{-- Timepicker --}}
    <script src="{{ asset('vendor/jquery/bootstrap-timepicker.min.js') }}" defer="defer"></script>

    @includeif('sections.push-setting-include')

    {{-- Include file for widgets if exist --}}
    @includeif('sections.custom_script')

    <script>
        const checkMiniSidebar = localStorage.getItem("mini-sidebar");
    </script>

</head>


<body id="body" class="{{ user()->dark_theme ? 'dark-theme' : '' }} {{ isRtl('rtl') }}">
<script>
    if (checkMiniSidebar == "yes" || checkMiniSidebar == "") {
        $('body').addClass('sidebar-toggled');
    }
</script>
{{-- AFV a11y: skip link (WCAG 2.4.1) — first focusable element, jumps over the sidebar and the topbar --}}
<a href="#fullscreen" class="dbk-skip-link">{{ __('databackd-misc.skip_to_content') }}</a>
{{-- include topbar --}}
@if(user()->is_superadmin)
    @includeIf('super-admin.sections.topbar')
@else
    @include('sections.topbar')
@endif

{{-- include sidebar menu --}}
@include('sections.sidebar')

<!-- BODY WRAPPER START -->
<div class="clearfix body-wrapper">


    <!-- MAIN CONTAINER START -->
    {{-- AFV a11y: main landmark + skip-link target --}}
    <section class="mb-5 main-container bg-additional-grey mb-sm-0" id="fullscreen" role="main" tabindex="-1">

        <div class="preloader-container d-flex justify-content-center align-items-center">
            <div class="spinner-border" role="status" aria-hidden="true"></div>
        </div>

        {{-- databackd: on phones/tablets the page title comes first, above the filter bar (on ≥ 992 px it is in the topbar) --}}
        <x-app-title class="d-block d-lg-none" :pageTitle="$pageTitle"></x-app-title>

        @yield('filter-section')

        @yield('content')


    </section>
    <!-- MAIN CONTAINER END -->
</div>
<!-- BODY WRAPPER END -->
@include('sections.modals')

@includeIf('aitools::floating-chat-widget')

<!-- Global Required Javascript -->
<script src="{{ asset('js/main.js') }}?v={{ @filemtime(public_path('js/main.js')) }}"></script>
{{-- databackd: Chart.js + shared chart theme/helpers (window.dbkChart) on every page — ui-guidelines §6 --}}
<script src="{{ asset('vendor/jquery/Chart.min.js') }}"></script>
<script src="{{ asset('js/databackd-charts.js') }}?v={{ @filemtime(public_path('js/databackd-charts.js')) }}"></script>
<script>
    // Translation of default values for the select picker box.
    $.fn.selectpicker.Constructor.DEFAULTS.noneSelectedText = "@lang('placeholders.noneSelectedText')";
    $.fn.selectpicker.Constructor.DEFAULTS.noneResultsText = "@lang('placeholders.noneResultsText')";
    $.fn.selectpicker.Constructor.DEFAULTS.selectAllText = "@lang('placeholders.selectAllText')";
    $.fn.selectpicker.Constructor.DEFAULTS.deselectAllText = "@lang('placeholders.deselectAllText')";

    const MODAL_DEFAULT = '#myModalDefault';
    const MODAL_LG = '#myModal';
    const MODAL_XL = '#myModalXl';
    const MODAL_HEADING = '#modelHeading';
    const RIGHT_MODAL = '#task-detail-1';
    const RIGHT_MODAL_CONTENT = '#right-modal-content';
    const RIGHT_MODAL_TITLE = '#right-modal-title';
    const company = @json(companyOrGlobalSetting());
    const pusher_setting = @json(pusher_settings());
    const message_setting = @json(message_setting());
    const SEARCH_KEYWORD = "{{ request('search_keyword') }}";
    const MOMENTJS_TIME_FORMAT = "{{ (companyOrGlobalSetting()->time_format == 'h:i A') ? 'hh:mm A' : ( (companyOrGlobalSetting()->time_format == 'h:i a') ? 'hh:mm a' : 'H:mm') }}";

    // froiden-helper shows document.loading in blockUI / modal placeholders (falls back to English "Loading...")
    document.loading = @json(__('app.loading'));

    // AFV a11y: no jQuery animations (fadeOut, slideDown, animated scrolling) with prefers-reduced-motion
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        $.fx.off = true;
    }

    // Month/day names and relative dates of moment.js follow the language of the signed-in user (bg, en, ...)
    if (typeof moment !== 'undefined') {
        moment.locale("{{ app()->getLocale() === 'eng' ? 'en' : str_replace('_', '-', app()->getLocale()) }}");
    }

    const datepickerConfig = {
        formatter: (input, date, instance) => {
            input.value = moment(date).format('{{ companyOrGlobalSetting()->moment_date_format }}')
        },
        showAllDates: true,
        customDays: {!!  json_encode(\App\Models\GlobalSetting::getDaysOfWeek())!!},
        customMonths: {!!  json_encode(\App\Models\GlobalSetting::getMonthsOfYear())!!},
        customOverlayMonths: {!!  json_encode(\App\Models\GlobalSetting::getMonthsOfYear())!!},
        overlayButton: "@lang('app.submit')",
        overlayPlaceholder: "@lang('app.enterYear')",
        startDay: parseInt("{{ attendance_setting()?->week_start_from }}")
    };

    const daterangeConfig = {
        "@lang('app.today')": [moment(), moment()],
        "@lang('app.last30Days')": [moment().subtract(29, 'days'), moment()],
        "@lang('app.thisMonth')": [moment().startOf('month'), moment().endOf('month')],
        "@lang('app.lastMonth')": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        "@lang('app.last90Days')": [moment().subtract(89, 'days'), moment()],
        "@lang('app.last6Months')": [moment().subtract(6, 'months'), moment()],
        "@lang('app.last1Year')": [moment().subtract(1, 'years'), moment()]
    };

    const daterangeLocale = {
        "format": "{{ companyOrGlobalSetting()->moment_date_format }}",
        "customRangeLabel": "@lang('app.customRange')",
        "separator": " @lang('app.to') ",
        "applyLabel": "@lang('app.apply')",
        "cancelLabel": "@lang('app.cancel')",
        "monthNames": {!!  json_encode(\App\Models\GlobalSetting::getMonthsOfYear())!!},
        "daysOfWeek": {!!  json_encode(\App\Models\GlobalSetting::getDaysOfWeek())!!},
        "firstDay": parseInt("{{ attendance_setting()?->week_start_from }}")
    };

    const dropifyMessages = {
        default: "@lang('app.dragDrop')",
        replace: "@lang('app.dragDropReplace')",
        remove: "@lang('app.remove')",
        error: "@lang('messages.errorOccured')",
    };

    const DROPZONE_FILE_ALLOW = "{{ global_setting()->allowed_file_types }}";
    const DROPZONE_MAX_FILESIZE = "{{ global_setting()->allowed_file_size }}";
    const DROPZONE_MAX_FILES = "{{ global_setting()->allow_max_no_of_files }}";

    Dropzone.prototype.defaultOptions.dictFallbackMessage = "{{ __('modules.projectTemplate.dropFallbackMessage') }}";
    Dropzone.prototype.defaultOptions.dictFallbackText = "{{ __('modules.projectTemplate.dropFallbackText') }}";
    Dropzone.prototype.defaultOptions.dictFileTooBig = "{{ __('modules.projectTemplate.dropFileTooBig') }}";
    Dropzone.prototype.defaultOptions.dictInvalidFileType = "{{ __('modules.projectTemplate.dropInvalidFileType') }}";
    Dropzone.prototype.defaultOptions.dictResponseError = "{{ __('modules.projectTemplate.dropResponseError') }}";
    Dropzone.prototype.defaultOptions.dictCancelUpload = "{{ __('modules.projectTemplate.dropCancelUpload') }}";
    Dropzone.prototype.defaultOptions.dictCancelUploadConfirmation = "{{ __('modules.projectTemplate.dropCancelUploadConfirmation') }}";
    Dropzone.prototype.defaultOptions.dictRemoveFile = "{{ __('modules.projectTemplate.dropRemoveFile') }}";
    Dropzone.prototype.defaultOptions.dictMaxFilesExceeded = "{{ __('modules.projectTemplate.dropMaxFilesExceeded') }}";
    Dropzone.prototype.defaultOptions.dictDefaultMessage = "{{ __('modules.projectTemplate.dropFile') }}";
    Dropzone.prototype.defaultOptions.timeout = 0;

    $('#datatableRange').on('apply.daterangepicker', (event, picker) => {
        cb(picker.startDate, picker.endDate);
        const startDate = picker.startDate.format('{{ companyOrGlobalSetting()->moment_date_format }}');
        const endDate = picker.endDate.format('{{ companyOrGlobalSetting()->moment_date_format }}');
        $('#datatableRange').val(`${startDate} @lang("app.to") ${endDate}`);
    });

    $('#datatableRange2').on('apply.daterangepicker', (event, picker) => {
        cb(picker.startDate, picker.endDate);
        $('#datatableRange2').val(picker.startDate.format('{{ companyOrGlobalSetting()->moment_date_format }}') +
            ' @lang("app.to") ' + picker.endDate.format(
                '{{ companyOrGlobalSetting()->moment_date_format }}'));
    });

    function cb(start, end) {
        $('#datatableRange, #datatableRange2').val(start.format('{{ companyOrGlobalSetting()->moment_date_format }}') +
            ' @lang("app.to") ' + end.format(
                '{{ companyOrGlobalSetting()->moment_date_format }}'));
        $('#reset-filters, #reset-filters-2').removeClass('d-none');

    }

</script>

<!-- Scripts -->
<script>
    window.Laravel = {!! json_encode([
    'csrfToken' => csrf_token(),
    'user' => user(),
]) !!};
</script>

@stack('scripts')

<script>
    // databackd: the preloader must never stay over the page — an exception in init() (a page plugin missing) or a
    // window "load" that never fires (stalled third-party resource) used to leave the spinner covering everything.
    function dbkHidePreloader() {
        $(".preloader-container").fadeOut("slow", function () {
            $(this).removeClass("d-flex");
        });
    }

    $(window).on('load', function () {
        try {
            init();
        } catch (e) {
            console.error(e);
        }
        dbkHidePreloader();
    });

    setTimeout(dbkHidePreloader, 10000);

    // AFV a11y (WCAG 2.4.11): a focused element must not be hidden under the sticky topbar / filter bar.
    (function () {
        function stickyTop() {
            var h = 0;
            var header = document.getElementById('header');
            var filters = document.querySelector('.main-container > .filter-box');

            if (header && getComputedStyle(header).position === 'sticky') h += header.offsetHeight;
            if (filters && getComputedStyle(filters).position === 'sticky') h += filters.offsetHeight;

            return h + 12;
        }

        function update() {
            document.documentElement.style.setProperty('--dbk-sticky-top', stickyTop() + 'px');
        }

        update();
        $(window).on('load resize', update);

        document.addEventListener('focusin', function (e) {
            var el = e.target;

            if (!el || el === document.body || !el.getBoundingClientRect || !el.closest
                || el.closest('#header, .filter-box, .modal, .main-sidebar, .mobile-close-sidebar-panel, .task-detail-panel, .more-filter-tab, .swal2-container')) {
                return;
            }

            // keyboard focus only — a mouse click never scrolls the page
            try {
                if (!el.matches(':focus-visible')) {
                    return;
                }
            } catch (err) {
                return;
            }

            // elements in fixed overlays (side panels, dropdowns appended to body, chat widget) never scroll the page
            for (var p = el; p && p !== document.body; p = p.parentElement) {
                if (getComputedStyle(p).position === 'fixed') {
                    return;
                }
            }

            var limit = stickyTop();
            var top = el.getBoundingClientRect().top;

            if (top < limit) {
                window.scrollBy(0, top - limit);
            }
        });
    })();

    // AFV a11y: the skip link moves keyboard focus into the content (not only the scroll position)
    $('.dbk-skip-link').on('click', function (e) {
        const main = document.getElementById('fullscreen');

        if (main) {
            e.preventDefault();
            main.focus({preventScroll: true});
            window.scrollTo(0, 0);
        }
    });

    // AFV a11y: the shared modals have no aria-labelledby (their title ids repeat) → name the open dialog by its title
    $(document).on('shown.bs.modal', '.modal', function () {
        const title = $(this).find('.modal-title').first().text().trim();

        if (title) {
            this.setAttribute('aria-label', title);
        }
    });

    $('body').on('click', '.view-notification', function (event) {
        event.preventDefault();
        const id = $(this).data('notification-id');
        const href = $(this).attr('href');

        $.easyAjax({
            url: "{{ route('mark_single_notification_read') }}",
            type: "POST",
            data: {
                '_token': "{{ csrf_token() }}",
                'id': id
            },
            success: function () {
                if (typeof href !== 'undefined') {
                    window.location = href;
                }
            }
        });
    });

    $('body').on('click', '.img-lightbox', function () {
        const imageUrl = $(this).data('image-url');
        const url = "{{ route('front.public.show_image').'?image_url=' }}" + encodeURIComponent(imageUrl);
        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    $('body').on('click', '.piechart-full-screen', function () {
        const chartData = JSON.stringify($(this).data('chart-data'));
        const chartId = $(this).data('chart-id');
        const url = "{{ route('front.public.show_piechart').'?chart_data=' }}" + encodeURIComponent(chartData) + "&chart_id=" + chartId;
        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    function updateOnesignalPlayerId(userId) {
        $.easyAjax({
            url: '{{ route('profile.update_onesignal_id') }}',
            type: 'POST',
            data: {
                'userId': userId,
                '_token': '{{ csrf_token() }}'
            }
        })
    }

    if (SEARCH_KEYWORD !== '' && $('#search-text-field').length > 0) {
        $('#search-text-field').val(SEARCH_KEYWORD);
        $('#reset-filters').removeClass('d-none');
    }

    $('body').on('click', '.show-hide-purchase-code', function () {
        $('> .icon', this).toggleClass('fa-eye-slash fa-eye');
        $(this).siblings('span').toggleClass('blur-code ');
    });

</script>

@include('layouts.quill-script-include')

<script>
    $('body').on('click', '#pause-timer-btn, .pause-active-timer', function () {
        const id = $(this).data('time-id');
        let url = "{{ route('timelogs.pause_timer', ':id') }}";
        url = url.replace(':id', id);
        const token = '{{ csrf_token() }}';

        let currentUrl = $(this).data('url');

        $.easyAjax({
            url: url,
            blockUI: true,
            type: "POST",
            disableButton: true,
            buttonSelector: "#pause-timer-btn",
            data: {
                timeId: id,
                currentUrl: currentUrl,
                _token: token
            },
            success: function (response) {
                if (response.status === 'success') {
                    // Always refresh the page when timer is paused
                    window.location.reload();
                    // if ($('#myActiveTimer').length > 0) {
                    //     $(MODAL_XL + ' .modal-content').html(response.html);

                    //     if ($('#allTasks-table').length) {
                    //         window.LaravelDataTables["allTasks-table"].draw(true);
                    //     }
                    // }

                    // if ($('#allTasks-table').length) {
                    //     window.LaravelDataTables["allTasks-table"].draw(true);
                    // }

                    // if (response.reload === 'yes') {
                    //     window.location.reload();
                    // } else {
                    //     $('#timer-clock').html(response.clockHtml);
                    // }
                }
            }
        })
    });

    $('body').on('click', '#resume-timer-btn, .resume-active-timer', function () {
        const id = $(this).data('time-id');
        let url = "{{ route('timelogs.resume_timer', ':id') }}";
        url = url.replace(':id', id);
        const token = '{{ csrf_token() }}';

        let currentUrl = $(this).data('url');

        $.easyAjax({
            url: url,
            blockUI: true,
            type: "POST",
            disableButton: true,
            buttonSelector: "#resume-timer-btn",
            data: {
                timeId: id,
                currentUrl: currentUrl,
                _token: token
            },
            success: function (response) {

                if (response.status === 'success') {
                    if (response.reload === 'yes' || $('#task-detail-section').length) {
                        window.location.reload();
                        return;
                    }

                    if ($('#myActiveTimer').length > 0) {
                        $(MODAL_XL + ' .modal-content').html(response.html);
                    }

                    $('#timer-clock').html(response.clockHtml);
                    if ($('#allTasks-table').length) {
                        window.LaravelDataTables["allTasks-table"].draw(true);
                    }
                }
            }
        })
    });

    $('body').on('click', '.stop-active-timer', function() {
            var url = "{{ route('timelogs.stopper_alert', ':id') }}?via=timelog";
            var id = $(this).data('time-id');
            url = url.replace(':id', id);
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
    });
</script>

@if (in_array('messages', user_modules()))
    <script>
        function newMessageNotificationPlay() {
            var audio = new Audio("{{ asset('message-notification.mp3') }}");
            audio.play();
        }

        function checkNewMessage() {
            var url = "{{ route('messages.check_new_message') }}";
            var token = "{{ csrf_token() }}";

            $.easyAjax({
                url: url,
                type: "POST",
                data: {
                    '_token': token,
                },
                success: function (response) {
                    if (response.new_message_count > 0) {
                        newMessageNotificationPlay();
                        Swal.fire({
                            icon: 'info',
                            text: @json(__('ui-shared.new_message_received')),

                            toast: true,
                            position: "top-end",
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false,

                            customClass: {
                                confirmButton: "btn btn-primary",
                            },
                            showClass: {
                                popup: "swal2-noanimation",
                                backdrop: "swal2-noanimation",
                            },
                        });
                    }
                }
            });
        }

    @if(!user()->is_superadmin)
        // if (message_setting.send_sound_notification == 1 && !(pusher_setting.status === 1 && pusher_setting.messages === 1)) {
        //     window.setInterval(function () {
        //         checkNewMessage()
        //     }, 10000); // Check messages every 10 seconds
        // }
    @endif

    </script>
@endif

</body>

</html>