<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;

/**
 * Client rating (1-5 stars + comment) of a delivered request.
 */
class RateAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return in_array('client', user_roles());
    }

    public function rules()
    {
        return [
            'client_rating' => ['required', 'integer', 'between:1,5'],
            'client_feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-analysis.attributes');
    }

}
