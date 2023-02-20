<div class="alert alert-primary">
    <h6>{{ __('ui-misc.cron.title') }}</h6>
    <code>{{ __('ui-misc.cron.everyMinute') }}</code>
    <br>
    <br>
    @php
        try {
            $phpPath = PHP_BINDIR.'/php';
        } catch (\Throwable $th) {
            $phpPath = 'php';
        }
           echo '<code  id="cron-command" class="f-12"> ' . $phpPath . ' ' . base_path() . '/artisan schedule:run >> /dev/null 2>&1</code>';
    @endphp
    <button type="button" data-clipboard-target="#cron-command"
            data-toggle="tooltip"
            data-original-title="@lang('app.copyAboveLink')"
            class="btn-copy-cron btn btn-sm btn-secondary p-1 f-10">
        <i class="fa fa-copy "></i>
    </button>

    <div class="mt-3"><strong>@lang('app.note'):</strong>

        {!! __('ui-misc.cron.noteText', ['path' => '<ins>' . e($phpPath) . '</ins>']) !!}
    </div>
</div>

@push('scripts')
    <script>
        var clipboard = new ClipboardJS('.btn-copy-cron');

        clipboard.on('success', function (e) {
            Swal.fire({
                icon: 'success',
                text: "{{ __('app.copied') }}",
                toast: true,
                position: 'top-end',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                },
                showClass: {
                    popup: 'swal2-noanimation',
                    backdrop: 'swal2-noanimation'
                },
            })
        });
    </script>
@endpush
