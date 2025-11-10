<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;

/**
 * Comment with an optional attachment on an analysis request.
 */
class CommentAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'comment' => ['required', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:' . (20 * 1024)],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-analysis.attributes');
    }

}
