<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\AnalysisRequest;
use Illuminate\Validation\Rule;

/**
 * Status change from the kanban board or the status dropdown (staff only).
 */
class MoveAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return !in_array('client', user_roles());
    }

    public function rules()
    {
        return [
            'status' => ['required', Rule::in(AnalysisRequest::STATUSES)],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-analysis.attributes');
    }

}
