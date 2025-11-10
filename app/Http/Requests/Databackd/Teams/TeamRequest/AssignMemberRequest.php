<?php

namespace App\Http\Requests\Databackd\Teams\TeamRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\SpecialistProfile;
use Illuminate\Validation\Rule;

/** Admin adds a specialist to / removes a specialist from a team request. */
class AssignMemberRequest extends CoreRequest
{

    public function authorize()
    {
        return in_array('admin', user_roles());
    }

    public function rules()
    {
        $rules = [
            'action' => ['required', Rule::in(['add', 'remove'])],
            'specialist_profile_id' => [
                'required',
                'integer',
                Rule::exists('specialist_profiles', 'id')->where('company_id', company()->id),
            ],
        ];

        if ($this->input('action') !== 'remove') {
            $rules['role'] = ['nullable', Rule::in(SpecialistProfile::ROLES)];
            $rules['hours_per_week'] = ['required', 'integer', 'min:1', 'max:60'];
        }

        return $rules;
    }

    public function attributes()
    {
        return __('databackd-teams-requests.attributes');
    }

}
