<?php

namespace App\Http\Requests\LeadForm;

use App\Http\Requests\CoreRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends CoreRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('lead_forms', 'slug')->where('company_id', company()->id),
            ],
            'status' => 'nullable|in:active,inactive',
            // AFV (upgrade 6.0.19): only records of the current company (HomeController::leadStore reads them without CompanyScope)
            'lead_pipeline_id' => ['nullable', 'integer', Rule::exists('lead_pipelines', 'id')->where('company_id', company()->id)],
            'pipeline_stage_id' => ['nullable', 'integer', Rule::exists('pipeline_stages', 'id')->where('company_id', company()->id)->where('lead_pipeline_id', $this->lead_pipeline_id)],
            'category_id' => ['nullable', 'integer', Rule::exists('lead_category', 'id')->where('company_id', company()->id)],
            'lead_source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('company_id', company()->id)],
        ];
    }
}
