@if(count($updateArray) > 0 && in_array('admin', user_roles()))
    <div class="row pt-2 d-none d-md-block">
        <div class="col-md-12">
            <x-alert type="danger">
                <div class="d-flex justify-content-between">
                    <div class="align-self-center">
                        <b>{{ __('app.note') }}:</b>
                        {!! trans_choice('ui-core.dashboard.update_modules_note', count($updateArray), ['modules' => '<b>' . e(implode(', ', $updateArray)) . '</b>']) !!}
                    </div>
                    <x-forms.link-primary :link="route('custom-modules.index').'?tab=custom'">{{ __('ui-core.dashboard.update_custom_modules') }}</x-forms.link-primary>
                </div>
            </x-alert>
        </div>
    </div>
@endif
