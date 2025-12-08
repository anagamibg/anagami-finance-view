@extends('layouts.app')

@section('content')
    @php
        $df = company()->date_format;
        $fmt = fn($d) => $d ? $d->format($df) : '';
    @endphp

    <div class="content-wrapper dbk-teams">
        <div class="dbk-teams-layout {{ $isEdit ? 'is-single' : 'is-8-4' }}">
            <div>
                <x-form id="analysis-form" :method="$isEdit ? 'PUT' : 'POST'" enctype="multipart/form-data">
                    <div class="add-client bg-white rounded dbk-teams-form-card">
                        <h4 class="mb-0 p-20 f-18 font-weight-normal border-bottom-grey">
                            {{ $isEdit ? __('databackd-teams-analysis.edit_request') : __('databackd-teams-analysis.new_request') }}
                        </h4>

                        <div class="row p-20">
                            @unless ($isClient)
                                <div class="col-md-6">
                                    <x-forms.select fieldId="client_id" fieldName="client_id" fieldRequired="true" :search="true"
                                                    :fieldLabel="__('databackd-teams-analysis.fields.client')">
                                        <option value="">@lang('databackd-teams-analysis.form.select_client')</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected($analysis->client_id == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                            @endunless

                            <div class="col-md-6">
                                <x-forms.select fieldId="type" fieldName="type" fieldRequired="true"
                                                :fieldLabel="__('databackd-teams-analysis.fields.type')">
                                    @foreach (\App\Models\Databackd\AnalysisRequest::TYPES as $type)
                                        <option value="{{ $type }}" @selected($analysis->type === $type)>@lang('databackd-teams-common.analysis_type.' . $type)</option>
                                    @endforeach
                                </x-forms.select>
                            </div>

                            <div class="col-md-12">
                                <x-forms.text fieldId="title" fieldName="title" fieldRequired="true"
                                              :fieldLabel="__('databackd-teams-analysis.fields.title')"
                                              :fieldPlaceholder="__('databackd-teams-analysis.form.title_placeholder')"
                                              :fieldValue="$analysis->title"/>
                            </div>

                            <div class="col-md-12">
                                <x-forms.textarea fieldId="description" fieldName="description"
                                                  :fieldLabel="__('databackd-teams-analysis.fields.description')"
                                                  :fieldPlaceholder="__('databackd-teams-analysis.form.description_placeholder')"
                                                  :fieldValue="$analysis->description"/>
                            </div>

                            <div class="col-md-4">
                                <x-forms.datepicker fieldId="period_from" fieldName="period_from"
                                                    :fieldLabel="__('databackd-teams-analysis.fields.period_from')"
                                                    :fieldPlaceholder="__('placeholders.date')"
                                                    :fieldValue="$fmt($analysis->period_from)"/>
                            </div>
                            <div class="col-md-4">
                                <x-forms.datepicker fieldId="period_to" fieldName="period_to"
                                                    :fieldLabel="__('databackd-teams-analysis.fields.period_to')"
                                                    :fieldPlaceholder="__('placeholders.date')"
                                                    :fieldValue="$fmt($analysis->period_to)"/>
                            </div>
                            <div class="col-md-4">
                                <x-forms.datepicker fieldId="due_date" fieldName="due_date" fieldRequired="true"
                                                    :fieldLabel="__('databackd-teams-analysis.fields.due_date')"
                                                    :fieldPlaceholder="__('placeholders.date')"
                                                    :fieldValue="$fmt($analysis->due_date)"/>
                            </div>

                            <div class="col-md-4">
                                <x-forms.select fieldId="priority" fieldName="priority" fieldRequired="true"
                                                :fieldLabel="__('databackd-teams-analysis.fields.priority')">
                                    @foreach (\App\Models\Databackd\AnalysisRequest::PRIORITIES as $priority)
                                        <option value="{{ $priority }}" @selected($analysis->priority === $priority)>@lang('databackd-teams-common.priority.' . $priority)</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div class="col-md-4">
                                <x-forms.select fieldId="recurrence" fieldName="recurrence" fieldRequired="true"
                                                :fieldLabel="__('databackd-teams-analysis.fields.recurrence')">
                                    @foreach (\App\Models\Databackd\AnalysisRequest::RECURRENCES as $recurrence)
                                        <option value="{{ $recurrence }}" @selected($analysis->recurrence === $recurrence)>@lang('databackd-teams-common.recurrence.' . $recurrence)</option>
                                    @endforeach
                                </x-forms.select>
                                <small class="form-text text-muted">@lang('databackd-teams-analysis.form.recurrence_help')</small>
                            </div>
                            <div class="col-md-4">
                                <x-forms.select fieldId="team_request_id" fieldName="team_request_id"
                                                :fieldLabel="__('databackd-teams-analysis.fields.team_request')">
                                    <option value="">@lang('databackd-teams-analysis.form.no_team_request')</option>
                                    @foreach ($teamRequests as $teamRequest)
                                        <option value="{{ $teamRequest->id }}" data-client="{{ $teamRequest->client_id }}"
                                            @selected($analysis->team_request_id == $teamRequest->id)>{{ $teamRequest->title }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>

                            @unless ($isEdit)
                                {{-- AFV (S12): source data for the team (saved as the first comment, private storage) --}}
                                <div class="col-md-12">
                                    <div class="form-group my-3">
                                        <label class="f-14 text-dark-grey mb-12" for="analysis_file">@lang('databackd-teams-analysis.form.file')</label>
                                        <input type="file" class="form-control-file f-14" name="file" id="analysis_file" aria-describedby="analysis_file_help">
                                        <small class="form-text text-muted" id="analysis_file_help">@lang('databackd-teams-analysis.form.file_help')</small>
                                    </div>
                                </div>
                            @endunless
                        </div>

                        <x-form-actions>
                            <x-forms.button-primary id="save-analysis" class="mr-3" icon="check">
                                @lang('databackd-teams-analysis.form.save')
                            </x-forms.button-primary>
                            <x-forms.button-cancel :link="$isEdit ? route('databackd.teams.analysis.show', $analysis->id) : route('databackd.teams.analysis.index')" class="border-0">
                                @lang('databackd-teams-analysis.form.cancel')
                            </x-forms.button-cancel>
                        </x-form-actions>
                    </div>
                </x-form>
            </div>

            @unless ($isEdit)
                {{-- AFV (S12): what the client gets, and when --}}
                <div>
                    <div class="dbk-card dbk-teams-howto">
                        <div class="dbk-card-body">
                            <h3 class="dbk-card-title">@lang('databackd-teams-analysis.howto.title')</h3>
                            <ol class="dbk-teams-steps">
                                @foreach (['send', 'work', 'result', 'rate'] as $step)
                                    <li>
                                        <strong>@lang('databackd-teams-analysis.howto.' . $step . '_title')</strong>
                                        <span>@lang('databackd-teams-analysis.howto.' . $step . '_text')</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            @endunless
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var isStaff = @json(!$isClient);
            var teamOptions = $('#team_request_id option').not('[value=""]').map(function () {
                return {value: $(this).val(), text: $(this).text(), client: String($(this).data('client'))};
            }).get();
            var selectedTeam = @json($analysis->team_request_id ? (string)$analysis->team_request_id : '');

            // staff: the team request list follows the selected client
            var syncTeamRequests = function () {
                if (!isStaff) {
                    return;
                }

                var client = String($('#client_id').val() || '');
                var current = $('#team_request_id').val() || selectedTeam;
                var html = '<option value="">' + @json(__('databackd-teams-analysis.form.no_team_request')) + '</option>';

                teamOptions.forEach(function (o) {
                    if (client !== '' && o.client === client) {
                        html += '<option value="' + o.value + '"' + (o.value === current ? ' selected' : '') + '>' + $('<div>').text(o.text).html() + '</option>';
                    }
                });

                $('#team_request_id').html(html).selectpicker('refresh');
                selectedTeam = '';
            };

            $('#client_id').on('change', syncTeamRequests);
            init('.content-wrapper');
            syncTeamRequests();

            ['period_from', 'period_to', 'due_date'].forEach(function (id) {
                datepicker('#' + id, {position: 'bl', ...datepickerConfig});
            });

            $('#save-analysis').click(function () {
                $.easyAjax({
                    url: "{{ $isEdit ? route('databackd.teams.analysis.update', $analysis->id) : route('databackd.teams.analysis.store') }}",
                    container: '#analysis-form',
                    type: 'POST',
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: '#save-analysis',
                    file: true,
                    success: function (response) {
                        if (response.status === 'success') {
                            window.location.href = response.redirectUrl;
                        }
                    }
                });
            });
        });
    </script>
@endpush
