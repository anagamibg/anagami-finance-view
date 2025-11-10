<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\TeamRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * Staff edit of an analysis request (full edit form or the assignee / priority / due date card). All fields optional.
 */
class UpdateAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return !in_array('client', user_roles());
    }

    public function rules()
    {
        $dateFormat = 'date_format:' . company()->date_format;

        return [
            'client_id' => [
                'sometimes', 'integer',
                Rule::exists('users', 'id')->where('company_id', company()->id),
                // S6/P2-02: only a user with the client role can own a request (the client scope of the lists relies on it)
                function ($attribute, $value, $fail) {
                    if (!User::withoutGlobalScopes()->where('id', (int)$value)->whereHas('roles', fn($q) => $q->where('name', 'client'))->exists()) {
                        $fail(__('validation.exists', ['attribute' => __('databackd-teams-analysis.attributes.client_id')]));
                    }
                },
            ],
            'type' => ['sometimes', Rule::in(AnalysisRequest::TYPES)],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'period_from' => ['sometimes', 'nullable', $dateFormat],
            'period_to' => ['sometimes', 'nullable', $dateFormat],
            'due_date' => ['sometimes', 'required', $dateFormat],
            'priority' => ['sometimes', Rule::in(AnalysisRequest::PRIORITIES)],
            'recurrence' => ['sometimes', Rule::in(AnalysisRequest::RECURRENCES)],
            'team_request_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('team_requests', 'id')->where('company_id', company()->id),
            ],
            'assigned_to' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('users', 'id')->where('company_id', company()->id),
                // S6/P2-02: the assignee is staff, never a client (the comment notifications of other clients would reach him)
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && !User::withoutGlobalScopes()->where('id', (int)$value)->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'employee']))->exists()) {
                        $fail(__('validation.exists', ['attribute' => __('databackd-teams-analysis.attributes.assigned_to')]));
                    }
                },
            ],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-analysis.attributes');
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $format = company()->date_format;

            if ($this->filled('period_from') && $this->filled('period_to')) {
                $from = Carbon::createFromFormat($format, $this->input('period_from'));
                $to = Carbon::createFromFormat($format, $this->input('period_to'));

                if ($to->lt($from)) {
                    $validator->errors()->add('period_to', __('databackd-teams-analysis.validation.period_order'));
                }
            }

            if ($this->filled('team_request_id')) {
                $current = AnalysisRequest::find((int)$this->route('id'));
                $clientId = $this->filled('client_id') ? (int)$this->input('client_id') : ($current?->client_id);
                $owns = TeamRequest::where('id', (int)$this->input('team_request_id'))->where('client_id', $clientId)->exists();

                if (!$owns) {
                    $validator->errors()->add('team_request_id', __('databackd-teams-analysis.validation.team_request_client'));
                }
            }
        });
    }

}
