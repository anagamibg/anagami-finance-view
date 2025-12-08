<x-form id="specialist-form" method="PUT" class="ajax-form">
    <div class="modal-header">
        <h5 class="modal-title" id="modelHeading">
            @if ($profile->exists)
                @lang('databackd-teams-specialists.edit_title', ['name' => $profile->user?->name])
            @else
                @lang('databackd-teams-specialists.add_title')
            @endif
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('databackd-misc.close') }}"><span aria-hidden="true">×</span></button>
    </div>
    <div class="modal-body">
        <div class="portlet-body">
            <div class="row">
                @unless ($profile->exists)
                    {{-- AFV (S12): new profile - an employee that is not a specialist yet --}}
                    <div class="col-lg-12">
                        <x-forms.select fieldId="specialist_user_id" fieldName="user_id" fieldRequired="true" search="true"
                                        :fieldLabel="__('databackd-teams-specialists.employee')">
                            <option value="">@lang('databackd-teams-specialists.employee_placeholder')</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                @endunless
                <div class="col-lg-6">
                    <x-forms.select fieldId="specialist_role" fieldName="role" fieldRequired="true"
                                    :fieldLabel="__('databackd-teams-specialists.role')">
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected($profile->role === $role)>@lang('databackd-teams-common.role.' . $role)</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-6">
                    <x-forms.select fieldId="specialist_seniority" fieldName="seniority" fieldRequired="true"
                                    :fieldLabel="__('databackd-teams-specialists.seniority')">
                        @foreach ($seniorities as $seniority)
                            <option value="{{ $seniority }}" @selected($profile->seniority === $seniority)>@lang('databackd-teams-common.seniority.' . $seniority)</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-lg-6">
                    <x-forms.number fieldId="specialist_hourly_rate" fieldName="hourly_rate" fieldRequired="true"
                                    :fieldLabel="__('databackd-teams-specialists.hourly_rate_label')"
                                    :fieldHelp="__('databackd-teams-specialists.hourly_rate_help')"
                                    :fieldValue="rtrim(rtrim(number_format($profile->hourly_rate, 2, '.', ''), '0'), '.')"
                                    minValue="0"/>
                </div>
                <div class="col-lg-6">
                    <x-forms.number fieldId="specialist_capacity" fieldName="weekly_capacity_hours" fieldRequired="true"
                                    :fieldLabel="__('databackd-teams-specialists.weekly_capacity')"
                                    :fieldValue="$profile->weekly_capacity_hours" minValue="1" maxValue="80"/>
                </div>
                <div class="col-lg-12">
                    <x-forms.text fieldId="specialist_languages" fieldName="languages"
                                  :fieldLabel="__('databackd-teams-specialists.languages_label')"
                                  :fieldPlaceholder="__('databackd-teams-specialists.languages_placeholder')"
                                  :fieldValue="$profile->languages ?? ''"/>
                </div>
                <div class="col-lg-12">
                    <x-forms.text fieldId="specialist_skills" fieldName="skills"
                                  :fieldLabel="__('databackd-teams-specialists.skills_label')"
                                  :fieldPlaceholder="__('databackd-teams-specialists.skills_placeholder')"
                                  :fieldHelp="__('databackd-teams-specialists.skills_help')"
                                  :fieldValue="implode(', ', $profile->skills ?? [])"/>
                </div>
                <div class="col-lg-12">
                    <x-forms.toggle-switch fieldId="specialist_is_available" fieldName="is_available"
                                           :fieldLabel="__('databackd-teams-specialists.is_available')"
                                           :checked="$profile->is_available"/>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('databackd-teams-specialists.close')</x-forms.button-cancel>
        <x-forms.button-primary id="save-specialist" icon="check">@lang('databackd-teams-specialists.save')</x-forms.button-primary>
    </div>
</x-form>

<script>
    init(MODAL_LG);

    $('#save-specialist').click(function () {
        $.easyAjax({
            url: @json(route('databackd.teams.specialists.update', $profile->exists ? $profile->id : 0)),
            container: '#specialist-form',
            type: "POST",
            blockUI: true,
            disableButton: true,
            buttonSelector: "#save-specialist",
            data: $('#specialist-form').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    window.location.reload();
                }
            }
        });
    });
</script>
