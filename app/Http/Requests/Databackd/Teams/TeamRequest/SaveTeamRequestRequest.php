<?php

namespace App\Http\Requests\Databackd\Teams\TeamRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\SpecialistProfile;
use App\Models\Databackd\TeamRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation of a new team request (client or admin form).
 * The sum of the role counts must equal the team size.
 */
class SaveTeamRequestRequest extends CoreRequest
{

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'title' => ['required', 'string', 'max:191'],
            'team_size' => ['required', Rule::in(TeamRequest::SIZES)],
            'custom_size' => ['nullable', 'required_if:team_size,custom', 'integer', 'min:1', 'max:1000'],
            'roles' => ['required', 'array'],
            'roles.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'engagement' => ['required', Rule::in(TeamRequest::ENGAGEMENTS)],
            'hours_per_week' => ['nullable', 'required_if:engagement,hours_per_week', 'integer', 'min:1', 'max:40'],
            'start_date' => ['required', 'date_format:' . company()->date_format],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'description' => ['required', 'string', 'max:5000'],
        ];

        // Only staff chooses the client, a client always orders for himself
        if (!in_array('client', user_roles())) {
            $rules['client_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('company_id', company()->id),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $roles = $this->cleanRoles();

            if (!$roles) {
                $validator->errors()->add('roles', __('databackd-teams-requests.validation.roles_required'));

                return;
            }

            $assigned = array_sum($roles);
            $total = $this->headcount();

            if ($assigned !== $total) {
                $validator->errors()->add('roles', __('databackd-teams-requests.validation.roles_sum', ['assigned' => $assigned, 'total' => $total]));
            }

            // AFV (S12): a client cannot order a team that starts in the past (staff may record an existing engagement)
            if (in_array('client', user_roles())) {
                $start = Carbon::createFromFormat(company()->date_format, (string)$this->input('start_date'));

                if ($start && $start->startOfDay()->lt(today())) {
                    $validator->errors()->add('start_date', __('databackd-teams-requests.validation.start_past'));
                }
            }

            if (!in_array('client', user_roles()) && !User::withoutGlobalScopes()->where('id', $this->input('client_id'))->whereHas('roles', fn($q) => $q->where('name', 'client'))->exists()) {
                $validator->errors()->add('client_id', __('validation.exists', ['attribute' => __('databackd-teams-requests.attributes.client_id')]));
            }
        });
    }

    /** Number of people of the request (custom size for the "other" option). */
    public function headcount(): int
    {
        return $this->input('team_size') === 'custom' ? (int)$this->input('custom_size') : (int)$this->input('team_size');
    }

    /** @return array<string, int> role => count, only known roles with a count above 0 */
    public function cleanRoles(): array
    {
        $roles = [];

        foreach ((array)$this->input('roles', []) as $role => $count) {
            if (in_array($role, SpecialistProfile::ROLES, true) && (int)$count > 0) {
                $roles[$role] = (int)$count;
            }
        }

        return $roles;
    }

    public function attributes()
    {
        return __('databackd-teams-requests.attributes');
    }

}
