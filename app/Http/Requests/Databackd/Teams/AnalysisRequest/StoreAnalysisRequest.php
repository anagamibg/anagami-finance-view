<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\TeamRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * New analysis request. Clients create it for themselves, staff picks the client.
 */
class StoreAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $dateFormat = 'date_format:' . company()->date_format;
        $isClient = in_array('client', user_roles());

        return [
            'client_id' => $isClient ? ['nullable'] : [
                'required', 'integer',
                Rule::exists('users', 'id')->where('company_id', company()->id),
                // S6/P2-02: only a user with the client role can own a request
                function ($attribute, $value, $fail) {
                    if (!User::withoutGlobalScopes()->where('id', (int)$value)->whereHas('roles', fn($q) => $q->where('name', 'client'))->exists()) {
                        $fail(__('validation.exists', ['attribute' => __('databackd-teams-analysis.attributes.client_id')]));
                    }
                },
            ],
            'type' => ['required', Rule::in(AnalysisRequest::TYPES)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period_from' => ['nullable', $dateFormat],
            'period_to' => ['nullable', $dateFormat],
            'due_date' => ['required', $dateFormat],
            'priority' => ['required', Rule::in(AnalysisRequest::PRIORITIES)],
            'recurrence' => ['required', Rule::in(AnalysisRequest::RECURRENCES)],
            'team_request_id' => [
                'nullable', 'integer',
                Rule::exists('team_requests', 'id')->where('company_id', company()->id),
            ],
            // AFV (S12): optional source data (stored as the first comment, see AnalysisRequestController::store())
            'file' => ['nullable', 'file', 'max:' . (20 * 1024)],
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

            // AFV (S12): a new request cannot be due in the past
            $due = Carbon::createFromFormat($format, (string)$this->input('due_date'));

            if ($due && $due->startOfDay()->lt(today())) {
                $validator->errors()->add('due_date', __('databackd-teams-analysis.validation.due_past'));
            }

            if ($this->filled('team_request_id')) {
                $clientId = in_array('client', user_roles()) ? user()->id : (int)$this->input('client_id');
                $owns = TeamRequest::where('id', (int)$this->input('team_request_id'))->where('client_id', $clientId)->exists();

                if (!$owns) {
                    $validator->errors()->add('team_request_id', __('databackd-teams-analysis.validation.team_request_client'));
                }
            }
        });
    }

}
